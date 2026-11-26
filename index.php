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
 * Public list of the events open for booking. No login required.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public page: visitors and guests use it without a Moodle account (access by personal link or token).
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('events', 'local_evenements'));
$PAGE->set_heading('');
$PAGE->add_body_class('evt-public');

use local_evenements\output\ui;

echo $OUTPUT->header();
echo html_writer::div(html_writer::tag('h1', get_string('events', 'local_evenements')) .
    html_writer::tag('p', get_string('eventsintro', 'local_evenements')), 'evt-pagehead');

$events = booking_manager::get_public_events();
if (!$events) {
    echo $OUTPUT->notification(get_string('noevents', 'local_evenements'), \core\output\notification::NOTIFY_INFO);
}

echo html_writer::start_div('evt-grid');
foreach ($events as $event) {
    $url = new moodle_url('/local/evenements/event.php', ['id' => $event->id]);
    $imageurl = booking_manager::get_image_url($event);
    $media = ($imageurl ? html_writer::empty_tag('img', ['src' => $imageurl, 'alt' => '', 'loading' => 'lazy']) : '') .
        ui::date_badge($event);

    $open = booking_manager::is_open($event) && booking_manager::remaining($event) !== 0;
    $button = html_writer::link(
        $url,
        get_string($open ? 'book' : 'seedetails', 'local_evenements'),
        ['class' => 'btn btn-sm ' . ($open ? 'btn-primary' : 'btn-outline-secondary')]
    );

    echo html_writer::div(
        html_writer::div($media, 'evt-card-media') .
        html_writer::div(
            html_writer::tag('h2', html_writer::link($url, format_string($event->name))) .
            ui::meta($event) .
            html_writer::div(ui::availability($event) . $button, 'evt-card-foot'),
            'evt-card-body'
        ),
        'evt-card'
    );
}
echo html_writer::end_div();

echo html_writer::div(html_writer::link(
    new moodle_url('/local/evenements/mytickets.php'),
    get_string('findmytickets', 'local_evenements')
), 'mt-4');

if (has_capability('local/evenements:manage', context_system::instance())) {
    echo html_writer::div(html_writer::link(
        new moodle_url('/local/evenements/manage.php'),
        get_string('manageevents', 'local_evenements'),
        ['class' => 'btn btn-secondary']
    ), 'mt-4');
}

echo $OUTPUT->footer();
