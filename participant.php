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
 * Organiser page of one participant: add by hand (t = 0) or change, cancel, restore, delete for good.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\participant_manager;
use local_evenements\form\participant_form;
use local_evenements\output\ui;

$id = required_param('id', PARAM_INT);
$ticketid = optional_param('t', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();
booking_manager::require_event_access($id);
$event = booking_manager::get_event($id);
global $DB;

$ticket = $ticketid ? participant_manager::get_ticket($id, $ticketid) : null;
$booking = $ticket ? $DB->get_record('local_evenements_booking', ['id' => $ticket->bookingid], '*', MUST_EXIST) : null;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/participant.php', ['id' => $id] + ($ticketid ? ['t' => $ticketid] : [])));
ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string($ticket ? 'participantedit' : 'participantadd', 'local_evenements'));
$PAGE->set_heading('');

$list = new moodle_url('/local/evenements/registrations.php', ['id' => $id]);
$ok = \core\output\notification::NOTIFY_SUCCESS;
$error = \core\output\notification::NOTIFY_ERROR;

// One-click actions on an existing ticket.
if ($ticket && in_array($action, ['cancel', 'restore', 'resend', 'delete'], true)) {
    if ($action === 'delete' && !optional_param('confirm', 0, PARAM_BOOL)) {
        echo $OUTPUT->header();
        echo ui::event_header($event, 'registrations');
        echo $OUTPUT->confirm(
            get_string('participantdeleteconfirm', 'local_evenements', s(participant_manager::ticket_name($ticket))),
            new moodle_url($PAGE->url, ['action' => 'delete', 'confirm' => 1, 'sesskey' => sesskey()]),
            $PAGE->url
        );
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    try {
        switch ($action) {
            case 'cancel':
                participant_manager::cancel_ticket($event, $ticket);
                redirect($PAGE->url, get_string('participantcancelled', 'local_evenements'), null, $ok);
            case 'restore':
                participant_manager::restore_ticket($event, $ticket, optional_param('force', 0, PARAM_BOOL));
                redirect($PAGE->url, get_string('participantrestored', 'local_evenements'), null, $ok);
            case 'resend':
                $sent = participant_manager::resend($event, $booking);
                redirect($PAGE->url, get_string($sent ? 'participantresent' : 'participantresendfailed', 'local_evenements',
                    s($booking->email)), null, $sent ? $ok : $error);
            case 'delete':
                participant_manager::delete_ticket($event, $ticket);
                redirect($list, get_string('participantdeleted', 'local_evenements'), null, $ok);
        }
    } catch (moodle_exception $e) {
        redirect($PAGE->url, $e->getMessage(), null, $error);
    }
}

$others = 0;
if ($booking) {
    $others = $DB->count_records_select('local_evenements_ticket', 'bookingid = ? AND id <> ?', [$booking->id, $ticket->id]);
}
$mform = new participant_form($PAGE->url, ['event' => $event, 'ticketid' => $ticketid, 'others' => $others]);
if ($mform->is_cancelled()) {
    redirect($list);
}
if ($data = $mform->get_data()) {
    try {
        if ($ticket) {
            $changes = participant_manager::update_ticket($event, $ticket, $data, !empty($data->force));
            $message = $changes ? get_string('participantsaved', 'local_evenements') : get_string('participantnochange', 'local_evenements');
            $booking = $DB->get_record('local_evenements_booking', ['id' => $ticket->bookingid], '*', MUST_EXIST);
        } else {
            $booking = participant_manager::add_participant($event, $data, !empty($data->force));
            $message = get_string('participantadded', 'local_evenements');
        }
        $type = $ok;
        if (!empty($data->sendticket)) {
            if (participant_manager::resend($event, $booking)) {
                $message .= ' ' . get_string('participantresent', 'local_evenements', s($booking->email));
            } else {
                $message .= ' ' . get_string('participantresendfailed', 'local_evenements', s($booking->email));
                $type = \core\output\notification::NOTIFY_WARNING;
            }
        }
        if (!$ticket && optional_param('addanother', 0, PARAM_BOOL)) {
            redirect($PAGE->url, $message, null, $type);
        }
        redirect($list, $message, null, $type);
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

if ($ticket) {
    $mform->set_data([
        'firstname' => $ticket->firstname, 'lastname' => $ticket->lastname, 'email' => $ticket->email,
        'tickettypeid' => $ticket->tickettypeid, 'note' => $ticket->note, 'sessionid' => $booking->sessionid,
        'contactname' => $booking->contactname, 'contactemail' => $booking->email,
    ]);
}

echo $OUTPUT->header();
echo ui::event_header($event, 'registrations');
echo html_writer::div(html_writer::link($list, '← ' . get_string('registrations', 'local_evenements')), 'mb-2');
echo $OUTPUT->heading(get_string($ticket ? 'participantedit' : 'participantadd', 'local_evenements'), 2);

if ($ticket) {
    $dateformat = get_string('strftimedatetimeshort', 'langconfig');
    $status = html_writer::span(get_string('ticketstatus_' . $ticket->status, 'local_evenements'),
        'badge evt-tstatus evt-tstatus-' . $ticket->status);
    $facts = [
        get_string('status', 'local_evenements') => $status,
        get_string('participantnumber', 'local_evenements') => '#' . $ticket->id,
        get_string('participantbooked', 'local_evenements') => userdate($booking->timecreated, $dateformat),
    ];
    if ($ticket->timeused) {
        $facts[get_string('timeused', 'local_evenements')] = userdate($ticket->timeused, $dateformat);
    }
    $dl = '';
    foreach ($facts as $label => $value) {
        $dl .= html_writer::div(html_writer::tag('dt', $label) . html_writer::tag('dd', $value));
    }
    $actions = [];
    $url = function (string $a, array $extra = []) use ($PAGE) {
        return new moodle_url($PAGE->url, ['action' => $a, 'sesskey' => sesskey()] + $extra);
    };
    if ($ticket->status === booking_manager::TICKET_CANCELLED) {
        $actions[] = html_writer::link($url('restore'), get_string('participantrestore', 'local_evenements'),
            ['class' => 'btn btn-sm btn-success']);
    } else {
        $actions[] = html_writer::link($url('resend'), get_string('participantresendbtn', 'local_evenements'),
            ['class' => 'btn btn-sm btn-outline-primary']);
        $actions[] = html_writer::link(booking_manager::booking_url($booking), get_string('participantopenticket', 'local_evenements'),
            ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank', 'rel' => 'noopener']);
        $actions[] = html_writer::link($url('cancel'), get_string('participantcancel', 'local_evenements'),
            ['class' => 'btn btn-sm btn-outline-warning']);
    }
    $actions[] = html_writer::link(new moodle_url($PAGE->url, ['action' => 'delete']),
        get_string('participantdelete', 'local_evenements'), ['class' => 'btn btn-sm btn-outline-danger']);
    echo html_writer::div(html_writer::tag('dl', $dl, ['class' => 'evt-facts']) .
        html_writer::div(implode(' ', $actions), 'evt-factactions'), 'evt-panel evt-participant-head');
    if ($ticket->status === booking_manager::TICKET_CANCELLED) {
        echo $OUTPUT->notification(get_string('participantiscancelled', 'local_evenements'), 'warning');
    }
}

$mform->display();
if (!$ticket) {
    // "Save and add another" sends the same form with one more flag.
    $js = "(function(){var f=document.querySelector('form.mform');if(!f){return;}var b=f.querySelector('#id_submitbutton');" .
        "if(!b){return;}var n=document.createElement('input');n.type='submit';n.name='addanother';n.value=" .
        json_encode(get_string('participantaddanother', 'local_evenements')) . ";n.className='btn btn-secondary ms-2';" .
        "b.parentNode.insertBefore(n,b.nextSibling);})();";
    echo html_writer::script($js);
}

if ($ticket) {
    $history = participant_manager::get_log($id, 0, (int) $ticket->id);
    if ($history) {
        echo $OUTPUT->heading(get_string('changelog', 'local_evenements'), 3);
        echo ui::changelog_table($history);
    }
}

echo $OUTPUT->footer();
