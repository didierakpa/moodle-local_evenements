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
 * Public page of one event, with the choice of the number of tickets. No login required.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);
$event = booking_manager::get_event($id);
$context = context_system::instance();

if ($event->status === booking_manager::STATUS_DRAFT && !booking_manager::can_manage((int) $event->id)) {
    throw new moodle_exception('eventnotfound', 'local_evenements');
}

use local_evenements\form\waitlist_form;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/event.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(format_string($event->name));
$PAGE->set_heading('');

$waitform = null;
$waiterror = '';
if (booking_manager::waitlist_available($event)) {
    $waitform = new waitlist_form($PAGE->url, ['event' => $event]);
    if ($wdata = $waitform->get_data()) {
        if (optional_param('website', '', PARAM_RAW) !== '') {
            redirect(new moodle_url('/local/evenements/index.php'));
        }
        $uid = (isloggedin() && !isguestuser()) ? (int) $USER->id : 0;
        try {
            $entry = booking_manager::join_waitlist($event, trim($wdata->contactname), trim($wdata->email),
                $uid, (int) $wdata->quantity);
            redirect(new moodle_url('/local/evenements/waitlist.php', ['w' => $entry->token]),
                get_string('waitlistjoined', 'local_evenements'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            $waiterror = $e->getMessage();
        }
    }
}

use local_evenements\output\ui;

echo $OUTPUT->header();

$imageurl = booking_manager::get_image_url($event);
echo html_writer::start_div('evt-hero');
if ($imageurl) {
    echo html_writer::empty_tag('img', ['src' => $imageurl, 'alt' => '']);
}
echo html_writer::div(html_writer::tag('h1', format_string($event->name)), 'evt-hero-text');
echo html_writer::end_div();

if ($event->status === booking_manager::STATUS_DRAFT) {
    echo $OUTPUT->notification(get_string('status_draft', 'local_evenements'), \core\output\notification::NOTIFY_WARNING);
}

$remaining = booking_manager::remaining($event);

echo html_writer::start_div('evt-layout');

// Main column: description.
echo html_writer::start_div('evt-panel evt-about');
echo html_writer::tag('h2', get_string('aboutevent', 'local_evenements'));
if (!empty($event->description)) {
    echo format_text($event->description, $event->descriptionformat, ['context' => $context]);
}
echo html_writer::end_div();

// Side column: practical information and booking.
echo html_writer::start_div('evt-panel evt-aside');
echo ui::meta($event);
echo ui::availability($event);
$sessions = booking_manager::get_sessions((int) $event->id);
if ($sessions) {
    $items = '';
    foreach ($sessions as $session) {
        $left = booking_manager::session_remaining($session);
        $items .= html_writer::tag('li', html_writer::span(s(booking_manager::session_label($session))) .
            ($left === null ? '' : html_writer::span($left > 0 ? get_string('remaining', 'local_evenements', $left) :
                get_string('sessionfullshort', 'local_evenements'), 'evt-note ms-2')));
    }
    echo html_writer::tag('h2', get_string('sessions', 'local_evenements'), ['class' => 'h5']) .
        html_writer::tag('ul', $items, ['class' => 'evt-meta']);
}

if (!booking_manager::is_open($event)) {
    echo html_writer::tag('p', get_string('bookingnotopen', 'local_evenements'), ['class' => 'evt-note']);
} else if (booking_manager::is_list_event($event)) {
    echo html_writer::tag('h2', get_string('accesstitle', 'local_evenements'));
    echo html_writer::tag('p', get_string('accessintro', 'local_evenements'), ['class' => 'evt-note']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => (new moodle_url('/local/evenements/access.php'))->out(false), 'class' => 'evt-accessform']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $event->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::div(html_writer::tag('label', get_string('firstname'), ['for' => 'evt-af']) .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'firstname', 'id' => 'evt-af', 'required' => 'required',
            'class' => 'form-control', 'autocomplete' => 'given-name']), 'mb-2');
    echo html_writer::div(html_writer::tag('label', get_string('lastname'), ['for' => 'evt-al']) .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'lastname', 'id' => 'evt-al', 'required' => 'required',
            'class' => 'form-control', 'autocomplete' => 'family-name']), 'mb-2');
    echo html_writer::div(html_writer::empty_tag('input', ['type' => 'text', 'name' => 'website', 'tabindex' => '-1',
        'autocomplete' => 'off']), 'evt-hp', ['aria-hidden' => 'true']);
    echo html_writer::tag('button', get_string('accessbutton', 'local_evenements'), ['type' => 'submit', 'class' => 'btn btn-primary w-100']);
    echo html_writer::end_tag('form');
} else if ($remaining === 0) {
    if ($waitform) {
        echo html_writer::tag('h2', get_string('waitlist', 'local_evenements'));
        echo html_writer::tag('p', get_string('waitlistintro', 'local_evenements'), ['class' => 'evt-note']);
        if ($waiterror !== '') {
            echo $OUTPUT->notification($waiterror, \core\output\notification::NOTIFY_ERROR);
        }
        $waitform->display();
    }
} else {
    $max = (int) $event->maxperbooking;
    if ($remaining !== null) {
        $max = min($max, $remaining);
    }
    if (!empty($event->maxperperson)) {
        $uid = (isloggedin() && !isguestuser()) ? (int) $USER->id : 0;
        if ($uid) {
            $max = min($max, (int) booking_manager::person_remaining($event, $USER->email, $uid));
        }
    }
    echo html_writer::tag('h2', get_string('chooseqty', 'local_evenements'));
    if ($max < 1) {
        echo $OUTPUT->notification(get_string('personlimitreached', 'local_evenements'),
            \core\output\notification::NOTIFY_WARNING);
    } else {
        echo html_writer::start_div('evt-qty');
        for ($i = 1; $i <= $max; $i++) {
            $url = new moodle_url('/local/evenements/book.php', ['id' => $event->id, 'n' => $i]);
            echo html_writer::link($url, $i . html_writer::tag('small', get_string('ticketword', 'local_evenements', $i)),
                ['aria-label' => get_string('ticketsn', 'local_evenements', $i)]);
        }
        echo html_writer::end_div();
    }
    $limits = [];
    if ((int) $event->maxperbooking > 0) {
        $limits[] = get_string('limitbooking', 'local_evenements', (int) $event->maxperbooking);
    }
    if (!empty($event->maxperperson)) {
        $limits[] = get_string('limitperson', 'local_evenements', (int) $event->maxperperson);
    }
    if ($limits) {
        echo html_writer::tag('p', implode(' · ', $limits), ['class' => 'evt-note']);
    }
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::div(html_writer::link(new moodle_url('/local/evenements/index.php'),
    get_string('backtoevents', 'local_evenements')), 'mt-4');

echo $OUTPUT->footer();
