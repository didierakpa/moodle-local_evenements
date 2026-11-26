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
 * Tests of the participant management: add, edit, cancel, restore, delete, bulk actions, change log.
 *
 * @package    local_evenements
 * @category   test
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_evenements\participant_manager
 */
final class participant_manager_test extends \advanced_testcase {

    /**
     * New open event.
     *
     * @param int $capacity
     * @param array $extra More fields
     * @return \stdClass
     */
    protected function make_event(int $capacity, array $extra = []): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record('local_evenements_event', (object) ($extra + [
            'name' => 'Open day', 'description' => '', 'descriptionformat' => FORMAT_HTML, 'location' => 'School',
            'timestart' => $now + 10 * DAYSECS, 'timeend' => 0, 'capacity' => $capacity, 'maxperbooking' => 5,
            'status' => booking_manager::STATUS_OPEN, 'timecreated' => $now, 'timemodified' => $now,
        ]));
        return booking_manager::get_event($id);
    }

    /**
     * Ticket of a booking.
     *
     * @param \stdClass $booking
     * @return \stdClass
     */
    protected function ticket_of(\stdClass $booking): \stdClass {
        global $DB;
        return $DB->get_record('local_evenements_ticket', ['bookingid' => $booking->id], '*', MUST_EXIST);
    }

    /**
     * Fresh copy of a ticket.
     *
     * @param int $id
     * @return \stdClass
     */
    protected function reload(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_evenements_ticket', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Adding by hand follows the capacity and the category quotas, unless forced.
     */
    public function test_add_participant_limits(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $event = $this->make_event(3);
        booking_manager::save_categories((int) $event->id, ['Student|2', 'Parent|0']);
        [$student, $parent] = array_keys(booking_manager::get_categories((int) $event->id));

        $booking = participant_manager::add_participant($event, (object) ['firstname' => 'Lea', 'lastname' => 'Martin',
            'email' => 'lea@example.com', 'tickettypeid' => $student]);
        $this->assertEquals('Lea Martin', $booking->contactname);
        $this->assertEquals(booking_manager::TICKET_VALID, $this->ticket_of($booking)->status);
        participant_manager::add_participant($event, (object) ['firstname' => 'Tom', 'lastname' => 'Durand',
            'tickettypeid' => $student]);

        try {
            participant_manager::add_participant($event, (object) ['firstname' => 'X', 'lastname' => 'Y',
                'tickettypeid' => $student]);
            $this->fail('The category quota should be enforced.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('changeproblem', $e->errorcode);
        }
        participant_manager::add_participant($event, (object) ['firstname' => 'Ana', 'lastname' => 'Petit',
            'tickettypeid' => $parent]);
        try {
            participant_manager::add_participant($event, (object) ['firstname' => 'Z', 'lastname' => 'Z',
                'tickettypeid' => $parent]);
            $this->fail('The capacity should be enforced.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('changeproblem', $e->errorcode);
        }
        participant_manager::add_participant($event, (object) ['firstname' => 'Forced', 'lastname' => 'Guest',
            'tickettypeid' => $parent], true);
        $this->assertEquals(4, booking_manager::count_tickets((int) $event->id));
    }

    /**
     * Editing lists the changes and writes them in the change log.
     */
    public function test_update_ticket(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $event = $this->make_event(10);
        $ticket = $this->ticket_of(participant_manager::add_participant($event, (object) ['firstname' => 'Lea',
            'lastname' => 'Martin', 'email' => 'lea@example.com']));

        $changes = participant_manager::update_ticket($event, $ticket, (object) ['firstname' => 'Lea',
            'lastname' => 'Martin-Dupont', 'note' => 'Final year', 'contactname' => 'Mrs Martin']);
        $this->assertCount(3, $changes);
        $this->assertEquals('Martin-Dupont', $this->reload((int) $ticket->id)->lastname);
        $this->assertSame([], participant_manager::update_ticket($event, $this->reload((int) $ticket->id),
            (object) ['lastname' => 'Martin-Dupont']));

        $log = participant_manager::get_log((int) $event->id, 0, (int) $ticket->id);
        $actions = array_map(function ($row) {
            return $row->action;
        }, array_values($log));
        $this->assertEquals(['edit', 'add'], $actions);
    }

    /**
     * Cancelling frees the place and is refused at the door; restoring needs a free place.
     */
    public function test_cancel_and_restore(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $event = $this->make_event(1);
        $booking = participant_manager::add_participant($event, (object) ['firstname' => 'Lea', 'lastname' => 'Martin']);
        $ticket = $this->ticket_of($booking);

        $this->assertTrue(participant_manager::cancel_ticket($event, $ticket));
        $this->assertEquals('cancelled', $DB->get_field('local_evenements_booking', 'status', ['id' => $booking->id]));
        $this->assertFalse(participant_manager::cancel_ticket($event, $this->reload((int) $ticket->id)));
        $this->assertEquals('refused', booking_manager::check_in($this->reload((int) $ticket->id), (int) $USER->id));

        // The place is taken by someone else: no restore.
        participant_manager::add_participant($event, (object) ['firstname' => 'Tom', 'lastname' => 'Durand']);
        try {
            participant_manager::restore_ticket($event, $this->reload((int) $ticket->id));
            $this->fail('No place left: the ticket cannot be restored.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('changeproblem', $e->errorcode);
        }
        $this->assertTrue(participant_manager::restore_ticket($event, $this->reload((int) $ticket->id), true));
        $this->assertEquals(booking_manager::TICKET_VALID, $this->reload((int) $ticket->id)->status);
        $this->assertEquals('active', $DB->get_field('local_evenements_booking', 'status', ['id' => $booking->id]));
    }

    /**
     * A used ticket that is cancelled then restored is still used.
     */
    public function test_restore_used_ticket(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $event = $this->make_event(5);
        $ticket = $this->ticket_of(participant_manager::add_participant($event, (object) ['firstname' => 'A', 'lastname' => 'B']));
        $this->assertEquals('accepted', booking_manager::check_in($ticket, (int) $USER->id));
        participant_manager::cancel_ticket($event, $this->reload((int) $ticket->id));
        participant_manager::restore_ticket($event, $this->reload((int) $ticket->id));
        $this->assertEquals(booking_manager::TICKET_USED, $this->reload((int) $ticket->id)->status);
    }

    /**
     * Deleting for good removes the ticket, its scans, its booking and the name from the log.
     */
    public function test_delete_ticket(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $event = $this->make_event(5);
        $booking = participant_manager::add_participant($event, (object) ['firstname' => 'Ana', 'lastname' => 'Petit']);
        $ticket = $this->ticket_of($booking);
        booking_manager::check_in($ticket, (int) $USER->id);

        participant_manager::delete_ticket($event, $this->reload((int) $ticket->id));
        $this->assertFalse($DB->record_exists('local_evenements_ticket', ['id' => $ticket->id]));
        $this->assertFalse($DB->record_exists('local_evenements_booking', ['id' => $booking->id]));
        $this->assertFalse($DB->record_exists('local_evenements_scanlog', ['ticketid' => $ticket->id]));
        $this->assertFalse($DB->record_exists_select('local_evenements_changelog',
            'ticketid = ? AND ' . $DB->sql_like('details', '?'), [$ticket->id, '%Petit%']));
    }

    /**
     * Bulk actions report what was done and respect the quotas.
     */
    public function test_bulk(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $event = $this->make_event(10);
        booking_manager::save_categories((int) $event->id, ['Student|1', 'Parent|0']);
        [$student, $parent] = array_keys(booking_manager::get_categories((int) $event->id));
        $t1 = $this->ticket_of(participant_manager::add_participant($event, (object) ['firstname' => 'A', 'lastname' => 'A',
            'tickettypeid' => $student]));
        $t2 = $this->ticket_of(participant_manager::add_participant($event, (object) ['firstname' => 'B', 'lastname' => 'B',
            'tickettypeid' => $parent]));

        $report = participant_manager::bulk($event, [$t1->id, $t2->id, 999999], 'cancel');
        $this->assertEquals(2, $report->done);
        $report = participant_manager::bulk($event, [$t1->id, $t2->id], 'restore');
        $this->assertEquals(2, $report->done);
        $report = participant_manager::bulk($event, [$t2->id], 'category', $student);
        $this->assertEquals(1, $report->failed);
        $this->assertNotEmpty($report->messages);
    }

    /**
     * For a list event, cancelling frees the person of the list and restoring links them back.
     */
    public function test_list_event_invitee(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $event = $this->make_event(0, ['accessmode' => 'list', 'guestquota' => 2]);
        $invitee = booking_manager::add_invitee($event, 'Sam', 'Roux', 'sam@example.com', 'Graduate');
        $booking = booking_manager::create_booking($event, 'Sam Roux', 'sam@example.com', 0,
            [['firstname' => 'Sam', 'lastname' => 'Roux', 'tickettypeid' => $invitee->tickettypeid]], '127.0.0.1', [], 0, $invitee);
        $ticket = $this->ticket_of($booking);

        participant_manager::cancel_ticket($event, $ticket);
        $this->assertEquals(0, $DB->get_field('local_evenements_invitee', 'bookingid', ['id' => $invitee->id]));
        participant_manager::restore_ticket($event, $this->reload((int) $ticket->id));
        $this->assertEquals($booking->id, $DB->get_field('local_evenements_invitee', 'bookingid', ['id' => $invitee->id]));
    }
}
