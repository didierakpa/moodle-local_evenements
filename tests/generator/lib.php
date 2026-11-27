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

use local_evenements\booking_manager;
use local_evenements\participant_manager;

/**
 * Test data generator: events and participants.
 *
 * @package    local_evenements
 * @category   test
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_evenements_generator extends component_generator_base {
    /** @var int Number of events created, for default names. */
    protected $eventcount = 0;

    /**
     * Reset the counters between tests.
     */
    public function reset() {
        $this->eventcount = 0;
    }

    /**
     * Create an event. Categories may be given as "Name|quota" lines separated by commas.
     *
     * @param array|stdClass $record
     * @return stdClass
     */
    public function create_event($record = null): stdClass {
        global $DB;
        $record = (array) $record;
        $this->eventcount++;
        $now = time();
        $categories = $record['categories'] ?? '';
        unset($record['categories']);
        $event = (object) ($record + [
            'name' => 'Event ' . $this->eventcount,
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'location' => 'Main hall',
            'timestart' => $now + 7 * DAYSECS,
            'timeend' => 0,
            'capacity' => 100,
            'maxperbooking' => 5,
            'status' => booking_manager::STATUS_OPEN,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $event->id = $DB->insert_record('local_evenements_event', $event);
        if ($categories !== '') {
            booking_manager::save_categories((int) $event->id, array_map('trim', explode(',', $categories)));
        }
        return booking_manager::get_event((int) $event->id);
    }

    /**
     * Add a participant (one booking with one ticket) to an event.
     *
     * @param array|stdClass $record eventid, firstname, lastname, email, category (name)
     * @return stdClass The booking
     */
    public function create_participant($record): stdClass {
        global $USER;
        $record = (array) $record;
        $event = booking_manager::get_event((int) $record['eventid']);
        $typeid = 0;
        if (!empty($record['category'])) {
            foreach (booking_manager::get_categories((int) $event->id) as $category) {
                if ($category->name === $record['category']) {
                    $typeid = (int) $category->id;
                }
            }
        }
        $previous = $USER;
        \core\session\manager::set_user(get_admin());
        $booking = participant_manager::add_participant($event, (object) [
            'firstname' => $record['firstname'],
            'lastname' => $record['lastname'],
            'email' => $record['email'] ?? '',
            'tickettypeid' => $typeid,
        ], true);
        \core\session\manager::set_user($previous);
        return $booking;
    }
}
