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
 * Summary of the answers to the questions of an event.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
booking_manager::require_event_access($id, 'local/evenements:viewregistrations');

$event = booking_manager::get_event($id);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/results.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('results', 'local_evenements'));
$PAGE->set_heading('');

global $DB;
$byquestion = [];
foreach ($DB->get_records('local_evenements_answer', ['eventid' => $id], 'id ASC') as $answer) {
    $byquestion[$answer->questionid][] = $answer->value;
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'results');
echo html_writer::div(
    html_writer::link(new moodle_url('/local/evenements/export.php', ['id' => $id]),
        get_string('exportcsv', 'local_evenements'), ['class' => 'btn btn-secondary me-2']) .
    html_writer::link(new moodle_url('/local/evenements/questions.php', ['id' => $id]),
        get_string('questionnaires', 'local_evenements'), ['class' => 'btn btn-outline-secondary']),
    'mb-3'
);

$questions = booking_manager::get_questions($id);
if (!$questions) {
    echo $OUTPUT->notification(get_string('noquestions', 'local_evenements'), \core\output\notification::NOTIFY_INFO);
}

foreach ($questions as $question) {
    $values = $byquestion[$question->id] ?? [];
    echo html_writer::tag('h4', format_string($question->name), ['class' => 'mt-4']);
    echo html_writer::tag('p', get_string('moment_' . $question->moment, 'local_evenements') . ' · ' .
        get_string('answerscount', 'local_evenements', count($values)), ['class' => 'text-muted']);

    if (!$values) {
        echo html_writer::tag('p', get_string('noanswers', 'local_evenements'));
        continue;
    }

    if ($question->type === 'choice' || $question->type === 'multichoice') {
        $counts = array_fill_keys(array_keys(booking_manager::get_question_options($question)), 0);
        foreach ($values as $value) {
            foreach (preg_split('/\R/', $value) as $choice) {
                if (isset($counts[$choice])) {
                    $counts[$choice]++;
                }
            }
        }
        $total = max(1, count($values));
        $table = new html_table();
        $table->attributes['class'] = 'table table-sm';
        $table->head = [get_string('answer', 'local_evenements'), get_string('count', 'local_evenements'), '%'];
        foreach ($counts as $choice => $count) {
            $table->data[] = [s($choice), $count, round(100 * $count / $total) . ' %'];
        }
        echo html_writer::table($table);
    } else {
        echo html_writer::start_tag('ul');
        foreach (array_slice($values, 0, 200) as $value) {
            echo html_writer::tag('li', s($value));
        }
        echo html_writer::end_tag('ul');
    }
}

echo $OUTPUT->footer();
