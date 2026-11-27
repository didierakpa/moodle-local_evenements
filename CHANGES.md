# Changes

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
