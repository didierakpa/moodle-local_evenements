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
 * Hook callbacks of the plugin.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Adds a single "Events" entry to the main navigation bar (with "Manage events" for managers), according to the menu setting.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        // 0 = hidden, 1 = managers only (default, while the plugin is being developed), 2 = everybody.
        $show = get_config('local_evenements', 'menuentry');
        $show = ($show === false || $show === null || $show === '') ? 1 : (int) $show;
        if ($show === 0) {
            return;
        }
        $ismanager = has_capability('local/evenements:manage', \context_system::instance()) ||
            (isloggedin() && !isguestuser() && !empty(booking_manager::organiser_event_ids()));
        $view = $hook->get_primaryview();
        if (!$ismanager && $show !== 2) {
            return;
        }
        // A single "Events" entry. Managers get a drop-down with the public list and "Manage events".
        $node = $view->add(
            get_string('events', 'local_evenements'),
            $ismanager ? null : new \moodle_url('/local/evenements/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_evenements_public'
        );
        if ($ismanager) {
            $node->add(
                get_string('allevents', 'local_evenements'),
                new \moodle_url('/local/evenements/index.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_evenements_list'
            );
            $node->add(
                get_string('manageevents', 'local_evenements'),
                new \moodle_url('/local/evenements/manage.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_evenements_manage'
            );
        }
    }
}
