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
 * Receives the entries scanned offline and records them. Answers, for each ticket, the result.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

require_login();
require_sesskey();
$context = context_system::instance();
$PAGE->set_context($context);

$payload = json_decode(file_get_contents('php://input'), true);
$scans = is_array($payload['scans'] ?? null) ? $payload['scans'] : [];
$out = [];
foreach (array_slice($scans, 0, 500) as $scan) {
    $token = strtolower((string) ($scan['t'] ?? ''));
    if (!preg_match('/^[0-9a-f]{40}$/', $token)) {
        continue;
    }
    $ticket = booking_manager::get_ticket_by_token($token);
    if (!$ticket) {
        $out[] = ['t' => $token, 'r' => 'notfound'];
        continue;
    }
    if (!has_capability('local/evenements:scan', $context) && !booking_manager::can_manage((int) $ticket->eventid)) {
        $out[] = ['t' => $token, 'r' => 'forbidden'];
        continue;
    }
    $out[] = ['t' => $token, 'r' => booking_manager::check_in($ticket, (int) $USER->id, (int) ($scan['time'] ?? 0))];
}
echo json_encode(['results' => $out]);
