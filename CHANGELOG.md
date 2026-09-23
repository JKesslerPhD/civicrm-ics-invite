# Changelog

## 1.0.2 (2026-09-23)

- **Feature:** now converts `.ics` attachments on CiviMail / FlexMailer bulk
  mailings too, not only event confirmation emails. The old early-return for
  the `civimail` / `flexmailer` contexts is removed.
- **Fix:** `$params['from']` is only parsed for the ORGANIZER field when it is
  a string. CiviMail bulk sends can pass it as an array, which made
  `preg_match()` throw a TypeError and fail the whole "Send Scheduled
  Mailings" job. Non-string values now just skip ORGANIZER.
- **Fix:** for `civimail` / `flexmailer` contexts the attachment is rewritten
  in place only; the extra `inlineAttachments` part is added for other
  contexts (event receipts) only.

## 1.0.1 (2026-09-22)

- **Fix:** the extension could silently stop rewriting the calendar invite
  after a reinstall. `icsinvite_civicrm_alterMailParams` was a classic
  function-style hook, which runs inside a single bundled listener alongside
  every other extension's classic hooks (including EventICS's), in
  whatever order extensions happen to be listed in `civicrm_extension` —
  an accident of install history, not anything either extension declares.
  If ours ran before EventICS's, the `.ics` attachment didn't exist yet and
  we'd do nothing, with no error. Fixed by registering our hook via
  `hook_civicrm_container` at priority -200, which guarantees it always
  runs after EventICS regardless of extension order.
- Added the `Documentation` URL to `info.xml` (was missing, which caused
  the 1.0.0 release to be rejected by civicrm.org's release scanner).

## 1.0.0 (2026-09-08)

- Initial public release.
