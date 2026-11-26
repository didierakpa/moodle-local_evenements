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

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Tests of adding people from Moodle (cohort or course).
 *
 * @package    local_evenements
 * @category   test
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_evenements\moodle_import
 */
final class moodle_import_test extends \advanced_testcase {
    /**
     * New open event.
     *
     * @param int $capacity
     * @param array $extra
     * @return \stdClass
     */
    protected function make_event(int $capacity, array $extra = []): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record('local_evenements_event', (object) ($extra + [
            'name' => 'Trip', 'description' => '', 'descriptionformat' => FORMAT_HTML, 'location' => '',
            'timestart' => $now + 10 * DAYSECS, 'timeend' => 0, 'capacity' => $capacity, 'maxperbooking' => 5,
            'status' => booking_manager::STATUS_OPEN, 'timecreated' => $now, 'timemodified' => $now,
        ]));
        return booking_manager::get_event($id);
    }

    /**
     * Cohort with three active members and one suspended, and a course with two students and a teacher.
     *
     * @return array [cohortid, course, users, teacher]
     */
    protected function make_people(): array {
        $gen = $this->getDataGenerator();
        $users = [];
        for ($i = 1; $i <= 4; $i++) {
            $users[$i] = $gen->create_user(['firstname' => 'Student' . $i, 'lastname' => 'Class']);
        }
        $suspended = $gen->create_user(['suspended' => 1]);
        $teacher = $gen->create_user(['firstname' => 'Teacher', 'lastname' => 'X']);
        $cohort = $gen->create_cohort();
        foreach ([$users[1], $users[2], $users[3], $suspended] as $user) {
            cohort_add_member($cohort->id, $user->id);
        }
        $course = $gen->create_course();
        $gen->enrol_user($users[3]->id, $course->id, 'student');
        $gen->enrol_user($users[4]->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        return [$cohort->id, $course, $users, $teacher];
    }

    /**
     * Reading the people of a cohort or a course.
     */
    public function test_people(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$cohortid, $course] = $this->make_people();
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);

        $this->assertCount(3, moodle_import::people('cohort', $cohortid));
        $this->assertCount(2, moodle_import::people('course', $course->id, $student));
        $this->assertCount(3, moodle_import::people('course', $course->id, 0));
        $this->assertStringContainsString('(3)', moodle_import::cohort_options()[$cohortid]);
    }

    /**
     * Adding as participants: tickets linked to the accounts, people with a ticket skipped, capacity respected.
     */
    public function test_add_participants(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$cohortid, $course, $users, $teacher] = $this->make_people();
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $event = $this->make_event(4);

        $report = moodle_import::add($event, moodle_import::people('cohort', $cohortid), (object) ['sourcename' => 'Cohort']);
        $this->assertEquals(3, $report->added);
        $this->assertEquals($users[1]->id, $DB->get_field(
            'local_evenements_booking',
            'userid',
            ['eventid' => $event->id, 'email' => $users[1]->email]
        ));

        $report = moodle_import::add($event, moodle_import::people('course', $course->id, $student), (object) []);
        $this->assertEquals(1, $report->added);
        $this->assertEquals(1, $report->skipped);

        $report = moodle_import::add($event, [$teacher], (object) []);
        $this->assertEquals(0, $report->added);
        $this->assertEquals(1, $report->failed);
    }

    /**
     * Adding to the invitation list of a list event.
     */
    public function test_add_to_list(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$cohortid] = $this->make_people();
        $event = $this->make_event(0, ['accessmode' => 'list', 'guestquota' => 2]);
        $people = moodle_import::people('cohort', $cohortid);

        $report = moodle_import::add($event, $people, (object) ['category' => 'Graduate', 'detail' => 'Class of 2027']);
        $this->assertEquals(3, $report->added);
        $this->assertEquals(3, $DB->count_records('local_evenements_invitee', ['eventid' => $event->id]));
        $report = moodle_import::add($event, $people, (object) ['category' => 'Graduate']);
        $this->assertEquals(3, $report->skipped);
    }
}
