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
 * Download the tickets of a booking as a PDF (all of them, or one).
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public page: visitors and guests use it without a Moodle account (access by personal link or token).
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\ticket_pdf;

$PAGE->set_context(context_system::instance());
$token = required_param('b', PARAM_ALPHANUM);
$tickettoken = optional_param('t', '', PARAM_ALPHANUM);

$booking = booking_manager::get_booking_by_token($token);
if (!$booking) {
    throw new moodle_exception('invalidlink', 'local_evenements');
}
$event = booking_manager::get_event((int) $booking->eventid);

$tickets = [];
foreach (booking_manager::get_tickets((int) $booking->id) as $ticket) {
    if ($ticket->status === booking_manager::TICKET_CANCELLED) {
        continue;
    }
    if ($tickettoken !== '' && $ticket->token !== $tickettoken) {
        continue;
    }
    $tickets[] = $ticket;
}
if (!$tickets) {
    throw new moodle_exception('noticketsfound', 'local_evenements');
}

$pdf = ticket_pdf::generate($event, $booking, $tickets, (bool) optional_param('sheet', 0, PARAM_BOOL));
$filename = clean_filename('billets-' . format_string($event->name, true, ['escape' => false]) . '.pdf');
send_file($pdf, $filename, 0, 0, true, true, 'application/pdf');
