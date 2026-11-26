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
 * Change the guest named on a ticket (the QR code stays the same). No login: the booking link is the key.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public page: visitors and guests use it without a Moodle account (access by personal link or token).
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\ticket_edit_form;

$token = required_param('b', PARAM_ALPHANUM);
$tickettoken = required_param('t', PARAM_ALPHANUM);

$booking = booking_manager::get_booking_by_token($token);
if (!$booking || $booking->status !== 'active') {
    throw new moodle_exception('invalidlink', 'local_evenements');
}
$event = booking_manager::apply_session(booking_manager::get_event((int) $booking->eventid), $booking);
global $DB;
$ticket = $DB->get_record('local_evenements_ticket', ['token' => $tickettoken, 'bookingid' => $booking->id]);
$back = new moodle_url('/local/evenements/ticket.php', ['b' => $token]);
if (!$ticket || !booking_manager::can_edit_ticket($event, $ticket)) {
    redirect($back, get_string('changeguestnotallowed', 'local_evenements'), null, \core\output\notification::NOTIFY_ERROR);
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/editticket.php', ['b' => $token, 't' => $tickettoken]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('changeguest', 'local_evenements'));
$PAGE->set_heading('');

$mform = new ticket_edit_form($PAGE->url, ['b' => $token, 't' => $tickettoken]);
if ($mform->is_cancelled()) {
    redirect($back);
}
if ($data = $mform->get_data()) {
    $DB->update_record('local_evenements_ticket', (object) ['id' => $ticket->id, 'firstname' => trim($data->firstname),
        'lastname' => trim($data->lastname), 'email' => trim($data->email)]);
    redirect($back, get_string('changeguestdone', 'local_evenements'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$mform->set_data($ticket);

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('changeguest', 'local_evenements'), ['class' => 'h4']);
echo html_writer::tag('p', get_string('changeguesthelp', 'local_evenements'), ['class' => 'evt-note']);
$mform->display();
echo $OUTPUT->footer();
