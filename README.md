# Events and ticketing for Moodle

A Moodle *local* plugin to run events — open days, graduation ceremonies, career fairs — from online
booking to the check-in at the door. Visitors book without a Moodle account and receive named tickets
with a QR code by e-mail.

- Version 1.1.0 (stable) — Moodle 5.0 or later
- Licence: GNU GPL v3 or later
- French: [README.fr.md](README.fr.md)

## Features

- **Booking**: public event pages, ticket categories with quotas, time slots, waiting list,
  questions at booking time; invitation-list mode for named events (one personal link per person).
- **Tickets**: PDF with QR code, several formats, customisable; personal page to download, add to a
  calendar or cancel; reminder e-mail before the event.
- **Check-in**: check-in page and installable door app for volunteers (no Moodle account), camera scan,
  search by name, offline mode.
- **Organisers**: participants management with change log, bulk actions, people added from cohorts or
  courses, messages, exports, badges, attendance sheet.
- **Report**: attendance, arrival times, booking pace, survey answers, comparison with the previous edition.
- **Privacy**: Privacy API, personal data purged automatically after the event.

## Installation

1. Copy the plugin to `local/evenements` (Moodle 5.0) or `public/local/evenements` (Moodle 5.1 and later),
   or install the ZIP from *Site administration > Plugins > Install plugins*.
2. Go to *Site administration > Notifications*.
3. Adjust the settings in *Site administration > Plugins > Local plugins > Events and ticketing*.
4. The Moodle cron must run (reminders, surveys, waiting list, purge).

To read QR codes with the camera on iPhone (Safari), add `jsqr.min.js` (jsQR, Apache-2.0) to the `pwa/` folder.

## Permissions

- `local/evenements:manage` — create and manage events
- `local/evenements:viewregistrations` — see the participants
- `local/evenements:scan` — check tickets at the door

All three are given to the Manager role by default.

## Licence

GNU General Public License v3 or later. © 2026 Didier AKPA
