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

The extension listens for `hook_civicrm_alterMailParams`, which fires just before 
every outbound CiviCRM email is sent. It's registered via `hook_civicrm_container` 
at a low priority (-200) so it always runs *after* EventICS's own hook has attached 
the raw `.ics` file — see "Why priority -200?" below. It checks whether the email 
has an `.ics` attachment. If it does, it:

1. Reads the ICS content
2. Replaces `METHOD:PUBLISH` with `METHOD:REQUEST`
3. Adds an `ORGANIZER` field using your From email address (required by Outlook)
4. Ensures `SEQUENCE` and `UID` fields are present (required for meeting requests)
5. Updates the attachment with the modified content

### Why priority -200?

Classic function-style hooks (`icsinvite_civicrm_alterMailParams`, EventICS's own 
hook, etc.) all run inside one bundled listener at priority -100, in whatever order 
extensions happen to appear in `civicrm_extension` — which depends on install/reinstall 
history, not anything declared. If this extension's rewrite ran in that same bundle, 
it could race with EventICS: if ours happened to fire first, the `.ics` attachment 
wouldn't exist yet and we'd silently do nothing (this is exactly what version 1.0.0 
did after a reinstall shuffled the extension order — see CHANGELOG). Registering our 
own listener at priority -200 guarantees we run after that whole bundle, and therefore 
after EventICS, regardless of extension order.

## Troubleshooting

**Outlook still not showing Accept/Decline buttons?**
- Make sure EventICS is installed and the ICS attachment is actually being 
  generated first
- Check that your event has a start date/time set (not just a date)
- The From address on your CiviCRM emails must be a real, deliverable address — 
  Outlook validates the ORGANIZER field
- Confirm you're on version 1.0.1+ of this extension (1.0.0 had a hook-ordering 
  bug — see "Why priority -200?" above)

**Getting a PHP error on install?**
- Check that your CiviCRM extensions directory is writable
- Confirm CiviCRM 5.x compatibility

## License

AGPL-3.0, same as EventICS.
