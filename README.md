# Events and ticketing for Moodle (local_evenements)

A Moodle *local* plugin to run school events — open days, graduation ceremonies, career fairs —
from online booking to the check-in at the door. Visitors book without a Moodle account and
receive **named tickets with a QR code** by e-mail; the door team scans them with a phone,
even without a network.

- Version: **1.0.1 (stable)**
- Requires: Moodle 5.0 or later, PHP 8.2 or later
- Licence: GNU GPL v3 or later
- French documentation: [README.fr.md](README.fr.md)

## Features

**Booking**
- Public event list and event pages, no Moodle account needed.
- Ticket categories with quotas, overall capacity, opening window, time slots with their own places.
- Limits per booking and per person; anti-spam (honeypot and rate limit).
- Waiting list: when places free up, the first people who fit are told by e-mail.
- **Invitation list mode** for named events (graduations): one personal link per person,
  one booking each, with a quota of guests.
- Questions at booking time (per booking or per guest) and a satisfaction survey after the event;
  10 ready-made questionnaires.

**Tickets**
- PDF tickets (Moodle's TCPDF, no external library): compact, boarding-pass or concert formats,
  colour, font, logo, image, free text, live preview; A4 sheets with cut marks.
- Personal page for each booking: download, add to calendar (.ics, Google Calendar), change a guest name,
  cancel.
- Reminder e-mail before the event, with a link to free one's place.

**Check-in**
- Check-in page: phone camera, code entry, offline mode with later synchronisation.
- **Door app** (installable PWA, `operator.php`): volunteers sign in with a personal access code,
  no Moodle account; camera scan, search by name, undo, offline list, live counter.
  Safari on iPhone cannot read QR codes natively: put `jsqr.min.js` (jsQR, Apache-2.0) in `pwa/`
  to enable it there.
- Printable attendance sheet and badges (staff, operators, guests).

**Organisers**
- Organiser console with a side menu and a command palette (Ctrl+K).
- Participants: search, filters, edit, cancel / restore (same QR code), delete for good,
  add by hand, bulk actions, **change log** (who did what, when).
- Add people from Moodle: members of a cohort or users of a course with a given role.
- Co-organisers per event, message to participants, CSV export and import, event duplication.
- **Report**: attendance by category and time slot, arrival times, booking pace, door team,
  survey answers, comparison with the previous edition; printable.

**Privacy**
- Privacy API implemented. Personal data is purged automatically after the event (delay set in
  the plugin settings); anonymous figures are kept for the report.

## Installation

1. Put the plugin in `local/evenements` (Moodle 5.0) or `public/local/evenements` (Moodle 5.1 and later),
   or install the ZIP from *Site administration > Plugins > Install plugins*.
2. Go to *Site administration > Notifications* to complete the installation.
3. Settings: *Site administration > Plugins > Local plugins > Events and ticketing*, or the
   *General settings* page of the organiser console (retention delay, reminders, anti-spam,
   privacy notice, main menu entries).
4. The Moodle cron must run: reminders, surveys, waiting list and purge are scheduled tasks.

## Permissions

| Capability | Default | Use |
| --- | --- | --- |
| `local/evenements:manage` | Manager | Create and manage all events |
| `local/evenements:viewregistrations` | Manager | See the participants |
| `local/evenements:scan` | Manager | Check tickets at the door |

Organisers can also be given one event only (co-organisers), and volunteers get a door-app code
without any Moodle account.

## Checks before going live

- Public pages do not require a login; check that they work with `forcelogin` on your site.
- The mail server must accept many messages with attachments (the day before an open day).
- On a school Wi-Fi many visitors share one IP address: adjust the anti-spam limit.
- When minors book, have the privacy notice reviewed by your data protection officer.

## Development

- JavaScript lives in `amd/src` (ES modules); `amd/build` is produced by Moodle's Grunt.
  The *Build AMD modules* workflow (GitHub Actions, run by hand) rebuilds it.
- Every push runs [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci): PHP lint,
  Moodle code checker, PHPDoc, validation, upgrade savepoints, Mustache, Grunt and PHPUnit.
- PHPUnit tests are in `tests/`.

## Licence

This program is free software: you can redistribute it and/or modify it under the terms of the
GNU General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

© 2026 Didier AKPA
