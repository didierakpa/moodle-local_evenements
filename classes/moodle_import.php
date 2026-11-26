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

defined('MOODLE_INTERNAL') || die();

/**
 * Add people from Moodle (a cohort, or the users of a course with a given role) to an event:
 * to its invitation list (list events) or straight as participants with a ticket.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_import {

    /**
     * Cohorts the current user may read, with their number of members.
     *
     * @return array id => "Name (n)"
     */
    public static function cohort_options(): array {
        global $DB;
        if (!has_capability('moodle/cohort:view', \context_system::instance())) {
            return [];
        }
        $sql = "SELECT c.id, c.name, COUNT(cm.id) AS n
                  FROM {cohort} c
             LEFT JOIN {cohort_members} cm ON cm.cohortid = c.id
                       AND EXISTS (SELECT 1 FROM {user} u WHERE u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0)
                 WHERE c.visible = 1
              GROUP BY c.id, c.name
              ORDER BY c.name";
        $options = [];
        foreach ($DB->get_records_sql($sql) as $cohort) {
            $options[$cohort->id] = format_string($cohort->name) . ' (' . (int) $cohort->n . ')';
        }
        return $options;
    }

    /**
     * Roles that can be chosen for a course, student first.
     *
     * @return array id => name
     */
    public static function role_options(): array {
        $roles = role_fix_names(get_all_roles(), \context_system::instance(), ROLENAME_ALIAS, true);
        $options = [];
        foreach (get_archetype_roles('student') as $role) {
            if (isset($roles[$role->id])) {
                $options[$role->id] = $roles[$role->id];
            }
        }
        foreach (get_roles_for_contextlevels(CONTEXT_COURSE) as $roleid) {
            if (isset($roles[$roleid]) && !isset($options[$roleid])) {
                $options[$roleid] = $roles[$roleid];
            }
        }
        return $options;
    }

    /**
     * People of a source, active accounts only, sorted by name.
     *
     * @param string $source 'cohort' or 'course'
     * @param int $id Cohort or course id
     * @param int $roleid For a course: only the users with this role (0 = everybody enrolled)
     * @return \stdClass[] id, firstname, lastname, email
     * @throws \required_capability_exception when the user may not see these people
     */
    public static function people(string $source, int $id, int $roleid = 0): array {
        global $DB;
        $fields = 'u.id, u.firstname, u.lastname, u.email';
        if ($source === 'cohort') {
            require_capability('moodle/cohort:view', \context_system::instance());
            $users = $DB->get_records_sql("SELECT $fields
                                             FROM {cohort_members} cm
                                             JOIN {user} u ON u.id = cm.userid
                                            WHERE cm.cohortid = :c AND u.deleted = 0 AND u.suspended = 0
                                         ORDER BY u.lastname, u.firstname", ['c' => $id]);
        } else if ($source === 'course') {
            $context = \context_course::instance($id);
            require_capability('moodle/course:viewparticipants', $context);
            $users = get_enrolled_users($context, '', 0, $fields . ', u.suspended', 'u.lastname, u.firstname', 0, 0, true);
            if ($roleid) {
                $withrole = [];
                foreach (get_role_users($roleid, $context, false, 'ra.id, u.id AS userid', 'ra.id') as $ra) {
                    $withrole[(int) $ra->userid] = true;
                }
                $users = array_filter($users, function ($u) use ($withrole) {
                    return isset($withrole[(int) $u->id]);
                });
            }
            $users = array_filter($users, function ($u) {
                return empty($u->suspended);
            });
        } else {
            throw new \coding_exception('Unknown source ' . $source);
        }
        return array_values(array_filter($users, function ($u) {
            return trim($u->firstname . $u->lastname) !== '' && !isguestuser($u->id);
        }));
    }

    /**
     * Whether a person already has an active ticket in the event (same Moodle account, e-mail or name).
     *
     * @param int $eventid
     * @param \stdClass $user
     * @return bool
     */
    protected static function has_ticket(int $eventid, \stdClass $user): bool {
        global $DB;
        $sql = "SELECT 1
                  FROM {local_evenements_ticket} t
                  JOIN {local_evenements_booking} b ON b.id = t.bookingid
                 WHERE t.eventid = :e AND t.status <> :c
                       AND (b.userid = :u OR (" . $DB->sql_equal('t.email', ':m', false, false) . " AND t.email <> '')
                            OR (" . $DB->sql_equal('t.firstname', ':f', false, false) . " AND " .
                                    $DB->sql_equal('t.lastname', ':l', false, false) . "))";
        return $DB->record_exists_sql($sql, ['e' => $eventid, 'c' => booking_manager::TICKET_CANCELLED, 'u' => $user->id,
            'm' => (string) $user->email, 'f' => $user->firstname, 'l' => $user->lastname]);
    }

    /**
     * Add people to an event.
     *
     * @param \stdClass $event
     * @param \stdClass[] $people From self::people()
     * @param \stdClass $options tickettypeid, category (name, list events), detail, sessionid, sendmail, force
     * @return \stdClass added, skipped, failed, messages
     */
    public static function add(\stdClass $event, array $people, \stdClass $options): \stdClass {
        $report = (object) ['added' => 0, 'skipped' => 0, 'failed' => 0, 'messages' => []];
        $islist = booking_manager::is_list_event($event);
        foreach ($people as $user) {
            try {
                if ($islist) {
                    $invitee = booking_manager::add_invitee($event, $user->firstname, $user->lastname, (string) $user->email,
                        (string) ($options->category ?? ''), (string) ($options->detail ?? ''));
                    if ($invitee) {
                        $report->added++;
                    } else {
                        $report->skipped++;
                    }
                    continue;
                }
                if (self::has_ticket((int) $event->id, $user)) {
                    $report->skipped++;
                    continue;
                }
                $booking = participant_manager::add_participant($event, (object) [
                    'firstname' => $user->firstname, 'lastname' => $user->lastname, 'email' => (string) $user->email,
                    'tickettypeid' => (int) ($options->tickettypeid ?? 0), 'sessionid' => (int) ($options->sessionid ?? 0),
                    'userid' => (int) $user->id,
                ], !empty($options->force));
                $report->added++;
                if (!empty($options->sendmail) && trim((string) $user->email) !== '') {
                    participant_manager::resend($event, $booking);
                }
            } catch (\moodle_exception $e) {
                $report->failed++;
                $report->messages[] = trim($user->firstname . ' ' . $user->lastname) . ' : ' . $e->getMessage();
                // Event full: the next ones would fail the same way.
                if (empty($options->force) && $e->errorcode === 'changeproblem' &&
                        booking_manager::remaining($event) === 0) {
                    $report->failed += count($people) - $report->added - $report->skipped - $report->failed;
                    break;
                }
            }
        }
        if ($report->added) {
            participant_manager::log((int) $event->id, 0, 0, 'add',
                get_string('logmoodleimport', 'local_evenements', (object) ['n' => $report->added, 'source' => $options->sourcename ?? '']));
        }
        return $report;
    }
}
