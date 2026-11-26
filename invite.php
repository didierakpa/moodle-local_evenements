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
 * Personal link of a person of the invitation list: confirm identity, then book (once) with the guests.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\invite_form;

$token = required_param('t', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);

global $DB;
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/invite.php', ['t' => $token]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_heading('');

// Wrong links are counted per address (anti-guessing).
$cache = cache::make_from_params(cache_store::MODE_APPLICATION, 'local_evenements', 'invaccess');
$failkey = 'bad' . substr(hash('sha256', getremoteaddr() . get_site_identifier()), 0, 24);
if ((int) $cache->get($failkey) >= 20) {
    throw new moodle_exception('ratelimited', 'local_evenements');
}
$invitee = strlen($token) === 40 ? $DB->get_record('local_evenements_invitee', ['token' => $token]) : false;
if (!$invitee) {
    $cache->set($failkey, (int) $cache->get($failkey) + 1);
    throw new moodle_exception('invalidlink', 'local_evenements');
}
$event = booking_manager::get_event((int) $invitee->eventid);
if ($event->status === booking_manager::STATUS_DRAFT && !booking_manager::can_manage((int) $event->id)) {
    throw new moodle_exception('eventnotfound', 'local_evenements');
}
$PAGE->set_title(format_string($event->name));

// Already booked: the personal link now only opens the tickets.
if ($invitee->bookingid) {
    $booking = $DB->get_record('local_evenements_booking', ['id' => $invitee->bookingid]);
    if ($booking) {
        redirect(booking_manager::booking_url($booking));
    }
}

$eventurl = new moodle_url('/local/evenements/event.php', ['id' => $event->id]);
$fullname = trim($invitee->firstname . ' ' . $invitee->lastname);

$header = function () use ($OUTPUT, $event, $invitee, $fullname) {
    echo $OUTPUT->header();
    echo html_writer::div(
        \local_evenements\output\ui::date_badge($event) .
        html_writer::div(html_writer::div(format_string($event->name), 'evt-strip-title') . \local_evenements\output\ui::meta($event)),
        'evt-strip');
};

if (!booking_manager::is_open($event)) {
    $header();
    echo $OUTPUT->notification(get_string('bookingclosed', 'local_evenements'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

// The person says that the identity shown is wrong.
if ($action === 'wrong' && confirm_sesskey()) {
    $DB->set_field('local_evenements_invitee', 'timereported', time(), ['id' => $invitee->id]);
    $header();
    echo $OUTPUT->notification(get_string('invwrongdone', 'local_evenements'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}
if ($action === 'confirm' && confirm_sesskey()) {
    $DB->set_field('local_evenements_invitee', 'timeconfirmed', time(), ['id' => $invitee->id]);
    redirect($PAGE->url);
}

$categories = booking_manager::get_categories((int) $event->id);

// Step 1: confirm the identity.
if (!$invitee->timeconfirmed) {
    $header();
    echo html_writer::div(
        html_writer::tag('p', get_string('invstep1', 'local_evenements'), ['class' => 'evt-note mb-1']) .
        html_writer::tag('h2', s($fullname), ['class' => 'h2 mb-1']) .
        html_writer::tag('p', s(trim(($categories[$invitee->tickettypeid]->name ?? '') .
            ($invitee->detail ? ' · ' . $invitee->detail : ''))), ['class' => 'lead']) .
        html_writer::tag('p', get_string('invconfirmask', 'local_evenements'), ['class' => 'mb-3']) .
        html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'd-flex flex-wrap gap-2']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::tag('button', get_string('invconfirmyes', 'local_evenements'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'confirm', 'class' => 'btn btn-primary']) .
        html_writer::tag('button', get_string('invconfirmno', 'local_evenements'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'wrong', 'class' => 'btn btn-outline-secondary']) .
        html_writer::end_tag('form'), 'evt-panel');
    echo $OUTPUT->footer();
    exit;
}

// Step 2: guests, slot and questions.
$questions = array_filter(booking_manager::get_questions((int) $event->id, 'before'), function ($q) {
    return $q->scope === 'booking';
});
$sessions = booking_manager::get_sessions((int) $event->id);
$mform = new invite_form($PAGE->url, ['invitee' => $invitee, 'questions' => $questions, 'sessions' => $sessions]);
$error = '';

if ($mform->is_cancelled()) {
    redirect($eventurl);
} else if ($data = $mform->get_data()) {
    $guesttype = booking_manager::ensure_category((int) $event->id, get_string('invcategoryguest', 'local_evenements'));
    $guests = [[
        'firstname' => $invitee->firstname, 'lastname' => $invitee->lastname, 'email' => $invitee->email,
        'tickettypeid' => (int) $invitee->tickettypeid, 'note' => (string) $invitee->detail, 'answers' => [],
    ]];
    for ($i = 1; $i <= (int) $invitee->quota; $i++) {
        $first = trim($data->{'gfirst_' . $i} ?? '');
        $last = trim($data->{'glast_' . $i} ?? '');
        if ($first !== '' && $last !== '') {
            $guests[] = ['firstname' => $first, 'lastname' => $last, 'email' => '', 'tickettypeid' => $guesttype,
                'note' => get_string('invguestof', 'local_evenements', $fullname), 'answers' => []];
        }
    }
    $answers = [];
    foreach ($questions as $question) {
        $answers[$question->id] = booking_manager::clean_answer($question, $data->{'q_' . $question->id} ?? '');
    }
    try {
        $booking = booking_manager::create_booking($event, $fullname, $invitee->email, 0, $guests, getremoteaddr(), $answers,
            (int) ($data->sessionid ?? 0), $invitee);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
        if ($e->errorcode === 'invitealreadybooked') {
            $fresh = $DB->get_record('local_evenements_invitee', ['id' => $invitee->id]);
            $b = $fresh && $fresh->bookingid ? $DB->get_record('local_evenements_booking', ['id' => $fresh->bookingid]) : false;
            if ($b) {
                redirect(booking_manager::booking_url($b));
            }
        }
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
}

$header();
echo html_writer::div(
    html_writer::tag('p', get_string('invstep2', 'local_evenements', s($fullname)), ['class' => 'mb-0']) .
    html_writer::tag('p', get_string('invstep2note', 'local_evenements'), ['class' => 'evt-note mb-0']), 'evt-panel mb-3');
if ($error !== '') {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
$mform->display();
echo $OUTPUT->footer();
