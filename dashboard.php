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
 * Organiser dashboard of an event: key figures, booking curve, categories, latest bookings.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\output\ui;

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
booking_manager::require_event_access($id);

$event = booking_manager::get_event($id);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/dashboard.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(format_string($event->name));
$PAGE->set_heading('');

$newstatus = ['publish' => booking_manager::STATUS_OPEN, 'close' => booking_manager::STATUS_CLOSED,
    'draft' => booking_manager::STATUS_DRAFT];
$statusaction = optional_param('action', '', PARAM_ALPHA);
if (isset($newstatus[$statusaction]) && confirm_sesskey()) {
    $DB->set_field('local_evenements_event', 'status', $newstatus[$statusaction], ['id' => $id]);
    $messages = ['publish' => 'eventpublished', 'close' => 'eventclosed', 'draft' => 'eventbacktodraft'];
    redirect($PAGE->url, get_string($messages[$statusaction], 'local_evenements'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$stats = booking_manager::get_event_stats($event);

echo $OUTPUT->header();
echo ui::event_header($event, 'dashboard');

// Checklist to get the event ready (shown until the event is published).
if ($event->status !== booking_manager::STATUS_OPEN) {
    $imageurl = booking_manager::get_image_url($event);
    $hasquestions = $DB->record_exists('local_evenements_question', ['eventid' => $id]);
    $steps = [
        [true, 'checkinfo', null, ''],
        [!empty($stats->categories), 'checktickets', new moodle_url('/local/evenements/edit.php', ['id' => $id]), 'checkticketshelp'],
        [$hasquestions, 'checkquestions', new moodle_url('/local/evenements/questions.php', ['id' => $id]), 'checkquestionshelp'],
        [!empty($event->tickettitle) || (bool) $imageurl, 'checkdesign', new moodle_url('/local/evenements/edit.php', ['id' => $id]), 'checkdesignhelp'],
    ];
    $items = '';
    foreach ($steps as [$done, $label, $url, $help]) {
        $mark = html_writer::span($done ? '✓' : '', 'evt-checkmark' . ($done ? ' is-done' : ''));
        $text = html_writer::div(html_writer::span(get_string($label, 'local_evenements'), 'evt-checklabel') .
            ($help ? html_writer::div(get_string($help, 'local_evenements'), 'evt-note') : ''));
        $action = ($url && !$done) ? html_writer::link($url, get_string('complete', 'local_evenements'), ['class' => 'btn btn-sm btn-outline-primary']) : '';
        $items .= html_writer::div($mark . $text . html_writer::div($action, 'ms-auto'), 'evt-checkitem');
    }
    $publish = html_writer::link(new moodle_url($PAGE->url, ['action' => 'publish', 'sesskey' => sesskey()]),
        get_string('publishevent', 'local_evenements'), ['class' => 'btn btn-primary']);
    echo html_writer::div(html_writer::tag('h2', get_string('readytopublish', 'local_evenements')) .
        html_writer::tag('p', get_string('readytopublishhelp', 'local_evenements'), ['class' => 'evt-note']) .
        $items . html_writer::div($publish, 'mt-3'), 'evt-panel mb-3');
}

// Status actions and public link.
$publicurl = new moodle_url('/local/evenements/event.php', ['id' => $id]);
$actions = '';
if ($event->status === booking_manager::STATUS_OPEN) {
    $actions .= html_writer::link(new moodle_url($PAGE->url, ['action' => 'close', 'sesskey' => sesskey()]),
        get_string('closebookings', 'local_evenements'), ['class' => 'btn btn-outline-secondary btn-sm']);
} else if ($event->status === booking_manager::STATUS_CLOSED) {
    $actions .= html_writer::link(new moodle_url($PAGE->url, ['action' => 'publish', 'sesskey' => sesskey()]),
        get_string('reopenbookings', 'local_evenements'), ['class' => 'btn btn-primary btn-sm']);
}
if ($event->status !== booking_manager::STATUS_DRAFT) {
    $actions .= html_writer::link(new moodle_url($PAGE->url, ['action' => 'draft', 'sesskey' => sesskey()]),
        get_string('backtodraft', 'local_evenements'), ['class' => 'btn btn-outline-secondary btn-sm']);
}
echo html_writer::div(
    html_writer::div(html_writer::tag('label', get_string('sharelink', 'local_evenements'), ['for' => 'evt-sharelink',
        'class' => 'evt-note d-block']) .
        html_writer::empty_tag('input', ['type' => 'text', 'id' => 'evt-sharelink', 'readonly' => 'readonly',
            'class' => 'form-control form-control-sm', 'value' => $publicurl->out(false),
            'onclick' => 'this.select()']), 'evt-share') .
    html_writer::div($actions, 'd-flex gap-2 flex-wrap'),
    'evt-panel evt-sharebar mb-3');

// Key figures.
$tickethint = $stats->fillrate !== null ? get_string('filledpercent', 'local_evenements', $stats->fillrate) : '';
if ($stats->cancelled) {
    $tickethint .= ($tickethint !== '' ? ' · ' : '') . get_string('cancelledcount', 'local_evenements', $stats->cancelled);
}
$figures = [
    [$stats->tickets . ($stats->capacity ? ' / ' . $stats->capacity : ''), get_string('statticketsall', 'local_evenements'),
        $tickethint],
    [$stats->bookings, get_string('statbookings', 'local_evenements'), ''],
    [$stats->used, get_string('statcheckedin', 'local_evenements'),
        $stats->tickets ? get_string('attendancepercent', 'local_evenements', $stats->attendance) : ''],
    [$stats->waiting, get_string('statwaiting', 'local_evenements'), ''],
];
echo html_writer::start_div('evt-stats evt-stats-4');
foreach ($figures as $i => [$value, $label, $hint]) {
    $bar = ($i === 0 && $stats->fillrate !== null) ? html_writer::div(html_writer::div('', 'evt-bar-fill',
        ['style' => 'width:' . min(100, $stats->fillrate) . '%']), 'evt-bar mt-2') : '';
    echo html_writer::div(html_writer::div($value, 'evt-stat-value') . html_writer::div($label, 'evt-stat-label') .
        ($hint !== '' ? html_writer::div($hint, 'evt-stat-hint') : '') . $bar, 'evt-stat');
}
echo html_writer::end_div();

echo html_writer::start_div('evt-dash');

// Booking curve.
$chart = new \core\chart_line();
$chart->set_smooth(true);
$series = new \core\chart_series(get_string('statticketsperday', 'local_evenements'), array_values($stats->perday));
$chart->add_series($series);
$chart->set_labels(array_keys($stats->perday));
$chart->set_legend_options(['display' => false]);
echo html_writer::div(html_writer::tag('h2', get_string('statticketsperday', 'local_evenements')) .
    ($stats->tickets ? $OUTPUT->render($chart) :
        html_writer::div(get_string('nochartyet', 'local_evenements'), 'evt-empty')), 'evt-panel');

// By category.
$cats = '';
foreach ($stats->categories as $cat) {
    $percent = $cat->quota > 0 ? min(100, (int) round(100 * $cat->count / $cat->quota)) : 0;
    $cats .= html_writer::div(
        html_writer::div(html_writer::span(s($cat->name), 'evt-catname') .
            html_writer::span($cat->count . ($cat->quota ? ' / ' . $cat->quota : ''), 'evt-catcount'), 'evt-catline') .
        ($cat->quota ? html_writer::div(html_writer::div('', 'evt-bar-fill', ['style' => 'width:' . $percent . '%']), 'evt-bar') : ''),
        'evt-cat');
}
echo html_writer::div(html_writer::tag('h2', get_string('statbycategory', 'local_evenements')) .
    ($cats ?: html_writer::tag('p', get_string('nocategories', 'local_evenements'), ['class' => 'evt-note'])), 'evt-panel');

echo html_writer::end_div();

// Entrance: who validated what, live.
if ($stats->used > 0 || $DB->record_exists('local_evenements_operator', ['eventid' => $id])) {
    $door = booking_manager::get_entrance_stats($id);
    $left = '';
    foreach ($door->peroperator as $who => $count) {
        $left .= html_writer::tag('li', html_writer::span(s($who)) . html_writer::span($count, 'evt-badge evt-badge-open ms-2'));
    }
    $right = '';
    foreach ($door->latest as $entry) {
        $right .= html_writer::tag('li', html_writer::span(s($entry->name)) . ' ' .
            html_writer::span(userdate($entry->time, get_string('strftimetime', 'langconfig')) .
            ($entry->by !== '' ? ' · ' . s($entry->by) : ''), 'evt-note'));
    }
    echo html_writer::start_div('evt-panel mt-3');
    echo html_writer::tag('h2', get_string('entrancelive', 'local_evenements'));
    echo html_writer::div(
        html_writer::div(html_writer::tag('h3', get_string('entrancebyoperator', 'local_evenements'), ['class' => 'h6']) .
            ($left ? html_writer::tag('ul', $left, ['class' => 'evt-meta']) : html_writer::tag('p', '-', ['class' => 'evt-note'])), '') .
        html_writer::div(html_writer::tag('h3', get_string('entrancelatest', 'local_evenements'), ['class' => 'h6']) .
            ($right ? html_writer::tag('ul', $right, ['class' => 'evt-meta']) : html_writer::tag('p', '-', ['class' => 'evt-note'])), ''),
        'evt-dash');
    echo html_writer::link(new moodle_url('/local/evenements/operators.php', ['id' => $id]),
        get_string('taboperators', 'local_evenements'), ['class' => 'btn btn-outline-secondary btn-sm mt-2']);
    echo html_writer::end_div();
}

// Latest bookings.
$rows = '';
foreach ($stats->latest as $booking) {
    $rows .= html_writer::tag('tr',
        html_writer::tag('td', html_writer::span(s($booking->contactname), 'evt-name')) .
        html_writer::tag('td', s($booking->email)) .
        html_writer::tag('td', (int) $booking->tickets) .
        html_writer::tag('td', userdate($booking->timecreated, get_string('strftimedatetimeshort', 'langconfig'))));
}
echo html_writer::start_div('evt-panel mt-3');
echo html_writer::tag('h2', get_string('statlatest', 'local_evenements'));
if ($rows) {
    echo html_writer::tag('table', html_writer::tag('thead', html_writer::tag('tr',
        html_writer::tag('th', get_string('contactname', 'local_evenements')) .
        html_writer::tag('th', get_string('contactemail', 'local_evenements')) .
        html_writer::tag('th', get_string('statticketsall', 'local_evenements')) .
        html_writer::tag('th', get_string('date')))) . html_writer::tag('tbody', $rows), ['class' => 'evt-table']);
} else {
    echo html_writer::tag('p', get_string('nobookingsyet', 'local_evenements'), ['class' => 'evt-note']);
}
echo html_writer::end_div();

echo $OUTPUT->footer();
