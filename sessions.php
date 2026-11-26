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
 * Time slots of an event (for example guided visits at 10:00, 11:00, 14:00), each with its own capacity.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\session_form;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sid = optional_param('s', 0, PARAM_INT);

require_login();
booking_manager::require_event_access($id);
$event = booking_manager::get_event($id);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/sessions.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('tabsessions', 'local_evenements'));
$PAGE->set_heading('');

global $DB;
if ($action === 'delete' && $sid && confirm_sesskey()) {
    if ($DB->record_exists('local_evenements_booking', ['sessionid' => $sid])) {
        redirect($PAGE->url, get_string('sessioninuse', 'local_evenements'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $DB->delete_records('local_evenements_session', ['id' => $sid, 'eventid' => $id]);
    redirect($PAGE->url, get_string('sessiondeleted', 'local_evenements'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform = new session_form(new moodle_url('/local/evenements/sessions.php', ['id' => $id, 's' => $sid]), ['event' => $event]);
if ($mform->is_cancelled()) {
    redirect($PAGE->url);
}
if ($data = $mform->get_data()) {
    $record = (object) ['eventid' => $id, 'name' => trim($data->name), 'timestart' => $data->timestart,
        'timeend' => $data->timeend, 'capacity' => max(0, (int) $data->capacity)];
    $count = max(1, min(30, (int) ($data->repeatcount ?? 1)));
    if ($sid) {
        $record->id = $sid;
        $DB->update_record('local_evenements_session', $record);
    } else {
        // Several slots at once: same length, one after the other (+ the length of a slot).
        $length = $record->timeend ? $record->timeend - $record->timestart : 0;
        for ($i = 0; $i < $count; $i++) {
            $copy = clone $record;
            $copy->timestart = $record->timestart + $i * ($length ?: HOURSECS);
            $copy->timeend = $length ? $copy->timestart + $length : 0;
            $DB->insert_record('local_evenements_session', $copy);
        }
    }
    redirect($PAGE->url, get_string('sessionsaved', 'local_evenements'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($sid && ($session = $DB->get_record('local_evenements_session', ['id' => $sid, 'eventid' => $id]))) {
    $mform->set_data($session);
} else {
    $mform->set_data(['timestart' => $event->timestart, 'timeend' => $event->timestart + HOURSECS]);
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'sessions');
echo html_writer::tag('p', get_string('sessionshelp', 'local_evenements'), ['class' => 'evt-note']);

$sessions = booking_manager::get_sessions($id);
if ($sessions) {
    $rows = '';
    foreach ($sessions as $session) {
        $booked = booking_manager::count_session_tickets((int) $session->id);
        $rows .= html_writer::tag(
            'tr',
            html_writer::tag('td', html_writer::span(s(booking_manager::session_label($session)), 'evt-name')) .
            html_writer::tag('td', $booked . ($session->capacity ? ' / ' . $session->capacity : '')) .
            html_writer::tag(
                'td',
                html_writer::link(
                    new moodle_url($PAGE->url, ['s' => $session->id]),
                    get_string('edit'),
                    ['class' => 'btn btn-sm btn-outline-secondary me-1']
                ) .
                html_writer::link(
                    new moodle_url($PAGE->url, ['action' => 'delete', 's' => $session->id,
                    'sesskey' => sesskey()]),
                    get_string('delete'),
                    ['class' => 'btn btn-sm btn-outline-danger']
                )
            )
        );
    }
    echo html_writer::tag('table', html_writer::tag('thead', html_writer::tag(
        'tr',
        html_writer::tag('th', get_string('tabsessions', 'local_evenements')) .
        html_writer::tag('th', get_string('statticketsall', 'local_evenements')) . html_writer::tag('th', '')
    )) .
        html_writer::tag('tbody', $rows), ['class' => 'evt-table mb-4']);
} else {
    echo html_writer::div(get_string('nosessions', 'local_evenements'), 'evt-panel evt-empty mb-4');
}

echo html_writer::tag('h2', get_string($sid ? 'editsession' : 'addsession', 'local_evenements'), ['class' => 'h4']);
$mform->display();
echo $OUTPUT->footer();
