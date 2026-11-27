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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps of the plugin: pages of an event, found by its name.
 *
 * @package    local_evenements
 * @category   test
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_evenements extends behat_base {
    /**
     * Pages without an instance: "I am on the "local_evenements > Manage" page".
     *
     * @param string $page
     * @return moodle_url
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'manage':
                return new moodle_url('/local/evenements/manage.php');
            case 'public list':
                return new moodle_url('/local/evenements/index.php');
            default:
                throw new Exception('Unknown page "' . $page . '".');
        }
    }

    /**
     * Pages of an event: "I am on the "Open day" "local_evenements > Booking" page".
     *
     * @param string $type event, booking, registrations, dashboard, report
     * @param string $identifier Name of the event
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;
        $id = (int) $DB->get_field('local_evenements_event', 'id', ['name' => $identifier], MUST_EXIST);
        switch (strtolower($type)) {
            case 'event':
                return new moodle_url('/local/evenements/event.php', ['id' => $id]);
            case 'booking':
                return new moodle_url('/local/evenements/book.php', ['id' => $id, 'n' => 1]);
            case 'registrations':
                return new moodle_url('/local/evenements/registrations.php', ['id' => $id]);
            case 'dashboard':
                return new moodle_url('/local/evenements/dashboard.php', ['id' => $id]);
            case 'report':
                return new moodle_url('/local/evenements/report.php', ['id' => $id]);
            default:
                throw new Exception('Unknown page type "' . $type . '".');
        }
    }
}
