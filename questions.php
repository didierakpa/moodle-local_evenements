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
 * Questions of an event: asked at booking time or in the survey after the event.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\question_form;

$eventid = required_param('id', PARAM_INT);
$qid = optional_param('q', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();
$context = context_system::instance();
booking_manager::require_event_access($eventid);

$event = booking_manager::get_event($eventid);

// A question of another event cannot be changed from here.
if ($qid && !$DB->record_exists('local_evenements_question', ['id' => $qid, 'eventid' => $eventid])) {
    throw new moodle_exception('invalidquestion', 'local_evenements');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/questions.php', ['id' => $eventid]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('questionnaires', 'local_evenements'));
$PAGE->set_heading('');

global $DB;

// Deletion, with confirmation.
if ($action === 'delete' && $qid) {
    $question = $DB->get_record('local_evenements_question', ['id' => $qid, 'eventid' => $eventid], '*', MUST_EXIST);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $DB->delete_records('local_evenements_answer', ['questionid' => $qid]);
        $DB->delete_records('local_evenements_question', ['id' => $qid]);
        redirect(
            $PAGE->url,
            get_string('questiondeleted', 'local_evenements'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('deletequestionconfirm', 'local_evenements', format_string($question->name)),
        new moodle_url($PAGE->url, ['action' => 'delete', 'q' => $qid, 'confirm' => 1, 'sesskey' => sesskey()]),
        $PAGE->url
    );
    echo $OUTPUT->footer();
    exit;
}

// Add a ready-made questionnaire.
if ($action === 'addmodel' && confirm_sesskey()) {
    $added = \local_evenements\question_models::add_to_event($eventid, optional_param('m', '', PARAM_ALPHA));
    redirect(
        $PAGE->url,
        $added ? get_string('qmodeladded', 'local_evenements', $added) : get_string('qmodelnone', 'local_evenements'),
        null,
        $added ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO
    );
}

// Move up or down.
if (($action === 'up' || $action === 'down') && $qid && confirm_sesskey()) {
    $list = array_values($DB->get_records(
        'local_evenements_question',
        ['eventid' => $eventid],
        'sortorder ASC, id ASC'
    ));
    foreach ($list as $index => $item) {
        if ((int) $item->id === $qid) {
            $other = $action === 'up' ? $index - 1 : $index + 1;
            if (isset($list[$other])) {
                // Renumber everything to avoid equal sort orders, then swap.
                [$list[$index], $list[$other]] = [$list[$other], $list[$index]];
                foreach ($list as $position => $entry) {
                    $DB->set_field('local_evenements_question', 'sortorder', $position + 1, ['id' => $entry->id]);
                }
            }
            break;
        }
    }
    redirect($PAGE->url);
}

$mform = new question_form(new moodle_url('/local/evenements/questions.php', ['id' => $eventid, 'q' => $qid]));

if ($mform->is_cancelled()) {
    redirect($PAGE->url);
}

if ($data = $mform->get_data()) {
    $record = new stdClass();
    $record->eventid = $eventid;
    $record->name = trim($data->name);
    $record->moment = $data->moment;
    $record->scope = $data->moment === 'after' ? 'booking' : $data->scope;
    $record->type = $data->type;
    $record->options = ($data->type === 'choice' || $data->type === 'multichoice') ? trim($data->options) : '';
    $record->isrequired = empty($data->isrequired) ? 0 : 1;

    if ($qid) {
        $record->id = $qid;
        $DB->update_record('local_evenements_question', $record);
    } else {
        $max = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {local_evenements_question} WHERE eventid = ?', [$eventid]);
        $record->sortorder = $max + 1;
        $DB->insert_record('local_evenements_question', $record);
    }
    redirect(
        $PAGE->url,
        get_string('questionsaved', 'local_evenements'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($qid) {
    $question = $DB->get_record('local_evenements_question', ['id' => $qid, 'eventid' => $eventid], '*', MUST_EXIST);
    $question->q = $question->id;
    $question->id = $eventid;
    $mform->set_data($question);
} else {
    $mform->set_data((object) ['id' => $eventid, 'q' => 0]);
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'questions');
echo html_writer::tag('p', get_string('questionshelp', 'local_evenements'), ['class' => 'text-muted']);
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/evenements/results.php', ['id' => $eventid]),
        get_string('results', 'local_evenements'),
        ['class' => 'btn btn-secondary me-2']
    ) .
    html_writer::link(
        new moodle_url('/local/evenements/manage.php'),
        get_string('backtomanage', 'local_evenements'),
        ['class' => 'btn btn-outline-secondary']
    ),
    'mb-3'
);

$questions = booking_manager::get_questions($eventid);
if (!$questions) {
    echo $OUTPUT->notification(get_string('noquestions', 'local_evenements'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('questiontext', 'local_evenements'),
        get_string('moment', 'local_evenements'),
        get_string('scope', 'local_evenements'),
        get_string('type', 'local_evenements'),
        get_string('isrequired', 'local_evenements'),
        get_string('actions', 'local_evenements'),
    ];
    $table->attributes['class'] = 'table table-striped';
    foreach ($questions as $question) {
        $base = ['id' => $eventid, 'q' => $question->id];
        $links = [
            html_writer::link(new moodle_url($PAGE->url, $base), get_string('edit')),
            html_writer::link(
                new moodle_url($PAGE->url, $base + ['action' => 'up', 'sesskey' => sesskey()]),
                get_string('moveup')
            ),
            html_writer::link(
                new moodle_url($PAGE->url, $base + ['action' => 'down', 'sesskey' => sesskey()]),
                get_string('movedown')
            ),
            html_writer::link(
                new moodle_url($PAGE->url, $base + ['action' => 'delete']),
                get_string('delete'),
                ['class' => 'text-danger']
            ),
        ];
        $table->data[] = [
            format_string($question->name),
            get_string('moment_' . $question->moment, 'local_evenements'),
            $question->moment === 'before' ? get_string('scope_' . $question->scope, 'local_evenements') : '',
            get_string('type_' . $question->type, 'local_evenements'),
            $question->isrequired ? get_string('yes') : get_string('no'),
            implode(' · ', $links),
        ];
    }
    echo html_writer::table($table);
}

// Ready-made questionnaires.
$cards = '';
foreach (\local_evenements\question_models::MODELS as $key => [$moment, $icon]) {
    $list = '';
    foreach (\local_evenements\question_models::questions($key) as $q) {
        $list .= html_writer::tag('li', s($q['name']));
    }
    $cards .= html_writer::div(
        html_writer::div(html_writer::tag('i', '', ['class' => 'fa-solid fa-' . $icon, 'aria-hidden' => 'true']) .
            html_writer::span(
                get_string($moment === 'before' ? 'qmodelbefore' : 'qmodelafter', 'local_evenements'),
                'evt-qm-moment evt-qm-' . $moment
            ), 'evt-qm-top') .
        html_writer::tag('h3', get_string('qm_' . $key, 'local_evenements'), ['class' => 'h6']) .
        html_writer::tag('p', get_string('qm_' . $key . '_desc', 'local_evenements'), ['class' => 'evt-note']) .
        html_writer::tag('details', html_writer::tag('summary', get_string('qmodelpreview', 'local_evenements') . ' (' .
            \local_evenements\question_models::count($key) . ')') . html_writer::tag('ul', $list), ['class' => 'evt-qm-prev']) .
        html_writer::tag(
            'form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addmodel']) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'm', 'value' => $key]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $eventid]) .
            html_writer::tag('button', get_string('qmodeladd', 'local_evenements'), ['type' => 'submit',
                'class' => 'btn btn-sm btn-outline-primary']),
            ['method' => 'post', 'action' => $PAGE->url->out(false)]
        ),
        'evt-qm'
    );
}
echo html_writer::tag('details', html_writer::tag('summary', get_string('qmodels', 'local_evenements'), ['class' => 'h5 mb-2']) .
    html_writer::tag('p', get_string('qmodelshelp', 'local_evenements'), ['class' => 'evt-note mb-2']) .
    html_writer::div($cards, 'evt-qm-grid'), ['class' => 'evt-qm-wrap mb-4', 'open' => 'open']);

echo $OUTPUT->heading(get_string($qid ? 'editquestion' : 'addquestion', 'local_evenements'), 3);
$mform->display();

echo $OUTPUT->footer();
