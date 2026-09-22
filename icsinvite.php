<?php

/**
 * ICS Meeting Invite Extension
 *
 * Intercepts outbound CiviCRM emails that contain an EventICS .ics attachment
 * and rebuilds the email so the ICS is embedded as a proper text/calendar
 * MIME part with METHOD:REQUEST — triggering Outlook's Accept/Decline/Cancel buttons.
 */

require_once 'CRM/Core/Page.php';

/**
 * Implements hook_civicrm_container
 *
 * EventICS attaches the raw .ics file via its own hook_civicrm_alterMailParams.
 * Classic function-style hooks (like EventICS's) all run inside a single
 * bundled listener at priority -100, in the order extensions happen to be
 * listed in civicrm_extension (an accident of install/reinstall history, not
 * something we can rely on). If our rewrite ran in that same bundle and
 * happened to fire before EventICS's, the .ics attachment wouldn't exist yet
 * and we'd silently do nothing. Registering our own listener at a lower
 * priority guarantees we always run after that entire bundle — after
 * EventICS, regardless of extension order.
 */
function icsinvite_civicrm_container($container) {
  $container->addResource(new \Symfony\Component\Config\Resource\FileResource(__FILE__));
  $container->findDefinition('dispatcher')->addMethodCall('addListener', [
    'hook_civicrm_alterMailParams',
    '_icsinvite_alter_mail_params',
    -200,
  ])->setPublic(TRUE);
}

/**
 * Rewrites the EventICS .ics attachment into a METHOD:REQUEST calendar
 * invite. Registered via icsinvite_civicrm_container() above.
 */
function _icsinvite_alter_mail_params(\Civi\Core\Event\GenericHookEvent $event) {
  $params = &$event->params;
  $context = $event->context;

  // Only act on event confirmation emails, not mass mailings
  if ($context === 'civimail' || $context === 'flexmailer') {
    return;
  }

  // Only act on event registration confirmation emails
  if (empty($params['attachments'])) {
    return;
  }

  $icsAttachmentKey = null;
  $icsContent = null;

  // Find the .ics attachment added by EventICS
  foreach ($params['attachments'] as $key => $attachment) {
    $filename = isset($attachment['fullPath']) ? $attachment['fullPath'] : '';
    $mime = isset($attachment['mime_type']) ? $attachment['mime_type'] : '';

    if (
      strpos($filename, '.ics') !== false ||
      $mime === 'text/calendar' ||
      $mime === 'application/ics'
    ) {
      // Read the ICS file content
      if (file_exists($filename)) {
        $icsContent = file_get_contents($filename);
      }
      elseif (!empty($attachment['content'])) {
        $icsContent = $attachment['content'];
      }
      $icsAttachmentKey = $key;
      break;
    }
  }

  if ($icsContent === null) {
    // No ICS found, nothing to do
    return;
  }

  // Replace METHOD:PUBLISH with METHOD:REQUEST so Outlook shows Accept/Decline
  $icsContent = _icsinvite_upgradeMethod($icsContent, $params);

  // Store the modified ICS back — we'll also add it as an inline part
  // Update the attachment content in place
  if ($icsAttachmentKey !== null) {
    // Update the existing attachment with the new METHOD:REQUEST content
    if (!empty($params['attachments'][$icsAttachmentKey]['fullPath'])) {
      // Write modified content to a temp file
      $tmpFile = tempnam(sys_get_temp_dir(), 'civiics_') . '.ics';
      file_put_contents($tmpFile, $icsContent);
      $params['attachments'][$icsAttachmentKey]['fullPath'] = $tmpFile;
      $params['attachments'][$icsAttachmentKey]['mime_type'] = 'text/calendar; method=REQUEST';
      $params['attachments'][$icsAttachmentKey]['cleanName'] = 'invite.ics';
    }
    else {
      $params['attachments'][$icsAttachmentKey]['content'] = $icsContent;
      $params['attachments'][$icsAttachmentKey]['mime_type'] = 'text/calendar; method=REQUEST';
      $params['attachments'][$icsAttachmentKey]['cleanName'] = 'invite.ics';
    }
  }

  // Add the ICS as an inline text/calendar MIME part
  // This is what actually triggers Outlook's Accept/Decline buttons
  // rather than just showing the file as an attachment
  if (!isset($params['inlineAttachments'])) {
    $params['inlineAttachments'] = [];
  }

  $params['inlineAttachments'][] = [
    'content'   => $icsContent,
    'mime_type' => 'text/calendar',
    'method'    => 'REQUEST',
    'charset'   => 'UTF-8',
  ];
}

/**
 * Upgrades the ICS content from METHOD:PUBLISH to METHOD:REQUEST
 * and ensures all required fields for a meeting request are present.
 *
 * @param string $icsContent  Raw ICS file content
 * @param array  $params      Mail params (used to get organizer email)
 * @return string             Modified ICS content
 */
function _icsinvite_upgradeMethod($icsContent, $params) {
  // Replace or insert METHOD
  if (strpos($icsContent, 'METHOD:') !== false) {
    $icsContent = preg_replace('/METHOD:[A-Z]+/', 'METHOD:REQUEST', $icsContent);
  }
  else {
    // Insert METHOD after BEGIN:VCALENDAR
    $icsContent = str_replace(
      'BEGIN:VCALENDAR',
      "BEGIN:VCALENDAR\r\nMETHOD:REQUEST",
      $icsContent
    );
  }

  // Ensure ORGANIZER is set — required for METHOD:REQUEST to work in Outlook
  // Use the from address from the mail params
  if (strpos($icsContent, 'ORGANIZER') === false) {
    $fromEmail = '';
    $fromName  = '';

    if (!empty($params['from'])) {
      // Parse "Name <email>" format
      if (preg_match('/^(.*?)\s*<(.+?)>$/', $params['from'], $matches)) {
        $fromName  = trim($matches[1]);
        $fromEmail = trim($matches[2]);
      }
      else {
        $fromEmail = trim($params['from']);
      }
    }

    if ($fromEmail) {
      $organizer = 'ORGANIZER';
      if ($fromName) {
        $organizer .= ';CN=' . $fromName;
      }
      $organizer .= ':mailto:' . $fromEmail;

      // Insert ORGANIZER inside the VEVENT block
      $icsContent = preg_replace(
        '/(BEGIN:VEVENT\r?\n)/',
        "$1{$organizer}\r\n",
        $icsContent
      );
    }
  }

  // Ensure a UID is present (required for meeting requests)
  if (strpos($icsContent, 'UID:') === false) {
    $uid = uniqid('civicrm-', true) . '@' . \CRM_Utils_System::baseURL();
    $icsContent = preg_replace(
      '/(BEGIN:VEVENT\r?\n)/',
      "$1UID:{$uid}\r\n",
      $icsContent
    );
  }

  // Ensure SEQUENCE is present (Outlook needs this for REQUEST method)
  if (strpos($icsContent, 'SEQUENCE:') === false) {
    $icsContent = preg_replace(
      '/(BEGIN:VEVENT\r?\n)/',
      "$1SEQUENCE:0\r\n",
      $icsContent
    );
  }

  return $icsContent;
}

/**
 * Implements hook_civicrm_install
 */
function icsinvite_civicrm_install() {
  return;
}

/**
 * Implements hook_civicrm_uninstall
 */
function icsinvite_civicrm_uninstall() {
  return;
}

/**
 * Implements hook_civicrm_enable
 */
function icsinvite_civicrm_enable() {
  return;
}

/**
 * Implements hook_civicrm_disable
 */
function icsinvite_civicrm_disable() {
  return;
}
