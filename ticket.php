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
 * Personal page of a booking, reached with the secret link sent by e-mail.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$token = required_param('b', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);

$booking = booking_manager::get_booking_by_token($token);
if (!$booking) {
    throw new moodle_exception('invalidlink', 'local_evenements');
}
$event = booking_manager::get_event((int) $booking->eventid);
$event = booking_manager::apply_session($event, $booking);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/ticket.php', ['b' => $token]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('mytickets', 'local_evenements'));
$PAGE->set_heading('');

$active = ($booking->status === 'active');

// Cancellation, with confirmation.
if ($action === 'cancel' && $active) {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        booking_manager::cancel_booking($booking);
        redirect($PAGE->url, get_string('bookingcancelled', 'local_evenements'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('cancelconfirm', 'local_evenements'),
        new moodle_url($PAGE->url, ['action' => 'cancel', 'confirm' => 1, 'sesskey' => sesskey()]),
        $PAGE->url
    );
    echo $OUTPUT->footer();
    exit;
}

$tickets = booking_manager::get_tickets((int) $booking->id);
$categories = booking_manager::get_categories((int) $event->id);

use local_evenements\output\ui;

echo $OUTPUT->header();
echo ui::steps($active ? 3 : 1);
if (optional_param('new', 0, PARAM_INT) === 1 && $active) {
    echo html_writer::div(html_writer::span('✓', 'badge bg-success text-white fs-5') .
        html_writer::div(html_writer::tag('strong', get_string('bookingconfirmed', 'local_evenements')) .
            get_string('bookingconfirmedtext', 'local_evenements')), 'evt-success');
}
echo html_writer::div(
    ui::date_badge($event) .
    html_writer::div(html_writer::div(format_string($event->name), 'evt-strip-title') . ui::meta($event)),
    'evt-strip');

if (!$active) {
    echo $OUTPUT->notification(get_string('bookingiscancelled', 'local_evenements'),
        \core\output\notification::NOTIFY_WARNING);
}

echo html_writer::tag('h2', get_string('mytickets', 'local_evenements'), ['class' => 'h4']);
echo html_writer::start_div('evt-tickets');
foreach ($tickets as $ticket) {
    $category = booking_manager::ticket_label($ticket, $categories);
    $link = '';
    if ($active && booking_manager::can_edit_ticket($event, $ticket)) {
        $link .= html_writer::link(new moodle_url('/local/evenements/editticket.php', ['b' => $token, 't' => $ticket->token]),
            get_string('changeguest', 'local_evenements'), ['class' => 'btn btn-outline-secondary btn-sm me-1']);
    }
    if ($ticket->status !== booking_manager::TICKET_CANCELLED) {
        $link .= html_writer::link(new moodle_url('/local/evenements/download.php',
            ['b' => $token, 't' => $ticket->token]), get_string('downloadticket', 'local_evenements'),
            ['class' => 'btn btn-outline-primary btn-sm']);
    }
    $statusclass = $ticket->status === booking_manager::TICKET_CANCELLED ? 'is-cancelled' :
        ($ticket->status === booking_manager::TICKET_USED ? 'is-used' : '');
    echo html_writer::div(
        html_writer::div(html_writer::div(s($ticket->firstname . ' ' . $ticket->lastname), 'evt-ticket-name') .
            html_writer::div(s($category), 'evt-ticket-cat'), 'evt-ticket-main') .
        html_writer::div(html_writer::span(get_string('ticketstatus_' . $ticket->status, 'local_evenements'),
            'badge bg-light text-dark border') . $link, 'evt-ticket-actions'),
        'evt-ticket ' . $statusclass);
}
echo html_writer::end_div();

if ($active) {
    echo html_writer::start_div('evt-actions');
    echo html_writer::link(new moodle_url('/local/evenements/download.php', ['b' => $token]),
        get_string('downloadall', 'local_evenements'), ['class' => 'btn btn-primary']);
    if (in_array($event->ticketformat ?? 'concert', ['compact', 'boarding'], true)) {
        echo html_writer::link(new moodle_url('/local/evenements/download.php', ['b' => $token, 'sheet' => 1]),
            html_writer::tag('i', '', ['class' => 'fa-solid fa-print me-1', 'aria-hidden' => 'true']) .
            get_string('printsheetA4', 'local_evenements'), ['class' => 'btn btn-outline-secondary']);
    }
    echo html_writer::link(new moodle_url('/local/evenements/ics.php', ['b' => $token]),
        html_writer::tag('i', '', ['class' => 'fa-regular fa-calendar-plus me-1', 'aria-hidden' => 'true']) .
        get_string('addtocalendar', 'local_evenements'), ['class' => 'btn btn-outline-secondary']);
    $gend = $event->timeend ?: $event->timestart + 2 * HOURSECS;
    echo html_writer::link(new moodle_url('https://calendar.google.com/calendar/render', [
        'action' => 'TEMPLATE', 'text' => format_string($event->name, true, ['escape' => false]),
        'dates' => gmdate('Ymd\THis\Z', (int) $event->timestart) . '/' . gmdate('Ymd\THis\Z', (int) $gend),
        'location' => (string) ($event->location ?? ''), 'details' => $PAGE->url->out(false)]),
        get_string('addtogooglecalendar', 'local_evenements'), ['class' => 'btn btn-outline-secondary', 'target' => '_blank',
            'rel' => 'noopener']);
    echo html_writer::link(new moodle_url($PAGE->url, ['action' => 'cancel']),
        get_string('cancelbooking', 'local_evenements'), ['class' => 'btn btn-outline-danger']);
    echo html_writer::end_div();
    if (time() >= $event->timestart && booking_manager::get_questions((int) $event->id, 'after')) {
        echo html_writer::div(html_writer::link(new moodle_url('/local/evenements/survey.php', ['b' => $token]),
            get_string('givefeedback', 'local_evenements'), ['class' => 'btn btn-outline-primary']), 'mt-3');
    }
    echo html_writer::tag('p', get_string('keepthislink', 'local_evenements'), ['class' => 'text-muted mt-3']);
}

echo $OUTPUT->footer();
