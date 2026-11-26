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
 * Create or edit an event, its categories and its ticket design.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\event_form;

require_login();
$context = context_system::instance();
$id = optional_param('id', 0, PARAM_INT);
if ($id) {
    booking_manager::require_event_access($id);
} else {
    require_capability('local/evenements:manage', $context);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/edit.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$title = get_string($id ? 'editevent' : 'createevent', 'local_evenements');
$PAGE->set_title($title);
$PAGE->set_heading('');

$manageurl = new moodle_url('/local/evenements/manage.php');
$mform = new event_form($PAGE->url);

if ($mform->is_cancelled()) {
    redirect($id ? new moodle_url('/local/evenements/dashboard.php', ['id' => $id]) : $manageurl);
}

if ($data = $mform->get_data()) {
    $record = new stdClass();
    $record->name = $data->name;
    $record->description = $data->description_editor['text'];
    $record->descriptionformat = $data->description_editor['format'];
    $record->location = $data->location;
    $record->timestart = $data->timestart;
    $record->timeend = $data->timeend;
    $record->openfrom = $data->openfrom;
    $record->openuntil = $data->openuntil;
    $record->capacity = max(0, (int) $data->capacity);
    $record->maxperbooking = (int) $data->maxperbooking;
    $record->maxperperson = max(0, (int) $data->maxperperson);
    $record->imagetheme = in_array($data->imagetheme ?? '', booking_manager::IMAGE_THEMES, true) ? $data->imagetheme : 'generic';
    $record->accessmode = ($data->accessmode ?? 'open') === 'list' ? 'list' : 'open';
    $record->guestquota = max(0, min(20, (int) ($data->guestquota ?? 3)));
    $record->status = $data->status;
    $record->ticketcolor = trim($data->ticketcolor);
    $record->ticketformat = in_array($data->ticketformat, ['concert', 'boarding', 'compact'], true) ? $data->ticketformat
        : 'compact';
    $record->ticketfont = in_array($data->ticketfont, ['sans', 'serif', 'mono'], true) ? $data->ticketfont : 'sans';
    $record->tickettitle = $data->tickettitle;
    $record->tickettext = $data->tickettext;
    $record->timemodified = time();
    $record->usermodified = $USER->id;

    if ($id) {
        $record->id = $id;
        $DB->update_record('local_evenements_event', $record);
    } else {
        $record->timecreated = time();
        $id = $DB->insert_record('local_evenements_event', $record);
    }

    booking_manager::process_waitlist(booking_manager::get_event($id));

    file_save_draft_area_files(
        $data->eventimage,
        $context->id,
        'local_evenements',
        'eventimage',
        $id,
        event_form::image_options()
    );
    file_save_draft_area_files(
        $data->ticketlogo,
        $context->id,
        'local_evenements',
        'ticketlogo',
        $id,
        event_form::image_options()
    );
    booking_manager::save_categories($id, preg_split('/\R/', (string) $data->categories));

    redirect(
        new moodle_url('/local/evenements/dashboard.php', ['id' => $id]),
        get_string('eventsaved', 'local_evenements'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Fill the form.
$draftitemid = file_get_submitted_draft_itemid('eventimage');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'local_evenements',
    'eventimage',
    $id ?: null,
    event_form::image_options()
);
$logodraftid = file_get_submitted_draft_itemid('ticketlogo');
file_prepare_draft_area(
    $logodraftid,
    $context->id,
    'local_evenements',
    'ticketlogo',
    $id ?: null,
    event_form::image_options()
);

if ($id) {
    $event = booking_manager::get_event($id);
    $lines = [];
    foreach (booking_manager::get_categories($id) as $category) {
        $lines[] = $category->name . ($category->quota || $category->info ? '|' . (int) $category->quota : '') . (
            $category->info ? '|' . $category->info : '');
    }
    $event->description_editor = ['text' => $event->description ?? '', 'format' => $event->descriptionformat];
    $event->categories = implode("\n", $lines);
    $event->eventimage = $draftitemid;
    $event->ticketlogo = $logodraftid;
    $mform->set_data($event);
} else {
    $mform->set_data((object) [
        'id' => 0,
        'status' => 'draft',
        'categories' => get_string('defaultcategories', 'local_evenements'),
        'timestart' => time() + WEEKSECS,
        'capacity' => 0,
        'maxperbooking' => 6,
        'maxperperson' => 0,
        'accessmode' => 'open',
        'imagetheme' => 'generic',
        'guestquota' => 3,
        'ticketcolor' => '#1d4e89',
        'eventimage' => $draftitemid,
        'ticketlogo' => $logodraftid,
        'ticketfont' => 'sans',
        'ticketformat' => 'compact',
    ]);
}

echo $OUTPUT->header();
if ($id && !empty($event)) {
    echo \local_evenements\output\ui::event_header($event, 'settings');
} else {
    echo \local_evenements\output\ui::console(null, 'events');
    echo $OUTPUT->heading($title);
}
$mform->display();
echo $OUTPUT->footer();
