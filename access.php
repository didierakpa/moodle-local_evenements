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
 * "Find my invitation": the visitor types a name, the personal link is e-mailed to the address of the list.
 *
 * The answer is always the same, whether the name is in the list or not, so that nobody can find out who is invited.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public page: visitors and guests use it without a Moodle account (access by personal link or token).
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);
$event = booking_manager::get_event($id);
$eventurl = new moodle_url('/local/evenements/event.php', ['id' => $id]);

if ($event->status === booking_manager::STATUS_DRAFT && !booking_manager::can_manage($id)) {
    throw new moodle_exception('eventnotfound', 'local_evenements');
}
if (!booking_manager::is_list_event($event) || !booking_manager::is_open($event)) {
    redirect($eventurl);
}
require_sesskey();
if (optional_param('website', '', PARAM_RAW) !== '') {
    redirect($eventurl);
}

$first = trim(optional_param('firstname', '', PARAM_TEXT));
$last = trim(optional_param('lastname', '', PARAM_TEXT));

// Brakes: 15 requests per address and ten minutes, one e-mail per person every ten minutes.
$cache = cache::make_from_params(cache_store::MODE_APPLICATION, 'local_evenements', 'invaccess');
$ipkey = 'ip' . substr(hash('sha256', getremoteaddr() . get_site_identifier()), 0, 24);
$count = (int) $cache->get($ipkey);
$cache->set($ipkey, $count + 1);

if ($count < 15 && $first !== '' && $last !== '') {
    global $DB;
    $key = booking_manager::name_key($first, $last);
    foreach ($DB->get_records('local_evenements_invitee', ['eventid' => $id, 'namekey' => $key]) as $invitee) {
        $mailkey = 'inv' . $invitee->id;
        if ($cache->get($mailkey)) {
            continue;
        }
        $cache->set($mailkey, 1);
        try {
            booking_manager::send_invitation($invitee, $event);
        } catch (Throwable $e) {
            debugging('local_evenements: invitation e-mail failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/access.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(format_string($event->name));
$PAGE->set_heading('');

echo $OUTPUT->header();
echo html_writer::div(
    html_writer::tag('h1', get_string('accessdonetitle', 'local_evenements'), ['class' => 'h3']) .
    html_writer::tag('p', get_string('accessdonetext', 'local_evenements')) .
    html_writer::tag('p', html_writer::link($eventurl, get_string('backtoevent', 'local_evenements'), [
        'class' => 'btn btn-outline-primary'])),
    'evt-panel'
);
echo $OUTPUT->footer();
