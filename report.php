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
 * Report of an event: attendance, arrivals, door team, survey, comparison with the previous edition.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\report_manager;
use local_evenements\output\ui;

$id = required_param('id', PARAM_INT);

require_login();
booking_manager::require_event_access($id, 'local/evenements:viewregistrations');
$event = booking_manager::get_event($id);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/report.php', ['id' => $id]));
ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('report', 'local_evenements'));
$PAGE->set_heading('');

$r = report_manager::get($id);
$previous = report_manager::previous($event);
$p = $previous ? report_manager::get((int) $previous->id) : null;
if ($p && !$p->booked) {
    $p = null;
}

echo $OUTPUT->header();
echo ui::event_header($event, 'report');

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$intro = '';
if ($r && $r->snapshot) {
    $intro = get_string('reportsnapshot', 'local_evenements', userdate($r->generated, $dateformat));
} else if ($event->timestart > time()) {
    $intro = get_string('reportprovisional', 'local_evenements');
}
$printbtn = html_writer::tag('button', html_writer::tag('i', '', ['class' => 'fa-solid fa-print me-1', 'aria-hidden' => 'true']) .
    get_string('reportprint', 'local_evenements'), ['type' => 'button', 'class' => 'btn btn-secondary btn-sm', 'onclick' => 'window.print()']);
echo html_writer::div(html_writer::tag('p', $intro, ['class' => 'evt-note mb-0']) . $printbtn, 'evt-reporthead');

if (!$r || !$r->booked) {
    echo $OUTPUT->notification(get_string('reportempty', 'local_evenements'), 'info');
    echo $OUTPUT->footer();
    exit;
}

/**
 * Difference with the previous edition, as a small coloured text.
 *
 * @param int|float|null $now
 * @param int|float|null $before
 * @param string $unit '' or '%'
 * @param bool $lowerisbetter Green when the figure goes down (absent people)
 * @return string
 */
$delta = function ($now, $before, string $unit = '', bool $lowerisbetter = false) use ($p): string {
    if (!$p || $now === null || $before === null) {
        return '';
    }
    $diff = (int) $now - (int) $before;
    $sign = $diff > 0 ? '+' : ($diff < 0 ? '−' : '±');
    $text = get_string('reportvsprevious', 'local_evenements', $sign . abs($diff) . ($unit === '%' ? ' pt' : ''));
    $good = $lowerisbetter ? $diff < 0 : $diff > 0;
    return html_writer::div($text, 'evt-delta ' . ($diff === 0 ? '' : ($good ? 'is-up' : 'is-down')));
};

$rate = report_manager::percent($r->attended, $r->booked);
$prate = $p ? report_manager::percent($p->attended, $p->booked) : null;
$noshow = $r->booked - $r->attended;
$waitlisted = array_sum((array) $r->waitlist);
$survey = (array) $r->survey;
$surveyrate = report_manager::percent($survey['answered'] ?? 0, $survey['sent'] ?? 0);

$tiles = [
    [$r->booked . ($r->capacity ? ' / ' . $r->capacity : ''), get_string('reportbooked', 'local_evenements'),
        $r->capacity ? get_string('filledpercent', 'local_evenements', report_manager::percent($r->booked, $r->capacity)) : '',
        $delta($r->booked, $p->booked ?? null)],
    [$r->attended, get_string('reportattended', 'local_evenements'),
        $rate !== null ? get_string('attendancepercent', 'local_evenements', $rate) : '', $delta($rate, $prate, '%')],
    [$noshow, get_string('reportnoshow', 'local_evenements'), $r->cancelled ? get_string('cancelledcount', 'local_evenements', $r->cancelled) : '',
        $delta($noshow, $p ? $p->booked - $p->attended : null, '', true)],
    [$waitlisted, get_string('reportwaitlist', 'local_evenements'),
        $waitlisted ? get_string('reportwaitlistbooked', 'local_evenements', (int) ($r->waitlist['booked'] ?? 0)) : '', ''],
];
if (!empty($survey['sent'])) {
    $tiles[] = [$surveyrate . ' %', get_string('reportsurvey', 'local_evenements'),
        get_string('reportsurveyhint', 'local_evenements', (object) $survey), ''];
}
echo html_writer::start_div('evt-stats evt-stats-' . count($tiles));
foreach ($tiles as [$value, $label, $hint, $cmp]) {
    echo html_writer::div(html_writer::div($value, 'evt-stat-value') . html_writer::div($label, 'evt-stat-label') .
        ($hint !== '' ? html_writer::div($hint, 'evt-stat-hint') : '') . $cmp, 'evt-stat');
}
echo html_writer::end_div();
if ($p) {
    echo html_writer::tag('p', get_string('reportprevious', 'local_evenements', (object) [
        'name' => format_string($previous->name),
        'date' => userdate($previous->timestart, get_string('strftimedate', 'langconfig')),
        'url' => (new moodle_url('/local/evenements/report.php', ['id' => $previous->id]))->out(),
    ]), ['class' => 'evt-note']);
}
if (!$r->snapshot && $noshow > 0 && $event->timestart < time()) {
    echo html_writer::div(html_writer::link(new moodle_url('/local/evenements/registrations.php', ['id' => $id, 'st' => 'valid']),
        get_string('reportseenoshow', 'local_evenements')), 'mb-3');
}

