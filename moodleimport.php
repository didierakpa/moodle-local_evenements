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
 * Add people from Moodle (cohort or course) to an event: invitation list or participants.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\moodle_import;
use local_evenements\form\moodle_import_form;
use local_evenements\output\ui;

$id = required_param('id', PARAM_INT);
require_login();
booking_manager::require_event_access($id);
$event = booking_manager::get_event($id);
$islist = booking_manager::is_list_event($event);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/moodleimport.php', ['id' => $id]));
ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('mimporttitle', 'local_evenements'));
$PAGE->set_heading('');

$back = new moodle_url($islist ? '/local/evenements/invitees.php' : '/local/evenements/registrations.php', ['id' => $id]);
$mform = new moodle_import_form($PAGE->url, ['event' => $event]);
if ($mform->is_cancelled()) {
    redirect($back);
}

$preview = null;
$sourcename = '';
if ($data = $mform->get_data()) {
    global $DB;
    $sourceid = $data->source === 'cohort' ? (int) ($data->cohortid ?? 0) : (int) $data->courseid;
    $people = moodle_import::people($data->source, $sourceid, $data->source === 'course' ? (int) $data->roleid : 0);
    $sourcename = $data->source === 'cohort' ? format_string($DB->get_field('cohort', 'name', ['id' => $sourceid])) :
        format_string(get_course($sourceid)->fullname);
    if (!empty($data->previewbutton) || !$people) {
        $preview = $people;
    } else {
        $data->sourcename = $sourcename;
        $report = moodle_import::add($event, $people, $data);
        $message = get_string('mimportdone', 'local_evenements', $report);
        if ($report->messages) {
            $message .= html_writer::alist(array_map('s', array_slice($report->messages, 0, 10)));
        }
        redirect($back, $message, null, $report->failed ? \core\output\notification::NOTIFY_WARNING :
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo ui::event_header($event, 'moodleimport');
echo html_writer::div(html_writer::link($back, '← ' . get_string($islist ? 'tabinvitees' : 'registrations', 'local_evenements')), 'mb-2');
echo $OUTPUT->heading(get_string('mimporttitle', 'local_evenements'), 2);
echo html_writer::tag('p', get_string($islist ? 'mimportintrolist' : 'mimportintro', 'local_evenements'), ['class' => 'evt-note']);

if ($preview !== null) {
    if (!$preview) {
        echo $OUTPUT->notification(get_string('mimportnobody', 'local_evenements', s($sourcename)), 'warning');
    } else {
        $items = '';
        foreach (array_slice($preview, 0, 200) as $user) {
            $items .= html_writer::tag('li', s(core_text::strtoupper($user->lastname) . ' ' . $user->firstname) .
                ($user->email ? html_writer::span(' · ' . s($user->email), 'evt-psub d-inline') : ''));
        }
        $more = count($preview) > 200 ? html_writer::tag('p', get_string('mimportmore', 'local_evenements', count($preview) - 200),
            ['class' => 'evt-note']) : '';
        echo html_writer::div(html_writer::tag('h3', get_string('mimportpreviewtitle', 'local_evenements',
                (object) ['n' => count($preview), 'source' => s($sourcename)]), ['class' => 'h5']) .
            html_writer::tag('ol', $items, ['class' => 'evt-previewlist']) . $more .
            html_writer::tag('p', get_string('mimportpreviewhelp', 'local_evenements'), ['class' => 'evt-note mb-0']),
            'evt-panel mb-3');
    }
}

$mform->display();
echo $OUTPUT->footer();
