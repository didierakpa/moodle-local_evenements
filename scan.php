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
 * Entrance check. The QR code of a ticket opens this page: an agent with the
 * "scan" capability sees the guest and validates the entry.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$raw = optional_param('t', '', PARAM_RAW);
$action = optional_param('action', '', PARAM_ALPHA);

$token = '';
if ($raw !== '' && preg_match('/[0-9a-f]{40}/i', $raw, $m)) {
    $token = strtolower($m[0]);
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/scan.php', $token !== '' ? ['t' => $token] : []));
\local_evenements\output\ui::console_layout();
$PAGE->set_title(get_string('scan', 'local_evenements'));
$PAGE->set_heading(get_string('scan', 'local_evenements'));

require_login();
if (!has_capability('local/evenements:scan', $context) && !booking_manager::organiser_event_ids()) {
    require_capability('local/evenements:scan', $context);
}

$resultcode = '';
$ticket = $token !== '' ? booking_manager::get_ticket_by_token($token) : false;

if ($ticket && $action === 'validate' && confirm_sesskey()) {
    $resultcode = booking_manager::check_in($ticket, (int) $USER->id);
    $ticket = booking_manager::get_ticket_by_token($token);
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::console(null, 'scan');
echo $OUTPUT->heading(get_string('scan', 'local_evenements'));
foreach (booking_manager::get_live_counters() as $live) {
    echo html_writer::div(
        get_string('livecounter', 'local_evenements', (object) [
        'name' => format_string($live->name), 'used' => $live->used, 'total' => $live->total]),
        'evt-scan-counter'
    );
}

if ($token !== '' && !$ticket) {
    echo html_writer::div(get_string('ticketnotfound', 'local_evenements'), 'alert alert-danger fs-4 text-center');
} else if ($ticket) {
    $event = booking_manager::get_event((int) $ticket->eventid);
    $categories = booking_manager::get_categories((int) $event->id);
    $dateformat = get_string('strftimedatetime', 'langconfig');

    if ($ticket->status === booking_manager::TICKET_VALID) {
        $class = 'alert alert-success';
        $message = get_string('ticketvalid', 'local_evenements');
    } else if ($ticket->status === booking_manager::TICKET_USED) {
        $class = 'alert alert-warning';
        $message = get_string('alreadyused', 'local_evenements', userdate($ticket->timeused, $dateformat));
    } else {
        $class = 'alert alert-danger';
        $message = get_string('ticketcancelled', 'local_evenements');
    }

    if ($resultcode === 'accepted') {
        echo $OUTPUT->notification(
            get_string('entryvalidated', 'local_evenements'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo html_writer::start_div($class . ' text-center');
    echo html_writer::tag('div', $message, ['class' => 'fs-3 fw-bold']);
    echo html_writer::tag('div', s($ticket->firstname . ' ' . $ticket->lastname), ['class' => 'display-6 my-2']);
    if (!empty($categories[$ticket->tickettypeid])) {
        echo html_writer::tag('div', s(booking_manager::ticket_label($ticket, $categories)), ['class' => 'fs-5']);
    }
    echo html_writer::tag(
        'div',
        format_string($event->name) . ' · ' . s(booking_manager::event_when($event)),
        ['class' => 'mt-2 small']
    );
    echo html_writer::end_div();

    if ($ticket->status === booking_manager::TICKET_VALID) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'text-center']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'validate']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 't', 'value' => $token]);
        echo html_writer::tag(
            'button',
            get_string('validateentry', 'local_evenements'),
            ['type' => 'submit', 'class' => 'btn btn-success btn-lg']
        );
        echo html_writer::end_tag('form');
    } else {
        echo html_writer::div(html_writer::link(
            new moodle_url('/local/evenements/scan.php', ['camera' => 1]),
            get_string('nextticket', 'local_evenements'),
            ['class' => 'btn btn-primary btn-lg']
        ), 'text-center mt-3');
    }
}

// Camera scan (needs HTTPS and a browser with the BarcodeDetector API, e.g. Chrome on Android).
$autostart = optional_param('camera', 0, PARAM_BOOL);
$cameratexts = [
    'start' => get_string('scancamera', 'local_evenements'),
    'stop' => get_string('camerastop', 'local_evenements'),
    'unsupported' => get_string('cameranotsupported', 'local_evenements'),
    'denied' => get_string('cameradenied', 'local_evenements'),
];
echo html_writer::start_div('text-center my-4');
echo html_writer::tag('button', $cameratexts['start'], ['type' => 'button', 'id' => 'scan-camera-btn',
    'class' => 'btn btn-primary btn-lg']);
