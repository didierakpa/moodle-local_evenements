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
 * Booking page: one named ticket per guest. No login required.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\booking_form;

$id = required_param('id', PARAM_INT);
$n = optional_param('n', 1, PARAM_INT);
$event = booking_manager::get_event($id);
$context = context_system::instance();

$n = max(1, min($n, (int) $event->maxperbooking));
$presession = optional_param('s', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/book.php', ['id' => $id, 'n' => $n]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(format_string($event->name));
$PAGE->set_heading('');

$eventurl = new moodle_url('/local/evenements/event.php', ['id' => $id]);

if (booking_manager::is_list_event($event)) {
    redirect($eventurl, get_string('listonly', 'local_evenements'), null, \core\output\notification::NOTIFY_WARNING);
}

if (!booking_manager::is_open($event)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('bookingclosed', 'local_evenements'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->continue_button($eventurl);
    echo $OUTPUT->footer();
    exit;
}

$categories = booking_manager::get_categories((int) $event->id);
$questions = booking_manager::get_questions((int) $event->id, 'before');
$sessions = booking_manager::get_sessions((int) $event->id);
$mform = new booking_form($PAGE->url, ['event' => $event, 'n' => $n, 'categories' => $categories,
    'questions' => $questions, 'sessions' => $sessions]);

$error = '';

if ($mform->is_cancelled()) {
    redirect($eventurl);
} else if ($data = $mform->get_data()) {
    // The honeypot field must stay empty.
    if (optional_param('website', '', PARAM_RAW) !== '') {
        redirect(new moodle_url('/local/evenements/index.php'));
    }

    $guests = [];
    for ($i = 1; $i <= $n; $i++) {
        $guestanswers = [];
        foreach ($questions as $question) {
            if ($question->scope === 'guest') {
                $guestanswers[$question->id] = booking_manager::clean_answer($question,
                    $data->{'gq_' . $i . '_' . $question->id} ?? '');
            }
        }
        $guests[] = [
            'firstname' => trim($data->{'guestfirstname_' . $i}),
            'lastname' => trim($data->{'guestlastname_' . $i}),
            'email' => trim($data->{'guestemail_' . $i} ?? ''),
            'tickettypeid' => $categories ? (int) $data->{'guesttype_' . $i} : 0,
            'answers' => $guestanswers,
        ];
    }
    $bookinganswers = [];
    foreach ($questions as $question) {
        if ($question->scope === 'booking') {
            $bookinganswers[$question->id] = booking_manager::clean_answer($question,
                $data->{'q_' . $question->id} ?? '');
        }
    }
    $userid = (isloggedin() && !isguestuser()) ? (int) $USER->id : 0;

    try {
        $booking = booking_manager::create_booking($event, trim($data->contactname), trim($data->email),
            $userid, $guests, getremoteaddr(), $bookinganswers, (int) ($data->sessionid ?? 0));
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }

    if ($error === '') {
        $sent = false;
        try {
            $sent = booking_manager::send_confirmation($booking, $event);
        } catch (Throwable $e) {
            debugging('local_evenements: confirmation e-mail failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        redirect(new moodle_url(booking_manager::booking_url($booking), ['new' => $sent ? 1 : 2]),
            $sent ? '' : get_string('bookingdonenomail', 'local_evenements'), null,
            \core\output\notification::NOTIFY_WARNING);
    }
} else if (isloggedin() && !isguestuser()) {
    // Prefill with the Moodle account.
    $defaults = [
        'contactname' => fullname($USER),
        'email' => $USER->email,
        'email2' => $USER->email,
        'guestfirstname_1' => $USER->firstname,
        'guestlastname_1' => $USER->lastname,
        'guestemail_1' => $USER->email,
    ];
    $mform->set_data($defaults);
}
if (!$mform->is_submitted() && $presession && isset($sessions[$presession])) {
    $mform->set_data(['sessionid' => $presession]);
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::steps(2);
echo html_writer::div(
    \local_evenements\output\ui::date_badge($event) .
    html_writer::div(html_writer::div(format_string($event->name), 'evt-strip-title') .
        \local_evenements\output\ui::meta($event)) .
    html_writer::span(get_string('ticketsn', 'local_evenements', $n), 'badge bg-primary text-white ms-auto'),
    'evt-strip');

if ($error !== '') {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}

$mform->display();
echo $OUTPUT->footer();
