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
 * List of the tickets of an event as JSON, downloaded by the check-in page for offline use.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);
require_login();
require_sesskey();
$context = context_system::instance();
$PAGE->set_context($context);
if (!has_capability('local/evenements:scan', $context) && !booking_manager::can_manage($id)) {
    require_capability('local/evenements:scan', $context);
}
$event = booking_manager::get_event($id);
$categories = booking_manager::get_categories($id);

global $DB;
$tickets = [];
foreach ($DB->get_records('local_evenements_ticket', ['eventid' => $id]) as $t) {
    if ($t->status === booking_manager::TICKET_CANCELLED) {
        continue;
    }
    $tickets[$t->token] = [trim($t->firstname . ' ' . $t->lastname), $categories[$t->tickettypeid]->name ?? '',
        $t->status === booking_manager::TICKET_USED ? 1 : 0];
}
echo json_encode(['id' => (int) $id, 'name' => format_string($event->name, true, ['escape' => false]),
    'time' => time(), 'tickets' => $tickets]);
