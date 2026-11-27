# Changes

## 1.1.1 (2026-11-27)
- Moodle 5.3 support (tested on Moodle 5.0, 5.1, 5.2 and 5.3).
- QR codes are now drawn by the plugin: with PHP 8.4 and some OPcache JIT settings, the QR encoder
  of the PDF library failed and the tickets could not be generated.
- Finished events are shown as "Past" instead of "Open" on the dashboard and in the console.

## 1.1.0 (2026-11-27)
- Security: time slots and questions can only be changed from their own event; spreadsheet formulas
  neutralised in the participants export; co-organisers see the image of their draft events.
- Neutral wording (learner, institution) and neutral event templates.
- Behat tests (public list, booking, participants management).

## 1.0.1 (2026-11-26)
- Moodle coding style: the code passes the Moodle code checker, PHPDoc checker, Mustache, ESLint and Stylelint.
- JavaScript moved to AMD modules (`amd/src`), no inline scripts or event handlers any more.
- Door app page rendered from a Mustache template.
- PHPUnit tests; continuous integration with moodle-plugin-ci.

## 1.0.0
- First stable release: events, named QR tickets, waiting list, invitation lists, time slots,
  questionnaires, door app with offline mode, badges, participants management with change log,
  report with comparison between editions, people added from cohorts or courses.
