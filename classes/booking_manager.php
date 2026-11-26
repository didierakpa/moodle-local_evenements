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
 * Business logic: events, bookings, named tickets, entrance checks.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class booking_manager {
    /** @var string Event status: draft, not public yet. */
    public const STATUS_DRAFT = 'draft';
    /** @var string Event status: bookings open. */
    public const STATUS_OPEN = 'open';
    /** @var string Event status: bookings closed. */
    public const STATUS_CLOSED = 'closed';

    /** @var string Ticket status: valid, not used yet. */
    public const TICKET_VALID = 'valid';
    /** @var string Ticket status: used at the entrance. */
    public const TICKET_USED = 'used';
    /** @var string Ticket status: cancelled. */
    public const TICKET_CANCELLED = 'cancelled';

    /**
     * Get one event.
     *
     * @param int $id
     * @return \stdClass
     */
    public static function get_event(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_evenements_event', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Events shown on the public page: published and not over yet.
     *
     * @return \stdClass[]
     */
    public static function get_public_events(): array {
        global $DB;
        $sql = "SELECT *
                  FROM {local_evenements_event}
                 WHERE status = :status
                       AND ((timeend > 0 AND timeend > :now1) OR (timeend = 0 AND timestart > :now2))
              ORDER BY timestart ASC, id ASC";
        $now = time();
        return $DB->get_records_sql($sql, ['status' => self::STATUS_OPEN, 'now1' => $now, 'now2' => $now]);
    }

    /**
     * Whether people can book right now.
     *
     * @param \stdClass $event
     * @return bool
     */
    public static function is_open(\stdClass $event): bool {
        $now = time();
        if ($event->status !== self::STATUS_OPEN) {
            return false;
        }
        if ($event->openfrom && $now < $event->openfrom) {
            return false;
        }
        if ($event->openuntil && $now > $event->openuntil) {
            return false;
        }
        $end = $event->timeend ?: $event->timestart;
        if ($end && $now > $end) {
            return false;
        }
        return true;
    }

    /**
     * Tickets that take a place (everything except cancelled tickets).
     *
     * @param int $eventid
     * @return int
     */
    public static function count_tickets(int $eventid): int {
        global $DB;
        return $DB->count_records_select(
            'local_evenements_ticket',
            'eventid = :e AND status <> :s',
            ['e' => $eventid, 's' => self::TICKET_CANCELLED]
        );
    }

    /**
     * Places left, or null when the capacity is unlimited.
     *
     * @param \stdClass $event
     * @return int|null
     */
    public static function remaining(\stdClass $event): ?int {
        if (empty($event->capacity)) {
            return null;
        }
        return max(0, (int) $event->capacity - self::count_tickets((int) $event->id));
    }

    /**
     * Active tickets already held by a person (same contact e-mail, or same Moodle account) for an event.
     *
     * @param int $eventid
     * @param string $email
     * @param int $userid Moodle user id, 0 for none
     * @return int
     */
    public static function count_person_tickets(int $eventid, string $email, int $userid = 0): int {
        global $DB;
        $email = trim($email);
        $who = [];
        $params = ['e' => $eventid, 'cancelled' => self::TICKET_CANCELLED, 'active' => 'active'];
        if ($email !== '') {
            $who[] = $DB->sql_equal('b.email', ':email', false, false);
            $params['email'] = $email;
        }
        if ($userid > 0) {
            $who[] = 'b.userid = :uid';
            $params['uid'] = $userid;
        }
        if (!$who) {
            return 0;
        }
        $sql = "SELECT COUNT(t.id)
                  FROM {local_evenements_ticket} t
                  JOIN {local_evenements_booking} b ON b.id = t.bookingid
                 WHERE t.eventid = :e AND t.status <> :cancelled AND b.status = :active
                   AND (" . implode(' OR ', $who) . ")";
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Tickets a person can still book for an event, or null when there is no per-person limit.
     * The per-booking limit is not included here.
     *
     * @param \stdClass $event
     * @param string $email
     * @param int $userid
     * @return int|null
     */
    public static function person_remaining(\stdClass $event, string $email, int $userid = 0): ?int {
        $max = (int) ($event->maxperperson ?? 0);
        if ($max <= 0) {
            return null;
        }
        return max(0, $max - self::count_person_tickets((int) $event->id, $email, $userid));
    }

    /**
     * Ticket categories of an event.
     *
     * @param int $eventid
     * @return \stdClass[]
     */
    public static function get_categories(int $eventid): array {
        global $DB;
        return $DB->get_records('local_evenements_tickettype', ['eventid' => $eventid], 'sortorder ASC, id ASC');
    }

    /**
     * Figures for the organiser dashboard of an event.
     *
     * @param \stdClass $event
     * @param int $days Number of days of the booking curve
     * @return \stdClass
     */
    public static function get_event_stats(\stdClass $event, int $days = 14): \stdClass {
        global $DB;
        $eventid = (int) $event->id;
        $stats = new \stdClass();
        $stats->tickets = self::count_tickets($eventid);
        $stats->used = $DB->count_records('local_evenements_ticket', ['eventid' => $eventid, 'status' => self::TICKET_USED]);
        $stats->cancelled = $DB->count_records('local_evenements_ticket', ['eventid' => $eventid,
            'status' => self::TICKET_CANCELLED]);
        $stats->bookings = $DB->count_records('local_evenements_booking', ['eventid' => $eventid, 'status' => 'active']);
        $stats->waiting = self::count_waiting($eventid);
        $stats->capacity = (int) $event->capacity;
        $stats->fillrate = $stats->capacity > 0 ? (int) round(100 * $stats->tickets / $stats->capacity) : null;
        $stats->attendance = $stats->tickets > 0 ? (int) round(100 * $stats->used / $stats->tickets) : 0;

        // Tickets booked per day.
        $from = usergetmidnight(time() - ($days - 1) * DAYSECS);
        $perday = [];
        for ($i = 0; $i < $days; $i++) {
            $perday[userdate($from + $i * DAYSECS + 3600, '%d/%m')] = 0;
        }
        $rows = $DB->get_records_select(
            'local_evenements_ticket',
            'eventid = :e AND status <> :c AND timecreated >= :from',
            ['e' => $eventid, 'c' => self::TICKET_CANCELLED, 'from' => $from],
            '',
            'id, timecreated'
        );
        foreach ($rows as $row) {
            $key = userdate($row->timecreated, '%d/%m');
            if (isset($perday[$key])) {
                $perday[$key]++;
            }
        }
        $stats->perday = $perday;

        // By category.
        $stats->categories = [];
        foreach (self::get_categories($eventid) as $type) {
            $stats->categories[] = (object) ['name' => $type->name, 'count' => self::count_category_tickets((int) $type->id),
                'quota' => (int) $type->quota];
        }

        // Latest bookings.
        $stats->latest = $DB->get_records_select(
            'local_evenements_booking',
            'eventid = :e AND status = :s',
            ['e' => $eventid, 's' => 'active'],
            'id DESC',
            '*',
            0,
            6
        );
        foreach ($stats->latest as $booking) {
            $booking->tickets = self::count_booking_tickets((int) $booking->id);
        }
        return $stats;
    }

    /**
     * Tickets already taken in a category.
     *
     * @param int $typeid
     * @return int
     */
    public static function count_category_tickets(int $typeid): int {
        global $DB;
        return $DB->count_records_select(
            'local_evenements_ticket',
            'tickettypeid = :t AND status <> :s',
            ['t' => $typeid, 's' => self::TICKET_CANCELLED]
        );
    }

    /**
     * Create or update the categories from text lines "Name|quota".
     *
     * @param int $eventid
     * @param string[] $lines
     */
    public static function save_categories(int $eventid, array $lines): void {
        global $DB;
        $existing = [];
        foreach (self::get_categories($eventid) as $type) {
            $existing[\core_text::strtolower($type->name)] = $type;
        }
        $kept = [];
        $order = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 3);
            $name = trim($parts[0]);
            $quota = isset($parts[1]) ? max(0, (int) trim($parts[1])) : 0;
            $info = \core_text::substr(trim($parts[2] ?? ''), 0, 255);
            if ($name === '') {
                continue;
            }
            $key = \core_text::strtolower($name);
            $order++;
            if (isset($existing[$key])) {
                $type = $existing[$key];
                $type->quota = $quota;
                $type->info = $info;
                $type->sortorder = $order;
                $DB->update_record('local_evenements_tickettype', $type);
                $kept[$type->id] = true;
            } else {
                $id = $DB->insert_record('local_evenements_tickettype', (object) [
                    'eventid' => $eventid,
                    'name' => $name,
                    'quota' => $quota,
                    'info' => $info,
                    'sortorder' => $order,
                ]);
                $kept[$id] = true;
            }
        }
        // Remove categories that left the list, unless tickets already use them.
        foreach ($existing as $type) {
            if (!isset($kept[$type->id]) && !$DB->record_exists('local_evenements_ticket', ['tickettypeid' => $type->id])) {
                $DB->delete_records('local_evenements_tickettype', ['id' => $type->id]);
            }
        }
    }

    /**
     * URL of the illustration of an event, if any.
     *
     * @param \stdClass $event
     * @return \moodle_url|null
     */
    public static function get_image_url(\stdClass $event): ?\moodle_url {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_evenements',
            'eventimage',
            $event->id,
            'sortorder, id',
            false
        );
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                );
            }
        }
        return self::default_image_url($event);
    }

    /** @var string[] Default images shipped with the plugin (pix/defaults/<key>.jpg). */
    public const IMAGE_THEMES = ['generic', 'jpo', 'graduation', 'forum'];

    /**
     * Default image of an event that has no image of its own.
     *
     * @param \stdClass $event
     * @return \moodle_url
     */
    public static function default_image_url(\stdClass $event): \moodle_url {
        $theme = in_array($event->imagetheme ?? '', self::IMAGE_THEMES, true) ? $event->imagetheme : 'generic';
        return new \moodle_url('/local/evenements/pix/defaults/' . $theme . '.jpg');
    }

    /**
     * A long random token for QR codes and personal links.
     *
     * @return string 40 hexadecimal characters
     */
    public static function new_token(): string {
        return bin2hex(random_bytes(20));
    }

    /**
     * Create a booking with one named ticket per guest.
     *
     * @param \stdClass $event
     * @param string $contactname
     * @param string $email
     * @param int $userid Moodle user id, 0 for a public booking
     * @param array $guests List of arrays: firstname, lastname, email, tickettypeid
     * @param string $ip Visitor address, only used (hashed) for the anti-spam limit
     * @param array $bookinganswers Answers about the whole booking: questionid => value
     *              (each guest may carry an 'answers' entry with questionid => value)
     * @param int $sessionid Time slot (0 when the event has none)
     * @param \stdClass|null $invitee Person of the invitation list who books (list events)
     * @return \stdClass The booking record
     * @throws \moodle_exception
     */
    public static function create_booking(
        \stdClass $event,
        string $contactname,
        string $email,
        int $userid,
        array $guests,
        string $ip,
        array $bookinganswers = [],
        int $sessionid = 0,
        ?\stdClass $invitee = null
    ): \stdClass {
        global $DB;

        $n = count($guests);
        if (!self::is_open($event)) {
            throw new \moodle_exception('bookingclosed', 'local_evenements');
        }
        if (self::is_list_event($event) && !$invitee) {
            throw new \moodle_exception('listonly', 'local_evenements');
        }
        $maxtickets = $invitee ? (int) $invitee->quota + 1 : (int) $event->maxperbooking;
        if ($n < 1 || $n > $maxtickets) {
            throw new \moodle_exception('toomany', 'local_evenements', '', $maxtickets);
        }

        // Anti-spam: limit the number of bookings per visitor and per hour.
        $limit = get_config('local_evenements', 'ratelimit');
        $limit = ($limit === false || $limit === '') ? 10 : (int) $limit;
        $iphash = hash('sha256', $ip . '|' . get_site_identifier());
        if ($limit > 0 && !$invitee) {
            $recent = $DB->count_records_select(
                'local_evenements_booking',
                'iphash = :h AND timecreated > :t',
                ['h' => $iphash, 't' => time() - HOURSECS]
            );
            if ($recent >= $limit) {
                throw new \moodle_exception('ratelimited', 'local_evenements');
            }
        }

        $sessions = self::get_sessions((int) $event->id);
        if ($sessions && !isset($sessions[$sessionid])) {
            throw new \moodle_exception('invalidsession', 'local_evenements');
        }
        if (!$sessions) {
            $sessionid = 0;
        }

        $categories = self::get_categories((int) $event->id);
        $percategory = [];
        foreach ($guests as $guest) {
            $typeid = (int) $guest['tickettypeid'];
            if ($categories && !isset($categories[$typeid])) {
                throw new \moodle_exception('invalidcategory', 'local_evenements');
            }
            $percategory[$typeid] = ($percategory[$typeid] ?? 0) + 1;
        }

        // The lock avoids two simultaneous bookings taking the last places.
        $lock = \core\lock\lock_config::get_lock_factory('local_evenements')->get_lock('event_' . $event->id, 15);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_evenements');
        }

        try {
            $transaction = $DB->start_delegated_transaction();

            if ($invitee) {
                // One booking per person of the list, checked again under the lock.
                $fresh = $DB->get_record(
                    'local_evenements_invitee',
                    ['id' => $invitee->id, 'eventid' => $event->id],
                    '*',
                    MUST_EXIST
                );
                if ($fresh->bookingid) {
                    throw new \moodle_exception('invitealreadybooked', 'local_evenements');
                }
                if ($n > (int) $fresh->quota + 1) {
                    throw new \moodle_exception('toomany', 'local_evenements', '', (int) $fresh->quota + 1);
                }
            }
            $personleft = $invitee ? null : self::person_remaining($event, $email, $userid);
            if ($personleft !== null && $n > $personleft) {
                throw new \moodle_exception('personlimit', 'local_evenements', '', (object) [
                    'max' => (int) $event->maxperperson,
                    'left' => $personleft,
                ]);
            }
            $remaining = self::remaining($event);
            if ($remaining !== null && $n > $remaining) {
                throw new \moodle_exception('notenough', 'local_evenements', '', $remaining);
            }
            if ($sessionid) {
                $left = self::session_remaining($sessions[$sessionid]);
                if ($left !== null && $n > $left) {
                    throw new \moodle_exception('sessionfull', 'local_evenements');
                }
            }
            foreach ($categories as $category) {
                $wanted = $percategory[$category->id] ?? 0;
                if (
                    $wanted && $category->quota > 0 &&
                        self::count_category_tickets((int) $category->id) + $wanted > $category->quota
                ) {
                    throw new \moodle_exception('quotareached', 'local_evenements', '', $category->name);
                }
            }

            $now = time();
            $booking = (object) [
                'eventid' => $event->id,
                'sessionid' => $sessionid,
                'userid' => $userid,
                'contactname' => $contactname,
                'email' => $email,
                'accesstoken' => self::new_token(),
                'consent' => 1,
                'consenttime' => $now,
                'status' => 'active',
                'iphash' => $iphash,
                'timecreated' => $now,
            ];
            $booking->id = $DB->insert_record('local_evenements_booking', $booking);

            foreach ($guests as $guest) {
                $ticketid = $DB->insert_record('local_evenements_ticket', (object) [
                    'bookingid' => $booking->id,
                    'eventid' => $event->id,
                    'tickettypeid' => (int) $guest['tickettypeid'],
                    'firstname' => $guest['firstname'],
                    'lastname' => $guest['lastname'],
                    'email' => $guest['email'] ?? '',
                    'token' => self::new_token(),
                    'status' => self::TICKET_VALID,
                    'timeused' => 0,
                    'usedby' => 0,
                    'note' => \core_text::substr((string) ($guest['note'] ?? ''), 0, 255),
                    'timecreated' => $now,
                ]);
                foreach (($guest['answers'] ?? []) as $questionid => $value) {
                    self::insert_answer((int) $event->id, (int) $questionid, (int) $booking->id, (int) $ticketid, (string) $value);
                }
            }
            foreach ($bookinganswers as $questionid => $value) {
                self::insert_answer((int) $event->id, (int) $questionid, (int) $booking->id, 0, (string) $value);
            }

            if ($invitee) {
                $DB->set_field('local_evenements_invitee', 'bookingid', $booking->id, ['id' => $invitee->id]);
            }

            $DB->execute(
                "UPDATE {local_evenements_waitlist} SET status = :booked
                           WHERE eventid = :e AND status IN ('waiting', 'notified') AND " .
                $DB->sql_equal('email', ':email', false, false),
                ['booked' => 'booked', 'e' => $event->id, 'email' => $email]
            );

            $transaction->allow_commit();
        } catch (\Exception $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            $lock->release();
        }

        return $booking;
    }

    /**
     * Questions of an event, in display order.
     *
     * @param int $eventid
     * @param string|null $moment 'before', 'after' or null for both
     * @return \stdClass[]
     */
    public static function get_questions(int $eventid, ?string $moment = null): array {
        global $DB;
        $params = ['eventid' => $eventid];
        if ($moment !== null) {
            $params['moment'] = $moment;
        }
        return $DB->get_records('local_evenements_question', $params, 'moment DESC, sortorder ASC, id ASC');
    }

    /**
     * Choices of a question (one per line).
     *
     * @param \stdClass $question
     * @return string[]
     */
    public static function get_question_options(\stdClass $question): array {
        $options = [];
        foreach (preg_split('/\R/', (string) $question->options) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $options[$line] = $line;
            }
        }
        return $options;
    }

    /**
     * Turn a submitted value into the string to store. Returns '' for an
     * empty or invalid value. Multiple choices are joined with new lines.
     *
     * @param \stdClass $question
     * @param mixed $raw
     * @return string
     */
    public static function clean_answer(\stdClass $question, $raw): string {
        if ($question->type === 'choice' || $question->type === 'multichoice') {
            $options = self::get_question_options($question);
            $values = is_array($raw) ? $raw : [$raw];
            $kept = [];
            foreach ($values as $value) {
                $value = trim((string) $value);
                if ($value !== '' && isset($options[$value])) {
                    $kept[$value] = $value;
                }
            }
            if ($question->type === 'choice') {
                $kept = array_slice($kept, 0, 1);
            }
            return implode("\n", $kept);
        }
        $value = is_array($raw) ? '' : trim((string) $raw);
        return \core_text::substr($value, 0, 2000);
    }

    /**
     * Store one answer, ignoring empty ones.
     *
     * @param int $eventid
     * @param int $questionid
     * @param int $bookingid
     * @param int $ticketid 0 for an answer about the whole booking
     * @param string $value
     */
    protected static function insert_answer(int $eventid, int $questionid, int $bookingid, int $ticketid, string $value): void {
        global $DB;
        if ($value === '') {
            return;
        }
        $DB->insert_record('local_evenements_answer', (object) [
            'eventid' => $eventid,
            'questionid' => $questionid,
            'bookingid' => $bookingid,
            'ticketid' => $ticketid,
            'value' => $value,
            'timecreated' => time(),
        ]);
    }

    /**
     * Answers of the survey (after the event) for a booking: questionid => value.
     *
     * @param \stdClass $booking
     * @return string[]
     */
    public static function get_survey_answers(\stdClass $booking): array {
        global $DB;
        $sql = "SELECT a.questionid, a.value
                  FROM {local_evenements_answer} a
                  JOIN {local_evenements_question} q ON q.id = a.questionid
                 WHERE a.bookingid = :bookingid AND q.moment = :moment";
        $result = [];
        foreach ($DB->get_records_sql($sql, ['bookingid' => $booking->id, 'moment' => 'after']) as $row) {
            $result[$row->questionid] = $row->value;
        }
        return $result;
    }

    /**
     * Save (replace) the survey answers of a booking.
     *
     * @param \stdClass $booking
     * @param array $answers questionid => cleaned value
     */
    public static function save_survey(\stdClass $booking, array $answers): void {
        global $DB;
        $DB->delete_records_select(
            'local_evenements_answer',
            "bookingid = :bookingid AND questionid IN (SELECT id FROM {local_evenements_question}
                                                        WHERE eventid = :eventid AND moment = :moment)",
            ['bookingid' => $booking->id, 'eventid' => $booking->eventid, 'moment' => 'after']
        );
        foreach ($answers as $questionid => $value) {
            self::insert_answer((int) $booking->eventid, (int) $questionid, (int) $booking->id, 0, (string) $value);
        }
    }

    /**
     * Get a booking from its secret access token.
     *
     * @param string $token
     * @return \stdClass|false
     */
    public static function get_booking_by_token(string $token) {
        global $DB;
        if (!preg_match('/^[0-9a-f]{40}$/', $token)) {
            return false;
        }
        return $DB->get_record('local_evenements_booking', ['accesstoken' => $token]);
    }

    /**
     * Tickets of a booking.
     *
     * @param int $bookingid
     * @return \stdClass[]
     */
    public static function get_tickets(int $bookingid): array {
        global $DB;
        return $DB->get_records('local_evenements_ticket', ['bookingid' => $bookingid], 'id ASC');
    }

    /**
     * Cancel a booking: its unused tickets no longer take a place.
     *
     * @param \stdClass $booking
     */
    public static function cancel_booking(\stdClass $booking): void {
        global $DB;
        $DB->set_field('local_evenements_booking', 'status', 'cancelled', ['id' => $booking->id]);
        $DB->set_field(
            'local_evenements_ticket',
            'status',
            self::TICKET_CANCELLED,
            ['bookingid' => $booking->id, 'status' => self::TICKET_VALID]
        );
        // Places are free again: warn the people waiting.
        $event = $DB->get_record('local_evenements_event', ['id' => $booking->eventid]);
        if ($event) {
            self::process_waitlist($event);
        }
    }

    /**
     * Is the waiting list open for this event (booking open, but fewer places than needed)?
     *
     * @param \stdClass $event
     * @return bool
     */
    public static function waitlist_available(\stdClass $event): bool {
        return self::is_open($event) && !self::is_list_event($event) && self::remaining($event) === 0;
    }

    /**
     * Put a visitor on the waiting list.
     *
     * @param \stdClass $event
     * @param string $contactname
     * @param string $email
     * @param int $userid
     * @param int $quantity
     * @return \stdClass The waiting list entry
     * @throws \moodle_exception
     */
    public static function join_waitlist(
        \stdClass $event,
        string $contactname,
        string $email,
        int $userid,
        int $quantity
    ): \stdClass {
        global $DB;
        if (!self::is_open($event) || self::is_list_event($event)) {
            throw new \moodle_exception('bookingclosed', 'local_evenements');
        }
        if ($quantity < 1 || $quantity > (int) $event->maxperbooking) {
            throw new \moodle_exception('toomany', 'local_evenements', '', (int) $event->maxperbooking);
        }
        $personleft = self::person_remaining($event, $email, $userid);
        if ($personleft !== null && $quantity > $personleft) {
            throw new \moodle_exception('personlimit', 'local_evenements', '', (object) [
                'max' => (int) $event->maxperperson,
                'left' => $personleft,
            ]);
        }
        $sql = $DB->sql_equal('email', ':email', false, false) . ' AND eventid = :e AND status IN (:s1, :s2)';
        if (
            $DB->record_exists_select(
                'local_evenements_waitlist',
                $sql,
                ['email' => $email, 'e' => $event->id, 's1' => 'waiting', 's2' => 'notified']
            )
        ) {
            throw new \moodle_exception('alreadyonwaitlist', 'local_evenements');
        }
        $entry = (object) [
            'eventid' => $event->id,
            'userid' => $userid,
            'contactname' => $contactname,
            'email' => $email,
            'quantity' => $quantity,
            'token' => self::new_token(),
            'status' => 'waiting',
            'timecreated' => time(),
            'timenotified' => 0,
        ];
        $entry->id = $DB->insert_record('local_evenements_waitlist', $entry);
        return $entry;
    }

    /**
     * Waiting list entry from its secret token.
     *
     * @param string $token
     * @return \stdClass|false
     */
    public static function get_waitlist_by_token(string $token) {
        global $DB;
        return $DB->get_record('local_evenements_waitlist', ['token' => $token]);
    }

    /**
     * Number of people (entries) still waiting for an event.
     *
     * @param int $eventid
     * @return int
     */
    public static function count_waiting(int $eventid): int {
        global $DB;
        return $DB->count_records('local_evenements_waitlist', ['eventid' => $eventid, 'status' => 'waiting']);
    }

    /**
     * Leave the waiting list.
     *
     * @param \stdClass $entry
     */
    public static function leave_waitlist(\stdClass $entry): void {
        global $DB;
        if (in_array($entry->status, ['waiting', 'notified'])) {
            $DB->set_field('local_evenements_waitlist', 'status', 'cancelled', ['id' => $entry->id]);
        }
    }

    /**
     * Warn the next people of the waiting list when places are free (first come, first served).
     * Places are not held: the first to book gets them. Called when a booking is cancelled
     * or when the capacity grows.
     *
     * @param \stdClass $event
     * @return int Number of e-mails sent
     */
    public static function process_waitlist(\stdClass $event): int {
        global $DB;
        $remaining = self::remaining($event);
        if (empty($remaining) || !self::is_open($event)) {
            return 0;
        }
        $sent = 0;
        $entries = $DB->get_records(
            'local_evenements_waitlist',
            ['eventid' => $event->id, 'status' => 'waiting'],
            'id ASC'
        );
        foreach ($entries as $entry) {
            if ($remaining < 1) {
                break;
            }
            if ((int) $entry->quantity > $remaining) {
                continue;
            }
            $DB->set_field('local_evenements_waitlist', 'status', 'notified', ['id' => $entry->id]);
            $DB->set_field('local_evenements_waitlist', 'timenotified', time(), ['id' => $entry->id]);
            $remaining -= (int) $entry->quantity;
            try {
                if (self::send_waitlist_notice($entry, $event)) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                debugging('local_evenements: waiting list e-mail failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return $sent;
    }

    /**
     * E-mail telling someone that places are available.
     *
     * @param \stdClass $entry
     * @param \stdClass $event
     * @return bool
     */
    protected static function send_waitlist_notice(\stdClass $entry, \stdClass $event): bool {
        $a = (object) [
            'name' => $entry->contactname,
            'eventname' => format_string($event->name),
            'when' => self::event_when($event),
            'count' => (int) $entry->quantity,
            'url' => (new \moodle_url('/local/evenements/waitlist.php', ['w' => $entry->token]))->out(false),
        ];
        $text = get_string('waitlistnoticebody', 'local_evenements', $a);
        return (bool) email_to_user(
            self::make_recipient($entry->email, $entry->contactname),
            \core_user::get_noreply_user(),
            get_string('waitlistnoticesubject', 'local_evenements', $a),
            $text,
            text_to_html($text, false, false, true)
        );
    }

    /**
     * URL of the personal page of a booking.
     *
     * @param \stdClass $booking
     * @return \moodle_url
     */
    public static function booking_url(\stdClass $booking): \moodle_url {
        return new \moodle_url('/local/evenements/ticket.php', ['b' => $booking->accesstoken]);
    }

    /**
     * URL encoded in the QR code of a ticket.
     *
     * @param \stdClass $ticket
     * @return \moodle_url
     */
    public static function ticket_scan_url(\stdClass $ticket): \moodle_url {
        return new \moodle_url('/local/evenements/scan.php', ['t' => $ticket->token]);
    }

    /**
     * Readable date range of an event.
     *
     * @param \stdClass $event
     * @return string
     */
    public static function event_when(\stdClass $event): string {
        $format = get_string('strftimedaydatetime', 'langconfig');
        $when = userdate($event->timestart, $format);
        if ($event->timeend) {
            if (userdate($event->timestart, '%Y%m%d') === userdate($event->timeend, '%Y%m%d')) {
                // Same day: show the end time only.
                $when .= ' – ' . userdate($event->timeend, get_string('strftimetime', 'langconfig'));
            } else {
                $when .= ' – ' . userdate($event->timeend, $format);
            }
        }
        return $when;
    }

    /**
     * Find a ticket from the token carried by its QR code.
     *
     * @param string $token
     * @return \stdClass|false
     */
    public static function get_ticket_by_token(string $token) {
        global $DB;
        if (!preg_match('/^[0-9a-f]{40}$/', $token)) {
            return false;
        }
        return $DB->get_record('local_evenements_ticket', ['token' => $token]);
    }

    /**
     * Mark a ticket as used at the entrance. Returns the result code
     * (accepted, alreadyused or refused) and records it in the scan log.
     *
     * @param \stdClass $ticket
     * @param int $userid The staff member
     * @param int $time Time of the scan (0 = now; set by the door app when it syncs offline scans)
     * @param int $operatorid Door operator (app without a Moodle account), 0 otherwise
     * @return string
     */
    public static function check_in(\stdClass $ticket, int $userid, int $time = 0, int $operatorid = 0): string {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_evenements')->get_lock('ticket_' . $ticket->id, 10);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_evenements');
        }
        try {
            $fresh = $DB->get_record('local_evenements_ticket', ['id' => $ticket->id], '*', MUST_EXIST);
            if ($fresh->status === self::TICKET_VALID) {
                $DB->update_record('local_evenements_ticket', (object) [
                    'id' => $fresh->id,
                    'status' => self::TICKET_USED,
                    'timeused' => $time > 0 ? min($time, time()) : time(),
                    'usedby' => $userid,
                    'operatorid' => $operatorid,
                ]);
                $result = 'accepted';
            } else if ($fresh->status === self::TICKET_USED) {
                $result = 'alreadyused';
            } else {
                $result = 'refused';
            }
        } finally {
            $lock->release();
        }

        $DB->insert_record('local_evenements_scanlog', (object) [
            'ticketid' => $ticket->id,
            'eventid' => $ticket->eventid,
            'userid' => $userid,
            'operatorid' => $operatorid,
            'result' => $result,
            'timecreated' => time(),
        ]);
        return $result;
    }

    /**
     * Send the confirmation e-mail with the tickets as one PDF.
     *
     * @param \stdClass $booking
     * @param \stdClass $event
     * @return bool
     */
    public static function send_confirmation(\stdClass $booking, \stdClass $event): bool {
        $event = self::apply_session($event, $booking);
        $tickets = array_filter(self::get_tickets((int) $booking->id), function ($t) {
            return $t->status !== self::TICKET_CANCELLED;
        });
        if (!$tickets || trim((string) $booking->email) === '') {
            return false;
        }
        $pdf = ticket_pdf::generate($event, $booking, $tickets);

        $path = make_temp_directory('local_evenements') . '/' . $booking->accesstoken . '.pdf';
        file_put_contents($path, $pdf);

        $to = self::make_recipient($booking->email, $booking->contactname);
        $from = \core_user::get_noreply_user();
        $a = (object) [
            'name' => $booking->contactname,
            'eventname' => format_string($event->name),
            'when' => self::event_when($event),
            'location' => $event->location ?? '',
            'count' => count($tickets),
            'url' => self::booking_url($booking)->out(false),
        ];
        $subject = get_string('confirmationsubject', 'local_evenements', $a);
        $text = get_string('confirmationbody', 'local_evenements', $a);
        $html = text_to_html($text, false, false, true);

        $sent = email_to_user($to, $from, $subject, $text, $html, $path, 'billets.pdf');
        @unlink($path);
        return (bool) $sent;
    }

    /**
     * Send a plain e-mail to a booking contact.
     *
     * @param string $email
     * @param string $name
     * @param string $subject
     * @param string $text
     * @return bool
     */
    public static function send_text(string $email, string $name, string $subject, string $text): bool {
        return (bool) email_to_user(
            self::make_recipient($email, $name),
            \core_user::get_noreply_user(),
            $subject,
            $text,
            text_to_html($text, false, false, true)
        );
    }

    /**
     * Send the reminder to the bookings of events that start soon (once per booking).
     *
     * @return int Number of e-mails sent
     */
    public static function send_due_reminders(): int {
        global $DB;
        $hours = get_config('local_evenements', 'reminderhours');
        $hours = ($hours === false || $hours === '') ? 24 : (int) $hours;
        if ($hours <= 0) {
            return 0;
        }
        $now = time();
        $events = $DB->get_records_select(
            'local_evenements_event',
            'status = :s AND timestart > :now AND timestart <= :limit',
            ['s' => self::STATUS_OPEN, 'now' => $now, 'limit' => $now + $hours * HOURSECS]
        );
        $sent = 0;
        foreach ($events as $event) {
            $bookings = $DB->get_records(
                'local_evenements_booking',
                ['eventid' => $event->id, 'status' => 'active', 'remindersent' => 0]
            );
            foreach ($bookings as $booking) {
                $a = (object) [
                    'name' => $booking->contactname,
                    'eventname' => format_string($event->name),
                    'when' => self::event_when(self::apply_session($event, $booking)),
                    'location' => $event->location ?? '',
                    'count' => self::count_booking_tickets((int) $booking->id),
                    'url' => self::booking_url($booking)->out(false),
                ];
                // Marked first: a failing mail server must not cause the same mail to be sent again and again.
                $DB->set_field('local_evenements_booking', 'remindersent', $now, ['id' => $booking->id]);
                try {
                    if (
                        self::send_text(
                            $booking->email,
                            $booking->contactname,
                            get_string('remindersubject', 'local_evenements', $a),
                            get_string('reminderbody', 'local_evenements', $a)
                        )
                    ) {
                        $sent++;
                    }
                } catch (\Throwable $e) {
                    debugging('local_evenements: reminder failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }
        return $sent;
    }

    /**
     * Send the survey to the bookings of finished events (once per booking, only when somebody was checked in).
     *
     * @return int Number of e-mails sent
     */
    public static function send_due_surveys(): int {
        global $DB;
        if (!get_config('local_evenements', 'surveyemail')) {
            return 0;
        }
        $now = time();
        $sql = "SELECT *
                  FROM {local_evenements_event}
                 WHERE status IN (:s1, :s2)
                       AND (CASE WHEN timeend > 0 THEN timeend ELSE timestart + :span END) < :now
                       AND (CASE WHEN timeend > 0 THEN timeend ELSE timestart + :span2 END) > :oldest";
        $events = $DB->get_records_sql($sql, ['s1' => self::STATUS_OPEN, 's2' => self::STATUS_CLOSED,
            'span' => 4 * HOURSECS, 'span2' => 4 * HOURSECS, 'now' => $now, 'oldest' => $now - 7 * DAYSECS]);
        $sent = 0;
        foreach ($events as $event) {
            if (!self::get_questions((int) $event->id, 'after')) {
                continue;
            }
            $bookings = $DB->get_records_sql(
                "SELECT b.*
                  FROM {local_evenements_booking} b
                 WHERE b.eventid = :e AND b.status = 'active' AND b.surveysent = 0
                       AND EXISTS (SELECT 1 FROM {local_evenements_ticket} t
                                    WHERE t.bookingid = b.id AND t.status = :used)",
                ['e' => $event->id, 'used' => self::TICKET_USED]
            );
            foreach ($bookings as $booking) {
                $a = (object) [
                    'name' => $booking->contactname,
                    'eventname' => format_string($event->name),
                    'url' => (new \moodle_url('/local/evenements/survey.php', ['b' => $booking->accesstoken]))->out(false),
                ];
                $DB->set_field('local_evenements_booking', 'surveysent', $now, ['id' => $booking->id]);
                try {
                    if (
                        self::send_text(
                            $booking->email,
                            $booking->contactname,
                            get_string('surveysubject', 'local_evenements', $a),
                            get_string('surveybody', 'local_evenements', $a)
                        )
                    ) {
                        $sent++;
                    }
                } catch (\Throwable $e) {
                    debugging('local_evenements: survey e-mail failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }
        return $sent;
    }

    /**
     * Number of tickets of a booking that still count.
     *
     * @param int $bookingid
     * @return int
     */
    public static function count_booking_tickets(int $bookingid): int {
        global $DB;
        return $DB->count_records_select(
            'local_evenements_ticket',
            'bookingid = :b AND status <> :c',
            ['b' => $bookingid, 'c' => self::TICKET_CANCELLED]
        );
    }

    /**
     * Send one message to everybody who holds a booking for an event (one mail per e-mail address).
     *
     * @param \stdClass $event
     * @param string $subject
     * @param string $body
     * @return int Number of e-mails sent
     */
    public static function send_group_message(\stdClass $event, string $subject, string $body): int {
        global $DB;
        $sent = 0;
        $done = [];
        foreach (
            $DB->get_records(
                'local_evenements_booking',
                ['eventid' => $event->id, 'status' => 'active'],
                'id ASC'
            ) as $booking
        ) {
            $key = \core_text::strtolower(trim($booking->email));
            if (isset($done[$key])) {
                continue;
            }
            $done[$key] = true;
            $text = get_string('groupmessagehello', 'local_evenements', $booking->contactname) . "\n\n" . $body .
                "\n\n" . self::booking_url($booking)->out(false);
            try {
                if (self::send_text($booking->email, $booking->contactname, $subject, $text)) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                debugging('local_evenements: group message failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return $sent;
    }

    /**
     * Copy an event (draft, dates moved 52 weeks later so the weekday stays the same) with its categories and questions.
     *
     * @param int $eventid
     * @return int Id of the new event
     */
    public static function duplicate_event(int $eventid): int {
        global $DB, $USER;
        $event = self::get_event($eventid);
        $shift = 52 * WEEKSECS;
        $new = clone $event;
        unset($new->id);
        $new->name = get_string('copyof', 'local_evenements', $event->name);
        $new->status = self::STATUS_DRAFT;
        $new->sourceid = $eventid;
        foreach (['timestart', 'timeend', 'openfrom', 'openuntil'] as $field) {
            if (!empty($new->$field)) {
                $new->$field += $shift;
            }
        }
        $new->timecreated = time();
        $new->timemodified = time();
        $new->usermodified = $USER->id;
        $newid = $DB->insert_record('local_evenements_event', $new);

        foreach ($DB->get_records('local_evenements_tickettype', ['eventid' => $eventid], 'sortorder ASC, id ASC') as $type) {
            unset($type->id);
            $type->eventid = $newid;
            $DB->insert_record('local_evenements_tickettype', $type);
        }
        foreach ($DB->get_records('local_evenements_question', ['eventid' => $eventid], 'sortorder ASC, id ASC') as $question) {
            unset($question->id);
            $question->eventid = $newid;
            $DB->insert_record('local_evenements_question', $question);
        }
        $fs = get_file_storage();
        $systemid = \context_system::instance()->id;
        foreach (['eventimage', 'ticketlogo'] as $area) {
            foreach ($fs->get_area_files($systemid, 'local_evenements', $area, $eventid, 'id', false) as $file) {
                $fs->create_file_from_storedfile(['itemid' => $newid], $file);
            }
        }
        foreach ($DB->get_records('local_evenements_staff', ['eventid' => $eventid]) as $member) {
            unset($member->id);
            $member->eventid = $newid;
            $DB->insert_record('local_evenements_staff', $member);
        }
        foreach (self::get_sessions($eventid) as $session) {
            unset($session->id);
            $session->eventid = $newid;
            $session->timestart += $shift;
            $session->timeend = $session->timeend ? $session->timeend + $shift : 0;
            $DB->insert_record('local_evenements_session', $session);
        }
        return $newid;
    }

    /**
     * Counters of tickets for events happening around now (for the check-in page).
     *
     * @return \stdClass[] Events with ->total and ->used
     */
    public static function get_live_counters(): array {
        global $DB;
        $now = time();
        $events = $DB->get_records_select(
            'local_evenements_event',
            'status = :s AND timestart BETWEEN :from AND :to',
            ['s' => self::STATUS_OPEN,
            'from' => $now - DAYSECS,
            'to' => $now + DAYSECS],
            'timestart ASC'
        );
        foreach ($events as $event) {
            $event->total = self::count_tickets((int) $event->id);
            $event->used = $DB->count_records(
                'local_evenements_ticket',
                ['eventid' => $event->id, 'status' => self::TICKET_USED]
            );
        }
        return $events;
    }

    /**
     * A recipient object for people who have no Moodle account.
     *
     * @param string $email
     * @param string $name
     * @return \stdClass
     */
    protected static function make_recipient(string $email, string $name): \stdClass {
        $user = new \stdClass();
        foreach (\core_user\fields::get_name_fields() as $field) {
            $user->$field = '';
        }
        $user->id = -99;
        $user->username = 'evenements-guest';
        $user->firstname = $name;
        $user->email = $email;
        $user->mailformat = 1;
        $user->maildisplay = 1;
        $user->auth = 'manual';
        $user->deleted = 0;
        $user->suspended = 0;
        $user->emailstop = 0;
        $user->lang = current_language();
        return $user;
    }

    /**
     * Remove the personal data (bookings, tickets, scan log) of one event.
     *
     * @param int $eventid
     */
    public static function delete_personal_data(int $eventid): void {
        global $DB;
        // The anonymous figures of the event are kept for its report and for the next edition.
        report_manager::snapshot($eventid);
        $DB->delete_records('local_evenements_answer', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_waitlist', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_scanlog', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_changelog', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_ticket', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_booking', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_operator', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_staff', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_invitee', ['eventid' => $eventid]);
    }

    /**
     * Delete an event and everything attached to it.
     *
     * @param int $eventid
     */
    public static function delete_event(int $eventid): void {
        global $DB;
        self::delete_personal_data($eventid);
        $DB->delete_records('local_evenements_report', ['eventid' => $eventid]);
        $DB->set_field('local_evenements_event', 'sourceid', 0, ['sourceid' => $eventid]);
        $DB->delete_records('local_evenements_question', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_tickettype', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_session', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_organiser', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_operator', ['eventid' => $eventid]);
        $DB->delete_records('local_evenements_event', ['id' => $eventid]);
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'local_evenements', 'eventimage', $eventid);
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'local_evenements', 'ticketlogo', $eventid);
    }

    /**
     * Scheduled purge: delete personal data of events that ended long enough ago.
     *
     * @return int Number of events purged
     */
    public static function purge_expired(): int {
        global $DB;
        $days = get_config('local_evenements', 'retentiondays');
        $days = ($days === false || $days === '') ? 30 : (int) $days;
        if ($days <= 0) {
            return 0;
        }
        $cutoff = time() - $days * DAYSECS;
        $sql = "SELECT id
                  FROM {local_evenements_event}
                 WHERE (timeend > 0 AND timeend < :c1)
                       OR (timeend = 0 AND timestart > 0 AND timestart < :c2)";
        $ids = $DB->get_fieldset_sql($sql, ['c1' => $cutoff, 'c2' => $cutoff]);
        foreach ($ids as $id) {
            self::delete_personal_data((int) $id);
        }
        return count($ids);
    }

    // Time slots.

    /**
     * Time slots of an event, in chronological order, indexed by id.
     *
     * @param int $eventid
     * @return \stdClass[]
     */
    public static function get_sessions(int $eventid): array {
        global $DB;
        return $DB->get_records('local_evenements_session', ['eventid' => $eventid], 'timestart ASC, id ASC');
    }

    /**
     * Active tickets booked on a time slot.
     *
     * @param int $sessionid
     * @return int
     */
    public static function count_session_tickets(int $sessionid): int {
        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(t.id)
              FROM {local_evenements_ticket} t
              JOIN {local_evenements_booking} b ON b.id = t.bookingid
             WHERE b.sessionid = :sid AND t.status <> :cancelled",
            ['sid' => $sessionid, 'cancelled' => self::TICKET_CANCELLED]
        );
    }

    /**
     * Places left on a time slot, or null when unlimited.
     *
     * @param \stdClass $session
     * @return int|null
     */
    public static function session_remaining(\stdClass $session): ?int {
        if (empty($session->capacity)) {
            return null;
        }
        return max(0, (int) $session->capacity - self::count_session_tickets((int) $session->id));
    }

    /**
     * Readable label of a time slot: "Saturday 6 March, 10:00 - 11:00 (name)".
     *
     * @param \stdClass $session
     * @return string
     */
    public static function session_label(\stdClass $session): string {
        $text = userdate($session->timestart, get_string('strftimedaydatetime', 'langconfig'));
        if (!empty($session->timeend)) {
            $text .= ' - ' . userdate($session->timeend, get_string('strftimetime', 'langconfig'));
        }
        if (!empty($session->name)) {
            $text .= ' · ' . format_string($session->name);
        }
        return $text;
    }

    /**
     * Copy of the event whose dates are those of the time slot chosen in a booking (used for tickets, mails, calendar).
     *
     * @param \stdClass $event
     * @param \stdClass $booking
     * @return \stdClass
     */
    public static function apply_session(\stdClass $event, \stdClass $booking): \stdClass {
        global $DB;
        if (empty($booking->sessionid)) {
            return $event;
        }
        $session = $DB->get_record('local_evenements_session', ['id' => $booking->sessionid]);
        if (!$session) {
            return $event;
        }
        $copy = clone $event;
        $copy->timestart = (int) $session->timestart;
        $copy->timeend = (int) $session->timeend;
        return $copy;
    }

    // Co-organisers.

    /**
     * Whether a user is a co-organiser of an event.
     *
     * @param int $eventid
     * @param int $userid
     * @return bool
     */
    public static function is_organiser(int $eventid, int $userid): bool {
        global $DB;
        return $userid > 0 && $DB->record_exists('local_evenements_organiser', ['eventid' => $eventid, 'userid' => $userid]);
    }

    /**
     * Whether the current user manages an event: site manager or co-organiser of this event.
     *
     * @param int $eventid
     * @param string $capability Site-level capability that also grants access
     * @return bool
     */
    public static function can_manage(int $eventid, string $capability = 'local/evenements:manage'): bool {
        global $USER;
        return has_capability($capability, \context_system::instance()) ||
            self::is_organiser($eventid, (int) $USER->id);
    }

    /**
     * Requires the current user to manage the event (login included).
     *
     * @param int $eventid
     * @param string $capability Site-level capability that also grants access
     */
    public static function require_event_access(int $eventid, string $capability = 'local/evenements:manage'): void {
        require_login();
        if (!self::can_manage($eventid, $capability)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                $capability,
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Ids of the events the current user co-organises.
     *
     * @return int[]
     */
    public static function organiser_event_ids(): array {
        global $DB, $USER;
        if (!isloggedin() || isguestuser()) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_select(
            'local_evenements_organiser',
            'eventid',
            'userid = ?',
            [$USER->id]
        ));
    }

    // CSV import.

    /**
     * Creates one booking (with one ticket) per CSV row: lastname, firstname, e-mail, category (optional).
     * Limits of the event are not applied: the organiser decides.
     *
     * @param \stdClass $event
     * @param string $content CSV text, separator ";" or ","
     * @param bool $sendmail Send the ticket by e-mail to rows with an address
     * @param int $sessionid Time slot for every row (0 for none)
     * @return \stdClass created, skipped, errors (list of strings)
     */
    public static function import_guests(\stdClass $event, string $content, bool $sendmail, int $sessionid = 0): \stdClass {
        global $DB;
        $report = (object) ['created' => 0, 'skipped' => 0, 'errors' => []];
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\R/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $categories = self::get_categories((int) $event->id);
        $byname = [];
        foreach ($categories as $category) {
            $byname[\core_text::strtolower(trim($category->name))] = (int) $category->id;
        }
        $defaulttype = $categories ? (int) reset($categories)->id : 0;
        $now = time();
        $seen = [];

        foreach ($lines as $number => $line) {
            $sep = (substr_count($line, ';') >= substr_count($line, ',')) ? ';' : ',';
            $cols = array_map('trim', str_getcsv($line, $sep, '"', ''));
            $last = $cols[0] ?? '';
            $first = $cols[1] ?? '';
            $mail = $cols[2] ?? '';
            $catname = $cols[3] ?? '';
            // Header row.
            if ($number === 0 && preg_match('/^(nom|last|lastname|name)$/i', $last)) {
                continue;
            }
            if ($last === '' || $first === '') {
                $report->errors[] = get_string('importerrorline', 'local_evenements', (object) ['line' => $number + 1,
                    'why' => get_string('importmissingname', 'local_evenements')]);
                continue;
            }
            if ($mail !== '' && !validate_email($mail)) {
                $report->errors[] = get_string('importerrorline', 'local_evenements', (object) ['line' => $number + 1,
                    'why' => get_string('importbademail', 'local_evenements')]);
                continue;
            }
            $key = \core_text::strtolower($last . '|' . $first . '|' . $mail);
            if (isset($seen[$key])) {
                $report->skipped++;
                continue;
            }
            $typeid = $defaulttype;
            if ($catname !== '') {
                $lower = \core_text::strtolower($catname);
                if (!isset($byname[$lower])) {
                    $report->errors[] = get_string('importerrorline', 'local_evenements', (object) ['line' => $number + 1,
                        'why' => get_string('importbadcategory', 'local_evenements', s($catname))]);
                    continue;
                }
                $typeid = $byname[$lower];
            }

            $seen[$key] = true;
            $booking = (object) [
                'eventid' => $event->id, 'sessionid' => $sessionid, 'userid' => 0,
                'contactname' => $first . ' ' . $last,
                'email' => $mail, 'accesstoken' => self::new_token(), 'consent' => 1, 'consenttime' => $now,
                'status' => 'active', 'iphash' => null, 'timecreated' => $now,
            ];
            $booking->id = $DB->insert_record('local_evenements_booking', $booking);
            $DB->insert_record('local_evenements_ticket', (object) [
                'bookingid' => $booking->id, 'eventid' => $event->id, 'tickettypeid' => $typeid,
                'firstname' => $first, 'lastname' => $last, 'email' => $mail, 'token' => self::new_token(),
                'status' => self::TICKET_VALID, 'timeused' => 0, 'usedby' => 0, 'timecreated' => $now,
            ]);
            $report->created++;
            if ($sendmail && $mail !== '') {
                try {
                    self::send_confirmation($booking, $event);
                } catch (\Throwable $e) {
                    $report->errors[] = get_string('importerrorline', 'local_evenements', (object) ['line' => $number + 1,
                        'why' => get_string('importmailfailed', 'local_evenements')]);
                }
            }
        }
        return $report;
    }

    // Changing a guest.

    /**
     * Whether the holder of a booking may still change the name on a ticket (not used, before the deadline).
     *
     * @param \stdClass $event Event (already adjusted to the time slot)
     * @param \stdClass $ticket
     * @return bool
     */
    public static function can_edit_ticket(\stdClass $event, \stdClass $ticket): bool {
        if ($ticket->status !== self::TICKET_VALID) {
            return false;
        }
        $hours = get_config('local_evenements', 'editdeadlinehours');
        $hours = ($hours === false || $hours === '') ? 24 : (int) $hours;
        if ($hours < 0) {
            return false;
        }
        return time() < (int) $event->timestart - $hours * HOURSECS;
    }

    // Lost tickets.

    /**
     * Sends to an address the links of all its active bookings of upcoming events. Silent when there are none.
     *
     * @param string $email
     * @return int Number of bookings found
     */
    public static function send_booking_links(string $email): int {
        global $DB;
        $bookings = $DB->get_records_sql("SELECT b.*, e.name AS eventname, e.timestart AS evstart
              FROM {local_evenements_booking} b
              JOIN {local_evenements_event} e ON e.id = b.eventid
             WHERE b.status = 'active' AND " . $DB->sql_equal('b.email', ':email', false, false) . "
               AND e.status <> 'draft' AND e.timestart > :recent
          ORDER BY e.timestart ASC", ['email' => $email, 'recent' => time() - 7 * DAYSECS]);
        if (!$bookings) {
            return 0;
        }
        $lines = [];
        foreach ($bookings as $booking) {
            $lines[] = format_string($booking->eventname) . ' - ' . userdate($booking->evstart) . "\n" .
                self::booking_url($booking)->out(false);
        }
        $first = reset($bookings);
        self::send_text(
            $email,
            $first->contactname,
            get_string('lostticketssubject', 'local_evenements'),
            get_string('lostticketsbody', 'local_evenements', implode("\n\n", $lines))
        );
        return count($bookings);
    }

    // Door operators (app, no Moodle account).

    /**
     * Creates an operator for an event, with a random access code.
     *
     * @param int $eventid
     * @param string $name
     * @return \stdClass
     */
    public static function create_operator(int $eventid, string $name): \stdClass {
        global $DB;
        $op = (object) ['eventid' => $eventid, 'name' => $name, 'code' => self::new_operator_code(),
            'status' => 'active', 'lastseen' => 0, 'timecreated' => time()];
        $op->id = $DB->insert_record('local_evenements_operator', $op);
        return $op;
    }

    /**
     * Random access code: 16 characters without look-alikes (no 0/O, 1/I/L).
     *
     * @return string
     */
    public static function new_operator_code(): string {
        global $DB;
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 16; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($DB->record_exists('local_evenements_operator', ['code' => $code]));
        return $code;
    }

    /**
     * "ABCD-EFGH-JKMN-PQRS" form of a code.
     *
     * @param string $code
     * @return string
     */
    public static function format_operator_code(string $code): string {
        return implode('-', str_split($code, 4));
    }

    /**
     * Operator from the code typed or scanned (dashes, spaces and case are ignored). False when unknown.
     *
     * @param string $raw
     * @return \stdClass|false
     */
    public static function get_operator_by_code(string $raw) {
        global $DB;
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
        if (strlen($code) !== 16) {
            return false;
        }
        return $DB->get_record('local_evenements_operator', ['code' => $code]);
    }

    /**
     * Whether an operator may work now: not revoked, event still within 24 hours after its end.
     *
     * @param \stdClass $operator
     * @param \stdClass $event
     * @return bool
     */
    public static function operator_active(\stdClass $operator, \stdClass $event): bool {
        if ($operator->status !== 'active') {
            return false;
        }
        $end = (int) ($event->timeend ?: $event->timestart + 6 * HOURSECS);
        return time() < $end + DAYSECS;
    }

    /**
     * Label of the ticket's time slot for the door screens ("11:05"), or empty.
     *
     * @param \stdClass $ticket
     * @return string
     */
    public static function ticket_slot_label(\stdClass $ticket): string {
        global $DB;
        $sid = (int) $DB->get_field('local_evenements_booking', 'sessionid', ['id' => $ticket->bookingid]);
        if (!$sid) {
            return '';
        }
        $session = $DB->get_record('local_evenements_session', ['id' => $sid]);
        return $session ? userdate($session->timestart, get_string('strftimetime', 'langconfig')) : '';
    }

    /**
     * Who validated a ticket: operator name or Moodle user name.
     *
     * @param \stdClass $ticket
     * @return string
     */
    public static function validated_by(\stdClass $ticket): string {
        global $DB;
        if (!empty($ticket->operatorid)) {
            return (string) $DB->get_field('local_evenements_operator', 'name', ['id' => $ticket->operatorid]);
        }
        if (!empty($ticket->usedby)) {
            $user = $DB->get_record('user', ['id' => $ticket->usedby]);
            return $user ? fullname($user) : '';
        }
        return '';
    }

    /**
     * Cancels the validation of a ticket (mistake at the door): only by the operator who made it, within 5 minutes.
     *
     * @param \stdClass $ticket
     * @param int $operatorid
     * @return bool
     */
    public static function undo_check_in(\stdClass $ticket, int $operatorid): bool {
        global $DB;
        $fresh = $DB->get_record('local_evenements_ticket', ['id' => $ticket->id]);
        if (
            !$fresh || $fresh->status !== self::TICKET_USED || (int) $fresh->operatorid !== $operatorid ||
                time() - (int) $fresh->timeused > 5 * MINSECS
        ) {
            return false;
        }
        $DB->update_record('local_evenements_ticket', (object) ['id' => $fresh->id, 'status' => self::TICKET_VALID,
            'timeused' => 0, 'usedby' => 0, 'operatorid' => 0]);
        $DB->insert_record('local_evenements_scanlog', (object) ['ticketid' => $fresh->id, 'eventid' => $fresh->eventid,
            'userid' => 0, 'operatorid' => $operatorid, 'result' => 'undone', 'timecreated' => time()]);
        return true;
    }

    /**
     * Live figures of the entrance of an event: entries per operator and the latest entries.
     *
     * @param int $eventid
     * @param int $latest Number of latest entries
     * @return \stdClass ->peroperator (name => count), ->latest (list of name, time, by)
     */
    public static function get_entrance_stats(int $eventid, int $latest = 8): \stdClass {
        global $DB;
        $stats = (object) ['peroperator' => [], 'latest' => []];
        $rows = $DB->get_records_sql("SELECT t.operatorid, t.usedby, COUNT(t.id) AS n
              FROM {local_evenements_ticket} t
             WHERE t.eventid = :e AND t.status = :used
          GROUP BY t.operatorid, t.usedby", ['e' => $eventid, 'used' => self::TICKET_USED]);
        foreach ($rows as $row) {
            $label = $row->operatorid ? (string) $DB->get_field('local_evenements_operator', 'name', ['id' => $row->operatorid]) :
                ($row->usedby ? fullname($DB->get_record('user', ['id' => $row->usedby])) : '-');
            $stats->peroperator[$label] = ($stats->peroperator[$label] ?? 0) + (int) $row->n;
        }
        arsort($stats->peroperator);
        $tickets = $DB->get_records_select(
            'local_evenements_ticket',
            'eventid = :e AND status = :s',
            ['e' => $eventid, 's' => self::TICKET_USED],
            'timeused DESC',
            '*',
            0,
            $latest
        );
        foreach ($tickets as $t) {
            $stats->latest[] = (object) ['name' => trim($t->firstname . ' ' . $t->lastname), 'time' => (int) $t->timeused,
                'by' => self::validated_by($t)];
        }
        return $stats;
    }

    // Invitation list (closed registration).

    /**
     * Category name of a ticket followed by its mention ("Guest of ...").
     *
     * @param \stdClass $ticket
     * @param \stdClass[] $categories
     * @return string
     */
    public static function ticket_label(\stdClass $ticket, array $categories): string {
        $name = !empty($categories[$ticket->tickettypeid]) ? format_string($categories[$ticket->tickettypeid]->name) : '';
        $note = trim((string) ($ticket->note ?? ''));
        return trim($name . ($name !== '' && $note !== '' ? ' · ' : '') . $note);
    }

    /**
     * Information of the category of a ticket, printed in bold (e.g. photo ID required).
     *
     * @param \stdClass $ticket
     * @param \stdClass[] $categories
     * @return string
     */
    public static function ticket_info(\stdClass $ticket, array $categories): string {
        return trim((string) ($categories[$ticket->tickettypeid]->info ?? ''));
    }

    /**
     * Is booking restricted to the invitation list?
     *
     * @param \stdClass $event
     * @return bool
     */
    public static function is_list_event(\stdClass $event): bool {
        return ($event->accessmode ?? 'open') === 'list';
    }

    /**
     * Normalised key of a name: no accents, no case, no punctuation, words sorted
     * (so that "Léa MARTIN" and "martin lea" are the same person).
     *
     * @param string $first
     * @param string $last
     * @return string
     */
    public static function name_key(string $first, string $last): string {
        $text = \core_text::strtolower(\core_text::specialtoascii($first . ' ' . $last));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        sort($words);
        return \core_text::substr(implode(' ', $words), 0, 255);
    }

    /**
     * Ticket category of the event with this name (created when missing, without a quota).
     *
     * @param int $eventid
     * @param string $name
     * @return int Category id
     */
    public static function ensure_category(int $eventid, string $name): int {
        global $DB;
        $name = trim($name);
        foreach (self::get_categories($eventid) as $category) {
            if (\core_text::strtolower(trim($category->name)) === \core_text::strtolower($name)) {
                return (int) $category->id;
            }
        }
        $sort = (int) $DB->get_field_sql("SELECT MAX(sortorder) FROM {local_evenements_tickettype} WHERE eventid = ?", [$eventid]);
        $info = preg_match('/dipl[oô]m/iu', $name) ? get_string('idrequired', 'local_evenements') : '';
        return (int) $DB->insert_record('local_evenements_tickettype', (object) ['eventid' => $eventid,
            'name' => \core_text::substr($name, 0, 255), 'quota' => 0, 'info' => $info, 'sortorder' => $sort + 1]);
    }

    /**
     * Add a person to the invitation list.
     *
     * @param \stdClass $event
     * @param string $first
     * @param string $last
     * @param string $email
     * @param string $category Ticket category name (Diplômé, Partenaire...)
     * @param string $detail Diploma, company...
     * @param int|null $quota Number of guests (null = default of the event)
     * @return \stdClass|null The invitee, or null when it is already in the list
     */
    public static function add_invitee(
        \stdClass $event,
        string $first,
        string $last,
        string $email,
        string $category,
        string $detail = '',
        ?int $quota = null
    ): ?\stdClass {
        global $DB;
        $key = self::name_key($first, $last);
        foreach ($DB->get_records('local_evenements_invitee', ['eventid' => $event->id, 'namekey' => $key]) as $existing) {
            if (\core_text::strtolower($existing->email) === \core_text::strtolower($email)) {
                return null;
            }
        }
        $quota = $quota === null ? (int) $event->guestquota : max(0, min(20, $quota));
        $invitee = (object) [
            'eventid' => $event->id,
            'firstname' => \core_text::substr($first, 0, 100),
            'lastname' => \core_text::substr($last, 0, 100),
            'namekey' => $key,
            'email' => \core_text::substr($email, 0, 255),
            'tickettypeid' => self::ensure_category((int) $event->id, $category !== '' ? $category : get_string(
                'invcategorydefault',
                'local_evenements'
            )),
            'detail' => \core_text::substr($detail, 0, 255),
            'quota' => $quota,
            'token' => self::new_token(),
            'bookingid' => 0, 'timeinvited' => 0, 'timeconfirmed' => 0, 'timereported' => 0,
            'timecreated' => time(),
        ];
        $invitee->id = $DB->insert_record('local_evenements_invitee', $invitee);
        return $invitee;
    }

    /**
     * Import lines "LAST ; First ; e-mail ; Category ; Detail ; Guests" (semicolon or tab, one person per line).
     *
     * @param \stdClass $event
     * @param string $text
     * @return array [added, duplicates, errors (list of messages)]
     */
    public static function import_invitees(\stdClass $event, string $text): array {
        $added = 0;
        $dups = 0;
        $errors = [];
        $lineno = 0;
        foreach (preg_split('/\R/u', $text) as $line) {
            $lineno++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cols = array_map('trim', preg_split('/\s*[;\t]\s*/u', $line));
            // A header line is ignored.
            if ($lineno === 1 && !empty($cols[2]) && strpos($cols[2], '@') === false) {
                continue;
            }
            if (count($cols) < 3 || $cols[0] === '' || $cols[1] === '' || !validate_email($cols[2])) {
                $errors[] = get_string('invimporterror', 'local_evenements', (object) ['line' => $lineno, 'text' => s(
                    \core_text::substr($line, 0, 60)
                )]);
                continue;
            }
            $quota = (isset($cols[5]) && $cols[5] !== '' && ctype_digit($cols[5])) ? (int) $cols[5] : null;
            $invitee = self::add_invitee(
                $event,
                clean_param($cols[1], PARAM_TEXT),
                clean_param($cols[0], PARAM_TEXT),
                clean_param($cols[2], PARAM_EMAIL),
                clean_param($cols[3] ?? '', PARAM_TEXT),
                clean_param($cols[4] ?? '', PARAM_TEXT),
                $quota
            );
            if ($invitee) {
                $added++;
            } else {
                $dups++;
            }
        }
        return [$added, $dups, $errors];
    }

    /**
     * Personal link of an invitee.
     *
     * @param \stdClass $invitee
     * @return \moodle_url
     */
    public static function invitee_url(\stdClass $invitee): \moodle_url {
        return new \moodle_url('/local/evenements/invite.php', ['t' => $invitee->token]);
    }

    /**
     * Status of an invitee: new, invited, confirmed or booked.
     *
     * @param \stdClass $invitee
     * @return string
     */
    public static function invitee_status(\stdClass $invitee): string {
        if ($invitee->bookingid) {
            return 'booked';
        }
        if ($invitee->timeconfirmed) {
            return 'confirmed';
        }
        return $invitee->timeinvited ? 'invited' : 'new';
    }

    /**
     * E-mail the personal link to the address of the list.
     *
     * @param \stdClass $invitee
     * @param \stdClass $event
     * @return bool
     */
    public static function send_invitation(\stdClass $invitee, \stdClass $event): bool {
        global $DB;
        $a = (object) [
            'name' => $invitee->firstname,
            'event' => format_string($event->name),
            'date' => userdate($event->timestart),
            'place' => (string) $event->location,
            'url' => self::invitee_url($invitee)->out(false),
            'guests' => (int) $invitee->quota,
        ];
        $sent = self::send_text(
            $invitee->email,
            trim($invitee->firstname . ' ' . $invitee->lastname),
            get_string('invitemailsubject', 'local_evenements', $a),
            get_string('invitemailbody', 'local_evenements', $a)
        );
        if ($sent) {
            $DB->set_field('local_evenements_invitee', 'timeinvited', time(), ['id' => $invitee->id]);
        }
        return $sent;
    }

    /**
     * Give a person a new link (the old one stops working) and, when asked, cancel the booking
     * so that the person can start again.
     *
     * @param \stdClass $invitee
     * @param bool $cancelbooking
     */
    public static function reset_invitee(\stdClass $invitee, bool $cancelbooking = false): void {
        global $DB;
        if ($cancelbooking && $invitee->bookingid) {
            $booking = $DB->get_record('local_evenements_booking', ['id' => $invitee->bookingid]);
            if ($booking) {
                self::cancel_booking($booking);
            }
            $DB->set_field('local_evenements_invitee', 'bookingid', 0, ['id' => $invitee->id]);
            $DB->set_field('local_evenements_invitee', 'timeconfirmed', 0, ['id' => $invitee->id]);
        }
        $DB->set_field('local_evenements_invitee', 'token', self::new_token(), ['id' => $invitee->id]);
        $DB->set_field('local_evenements_invitee', 'timereported', 0, ['id' => $invitee->id]);
    }

    /**
     * Names that appear on several active tickets of the event (possible duplicates).
     *
     * @param int $eventid
     * @return \stdClass[] rows with names (text) and total
     */
    public static function duplicate_names(int $eventid): array {
        global $DB;
        $key = [];
        foreach (
            $DB->get_records_select(
                'local_evenements_ticket',
                'eventid = ? AND status <> ?',
                [$eventid, self::TICKET_CANCELLED],
                '',
                'id, firstname, lastname, bookingid'
            ) as $t
        ) {
            $key[self::name_key($t->firstname, $t->lastname)][] = $t;
        }
        $out = [];
        foreach ($key as $k => $tickets) {
            if (count($tickets) > 1) {
                $out[] = (object) ['name' => trim($tickets[0]->firstname . ' ' . $tickets[0]->lastname), 'total' => count(
                    $tickets
                )];
            }
        }
        return $out;
    }
}
