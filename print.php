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
 * Printable attendance sheet: alphabetical list with a box to tick at the entrance.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);
require_login();
booking_manager::require_event_access($id, 'local/evenements:viewregistrations');
$event = booking_manager::get_event($id);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/print.php', ['id' => $id]));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('printsheet', 'local_evenements'));
$PAGE->add_body_class('evt-printsheet');

global $DB;
$rows = $DB->get_records_sql("SELECT t.id, t.firstname, t.lastname, t.status, tt.name AS category, b.sessionid, b.contactname
      FROM {local_evenements_ticket} t
      JOIN {local_evenements_booking} b ON b.id = t.bookingid
 LEFT JOIN {local_evenements_tickettype} tt ON tt.id = t.tickettypeid
     WHERE t.eventid = :e AND t.status <> :c
  ORDER BY t.lastname, t.firstname, t.id", ['e' => $id, 'c' => booking_manager::TICKET_CANCELLED]);
$sessions = booking_manager::get_sessions($id);

echo $OUTPUT->header();
echo html_writer::start_div('evt-print-actions');
echo html_writer::tag('button', get_string('printnow', 'local_evenements'), ['type' => 'button', 'class' => 'btn btn-primary',
    'onclick' => 'window.print()']);
echo html_writer::end_div();
echo html_writer::tag('h1', format_string($event->name), ['class' => 'h3']);
echo html_writer::tag('p', s(booking_manager::event_when($event)) . ' · ' .
    get_string('printtotal', 'local_evenements', count($rows)) . ' · ' . userdate(time()));

$head = html_writer::tag('th', '✔') . html_writer::tag('th', get_string('lastname', 'local_evenements')) .
    html_writer::tag('th', get_string('firstname', 'local_evenements')) .
    html_writer::tag('th', get_string('category', 'local_evenements')) .
    ($sessions ? html_writer::tag('th', get_string('session', 'local_evenements')) : '') .
    html_writer::tag('th', get_string('timeused', 'local_evenements'));
$body = '';
foreach ($rows as $row) {
    $used = $row->status === booking_manager::TICKET_USED;
    $body .= html_writer::tag('tr',
        html_writer::tag('td', $used ? '☑' : '☐', ['class' => 'evt-box']) .
        html_writer::tag('td', s(\core_text::strtoupper($row->lastname))) .
        html_writer::tag('td', s($row->firstname)) .
        html_writer::tag('td', s($row->category ?? '')) .
        ($sessions ? html_writer::tag('td', isset($sessions[$row->sessionid]) ?
            s(userdate($sessions[$row->sessionid]->timestart, get_string('strftimetime', 'langconfig'))) : '') : '') .
        html_writer::tag('td', ''));
}
echo html_writer::tag('table', html_writer::tag('thead', html_writer::tag('tr', $head)) . html_writer::tag('tbody', $body),
    ['class' => 'evt-printtable']);
echo $OUTPUT->footer();
