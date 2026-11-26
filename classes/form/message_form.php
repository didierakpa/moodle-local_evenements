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
 * Message to everybody who booked an event.
 *
 * Custom data: event, recipients (number of e-mail addresses).
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['event']->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('static', 'recipientsinfo', '',
            get_string('messagerecipients', 'local_evenements', (int) $this->_customdata['recipients']));
        $mform->addElement('text', 'subject', get_string('messagesubject', 'local_evenements'), ['size' => 60]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');
        $mform->addElement('textarea', 'body', get_string('messagebody', 'local_evenements'),
            ['rows' => 10, 'cols' => 60]);
        $mform->setType('body', PARAM_TEXT);
        $mform->addRule('body', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('sendmessage', 'local_evenements'));
    }
}