/**
 * Table "booked / present / rate" for categories or time slots.
 *
 * @param array $rows
 * @param string $title
 * @return string
 */
$attendancetable = function (array $rows, string $title): string {
    $table = new html_table();
    $table->head = [$title, get_string('reportbooked', 'local_evenements'), get_string('reportattended', 'local_evenements'),
        get_string('reportrate', 'local_evenements')];
    $table->attributes['class'] = 'table table-sm evt-reporttable';
    foreach ($rows as $row) {
        $row = (array) $row;
        $pct = report_manager::percent($row['attended'], $row['booked']);
        $bar = $pct !== null ? html_writer::div(html_writer::div('', 'evt-bar-fill', ['style' => 'width:' . $pct . '%']), 'evt-bar') : '';
        $table->data[] = [s($row['name']), (int) $row['booked'], (int) $row['attended'],
            html_writer::div(($pct !== null ? $pct . ' %' : '-') . $bar, 'evt-ratecell')];
    }
    return html_writer::table($table);
};

echo html_writer::start_div('evt-dash evt-reportgrid');
$cats = array_filter($r->categories, function ($c) {
    return ((array) $c)['booked'] > 0;
});
if ($cats) {
    echo html_writer::div(html_writer::tag('h2', get_string('statbycategory', 'local_evenements')) .
        $attendancetable($cats, get_string('category', 'local_evenements')), 'evt-panel');
}
if ($r->sessions) {
    echo html_writer::div(html_writer::tag('h2', get_string('reportbysession', 'local_evenements')) .
        $attendancetable($r->sessions, get_string('session', 'local_evenements')), 'evt-panel');
}

echo html_writer::end_div();

// Arrivals, by quarter of an hour.
if ($r->arrivals) {
    $arrivals = [];
    $slots = array_map('intval', array_keys($r->arrivals));
    for ($t = min($slots); $t <= max($slots); $t += report_manager::ARRIVAL_STEP) {
        $arrivals[userdate($t, get_string('strftimetime', 'langconfig'))] = (int) ($r->arrivals[$t] ?? $r->arrivals[(string) $t] ?? 0);
    }
    $peak = array_search(max($arrivals), $arrivals);
    $chart = new \core\chart_bar();
    $chart->add_series(new \core\chart_series(get_string('reportarrivals', 'local_evenements'), array_values($arrivals)));
    $chart->set_labels(array_keys($arrivals));
    $chart->set_legend_options(['display' => false]);
    echo html_writer::div(html_writer::tag('h2', get_string('reportarrivals', 'local_evenements')) .
        html_writer::tag('p', get_string('reportpeak', 'local_evenements', (object) ['time' => $peak, 'count' => $arrivals[$peak]]),
            ['class' => 'evt-note']) . $OUTPUT->render($chart), 'evt-panel mt-3');
}

// Booking curve, compared with the previous edition.
if ($r->curve) {
    $labels = [];
    $now = [];
    $before = [];
    for ($d = report_manager::CURVE_DAYS; $d >= 0; $d--) {
        $labels[] = $d ? get_string('reportdaysbefore', 'local_evenements', $d) : get_string('reportdayof', 'local_evenements');
        $now[] = (int) ($r->curve[$d] ?? $r->curve[(string) $d] ?? 0);
        if ($p && $p->curve) {
            $before[] = (int) ($p->curve[$d] ?? $p->curve[(string) $d] ?? 0);
        }
    }
    $chart = new \core\chart_line();
    $chart->add_series(new \core\chart_series(get_string('reportthisedition', 'local_evenements'), $now));
    if ($before) {
        $chart->add_series(new \core\chart_series(get_string('reportpreviousedition', 'local_evenements'), $before));
    }
    $chart->set_labels($labels);
    $week = (int) ($r->curve[7] ?? $r->curve['7'] ?? 0);
    echo html_writer::div(html_writer::tag('h2', get_string('reportcurve', 'local_evenements')) .
        html_writer::tag('p', get_string('reportcurvehelp', 'local_evenements', $r->booked ? report_manager::percent($week, $r->booked) : 0),
            ['class' => 'evt-note']) . $OUTPUT->render($chart), 'evt-panel mt-3');
}

// Door team and incidents.
if ($r->operators || $r->refused || $r->alreadyused) {
    $list = '';
    foreach ($r->operators as $door) {
        $door = (array) $door;
        $list .= html_writer::tag('li', html_writer::span(s($door['name'])) . html_writer::span((int) $door['count'], 'evt-badge evt-badge-open ms-2'));
    }
    $incidents = html_writer::tag('ul',
        html_writer::tag('li', get_string('reportrefused', 'local_evenements', (int) $r->refused)) .
        html_writer::tag('li', get_string('reportalreadyused', 'local_evenements', (int) $r->alreadyused)), ['class' => 'evt-meta']);
    echo html_writer::div(html_writer::tag('h2', get_string('reportdoor', 'local_evenements')) .
        html_writer::div(
            html_writer::div(html_writer::tag('h3', get_string('entrancebyoperator', 'local_evenements'), ['class' => 'h6']) .
                ($list ? html_writer::tag('ul', $list, ['class' => 'evt-meta']) : '-')) .
            html_writer::div(html_writer::tag('h3', get_string('reportincidents', 'local_evenements'), ['class' => 'h6']) . $incidents),
            'evt-reportcols'), 'evt-panel mt-3');
}

echo $OUTPUT->footer();
