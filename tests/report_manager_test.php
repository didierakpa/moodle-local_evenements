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
 * Tests of the event report and of the anonymous figures kept after the purge.
 *
 * @package    local_evenements
 * @category   test
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_evenements\report_manager
 */
final class report_manager_test extends \advanced_testcase {
    /**
     * Event that took place two hours ago, with five tickets: three used, one twice, one cancelled.
     *
     * @return array [event, tickets]
     */
    protected function make_past_event(): array {
        global $DB, $USER;
        $now = time();
        $id = $DB->insert_record('local_evenements_event', (object) [
            'name' => 'Open day 2026', 'description' => '', 'descriptionformat' => FORMAT_HTML, 'location' => '',
            'timestart' => $now - 2 * HOURSECS, 'timeend' => 0, 'capacity' => 10, 'maxperbooking' => 5,
            'status' => booking_manager::STATUS_OPEN, 'timecreated' => $now - 30 * DAYSECS, 'timemodified' => $now,
        ]);
        $event = booking_manager::get_event($id);
        booking_manager::save_categories($id, ['Student|0', 'Parent|0']);
        [$c1, $c2] = array_keys(booking_manager::get_categories($id));
        $tickets = [];
        foreach ([['A', $c1, 20], ['B', $c1, 15], ['C', $c2, 10], ['D', $c2, 5], ['E', $c2, 1]] as [$name, $cat, $days]) {
            $booking = participant_manager::add_participant($event, (object) ['firstname' => $name, 'lastname' => 'Test',
                'tickettypeid' => $cat]);
            $ticket = $DB->get_record('local_evenements_ticket', ['bookingid' => $booking->id]);
            $DB->set_field('local_evenements_ticket', 'timecreated', $event->timestart - $days * DAYSECS, ['id' => $ticket->id]);
            $tickets[] = $ticket;
        }
        foreach ([0, 2, 3, 0] as $n) {
            booking_manager::check_in($DB->get_record('local_evenements_ticket', ['id' => $tickets[$n]->id]), (int) $USER->id);
        }
        participant_manager::cancel_ticket($event, $DB->get_record('local_evenements_ticket', ['id' => $tickets[4]->id]));
        return [$event, $tickets];
    }

    /**
     * Figures computed from the live data.
     */
    public function test_compute(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$event] = $this->make_past_event();
        $r = report_manager::compute((int) $event->id);

        $this->assertEquals(4, $r->booked);
        $this->assertEquals(3, $r->attended);
        $this->assertEquals(1, $r->cancelled);
        $this->assertEquals(2, $r->categories[0]['booked']);
        $this->assertEquals(1, $r->categories[0]['attended']);
        $this->assertEquals(2, $r->categories[1]['attended']);
        $this->assertEquals(3, array_sum($r->arrivals));
        $this->assertEquals(1, $r->alreadyused);
        $this->assertEquals(fullname($USER), $r->operators[0]['name']);
        $this->assertEquals(3, $r->operators[0]['count']);
        $this->assertEquals([0, 3, 4], [$r->curve[60], $r->curve[7], $r->curve[0]]);
        $this->assertEquals(75, report_manager::percent($r->attended, $r->booked));
        $this->assertNull(report_manager::percent(1, 0));
    }

    /**
     * The purge keeps anonymous figures, and the next edition finds them.
     */
    public function test_snapshot_and_previous_edition(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$event] = $this->make_past_event();
        $nextid = booking_manager::duplicate_event((int) $event->id);
        $next = booking_manager::get_event($nextid);
        $this->assertEquals($event->id, $next->sourceid);

        booking_manager::delete_personal_data((int) $event->id);
        $this->assertFalse($DB->record_exists('local_evenements_ticket', ['eventid' => $event->id]));
        $saved = report_manager::get((int) $event->id);
        $this->assertTrue($saved->snapshot);
        $this->assertEquals(4, $saved->booked);
        $this->assertEquals(3, $saved->attended);
        $this->assertEquals(get_string('reportoperatorn', 'local_evenements', 1), $saved->operators[0]['name']);
        $this->assertEquals(3, (int) ($saved->curve[7] ?? $saved->curve['7']));

        // A second purge does not lose the figures.
        booking_manager::delete_personal_data((int) $event->id);
        $this->assertEquals(4, report_manager::get((int) $event->id)->booked);
        $this->assertEquals($event->id, report_manager::previous($next)->id);

        booking_manager::delete_event((int) $event->id);
        $this->assertFalse($DB->record_exists('local_evenements_report', ['eventid' => $event->id]));
        $this->assertEquals(0, $DB->get_field('local_evenements_event', 'sourceid', ['id' => $nextid]));
    }
}
