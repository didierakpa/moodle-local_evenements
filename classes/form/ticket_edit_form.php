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
 * Form used by the holder of a booking to change the name on a ticket.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ticket_edit_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'b', $this->_customdata['b']);
        $mform->setType('b', PARAM_ALPHANUM);
        $mform->addElement('hidden', 't', $this->_customdata['t']);
        $mform->setType('t', PARAM_ALPHANUM);
        $mform->addElement('text', 'firstname', get_string('firstname', 'local_evenements'), ['size' => 30]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');
        $mform->addElement('text', 'lastname', get_string('lastname', 'local_evenements'), ['size' => 30]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');
        $mform->addElement('text', 'email', get_string('guestemail', 'local_evenements'), ['size' => 40]);
        $mform->setType('email', PARAM_EMAIL);
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
