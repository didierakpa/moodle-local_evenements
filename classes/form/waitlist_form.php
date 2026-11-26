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
 * Waiting list form, shown when an event is full.
 *
 * Custom data: event.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class waitlist_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $event = $this->_customdata['event'];

        $mform->addElement('hidden', 'id', $event->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'contactname', get_string('contactname', 'local_evenements'), ['size' => 40]);
        $mform->setType('contactname', PARAM_TEXT);
        $mform->addRule('contactname', null, 'required', null, 'client');

        $mform->addElement('text', 'email', get_string('contactemail', 'local_evenements'), ['size' => 40]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required', null, 'client');

        $max = max(1, (int) $event->maxperbooking);
        $options = [];
        for ($i = 1; $i <= $max; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'quantity', get_string('waitlistquantity', 'local_evenements'), $options);
        $mform->setType('quantity', PARAM_INT);

        $notice = get_config('local_evenements', 'privacynotice');
        if ($notice === false || trim($notice) === '') {
            $notice = get_string('privacynotice_default', 'local_evenements');
        }
        $mform->addElement('static', 'privacynoticetext', '', format_text($notice, FORMAT_PLAIN));
        $mform->addElement('checkbox', 'consent', get_string('consent', 'local_evenements'));
        $mform->addRule('consent', null, 'required', null, 'client');

        // Honeypot field: hidden from people, filled in by simple robots.
        $mform->addElement('html', '<div style="position:absolute;left:-9999px;" aria-hidden="true">' .
            '<input type="text" name="website" value="" tabindex="-1" autocomplete="off"></div>');

        $this->add_action_buttons(false, get_string('joinwaitlist', 'local_evenements'));
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
        if (empty($data['consent'])) {
            $errors['consent'] = get_string('consentrequired', 'local_evenements');
        }
        if (!empty($data['email']) && !validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        }
        return $errors;
    }
}
