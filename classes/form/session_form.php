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
 * Form to add or edit a time slot.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('sessionname', 'local_evenements'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('date_time_selector', 'timestart', get_string('sessionstart', 'local_evenements'));
        $mform->addElement('date_time_selector', 'timeend', get_string('sessionend', 'local_evenements'), ['optional' => true]);
        $mform->addElement('text', 'capacity', get_string('sessioncapacity', 'local_evenements'), ['size' => 6]);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);
        $mform->addHelpButton('capacity', 'sessioncapacity', 'local_evenements');
        $mform->addElement('text', 'repeatcount', get_string('sessionrepeat', 'local_evenements'), ['size' => 4]);
        $mform->setType('repeatcount', PARAM_INT);
        $mform->setDefault('repeatcount', 1);
        $mform->addHelpButton('repeatcount', 'sessionrepeat', 'local_evenements');
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Extra validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['timeend']) && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('errorenddate', 'local_evenements');
        }
        if ((int) $data['capacity'] < 0) {
            $errors['capacity'] = get_string('errornumber', 'local_evenements');
        }
        return $errors;
    }
}
