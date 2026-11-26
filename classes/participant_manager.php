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
 * Changes made by organisers to the participants of an event: edit, cancel, restore,
 * delete for good, add by hand, send the tickets again. Every change is written to the change log.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant_manager {

    /** @var string[] Actions of the change log. */
    public const ACTIONS = ['add', 'edit', 'cancel', 'restore', 'delete', 'resend'];

    /**
     * Write one line in the change log.
     *
     * @param int $eventid
     * @param int $bookingid
     * @param int $ticketid
     * @param string $action One of self::ACTIONS
     * @param string $details Readable summary
     */
    public static function log(int $eventid, int $bookingid, int $ticketid, string $action, string $details = ''): void {
        global $DB, $USER;
        $DB->insert_record('local_evenements_changelog', (object) [
            'eventid' => $eventid,
            'bookingid' => $bookingid,
            'ticketid' => $ticketid,
            'userid' => isloggedin() ? (int) $USER->id : 0,
            'action' => $action,
            'details' => $details,
            'timecreated' => time(),
        ]);
    }

    /**
     * Latest lines of the change log of an event, with the name of the organiser.
     *
     * @param int $eventid
     * @param int $limit 0 for all
     * @param int $ticketid Only the lines of one ticket (0 for all)
     * @return \stdClass[]
     */
    public static function get_log(int $eventid, int $limit = 100, int $ticketid = 0): array {
        global $DB;
        $names = \core_user\fields::for_name()->get_sql('u', true);
        $params = ['eventid' => $eventid] + $names->params;
        $where = 'c.eventid = :eventid';
        if ($ticketid) {
            $where .= ' AND c.ticketid = :ticketid';
            $params['ticketid'] = $ticketid;
        }
        $sql = "SELECT c.*{$names->selects}
                  FROM {local_evenements_changelog} c
             LEFT JOIN {user} u ON u.id = c.userid
                 WHERE $where
              ORDER BY c.timecreated DESC, c.id DESC";
        return $DB->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * A ticket of the event.
     *
     * @param int $eventid
     * @param int $ticketid
     * @return \stdClass
     */
    public static function get_ticket(int $eventid, int $ticketid): \stdClass {
        global $DB;
        return $DB->get_record('local_evenements_ticket', ['id' => $ticketid, 'eventid' => $eventid], '*', MUST_EXIST);
    }

    /**
     * "First LAST" of a ticket.
     *
     * @param \stdClass $ticket
     * @return string
     */
    public static function ticket_name(\stdClass $ticket): string {
        return trim($ticket->firstname . ' ' . $ticket->lastname);
    }

    /**
     * Lock of an event, so that two changes cannot take the last place at the same time.
     *
     * @param int $eventid
     * @return \core\lock\lock
     */
    protected static function lock(int $eventid): \core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory('local_evenements')->get_lock('event_' . $eventid, 15);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_evenements');
        }
        return $lock;
    }

    /**
     * Why one more ticket would not fit (capacity, category quota, time slot), or '' when it fits.
     *
     * @param \stdClass $event
     * @param int $typeid Ticket category, 0 to skip the category check
     * @param int $sessionid Time slot, 0 to skip the slot check
     * @param int $n Number of tickets
     * @return string Error message, '' when it fits
     */
    public static function place_problem(\stdClass $event, int $typeid, int $sessionid, int $n = 1): string {
        $remaining = booking_manager::remaining($event);
        if ($remaining !== null && $n > $remaining) {
            return $remaining === 0 ? get_string('participantfull', 'local_evenements') :
                get_string('notenough', 'local_evenements', $remaining);
        }
        if ($typeid) {
            $categories = booking_manager::get_categories((int) $event->id);
            if (isset($categories[$typeid]) && $categories[$typeid]->quota > 0 &&
                    booking_manager::count_category_tickets($typeid) + $n > $categories[$typeid]->quota) {
                return get_string('quotareached', 'local_evenements', format_string($categories[$typeid]->name));
            }
        }
        if ($sessionid) {
            $sessions = booking_manager::get_sessions((int) $event->id);
            if (isset($sessions[$sessionid])) {
                $left = booking_manager::session_remaining($sessions[$sessionid]);
                if ($left !== null && $n > $left) {
                    return get_string('sessionfull', 'local_evenements');
                }
            }
        }
        return '';
    }

    /**
     * Name of a category (for the change log).
     *
     * @param array $categories
     * @param int $typeid
     * @return string
     */
    protected static function category_name(array $categories, int $typeid): string {
        return isset($categories[$typeid]) ? format_string($categories[$typeid]->name) : '-';
    }

    /**
     * Name of a time slot (for the change log).
     *
     * @param array $sessions
     * @param int $sessionid
     * @return string
     */
    protected static function session_name(array $sessions, int $sessionid): string {
        return isset($sessions[$sessionid]) ? booking_manager::session_label($sessions[$sessionid]) : '-';
    }

    /**
     * Change a ticket (guest, category, note) and its booking (contact, time slot).
     *
     * @param \stdClass $event
     * @param \stdClass $ticket
     * @param \stdClass $data firstname, lastname, email, tickettypeid, note, contactname, contactemail, sessionid
     * @param bool $force Ignore the capacity, quotas and slot limits
     * @return string[] List of the changes (empty when nothing changed)
     * @throws \moodle_exception when the new category or slot is full
     */
    public static function update_ticket(\stdClass $event, \stdClass $ticket, \stdClass $data, bool $force = false): array {
        global $DB;
        $booking = $DB->get_record('local_evenements_booking', ['id' => $ticket->bookingid], '*', MUST_EXIST);
        $categories = booking_manager::get_categories((int) $event->id);
        $sessions = booking_manager::get_sessions((int) $event->id);
        $changes = [];
        $newticket = (object) ['id' => $ticket->id];
        $newbooking = (object) ['id' => $booking->id];

        foreach (['firstname' => 100, 'lastname' => 100, 'email' => 255, 'note' => 255] as $field => $max) {
            if (!property_exists($data, $field)) {
                continue;
            }
            $value = \core_text::substr(trim((string) $data->$field), 0, $max);
            if ($value !== (string) $ticket->$field) {
                $newticket->$field = $value;
                $changes[] = get_string('logfield_' . $field, 'local_evenements') . ' : ' .
                    ((string) $ticket->$field !== '' ? $ticket->$field : '-') . ' → ' . ($value !== '' ? $value : '-');
            }
        }
        $typeid = isset($data->tickettypeid) ? (int) $data->tickettypeid : (int) $ticket->tickettypeid;
        if ($typeid !== (int) $ticket->tickettypeid) {
            if ($categories && !isset($categories[$typeid])) {
                throw new \moodle_exception('invalidcategory', 'local_evenements');
            }
            $newticket->tickettypeid = $typeid;
            $changes[] = get_string('logfield_category', 'local_evenements') . ' : ' .
                self::category_name($categories, (int) $ticket->tickettypeid) . ' → ' . self::category_name($categories, $typeid);
        }
        foreach (['contactname' => 'contactname', 'contactemail' => 'email'] as $field => $column) {
            if (!property_exists($data, $field)) {
                continue;
            }
            $value = \core_text::substr(trim((string) $data->$field), 0, 255);
            if ($value !== '' && $value !== (string) $booking->$column) {
                $newbooking->$column = $value;
                $changes[] = get_string('logfield_' . $field, 'local_evenements') . ' : ' . $booking->$column . ' → ' . $value;
            }
        }
        $sessionid = isset($data->sessionid) ? (int) $data->sessionid : (int) $booking->sessionid;
        if ($sessions && $sessionid !== (int) $booking->sessionid) {
            if (!isset($sessions[$sessionid])) {
                throw new \moodle_exception('invalidsession', 'local_evenements');
            }
            $newbooking->sessionid = $sessionid;
            $changes[] = get_string('logfield_session', 'local_evenements') . ' : ' .
                self::session_name($sessions, (int) $booking->sessionid) . ' → ' . self::session_name($sessions, $sessionid);
        }
        if (!$changes) {
            return [];
        }

        $lock = self::lock((int) $event->id);
        try {
            $takesplace = $ticket->status !== booking_manager::TICKET_CANCELLED;
            if (!$force && $takesplace) {
                // The ticket already takes a place: only the quota of the new category matters.
                if (isset($newticket->tickettypeid) && isset($categories[$typeid]) && $categories[$typeid]->quota > 0 &&
                        booking_manager::count_category_tickets($typeid) + 1 > $categories[$typeid]->quota) {
                    throw new \moodle_exception('changeproblem', 'local_evenements', '',
                        get_string('quotareached', 'local_evenements', format_string($categories[$typeid]->name)));
                }
                if (isset($newbooking->sessionid)) {
                    $n = $DB->count_records_select('local_evenements_ticket', 'bookingid = ? AND status <> ?',
                        [$booking->id, booking_manager::TICKET_CANCELLED]);
                    $left = booking_manager::session_remaining($sessions[$sessionid]);
                    if ($left !== null && $n > $left) {
                        throw new \moodle_exception('changeproblem', 'local_evenements', '', get_string('sessionfull', 'local_evenements'));
                    }
                }
            }
            if (count((array) $newticket) > 1) {
                $DB->update_record('local_evenements_ticket', $newticket);
            }
            if (count((array) $newbooking) > 1) {
                $DB->update_record('local_evenements_booking', $newbooking);
            }
        } finally {
            $lock->release();
        }
        self::log((int) $event->id, (int) $booking->id, (int) $ticket->id, 'edit',
            self::ticket_name($ticket) . "\n" . implode("\n", $changes));
        return $changes;
    }

    /**
     * Cancel one ticket: it no longer takes a place and is refused at the entrance. The scan history stays.
     * When it was the last ticket of its booking, the booking is cancelled too (and, for a list event,
     * the person of the list can book again).
     *
     * @param \stdClass $event
     * @param \stdClass $ticket
     * @param bool $notifywaitlist Warn the waiting list (false during bulk actions, done once at the end)
     * @return bool False when the ticket was already cancelled
     */
    public static function cancel_ticket(\stdClass $event, \stdClass $ticket, bool $notifywaitlist = true): bool {
        global $DB;
        if ($ticket->status === booking_manager::TICKET_CANCELLED) {
            return false;
        }
        $DB->set_field('local_evenements_ticket', 'status', booking_manager::TICKET_CANCELLED, ['id' => $ticket->id]);
        $left = $DB->count_records_select('local_evenements_ticket', 'bookingid = ? AND status <> ?',
            [$ticket->bookingid, booking_manager::TICKET_CANCELLED]);
        if (!$left) {
            $DB->set_field('local_evenements_booking', 'status', 'cancelled', ['id' => $ticket->bookingid]);
            self::unlink_invitee((int) $ticket->bookingid);
        }
        self::log((int) $event->id, (int) $ticket->bookingid, (int) $ticket->id, 'cancel', self::ticket_name($ticket));
        if ($notifywaitlist) {
            booking_manager::process_waitlist($event);
        }
        return true;
    }

    /**
     * A person of the invitation list whose booking is gone can book again.
     *
     * @param int $bookingid
     */
    protected static function unlink_invitee(int $bookingid): void {
        global $DB;
        foreach ($DB->get_records('local_evenements_invitee', ['bookingid' => $bookingid]) as $invitee) {
            $DB->update_record('local_evenements_invitee', (object) ['id' => $invitee->id, 'bookingid' => 0, 'timeconfirmed' => 0]);
        }
    }

    /**
     * Give a cancelled ticket its place back (same QR code).
     *
     * @param \stdClass $event
     * @param \stdClass $ticket
     * @param bool $force Ignore the capacity, quotas and slot limits
     * @return bool False when the ticket was not cancelled
     * @throws \moodle_exception when there is no place left
     */
    public static function restore_ticket(\stdClass $event, \stdClass $ticket, bool $force = false): bool {
        global $DB;
        if ($ticket->status !== booking_manager::TICKET_CANCELLED) {
            return false;
        }
        $booking = $DB->get_record('local_evenements_booking', ['id' => $ticket->bookingid], '*', MUST_EXIST);
        $lock = self::lock((int) $event->id);
        try {
            if (!$force) {
                $problem = self::place_problem($event, (int) $ticket->tickettypeid, (int) $booking->sessionid, 1);
                if ($problem !== '') {
                    throw new \moodle_exception('changeproblem', 'local_evenements', '', $problem);
                }
            }
            $status = $ticket->timeused ? booking_manager::TICKET_USED : booking_manager::TICKET_VALID;
            $DB->set_field('local_evenements_ticket', 'status', $status, ['id' => $ticket->id]);
            if ($booking->status !== 'active') {
                $DB->set_field('local_evenements_booking', 'status', 'active', ['id' => $booking->id]);
            }
            // For a list event, the person of the list gets this booking back, unless they booked again meanwhile.
            if (booking_manager::is_list_event($event) &&
                    !$DB->record_exists('local_evenements_invitee', ['bookingid' => $booking->id])) {
                $select = 'eventid = :e AND bookingid = 0 AND ' . $DB->sql_equal('email', ':email', false, false);
                $invitees = $DB->get_records_select('local_evenements_invitee', $select,
                    ['e' => $event->id, 'email' => $booking->email], 'id ASC', '*', 0, 1);
                if ($invitee = reset($invitees)) {
                    $DB->update_record('local_evenements_invitee', (object) ['id' => $invitee->id, 'bookingid' => $booking->id,
                        'timeconfirmed' => time()]);
                }
            }
        } finally {
            $lock->release();
        }
        self::log((int) $event->id, (int) $booking->id, (int) $ticket->id, 'restore', self::ticket_name($ticket));
        return true;
    }

    /**
     * Delete a ticket for good (right to erasure): the ticket, its answers and its scan history.
     * The booking goes too when it has no ticket left. The change log keeps a line without the name.
     *
     * @param \stdClass $event
     * @param \stdClass $ticket
     * @param bool $notifywaitlist Warn the waiting list
     */
    public static function delete_ticket(\stdClass $event, \stdClass $ticket, bool $notifywaitlist = true): void {
        global $DB;
        $tookplace = $ticket->status !== booking_manager::TICKET_CANCELLED;
        $bookingid = (int) $ticket->bookingid;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_evenements_answer', ['ticketid' => $ticket->id]);
        $DB->delete_records('local_evenements_scanlog', ['ticketid' => $ticket->id]);
        // Earlier lines of the log named the person: they lose their details.
        $DB->set_field('local_evenements_changelog', 'details', '', ['ticketid' => $ticket->id]);
        $DB->delete_records('local_evenements_ticket', ['id' => $ticket->id]);
        if (!$DB->record_exists('local_evenements_ticket', ['bookingid' => $bookingid])) {
            $DB->delete_records('local_evenements_answer', ['bookingid' => $bookingid]);
            $DB->set_field('local_evenements_changelog', 'details', '', ['bookingid' => $bookingid]);
            self::unlink_invitee($bookingid);
            $DB->delete_records('local_evenements_booking', ['id' => $bookingid]);
        } else if (!$DB->record_exists_select('local_evenements_ticket', 'bookingid = ? AND status <> ?',
                [$bookingid, booking_manager::TICKET_CANCELLED])) {
            $DB->set_field('local_evenements_booking', 'status', 'cancelled', ['id' => $bookingid]);
            self::unlink_invitee($bookingid);
        }
        self::log((int) $event->id, $bookingid, (int) $ticket->id, 'delete',
            get_string('logticketnumber', 'local_evenements', (int) $ticket->id));
        $transaction->allow_commit();
        if ($tookplace && $notifywaitlist) {
            booking_manager::process_waitlist($event);
        }
    }

    /**
     * Add a participant by hand (phone booking, last-minute guest): one booking with one ticket.
     * The opening dates do not apply; the capacity, quotas and slot limits apply unless $force.
     *
     * @param \stdClass $event
     * @param \stdClass $data firstname, lastname, email, tickettypeid, note, sessionid, contactname, contactemail
     * @param bool $force
     * @return \stdClass The booking
     * @throws \moodle_exception
     */
    public static function add_participant(\stdClass $event, \stdClass $data, bool $force = false): \stdClass {
        global $DB, $USER;
        $categories = booking_manager::get_categories((int) $event->id);
        $sessions = booking_manager::get_sessions((int) $event->id);
        $typeid = (int) ($data->tickettypeid ?? 0);
        if ($categories && !isset($categories[$typeid])) {
            throw new \moodle_exception('invalidcategory', 'local_evenements');
        }
        if (!$categories) {
            $typeid = 0;
        }
        $sessionid = (int) ($data->sessionid ?? 0);
        if ($sessions && !isset($sessions[$sessionid])) {
            throw new \moodle_exception('invalidsession', 'local_evenements');
        }
        if (!$sessions) {
            $sessionid = 0;
        }
        $first = \core_text::substr(trim((string) $data->firstname), 0, 100);
        $last = \core_text::substr(trim((string) $data->lastname), 0, 100);
        $email = \core_text::substr(trim((string) ($data->email ?? '')), 0, 255);
        $contactname = trim((string) ($data->contactname ?? '')) ?: trim($first . ' ' . $last);
        $contactemail = trim((string) ($data->contactemail ?? '')) ?: $email;

        $lock = self::lock((int) $event->id);
        try {
            if (!$force) {
                $problem = self::place_problem($event, $typeid, $sessionid, 1);
                if ($problem !== '') {
                    throw new \moodle_exception('changeproblem', 'local_evenements', '', $problem);
                }
            }
            $now = time();
            $transaction = $DB->start_delegated_transaction();
            $booking = (object) [
                'eventid' => $event->id, 'sessionid' => $sessionid, 'userid' => (int) ($data->userid ?? 0),
                'contactname' => \core_text::substr($contactname, 0, 255), 'email' => \core_text::substr($contactemail, 0, 255),
                'accesstoken' => booking_manager::new_token(), 'consent' => 1, 'consenttime' => $now,
                'status' => 'active', 'iphash' => null, 'timecreated' => $now,
            ];
            $booking->id = $DB->insert_record('local_evenements_booking', $booking);
            $ticket = (object) [
                'bookingid' => $booking->id, 'eventid' => $event->id, 'tickettypeid' => $typeid,
                'firstname' => $first, 'lastname' => $last, 'email' => $email, 'token' => booking_manager::new_token(),
                'status' => booking_manager::TICKET_VALID, 'timeused' => 0, 'usedby' => 0,
                'note' => \core_text::substr(trim((string) ($data->note ?? '')), 0, 255), 'timecreated' => $now,
            ];
            $ticket->id = $DB->insert_record('local_evenements_ticket', $ticket);
            $DB->execute("UPDATE {local_evenements_waitlist} SET status = :booked
                           WHERE eventid = :e AND status IN ('waiting', 'notified') AND " .
                $DB->sql_equal('email', ':email', false, false),
                ['booked' => 'booked', 'e' => $event->id, 'email' => $booking->email]);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        self::log((int) $event->id, (int) $booking->id, (int) $ticket->id, 'add',
            self::ticket_name($ticket) . ($force ? "\n" . get_string('logforced', 'local_evenements') : ''));
        return $booking;
    }

    /**
     * Send the tickets of a booking again (only the tickets that are not cancelled).
     *
     * @param \stdClass $event
     * @param \stdClass $booking
     * @return bool
     */
    public static function resend(\stdClass $event, \stdClass $booking): bool {
        try {
            $sent = booking_manager::send_confirmation($booking, $event);
        } catch (\Throwable $e) {
            $sent = false;
        }
        if ($sent) {
            self::log((int) $event->id, (int) $booking->id, 0, 'resend', $booking->email);
        }
        return $sent;
    }

    /**
     * Apply one action to several tickets of an event.
     *
     * @param \stdClass $event
     * @param int[] $ticketids
     * @param string $action cancel, restore, delete, resend, category, session
     * @param int $value New category or time slot
     * @return \stdClass done (int), failed (int), messages (string[])
     */
    public static function bulk(\stdClass $event, array $ticketids, string $action, int $value = 0): \stdClass {
        global $DB;
        $report = (object) ['done' => 0, 'failed' => 0, 'messages' => []];
        $ticketids = array_values(array_unique(array_filter(array_map('intval', $ticketids))));
        if (!$ticketids) {
            return $report;
        }
        [$insql, $params] = $DB->get_in_or_equal($ticketids, SQL_PARAMS_NAMED);
        $params['eventid'] = $event->id;
        $tickets = $DB->get_records_select('local_evenements_ticket', "eventid = :eventid AND id $insql", $params, 'id ASC');
        $freed = false;
        $resent = [];
        foreach ($tickets as $ticket) {
            try {
                switch ($action) {
                    case 'cancel':
                        $ok = self::cancel_ticket($event, $ticket, false);
                        $freed = $freed || $ok;
                        break;
                    case 'restore':
                        $ok = self::restore_ticket($event, $ticket);
                        break;
                    case 'delete':
                        $freed = $freed || $ticket->status !== booking_manager::TICKET_CANCELLED;
                        self::delete_ticket($event, $ticket, false);
                        $ok = true;
                        break;
                    case 'resend':
                        if (isset($resent[$ticket->bookingid])) {
                            continue 2;
                        }
                        $resent[$ticket->bookingid] = true;
                        $booking = $DB->get_record('local_evenements_booking', ['id' => $ticket->bookingid], '*', MUST_EXIST);
                        $ok = self::resend($event, $booking);
                        break;
                    case 'category':
                        $ok = (bool) self::update_ticket($event, $ticket, (object) ['tickettypeid' => $value]);
                        break;
                    case 'session':
                        $booking = $DB->get_record('local_evenements_booking', ['id' => $ticket->bookingid], '*', MUST_EXIST);
                        if ((int) $booking->sessionid === $value) {
                            continue 2;
                        }
                        $ok = (bool) self::update_ticket($event, $ticket, (object) ['sessionid' => $value]);
                        break;
                    default:
                        throw new \coding_exception('Unknown bulk action ' . $action);
                }
            } catch (\moodle_exception $e) {
                $ok = false;
                $report->messages[] = self::ticket_name($ticket) . ' : ' . $e->getMessage();
            }
            if ($ok) {
                $report->done++;
            } else {
                $report->failed++;
            }
        }
        if ($freed) {
            booking_manager::process_waitlist($event);
        }
        return $report;
    }
}
