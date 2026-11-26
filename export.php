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
 * CSV export of the tickets of an event (attendance sheet), with the answers to the questions.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);

require_login();
$PAGE->set_context(context_system::instance());
$context = context_system::instance();
booking_manager::require_event_access($id, 'local/evenements:viewregistrations');

$event = booking_manager::get_event($id);

global $DB;
$sql = "SELECT t.id, t.bookingid, t.firstname, t.lastname, t.email, t.status, t.timeused,
               b.contactname, b.email AS contactemail, b.timecreated AS booked, b.sessionid,
               tt.name AS category
          FROM {local_evenements_ticket} t
          JOIN {local_evenements_booking} b ON b.id = t.bookingid
     LEFT JOIN {local_evenements_tickettype} tt ON tt.id = t.tickettypeid
         WHERE t.eventid = :eventid
      ORDER BY t.lastname, t.firstname, t.id";
$rows = $DB->get_records_sql($sql, ['eventid' => $id]);

// Answers: guest questions are stored per ticket, the others per booking.
$questions = booking_manager::get_questions($id);
$perticket = [];
$perbooking = [];
foreach ($DB->get_records('local_evenements_answer', ['eventid' => $id], 'id ASC') as $answer) {
    $value = str_replace("\n", ', ', $answer->value);
    if ($answer->ticketid) {
        $perticket[$answer->questionid][$answer->ticketid] = $value;
    } else {
        $perbooking[$answer->questionid][$answer->bookingid] = $value;
    }
}

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$csv = new csv_export_writer();
$csv->set_filename(clean_filename('inscrits-' . format_string($event->name, true, ['escape' => false])));

$headers = [
    get_string('ticketnumber', 'local_evenements'),
    get_string('lastname', 'local_evenements'),
    get_string('firstname', 'local_evenements'),
    get_string('category', 'local_evenements'),
    get_string('guestemail', 'local_evenements'),
    get_string('contactname', 'local_evenements'),
    get_string('contactemail', 'local_evenements'),
    get_string('bookedon', 'local_evenements'),
    get_string('status', 'local_evenements'),
    get_string('timeused', 'local_evenements'),
    get_string('session', 'local_evenements'),
];
$sessions = booking_manager::get_sessions($id);
foreach ($questions as $question) {
    $headers[] = format_string($question->name);
}
$headers[] = get_string('signature', 'local_evenements');
$csv->add_data($headers);

foreach ($rows as $row) {
    $line = [
        $row->id,
        $row->lastname,
        $row->firstname,
        $row->category ?? '',
        $row->email,
        $row->contactname,
        $row->contactemail,
        userdate($row->booked, $dateformat),
        get_string('ticketstatus_' . $row->status, 'local_evenements'),
        $row->timeused ? userdate($row->timeused, $dateformat) : '',
        isset($sessions[$row->sessionid]) ? booking_manager::session_label($sessions[$row->sessionid]) : '',
    ];
    foreach ($questions as $question) {
        if ($question->moment === 'before' && $question->scope === 'guest') {
            $line[] = $perticket[$question->id][$row->id] ?? '';
        } else {
            $line[] = $perbooking[$question->id][$row->bookingid] ?? '';
        }
    }
    $line[] = '';
    $csv->add_data($line);
}
$csv->download_file();
