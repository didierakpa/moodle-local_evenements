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
 * Behat data generator: "the following "local_evenements > events" exist".
 *
 * @package    local_evenements
 * @category   test
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_evenements_generator extends behat_generator_base {
    /**
     * Entities that scenarios can create.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'events' => [
                'singular' => 'event',
                'datagenerator' => 'event',
                'required' => ['name'],
            ],
            'participants' => [
                'singular' => 'participant',
                'datagenerator' => 'participant',
                'required' => ['event', 'firstname', 'lastname'],
                'switchids' => ['event' => 'eventid'],
            ],
        ];
    }

    /**
     * Id of an event from its name.
     *
     * @param string $name
     * @return int
     */
    protected function get_event_id(string $name): int {
        global $DB;
        return (int) $DB->get_field('local_evenements_event', 'id', ['name' => $name], MUST_EXIST);
    }
}
