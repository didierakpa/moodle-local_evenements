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
 * JSON interface of the door app (PWA). The operator is identified by his access code, sent in the
 * X-Operator-Key header: no Moodle account and no cookie are involved.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$PAGE->set_context(context_system::instance());
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fail = function (string $code, int $status = 403) {
    http_response_code($status);
    echo json_encode(['error' => $code]);
    exit;
};

// Brake on wrong codes: 30 failures per address and ten minutes.
$cache = cache::make_from_params(cache_store::MODE_APPLICATION, 'local_evenements', 'oplogin');
$ipkey = md5(getremoteaddr());
$failures = (int) $cache->get($ipkey);
if ($failures >= 30) {
    $fail('toomanyattempts', 429);
}

$key = $_SERVER['HTTP_X_OPERATOR_KEY'] ?? '';
$operator = booking_manager::get_operator_by_code((string) $key);
if (!$operator) {
    $cache->set($ipkey, $failures + 1);
    $fail('badcode');
}
$event = booking_manager::get_event((int) $operator->eventid);
if (!booking_manager::operator_active($operator, $event)) {
    $fail('revoked');
}
global $DB;
$DB->set_field('local_evenements_operator', 'lastseen', time(), ['id' => $operator->id]);

$action = optional_param('action', '', PARAM_ALPHA);
$body = json_decode((string) file_get_contents('php://input'), true);
$body = is_array($body) ? $body : [];

// Description of a ticket for the door screens.
$describe = function (\stdClass $ticket) use ($event) {
    $categories = booking_manager::get_categories((int) $event->id);
    return [
        'name' => trim($ticket->firstname . ' ' . $ticket->lastname),
        'cat' => booking_manager::ticket_label($ticket, $categories),
        'slot' => booking_manager::ticket_slot_label($ticket),
    ];
};
$counters = function () use ($event) {
    global $DB;
    return ['total' => booking_manager::count_tickets((int) $event->id),
        'used' => $DB->count_records(
            'local_evenements_ticket',
            ['eventid' => $event->id, 'status' => booking_manager::TICKET_USED]
        )];
};

switch ($action) {
    case 'login':
        echo json_encode(['operator' => format_string($operator->name, true, ['escape' => false]),
            'event' => ['name' => format_string($event->name, true, ['escape' => false]),
                'when' => booking_manager::event_when($event), 'location' => (string) ($event->location ?? '')],
            'counters' => $counters()]);
        break;

    case 'list':
        $categories = booking_manager::get_categories((int) $event->id);
        $tickets = [];
        foreach ($DB->get_records('local_evenements_ticket', ['eventid' => $event->id]) as $t) {
            if ($t->status === booking_manager::TICKET_CANCELLED) {
                continue;
            }
            $tickets[$t->token] = [trim($t->firstname . ' ' . $t->lastname), booking_manager::ticket_label($t, $categories),
                booking_manager::ticket_slot_label($t), $t->status === booking_manager::TICKET_USED ? 1 : 0];
        }
        echo json_encode(['tickets' => $tickets, 'counters' => $counters(), 'time' => time()]);
        break;

    case 'checkin':
        $token = strtolower((string) ($body['t'] ?? ''));
        if (!preg_match('/[0-9a-f]{40}/', $token, $m)) {
            echo json_encode(['r' => 'notfound']);
            break;
        }
        $ticket = booking_manager::get_ticket_by_token($m[0]);
        if (!$ticket) {
            echo json_encode(['r' => 'notfound']);
            break;
        }
        if ((int) $ticket->eventid !== (int) $event->id) {
            echo json_encode(['r' => 'wrongevent']);
            break;
        }
        $result = booking_manager::check_in($ticket, 0, 0, (int) $operator->id);
        $fresh = booking_manager::get_ticket_by_token($m[0]);
        echo json_encode(['r' => $result] + $describe($fresh) + [
            'usedat' => (int) $fresh->timeused, 'by' => $result === 'accepted' ? '' : booking_manager::validated_by($fresh),
            'counters' => $counters()]);
        break;

    case 'undo':
        $ticket = booking_manager::get_ticket_by_token(strtolower((string) ($body['t'] ?? '')));
        $ok = $ticket && (int) $ticket->eventid === (int) $event->id && booking_manager::undo_check_in(
            $ticket,
            (int) $operator->id
        );
        echo json_encode(['ok' => (bool) $ok, 'counters' => $counters()]);
        break;

    case 'sync':
        $out = [];
        foreach (array_slice(is_array($body['scans'] ?? null) ? $body['scans'] : [], 0, 500) as $scan) {
            $token = strtolower((string) ($scan['t'] ?? ''));
            if (!preg_match('/^[0-9a-f]{40}$/', $token)) {
                continue;
            }
            $ticket = booking_manager::get_ticket_by_token($token);
            if (!$ticket || (int) $ticket->eventid !== (int) $event->id) {
                $out[] = ['t' => $token, 'r' => 'notfound'];
                continue;
            }
            $out[] = ['t' => $token, 'r' => booking_manager::check_in($ticket, 0, (int) ($scan['time'] ?? 0), (int) $operator->id)];
        }
        echo json_encode(['results' => $out, 'counters' => $counters()]);
        break;

    default:
        $fail('badaction', 400);
}
