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
 * General settings of the plugin, edited from the organiser space (no need to go through the site administration).
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'menuh', get_string('menuentry', 'local_evenements'));
        $mform->addElement('select', 'menuentry', get_string('menuentry', 'local_evenements'), [
            0 => get_string('menuentry_none', 'local_evenements'),
            1 => get_string('menuentry_managers', 'local_evenements'),
            2 => get_string('menuentry_everyone', 'local_evenements'),
        ]);
        $mform->addElement('static', 'menuhelp', '', get_string('menuentry_desc', 'local_evenements'));

        $mform->addElement('header', 'emailsh', get_string('prefemails', 'local_evenements'));
        $mform->setExpanded('emailsh', true);
        $mform->addElement('text', 'reminderhours', get_string('reminderhours', 'local_evenements'), ['size' => 4]);
        $mform->setType('reminderhours', PARAM_INT);
        $mform->addElement('static', 'reminderhelp', '', get_string('reminderhours_desc', 'local_evenements'));
        $mform->addElement('text', 'editdeadlinehours', get_string('editdeadlinehours', 'local_evenements'), ['size' => 4]);
        $mform->setType('editdeadlinehours', PARAM_INT);
        $mform->addElement('static', 'editdeadlinehelp', '', get_string('editdeadlinehours_desc', 'local_evenements'));
        $mform->addElement('advcheckbox', 'surveyemail', get_string('surveyemail', 'local_evenements'));
        $mform->addElement('static', 'surveyhelp', '', get_string('surveyemail_desc', 'local_evenements'));

        $mform->addElement('header', 'privacyh', get_string('prefprivacy', 'local_evenements'));
        $mform->setExpanded('privacyh', true);
        $mform->addElement('text', 'retentiondays', get_string('retentiondays', 'local_evenements'), ['size' => 4]);
        $mform->setType('retentiondays', PARAM_INT);
        $mform->addElement('text', 'ratelimit', get_string('ratelimit', 'local_evenements'), ['size' => 4]);
        $mform->setType('ratelimit', PARAM_INT);
        $mform->addElement('textarea', 'privacynotice', get_string('privacynotice', 'local_evenements'),
            ['rows' => 6, 'cols' => 60]);
        $mform->setType('privacynotice', PARAM_TEXT);

        $this->add_action_buttons(true);
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['reminderhours' => [0, 720], 'retentiondays' => [0, 3650], 'ratelimit' => [0, 1000]] as $key => [$min, $max]) {
            if ((int) $data[$key] < $min || (int) $data[$key] > $max) {
                $errors[$key] = get_string('errorrange', 'local_evenements', (object) ['min' => $min, 'max' => $max]);
            }
        }
        return $errors;
    }
}
