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

namespace local_evenements;

/**
 * Report of an event: attendance, arrivals, door team, survey, comparison with the previous edition.
 * The figures are anonymous; a copy is kept when the personal data is purged, so the report and the
 * comparison with the next edition stay available.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_manager {
    /** @var int Width of the arrival bars, in seconds. */
    public const ARRIVAL_STEP = 900;

    /** @var int Number of days before the event shown on the booking curve. */
    public const CURVE_DAYS = 60;

    /**
     * Figures of an event computed from its live data.
     *
     * @param int $eventid
     * @return \stdClass
     */
    public static function compute(int $eventid): \stdClass {
        global $DB;
        $event = booking_manager::get_event($eventid);
        $cancelled = booking_manager::TICKET_CANCELLED;
        $used = booking_manager::TICKET_USED;

        $tickets = $DB->get_records_sql("SELECT t.id, t.tickettypeid, t.status, t.timeused, t.usedby, t.operatorid,
                                                t.timecreated, b.sessionid
                                           FROM {local_evenements_ticket} t
                                           JOIN {local_evenements_booking} b ON b.id = t.bookingid
                                          WHERE t.eventid = :e", ['e' => $eventid]);
        $r = (object) [
            'v' => 1, 'generated' => time(), 'snapshot' => false,
            'eventname' => format_string($event->name, true, ['escape' => false]),
            'timestart' => (int) $event->timestart, 'capacity' => (int) $event->capacity,
            'bookings' => $DB->count_records_select('local_evenements_booking', 'eventid = ? AND status = ?', [$eventid, 'active']),
            'booked' => 0, 'attended' => 0, 'cancelled' => 0,
            'categories' => [], 'sessions' => [], 'arrivals' => [], 'operators' => [], 'curve' => [],
            'refused' => 0, 'alreadyused' => 0,
            'waitlist' => ['waiting' => 0, 'notified' => 0, 'booked' => 0],
            'survey' => ['sent' => 0, 'answered' => 0],
        ];

        $categories = booking_manager::get_categories($eventid);
        foreach ($categories as $category) {
            $r->categories[$category->id] = ['name' => format_string($category->name, true, ['escape' => false]),
                'booked' => 0, 'attended' => 0];
        }
        $sessions = booking_manager::get_sessions($eventid);
        foreach ($sessions as $session) {
            $r->sessions[$session->id] = ['name' => booking_manager::session_label($session), 'booked' => 0, 'attended' => 0];
        }

        $operators = $DB->get_records_menu('local_evenements_operator', ['eventid' => $eventid], '', 'id, name');
        $doors = [];
        $created = [];
        foreach ($tickets as $t) {
            if ($t->status === $cancelled) {
                $r->cancelled++;
                continue;
            }
            $r->booked++;
            $created[] = (int) $t->timecreated;
            $isused = $t->status === $used;
            if ($isused) {
                $r->attended++;
                if ($t->timeused) {
                    $slot = (int) (floor($t->timeused / self::ARRIVAL_STEP) * self::ARRIVAL_STEP);
                    $r->arrivals[$slot] = ($r->arrivals[$slot] ?? 0) + 1;
                }
                if ($t->operatorid) {
                    $key = 'o' . $t->operatorid;
                    $label = $operators[$t->operatorid] ?? get_string('reportoperatorgone', 'local_evenements');
                } else if ($t->usedby) {
                    $key = 'u' . $t->usedby;
                    $label = $key;
                } else {
                    $key = 'self';
                    $label = get_string('reportdoorother', 'local_evenements');
                }
                $doors[$key] = ['name' => $label, 'count' => ($doors[$key]['count'] ?? 0) + 1];
            }
            if (isset($r->categories[$t->tickettypeid])) {
                $r->categories[$t->tickettypeid]['booked']++;
                $r->categories[$t->tickettypeid]['attended'] += $isused ? 1 : 0;
            }
            if (isset($r->sessions[$t->sessionid])) {
                $r->sessions[$t->sessionid]['booked']++;
                $r->sessions[$t->sessionid]['attended'] += $isused ? 1 : 0;
            }
        }
        // Names of the Moodle users who scanned.
        $userids = [];
        foreach ($doors as $key => $door) {
            if ($key[0] === 'u') {
                $userids[] = (int) substr($key, 1);
            }
        }
        if ($userids) {
            $users = $DB->get_records_list('user', 'id', $userids);
            foreach ($doors as $key => $door) {
                if ($key[0] === 'u') {
                    $uid = (int) substr($key, 1);
                    $doors[$key]['name'] = isset($users[$uid]) ? fullname($users[$uid]) : get_string(
                        'reportoperatorgone',
                        'local_evenements'
                    );
                }
            }
        }
        uasort($doors, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        $r->operators = array_values($doors);
        ksort($r->arrivals);
        $r->categories = array_values($r->categories);
        $r->sessions = array_values($r->sessions);

        // Booking curve: tickets booked so far, N days before the event (still active today).
        $start = (int) $event->timestart;
        if ($start) {
            sort($created);
            for ($d = self::CURVE_DAYS; $d >= 0; $d--) {
                $limit = $start - $d * DAYSECS;
                $n = 0;
                foreach ($created as $time) {
                    if ($time > $limit) {
                        break;
                    }
                    $n++;
                }
                $r->curve[$d] = $n;
            }
        }

        foreach (
            $DB->get_records_sql("SELECT result, COUNT(id) AS n FROM {local_evenements_scanlog}
                                        WHERE eventid = ? GROUP BY result", [$eventid]) as $row
        ) {
            if ($row->result === 'refused') {
                $r->refused = (int) $row->n;
            } else if ($row->result === 'alreadyused') {
                $r->alreadyused = (int) $row->n;
            }
        }
        foreach (
            $DB->get_records_sql("SELECT status, COUNT(id) AS n FROM {local_evenements_waitlist}
                                        WHERE eventid = ? GROUP BY status", [$eventid]) as $row
        ) {
            if (isset($r->waitlist[$row->status])) {
                $r->waitlist[$row->status] = (int) $row->n;
            }
        }
        $r->survey['sent'] = $DB->count_records_select('local_evenements_booking', 'eventid = ? AND surveysent > 0', [$eventid]);
        $r->survey['answered'] = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT a.bookingid)
                                                                FROM {local_evenements_answer} a
                                                                JOIN {local_evenements_question} q ON q.id = a.questionid
                                                               WHERE a.eventid = :e AND q.moment = :m",
            ['e' => $eventid, 'm' => 'after']
        );
        return $r;
    }

    /**
     * Report of an event: from its live data, or from the copy kept after the purge.
     *
     * @param int $eventid
     * @return \stdClass|null Null when there is nothing to show
     */
    public static function get(int $eventid): ?\stdClass {
        global $DB;
        $live = $DB->record_exists('local_evenements_ticket', ['eventid' => $eventid]);
        if (!$live) {
            $saved = $DB->get_field('local_evenements_report', 'data', ['eventid' => $eventid]);
            if ($saved) {
                $data = json_decode($saved);
                if ($data) {
                    $data->snapshot = true;
                    // JSON objects back into arrays.
                    foreach (['categories', 'sessions', 'operators'] as $list) {
                        $data->$list = array_map(function ($row) {
                            return (array) $row;
                        }, (array) ($data->$list ?? []));
                    }
                    foreach (['arrivals', 'curve', 'waitlist', 'survey'] as $map) {
                        $data->$map = (array) ($data->$map ?? []);
                    }
                    return $data;
                }
            }
        }
        if (!$DB->record_exists('local_evenements_event', ['id' => $eventid])) {
            return null;
        }
        return self::compute($eventid);
    }

    /**
     * Keep the anonymous figures of an event (before its personal data is purged). Names of the
     * operators are dropped: they are only numbered.
     *
     * @param int $eventid
     */
    public static function snapshot(int $eventid): void {
        global $DB;
        if (!$DB->record_exists('local_evenements_ticket', ['eventid' => $eventid])) {
            return;
        }
        $data = self::compute($eventid);
        foreach ($data->operators as $n => $door) {
            $data->operators[$n]['name'] = get_string('reportoperatorn', 'local_evenements', $n + 1);
        }
        unset($data->snapshot);
        $json = json_encode($data);
        if ($existing = $DB->get_record('local_evenements_report', ['eventid' => $eventid])) {
            $DB->update_record('local_evenements_report', (object) ['id' => $existing->id, 'data' => $json, 'timecreated' => time(
            )]);
        } else {
            $DB->insert_record('local_evenements_report', (object) ['eventid' => $eventid, 'data' => $json, 'timecreated' => time(
            )]);
        }
    }

    /**
     * Previous edition of an event (the event it was duplicated from), if it still exists.
     *
     * @param \stdClass $event
     * @return \stdClass|null
     */
    public static function previous(\stdClass $event): ?\stdClass {
        global $DB;
        if (empty($event->sourceid)) {
            return null;
        }
        return $DB->get_record('local_evenements_event', ['id' => $event->sourceid]) ?: null;
    }

    /**
     * Percentage, rounded, or null when the base is 0.
     *
     * @param int|float $part
     * @param int|float $whole
     * @return int|null
     */
    public static function percent($part, $whole): ?int {
        return $whole > 0 ? (int) round(100 * $part / $whole) : null;
    }
}
