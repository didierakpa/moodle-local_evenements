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

namespace local_evenements\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to import a CSV list of guests.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('csvfile', 'local_evenements'),
            null,
            ['accepted_types' => ['.csv', '.txt']]
        );
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $sessions = $this->_customdata['sessions'] ?? [];
        if ($sessions) {
            $options = [];
            foreach ($sessions as $session) {
                $options[$session->id] = \local_evenements\booking_manager::session_label($session);
            }
            $mform->addElement('select', 'sessionid', get_string('session', 'local_evenements'), $options);
        }
        $mform->addElement('advcheckbox', 'sendmail', get_string('importsendmail', 'local_evenements'));
        $mform->setDefault('sendmail', 1);
        $this->add_action_buttons(true, get_string('importguests', 'local_evenements'));
    }
}
