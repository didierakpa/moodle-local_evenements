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
 * Personal page of a waiting list entry (secret link): status, book when places are free, leave the list.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public page: visitors and guests use it without a Moodle account (access by personal link or token).
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$token = required_param('w', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);

$entry = booking_manager::get_waitlist_by_token($token);
if (!$entry) {
    throw new moodle_exception('invalidlink', 'local_evenements');
}
$event = booking_manager::get_event((int) $entry->eventid);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/waitlist.php', ['w' => $token]));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('waitlist', 'local_evenements'));
$PAGE->set_heading(format_string($event->name));

if ($action === 'leave' && in_array($entry->status, ['waiting', 'notified'])) {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        booking_manager::leave_waitlist($entry);
        redirect(
            $PAGE->url,
            get_string('waitlistleft', 'local_evenements'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('waitlistleaveconfirm', 'local_evenements'),
        new moodle_url($PAGE->url, ['action' => 'leave', 'confirm' => 1, 'sesskey' => sesskey()]),
        $PAGE->url
    );
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('waitlist', 'local_evenements'));
echo html_writer::tag('h4', format_string($event->name));
echo html_writer::tag('p', s(booking_manager::event_when($event)));
echo html_writer::tag('p', get_string(
    'waitlistsummary',
    'local_evenements',
    (object) ['count' => (int) $entry->quantity, 'status' => get_string('waitliststatus_' . $entry->status, 'local_evenements')]
));

if ($entry->status === 'notified' && booking_manager::is_open($event)) {
    $remaining = booking_manager::remaining($event);
    if ($remaining === null || $remaining >= (int) $entry->quantity) {
        echo $OUTPUT->notification(
            get_string('waitlistplacesfree', 'local_evenements'),
            \core\output\notification::NOTIFY_SUCCESS
        );
        echo html_writer::link(
            new moodle_url(
                '/local/evenements/book.php',
                ['id' => $event->id, 'n' => (int) $entry->quantity]
            ),
            get_string('waitlistbooknow', 'local_evenements'),
            ['class' => 'btn btn-primary me-2']
        );
    } else {
        echo $OUTPUT->notification(
            get_string('waitlistplacesgone', 'local_evenements'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}
if (in_array($entry->status, ['waiting', 'notified'])) {
    echo html_writer::link(
        new moodle_url($PAGE->url, ['action' => 'leave']),
        get_string('waitlistleave', 'local_evenements'),
        ['class' => 'btn btn-outline-danger']
    );
}

echo $OUTPUT->footer();
