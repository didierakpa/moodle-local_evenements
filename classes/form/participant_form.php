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

use local_evenements\booking_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Organiser form: add a participant by hand, or change a participant (ticket and booking).
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $event = $this->_customdata['event'];
        $isnew = empty($this->_customdata['ticketid']);
        $others = (int) ($this->_customdata['others'] ?? 0);

        $mform->addElement('hidden', 'id', $event->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 't', (int) ($this->_customdata['ticketid'] ?? 0));
        $mform->setType('t', PARAM_INT);

        $mform->addElement('header', 'guesthdr', get_string('participantguest', 'local_evenements'));
        $mform->addElement('text', 'lastname', get_string('lastname', 'local_evenements'), ['size' => 30, 'maxlength' => 100]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');
        $mform->addElement('text', 'firstname', get_string('firstname', 'local_evenements'), ['size' => 30, 'maxlength' => 100]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');
        $mform->addElement('text', 'email', get_string('guestemail', 'local_evenements'), ['size' => 40, 'maxlength' => 255]);
        $mform->setType('email', PARAM_RAW_TRIMMED);

        $categories = booking_manager::get_categories((int) $event->id);
        if ($categories) {
            $options = [];
            foreach ($categories as $category) {
                $options[$category->id] = format_string($category->name);
            }
            $mform->addElement('select', 'tickettypeid', get_string('category', 'local_evenements'), $options);
        } else {
            $mform->addElement('hidden', 'tickettypeid', 0);
        }
        $mform->setType('tickettypeid', PARAM_INT);
        $mform->addElement('text', 'note', get_string('participantnote', 'local_evenements'), ['size' => 40, 'maxlength' => 255]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addElement('static', 'notehelp', '', \html_writer::span(get_string('participantnote_help', 'local_evenements'),
            'text-muted small'));

        $mform->addElement('header', 'bookinghdr', get_string('participantbooking', 'local_evenements'));
        if ($others > 0) {
            $mform->addElement('static', 'sharedhelp', '', \html_writer::div(
                get_string('participantshared', 'local_evenements', $others), 'alert alert-info py-2 mb-0'));
        }
        $sessions = booking_manager::get_sessions((int) $event->id);
        if ($sessions) {
            $options = [];
            foreach ($sessions as $session) {
                $options[$session->id] = booking_manager::session_label($session);
            }
            $mform->addElement('select', 'sessionid', get_string('session', 'local_evenements'), $options);
        } else {
            $mform->addElement('hidden', 'sessionid', 0);
        }
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('text', 'contactname', get_string('participantcontactname', 'local_evenements'), ['size' => 40, 'maxlength' => 255]);
        $mform->setType('contactname', PARAM_TEXT);
        $mform->addElement('text', 'contactemail', get_string('participantcontactemail', 'local_evenements'), ['size' => 40, 'maxlength' => 255]);
        $mform->setType('contactemail', PARAM_RAW_TRIMMED);
        if ($isnew) {
            $mform->addElement('static', 'contacthelp', '', \html_writer::span(get_string('participantcontact_help', 'local_evenements'),
                'text-muted small'));
        } else {
            $mform->addRule('contactname', null, 'required', null, 'client');
        }

        $mform->addElement('header', 'optionshdr', get_string('participantoptions', 'local_evenements'));
        $mform->setExpanded('optionshdr', true);
        $mform->addElement('advcheckbox', 'sendticket', '', get_string($isnew ? 'participantsend' : 'participantresend',
            'local_evenements'));
        $mform->setDefault('sendticket', $isnew ? 1 : 0);
        $mform->addElement('advcheckbox', 'force', '', get_string('participantforce', 'local_evenements'));

        $this->add_action_buttons(true, get_string($isnew ? 'participantadd' : 'savechanges', $isnew ? 'local_evenements' : 'moodle'));
    }

    /**
     * Checks the e-mail addresses.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['email', 'contactemail'] as $field) {
            if (trim((string) ($data[$field] ?? '')) !== '' && !validate_email(trim($data[$field]))) {
                $errors[$field] = get_string('invalidemail');
            }
        }
        if (!empty($data['sendticket']) && trim((string) ($data['contactemail'] ?? '')) === '' &&
                trim((string) ($data['email'] ?? '')) === '') {
            $errors['sendticket'] = get_string('participantnoemail', 'local_evenements');
        }
        return $errors;
    }
}
