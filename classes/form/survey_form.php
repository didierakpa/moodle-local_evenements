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
 * Satisfaction survey sent after the event.
 *
 * Custom data: questions (the "after" questions of the event).
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class survey_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'b', $this->_customdata['token']);
        $mform->setType('b', PARAM_ALPHANUM);

        foreach ($this->_customdata['questions'] as $question) {
            question_elements::add($mform, 'q_' . $question->id, $question);
        }

        $this->add_action_buttons(false, get_string('sendanswers', 'local_evenements'));
    }

    /**
     * Check the required questions.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach ($this->_customdata['questions'] as $question) {
            $name = 'q_' . $question->id;
            $error = question_elements::validate($data, $name, $question);
            if ($error !== null) {
                $errors[$name] = $error;
            }
        }
        return $errors;
    }
}
