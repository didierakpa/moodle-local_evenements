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

namespace local_evenements\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for local_evenements.
 *
 * Bookings made by visitors without a Moodle account hold an e-mail address
 * but no user id: they are removed by the scheduled purge after the event.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_evenements_booking', [
            'userid' => 'privacy:metadata:booking:userid',
            'contactname' => 'privacy:metadata:booking:contactname',
            'email' => 'privacy:metadata:booking:email',
            'consenttime' => 'privacy:metadata:booking:consenttime',
            'iphash' => 'privacy:metadata:booking:iphash',
            'timecreated' => 'privacy:metadata:booking:timecreated',
        ], 'privacy:metadata:booking');

        $collection->add_database_table('local_evenements_ticket', [
            'firstname' => 'privacy:metadata:ticket:firstname',
            'lastname' => 'privacy:metadata:ticket:lastname',
            'email' => 'privacy:metadata:ticket:email',
            'timeused' => 'privacy:metadata:ticket:timeused',
        ], 'privacy:metadata:ticket');

        $collection->add_database_table('local_evenements_scanlog', [
            'userid' => 'privacy:metadata:scanlog:userid',
            'result' => 'privacy:metadata:scanlog:result',
            'timecreated' => 'privacy:metadata:scanlog:timecreated',
        ], 'privacy:metadata:scanlog');

        $collection->add_database_table('local_evenements_answer', [
            'value' => 'privacy:metadata:answer:value',
            'timecreated' => 'privacy:metadata:answer:timecreated',
        ], 'privacy:metadata:answer');

        $collection->add_database_table('local_evenements_waitlist', [
            'userid' => 'privacy:metadata:waitlist:userid',
            'contactname' => 'privacy:metadata:waitlist:contactname',
            'email' => 'privacy:metadata:waitlist:email',
            'quantity' => 'privacy:metadata:waitlist:quantity',
            'timecreated' => 'privacy:metadata:waitlist:timecreated',
        ], 'privacy:metadata:waitlist');
        $collection->add_database_table('local_evenements_organiser', [
            'userid' => 'privacy:metadata:organiser:userid',
            'timecreated' => 'privacy:metadata:organiser:timecreated',
        ], 'privacy:metadata:organiser');
        $collection->add_database_table('local_evenements_operator', [
            'name' => 'privacy:metadata:operator:name',
            'lastseen' => 'privacy:metadata:operator:lastseen',
        ], 'privacy:metadata:operator');
        $collection->add_database_table('local_evenements_staff', [
            'name' => 'privacy:metadata:staff:name',
            'role' => 'privacy:metadata:staff:role',
        ], 'privacy:metadata:staff');
        $collection->add_database_table('local_evenements_invitee', [
            'firstname' => 'privacy:metadata:invitee:name',
            'lastname' => 'privacy:metadata:invitee:name',
            'email' => 'privacy:metadata:invitee:email',
            'detail' => 'privacy:metadata:invitee:detail',
        ], 'privacy:metadata:invitee');
        $collection->add_database_table('local_evenements_changelog', [
            'userid' => 'privacy:metadata:changelog:userid',
            'action' => 'privacy:metadata:changelog:action',
            'details' => 'privacy:metadata:changelog:details',
            'timecreated' => 'privacy:metadata:changelog:timecreated',
        ], 'privacy:metadata:changelog');

        return $collection;
    }

    /**
     * Contexts that hold data about a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :level
                       AND (EXISTS (SELECT 1 FROM {local_evenements_waitlist} w WHERE w.userid = :userid3)
                            OR EXISTS (SELECT 1 FROM {local_evenements_organiser} o WHERE o.userid = :userid4)
                            OR EXISTS (SELECT 1 FROM {local_evenements_booking} b WHERE b.userid = :userid1)
                            OR EXISTS (SELECT 1 FROM {local_evenements_scanlog} s WHERE s.userid = :userid2)
                            OR EXISTS (SELECT 1 FROM {local_evenements_changelog} c WHERE c.userid = :userid5))";
        $contextlist->add_from_sql($sql, ['level' => CONTEXT_SYSTEM, 'userid1' => $userid, 'userid2' => $userid,
            'userid4' => $userid,
            'userid3' => $userid, 'userid5' => $userid]);
        return $contextlist;
    }

    /**
     * Users who have data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_evenements_booking} WHERE userid > 0", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_evenements_scanlog} WHERE userid > 0", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_evenements_waitlist} WHERE userid > 0", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_evenements_organiser}", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_evenements_changelog} WHERE userid > 0", []);
    }

    /**
     * Export the data of a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $systemid = \context_system::instance()->id;
        $found = false;
        foreach ($contextlist->get_contextids() as $contextid) {
            if ((int) $contextid === (int) $systemid) {
                $found = true;
            }
        }
        if (!$found) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();
        $base = get_string('pluginname', 'local_evenements');
        $dateformat = get_string('strftimedatetime', 'langconfig');

        $bookings = $DB->get_records('local_evenements_booking', ['userid' => $userid], 'id ASC');
        foreach ($bookings as $booking) {
            $event = $DB->get_record('local_evenements_event', ['id' => $booking->eventid]);
            $tickets = [];
            foreach ($DB->get_records('local_evenements_ticket', ['bookingid' => $booking->id], 'id ASC') as $ticket) {
                $tickets[] = (object) [
                    'firstname' => $ticket->firstname,
                    'lastname' => $ticket->lastname,
                    'email' => $ticket->email,
                    'status' => $ticket->status,
                    'timeused' => $ticket->timeused ? userdate($ticket->timeused, $dateformat) : '',
                ];
            }
            $answers = [];
            $answersql = "SELECT a.id, q.name AS question, a.value
                            FROM {local_evenements_answer} a
                            JOIN {local_evenements_question} q ON q.id = a.questionid
                           WHERE a.bookingid = :bookingid
                        ORDER BY a.id";
            foreach ($DB->get_records_sql($answersql, ['bookingid' => $booking->id]) as $answer) {
                $answers[] = (object) ['question' => $answer->question, 'answer' => $answer->value];
            }
            writer::with_context($context)->export_data([$base, 'booking_' . $booking->id], (object) [
                'answers' => $answers,
                'event' => $event ? format_string($event->name) : '',
                'contactname' => $booking->contactname,
                'email' => $booking->email,
                'status' => $booking->status,
                'consenttime' => userdate($booking->consenttime, $dateformat),
                'timecreated' => userdate($booking->timecreated, $dateformat),
                'tickets' => $tickets,
            ]);
        }

        $waiting = $DB->get_records('local_evenements_waitlist', ['userid' => $userid], 'id ASC');
        if ($waiting) {
            $rows = [];
            foreach ($waiting as $entry) {
                $event = $DB->get_record('local_evenements_event', ['id' => $entry->eventid]);
                $rows[] = (object) [
                    'event' => $event ? format_string($event->name) : '',
                    'contactname' => $entry->contactname,
                    'email' => $entry->email,
                    'quantity' => $entry->quantity,
                    'status' => $entry->status,
                    'timecreated' => userdate($entry->timecreated, $dateformat),
                ];
            }
            writer::with_context($context)->export_data([$base, 'waitlist'], (object) ['waitlist' => $rows]);
        }

        $scans = $DB->get_records('local_evenements_scanlog', ['userid' => $userid], 'id ASC');
        if ($scans) {
            $rows = [];
            foreach ($scans as $scan) {
                $rows[] = (object) [
                    'ticketid' => $scan->ticketid,
                    'result' => $scan->result,
                    'timecreated' => userdate($scan->timecreated, $dateformat),
                ];
            }
            writer::with_context($context)->export_data([$base, 'scans'], (object) ['scans' => $rows]);
        }

        $changes = $DB->get_records('local_evenements_changelog', ['userid' => $userid], 'id ASC');
        if ($changes) {
            $rows = [];
            foreach ($changes as $change) {
                $rows[] = (object) [
                    'eventid' => $change->eventid,
                    'action' => $change->action,
                    'details' => $change->details,
                    'timecreated' => userdate($change->timecreated, $dateformat),
                ];
            }
            writer::with_context($context)->export_data([$base, 'changes'], (object) ['changes' => $rows]);
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_evenements_answer');
        $DB->delete_records('local_evenements_waitlist');
        $DB->delete_records('local_evenements_organiser');
        $DB->delete_records('local_evenements_operator');
        $DB->delete_records('local_evenements_staff');
        $DB->delete_records('local_evenements_invitee');
        $DB->delete_records('local_evenements_scanlog');
        $DB->delete_records('local_evenements_changelog');
        $DB->delete_records('local_evenements_ticket');
        $DB->delete_records('local_evenements_booking');
    }

    /**
     * Delete the data of one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        $systemid = \context_system::instance()->id;
        foreach ($contextlist->get_contextids() as $contextid) {
            if ((int) $contextid === (int) $systemid) {
                self::delete_for_userids([$userid]);
            }
        }
    }

    /**
     * Delete the data of several users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        self::delete_for_userids($userlist->get_userids());
    }

    /**
     * Delete bookings, tickets and scan entries of the given users.
     *
     * @param int[] $userids
     */
    private static function delete_for_userids(array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'local_evenements_answer',
            "bookingid IN (SELECT id FROM {local_evenements_booking} WHERE userid $insql)",
            $params
        );
        $DB->delete_records_select(
            'local_evenements_ticket',
            "bookingid IN (SELECT id FROM {local_evenements_booking} WHERE userid $insql)",
            $params
        );
        $DB->delete_records_select('local_evenements_booking', "userid $insql", $params);
        $DB->delete_records_select('local_evenements_scanlog', "userid $insql", $params);
        $DB->delete_records_select('local_evenements_waitlist', "userid $insql", $params);
        $DB->delete_records_select('local_evenements_organiser', "userid $insql", $params);
        // The history of the changes stays, without the name of the organiser.
        $DB->set_field_select('local_evenements_changelog', 'userid', 0, "userid $insql", $params);
    }
}
