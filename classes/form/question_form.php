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
 * Form to add or edit a question of an event.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'q');
        $mform->setType('q', PARAM_INT);

        $mform->addElement('text', 'name', get_string('questiontext', 'local_evenements'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('select', 'moment', get_string('moment', 'local_evenements'), [
            'before' => get_string('moment_before', 'local_evenements'),
            'after' => get_string('moment_after', 'local_evenements'),
        ]);

        $mform->addElement('select', 'scope', get_string('scope', 'local_evenements'), [
            'booking' => get_string('scope_booking', 'local_evenements'),
            'guest' => get_string('scope_guest', 'local_evenements'),
        ]);
        $mform->hideIf('scope', 'moment', 'eq', 'after');

        $mform->addElement('select', 'type', get_string('type', 'local_evenements'), [
            'text' => get_string('type_text', 'local_evenements'),
            'longtext' => get_string('type_longtext', 'local_evenements'),
            'choice' => get_string('type_choice', 'local_evenements'),
            'multichoice' => get_string('type_multichoice', 'local_evenements'),
        ]);

        $mform->addElement(
            'textarea',
            'options',
            get_string('questionoptions', 'local_evenements'),
            ['rows' => 5, 'cols' => 50]
        );
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'questionoptions', 'local_evenements');
        $mform->hideIf('options', 'type', 'in', ['text', 'longtext']);

        $mform->addElement('advcheckbox', 'isrequired', get_string('isrequired', 'local_evenements'));

        $this->add_action_buttons(true, get_string('savequestion', 'local_evenements'));
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
        if ($data['type'] === 'choice' || $data['type'] === 'multichoice') {
            $lines = array_filter(array_map('trim', preg_split('/\R/', (string) $data['options'])), 'strlen');
            if (count($lines) < 2) {
                $errors['options'] = get_string('errorminoptions', 'local_evenements');
            }
        }
        return $errors;
    }
}
