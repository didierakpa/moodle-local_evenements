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
 * Import a list of guests (CSV): one named ticket per row, optionally sent by e-mail.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\import_form;

$id = required_param('id', PARAM_INT);
require_login();
booking_manager::require_event_access($id);
$event = booking_manager::get_event($id);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/import.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('importguests', 'local_evenements'));
$PAGE->set_heading('');

$sessions = booking_manager::get_sessions($id);
$mform = new import_form($PAGE->url, ['sessions' => $sessions]);
$report = null;
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/evenements/registrations.php', ['id' => $id]));
}
if ($data = $mform->get_data()) {
    $content = $mform->get_file_content('csvfile');
    $report = booking_manager::import_guests($event, (string) $content, !empty($data->sendmail), (int) ($data->sessionid ?? 0));
    if ($report->created) {
        \local_evenements\participant_manager::log((int) $event->id, 0, 0, 'add',
            get_string('logimported', 'local_evenements', $report->created));
    }
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'registrations');
if ($report) {
    echo $OUTPUT->notification(get_string('importresult', 'local_evenements', (object) ['created' => $report->created,
        'skipped' => $report->skipped, 'errors' => count($report->errors)]),
        $report->created ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING);
    if ($report->errors) {
        echo html_writer::tag('ul', implode('', array_map(function ($e) {
            return html_writer::tag('li', s($e));
        }, $report->errors)), ['class' => 'text-danger']);
    }
    echo html_writer::div(html_writer::link(new moodle_url('/local/evenements/registrations.php', ['id' => $id]),
        get_string('registrations', 'local_evenements'), ['class' => 'btn btn-primary']), 'mb-3');
}
echo html_writer::tag('p', get_string('importhelp', 'local_evenements'), ['class' => 'evt-note']);
$mform->display();
echo $OUTPUT->footer();
