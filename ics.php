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
 * Calendar file (.ics) of an event, reached from the personal page of a booking.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$token = required_param('b', PARAM_ALPHANUM);
$booking = booking_manager::get_booking_by_token($token);
if (!$booking || $booking->status !== 'active') {
    throw new moodle_exception('invalidlink', 'local_evenements');
}
$event = booking_manager::get_event((int) $booking->eventid);
$event = booking_manager::apply_session($event, $booking);

$PAGE->set_context(context_system::instance());
$escape = function (string $text): string {
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace([';', ','], ['\;', '\,'], $text);
    return str_replace(["\r\n", "\n", "\r"], '\n', $text);
};
$utc = function (int $time): string {
    return gmdate('Ymd\THis\Z', $time);
};
$end = $event->timeend ?: $event->timestart + 2 * HOURSECS;
$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//local_evenements//Moodle//FR',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:event' . $event->id . '-' . $booking->id . '@' . parse_url($CFG->wwwroot, PHP_URL_HOST),
    'DTSTAMP:' . $utc(time()),
    'DTSTART:' . $utc((int) $event->timestart),
    'DTEND:' . $utc((int) $end),
    'SUMMARY:' . $escape(format_string($event->name, true, ['escape' => false])),
    'LOCATION:' . $escape((string) ($event->location ?? '')),
    'URL:' . booking_manager::booking_url($booking)->out(false),
    'END:VEVENT',
    'END:VCALENDAR',
];

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="evenement-' . (int) $event->id . '.ics"');
echo implode("\r\n", $lines) . "\r\n";
