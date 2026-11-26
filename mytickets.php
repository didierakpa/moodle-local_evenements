<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * "I lost my tickets": the links of the bookings of an address are sent to that address. The answer is always the same,
 * so that the page cannot be used to find out who is registered.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public page: visitors and guests use it without a Moodle account (access by personal link or token).
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/mytickets.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('findmytickets', 'local_evenements'));
$PAGE->set_heading('');

$done = false;
$email = optional_param('email', '', PARAM_EMAIL);
if ($email !== '' && optional_param('website', '', PARAM_RAW) === '' && confirm_sesskey()) {
    // At most one request per address and per ten minutes, whatever the result.
    $cache = cache::make_from_params(cache_store::MODE_APPLICATION, 'local_evenements', 'mytickets');
    $key = md5(core_text::strtolower($email));
    if (!$cache->get($key)) {
        $cache->set($key, time());
        try {
            booking_manager::send_booking_links($email);
        } catch (Throwable $e) {
            debugging('local_evenements: lost tickets mail failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    $done = true;
}

echo $OUTPUT->header();
echo html_writer::div(html_writer::tag('h1', get_string('findmytickets', 'local_evenements')) .
    html_writer::tag('p', get_string('findmyticketsintro', 'local_evenements')), 'evt-pagehead');
if ($done) {
    echo $OUTPUT->notification(get_string('findmyticketsdone', 'local_evenements'), \core\output\notification::NOTIFY_SUCCESS);
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'evt-panel']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::div(
    '<input type="text" name="website" value="" tabindex="-1" autocomplete="off">',
    '',
    ['style' => 'position:absolute;left:-9999px;', 'aria-hidden' => 'true']
);
echo html_writer::tag('label', get_string('contactemail', 'local_evenements'), ['for' => 'evt-mail', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'email', 'name' => 'email', 'id' => 'evt-mail', 'required' => 'required',
    'class' => 'form-control mb-3']);
echo html_writer::tag('button', get_string('sendmelinks', 'local_evenements'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::div(html_writer::link(
    new moodle_url('/local/evenements/index.php'),
    get_string('backtoevents', 'local_evenements')
), 'mt-4');
echo $OUTPUT->footer();