echo html_writer::tag('p', '', ['id' => 'scan-camera-msg', 'class' => 'text-danger mt-2']);
echo html_writer::start_div('', ['id' => 'scan-camera-box', 'style' => 'display:none;max-width:480px;margin:1rem auto;']);
echo html_writer::tag('video', '', ['id' => 'scan-video', 'playsinline' => 'playsinline', 'muted' => 'muted',
    'autoplay' => 'autoplay', 'style' => 'width:100%;border-radius:8px;background:#000;']);
echo html_writer::end_div();
echo html_writer::end_div();

$PAGE->requires->js_call_amd('local_evenements/scan', 'initCamera', [
    $cameratexts,
    (new moodle_url('/local/evenements/scan.php'))->out(false),
    (bool) $autostart,
]);

// Offline mode: the list of tickets is stored in the phone; entries are sent later.
$offlinetexts = [
    'noevent' => get_string('offlinenoevent', 'local_evenements'),
    'downloaded' => get_string('offlinedownloaded', 'local_evenements'),
    'failed' => get_string('offlinefailed', 'local_evenements'),
    'valid' => get_string('ticketvalid', 'local_evenements'),
    'used' => get_string('offlineused', 'local_evenements'),
    'unknown' => get_string('ticketnotfound', 'local_evenements'),
    'pending' => get_string('offlinepending', 'local_evenements'),
    'synced' => get_string('offlinesynced', 'local_evenements'),
    'conflicts' => get_string('offlineconflicts', 'local_evenements'),
    'scan' => get_string('offlinescan', 'local_evenements'),
    'stop' => get_string('camerastop', 'local_evenements'),
    'unsupported' => get_string('cameranotsupported', 'local_evenements'),
    'denied' => get_string('cameradenied', 'local_evenements'),
    'cleared' => get_string('offlinecleared', 'local_evenements'),
];
$offlineevents = [];
foreach (
    $DB->get_records_select(
        'local_evenements_event',
        'status = :s',
        ['s' => booking_manager::STATUS_OPEN],
        'timestart DESC'
    ) as $e
) {
    if (has_capability('local/evenements:scan', $context) || booking_manager::can_manage((int) $e->id)) {
        $offlineevents[$e->id] = format_string($e->name);
    }
}
if ($offlineevents) {
    echo html_writer::start_div('evt-panel mt-4', ['id' => 'evt-offline']);
    echo html_writer::tag('h2', get_string('offlinemode', 'local_evenements'), ['class' => 'h5']);
    echo html_writer::tag('p', get_string('offlinehelp', 'local_evenements'), ['class' => 'evt-note']);
    echo html_writer::select($offlineevents, 'offevent', '', false, ['id' => 'off-event', 'class' => 'form-select mb-2']);
    echo html_writer::div(
        html_writer::tag('button', get_string('offlinedownload', 'local_evenements'), ['type' => 'button', 'id' => 'off-download',
            'class' => 'btn btn-outline-primary']) .
        html_writer::tag('button', get_string('offlinescan', 'local_evenements'), ['type' => 'button', 'id' => 'off-scan',
            'class' => 'btn btn-primary']) .
        html_writer::tag('button', get_string('offlinesync', 'local_evenements'), ['type' => 'button', 'id' => 'off-sync',
            'class' => 'btn btn-outline-success']),
        'd-flex flex-wrap gap-2'
    );
    echo html_writer::tag('p', '', ['id' => 'off-status', 'class' => 'mt-2 mb-1', 'aria-live' => 'polite']);
    echo html_writer::div('', 'text-center fs-3 fw-bold rounded p-3 d-none', ['id' => 'off-result']);
    echo html_writer::start_div('', ['id' => 'off-box', 'style' => 'display:none;max-width:480px;margin:1rem auto;']);
    echo html_writer::tag('video', '', ['id' => 'off-video', 'playsinline' => 'playsinline', 'muted' => 'muted',
        'autoplay' => 'autoplay', 'style' => 'width:100%;border-radius:8px;background:#000;']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    $PAGE->requires->js_call_amd('local_evenements/scan', 'initOffline', [$offlinetexts]);
}

// Manual entry: paste the token or the full address of the QR code.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => (new moodle_url('/local/evenements/scan.php'))->out(false),
    'class' => 'mt-4']);
echo html_writer::tag('label', get_string('tokenlabel', 'local_evenements'), ['for' => 'scan-token', 'class' => 'form-label']);
echo html_writer::start_div('input-group');
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 't', 'id' => 'scan-token', 'class' => 'form-control',
    'autocomplete' => 'off', 'autofocus' => 'autofocus']);
echo html_writer::tag('button', get_string('checkticket', 'local_evenements'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
