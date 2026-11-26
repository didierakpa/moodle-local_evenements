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
 * Exports of all the events: registrations (CSV), attendance sheet, results.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

require_login();
$context = context_system::instance();
$ismanager = has_capability('local/evenements:manage', $context);
$mine = booking_manager::organiser_event_ids();
if (!$ismanager && !$mine) {
    require_capability('local/evenements:manage', $context);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/exports.php'));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('navexports', 'local_evenements'));
$PAGE->set_heading('');

global $DB;
$events = $DB->get_records('local_evenements_event', null, 'timestart DESC');
if (!$ismanager) {
    $events = array_filter($events, function ($e) use ($mine) {
        return in_array((int) $e->id, $mine, true);
    });
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::console(null, 'exports');
echo html_writer::div(html_writer::tag('h1', get_string('navexports', 'local_evenements')) .
    html_writer::tag('p', get_string('exportsintro', 'local_evenements')), 'evt-pagehead');

if (!$events) {
    echo html_writer::div(get_string('noevents', 'local_evenements'), 'evt-panel evt-empty');
}
$rows = '';
foreach ($events as $e) {
    $id = (int) $e->id;
    $tickets = booking_manager::count_tickets($id);
    $btn = function (string $path, string $label, array $extra = []) use ($id) {
        return html_writer::link(
            new moodle_url('/local/evenements/' . $path, ['id' => $id]),
            $label,
            ['class' => 'btn btn-sm btn-outline-secondary me-1 mb-1'] + $extra
        );
    };
    $rows .= html_writer::tag(
        'tr',
        html_writer::tag('td', html_writer::link(
            new moodle_url('/local/evenements/dashboard.php', ['id' => $id]),
            html_writer::span(format_string($e->name), 'evt-name')
        ) . html_writer::div(userdate(
            $e->timestart,
            get_string('strftimedatefullshort', 'langconfig')
        ), 'evt-note')) .
        html_writer::tag('td', $tickets) .
        html_writer::tag('td', $btn('export.php', get_string('exportcsv', 'local_evenements')) .
            $btn('print.php', get_string('printsheet', 'local_evenements'), ['target' => '_blank']) .
        $btn('results.php', get_string('results', 'local_evenements')))
    );
}
if ($rows) {
    echo html_writer::tag('table', html_writer::tag('thead', html_writer::tag(
        'tr',
        html_writer::tag('th', get_string('eventname', 'local_evenements')) .
        html_writer::tag('th', get_string('statticketsall', 'local_evenements')) . html_writer::tag('th', '')
    )) .
        html_writer::tag('tbody', $rows), ['class' => 'evt-table']);
}
echo $OUTPUT->footer();
