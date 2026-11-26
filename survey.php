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
 * Satisfaction survey after the event, reached with the personal link of a booking.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\survey_form;

$token = required_param('b', PARAM_ALPHANUM);
$booking = booking_manager::get_booking_by_token($token);
if (!$booking) {
    throw new moodle_exception('invalidlink', 'local_evenements');
}
$event = booking_manager::get_event((int) $booking->eventid);
$questions = booking_manager::get_questions((int) $event->id, 'after');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/survey.php', ['b' => $token]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('surveytitle', 'local_evenements'));
$PAGE->set_heading(format_string($event->name));

$ticketurl = booking_manager::booking_url($booking);

$mform = null;
if ($questions && $booking->status === 'active' && time() >= $event->timestart) {
    $mform = new survey_form($PAGE->url, ['token' => $token, 'questions' => $questions]);
    // Saving must happen before any output, because it ends with a redirect.
    if ($data = $mform->get_data()) {
        $answers = [];
        foreach ($questions as $question) {
            $answers[$question->id] = booking_manager::clean_answer($question, $data->{'q_' . $question->id} ?? '');
        }
        booking_manager::save_survey($booking, $answers);
        redirect($ticketurl, get_string('surveythanks', 'local_evenements'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('surveytitle', 'local_evenements'));
echo html_writer::tag('h4', format_string($event->name));

if (!$questions) {
    echo $OUTPUT->notification(get_string('nosurvey', 'local_evenements'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->continue_button($ticketurl);
    echo $OUTPUT->footer();
    exit;
}
if ($booking->status !== 'active') {
    echo $OUTPUT->notification(get_string('bookingiscancelled', 'local_evenements'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}
if (time() < $event->timestart) {
    echo $OUTPUT->notification(get_string('surveynotyet', 'local_evenements'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->continue_button($ticketurl);
    echo $OUTPUT->footer();
    exit;
}

// Show the previous answers, if the person already answered.
$previous = booking_manager::get_survey_answers($booking);
$defaults = [];
foreach ($questions as $question) {
    if (isset($previous[$question->id])) {
        $value = $previous[$question->id];
        $defaults['q_' . $question->id] = $question->type === 'multichoice' ? preg_split('/\R/', $value) : $value;
    }
}
$mform->set_data($defaults);
$mform->display();

echo $OUTPUT->footer();
