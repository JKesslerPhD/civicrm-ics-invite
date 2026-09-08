# CiviCRM ICS Meeting Invite Extension

## What This Does

Converts the `.ics` attachment added by the EventICS extension into a proper 
`METHOD:REQUEST` calendar invite. This causes Outlook to display **Accept / 
Tentative / Decline** buttons directly in the email, instead of just showing 
a passive calendar file attachment.

## Requirements

- CiviCRM 5.x
- [EventICS](https://lab.civicrm.org/extensions/eventics) extension installed
  and active (this extension piggybacks on the ICS file EventICS generates)

## Installation

1. Unzip this folder into your CiviCRM extensions directory. This is typically:
   - WordPress: `wp-content/uploads/civicrm/ext/`
   - Drupal: `sites/default/files/civicrm/ext/`

2. Go to **Administer → System Settings → Extensions**

3. Find **ICS Meeting Invite** in the list and click **Install**

4. That's it. No configuration needed.

## How It Works

The extension hooks into `hook_civicrm_alterMailParams` which fires just before 
every outbound CiviCRM email is sent. It checks whether the email has an `.ics` 
attachment. If it does, it:

1. Reads the ICS content
2. Replaces `METHOD:PUBLISH` with `METHOD:REQUEST`
3. Adds an `ORGANIZER` field using your From email address (required by Outlook)
4. Ensures `SEQUENCE` and `UID` fields are present (required for meeting requests)
5. Updates the attachment with the modified content

## Troubleshooting

**Outlook still not showing Accept/Decline buttons?**
- Make sure EventICS is installed and the ICS attachment is actually being 
  generated first
- Check that your event has a start date/time set (not just a date)
- The From address on your CiviCRM emails must be a real, deliverable address — 
  Outlook validates the ORGANIZER field

**Getting a PHP error on install?**
- Check that your CiviCRM extensions directory is writable
- Confirm CiviCRM 5.x compatibility

## License

AGPL-3.0, same as EventICS.
