# Changes

## 1.0.1 (2026-11-26)
- Moodle coding style: the code passes the Moodle code checker, PHPDoc checker, Mustache, ESLint and Stylelint.
- JavaScript moved to AMD modules (`amd/src`), no inline scripts or event handlers any more.
- Door app page rendered from a Mustache template.
- PHPUnit tests; continuous integration with moodle-plugin-ci.

## 1.0.0
- First stable release: events, named QR tickets, waiting list, invitation lists, time slots,
  questionnaires, door app with offline mode, badges, participants management with change log,
  report with comparison between editions, people added from cohorts or courses.
