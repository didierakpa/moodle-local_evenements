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
 * Booking form of a person of the invitation list: guests (up to the quota), time slot and questions.
 *
 * Custom data: event, invitee, questions (booking scope only), sessions.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invite_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $invitee = $this->_customdata['invitee'];
        $sessions = $this->_customdata['sessions'] ?? [];
        $questions = $this->_customdata['questions'] ?? [];

        $mform->addElement('hidden', 't', $invitee->token);
        $mform->setType('t', PARAM_ALPHANUM);

        if ($sessions) {
            $options = ['' => get_string('choosedots')];
            foreach ($sessions as $session) {
                $left = booking_manager::session_remaining($session);
                $options[$session->id] = booking_manager::session_label($session) .
                    ($left !== null && $left < 1 ? ' - ' . get_string('sessionfullshort', 'local_evenements') : '');
            }
            $mform->addElement('select', 'sessionid', get_string('session', 'local_evenements'), $options);
            $mform->addRule('sessionid', get_string('required'), 'required', null, 'client');
        }

        $quota = (int) $invitee->quota;
        if ($quota > 0) {
            $mform->addElement('header', 'guestsheader', get_string('invguests', 'local_evenements', $quota));
            $mform->setExpanded('guestsheader', true);
            $mform->addElement('static', 'guestshelp', '', get_string('invguestshelp', 'local_evenements'));
            for ($i = 1; $i <= $quota; $i++) {
                $mform->addElement('text', 'gfirst_' . $i, get_string('invguestn', 'local_evenements', $i) . ' - ' .
                    get_string('firstname'), ['size' => 24]);
                $mform->setType('gfirst_' . $i, PARAM_TEXT);
                $mform->addElement('text', 'glast_' . $i, get_string('lastname'), ['size' => 24]);
                $mform->setType('glast_' . $i, PARAM_TEXT);
            }
        }

        if ($questions) {
            $mform->addElement('header', 'questionsheader', get_string('questionsheader', 'local_evenements'));
            $mform->setExpanded('questionsheader', true);
            foreach ($questions as $question) {
                question_elements::add($mform, 'q_' . $question->id, $question);
            }
        }

        $this->add_action_buttons(true, get_string('invsubmit', 'local_evenements'));
    }

    /**
     * Validation: both names for every guest, no duplicate guest.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $invitee = $this->_customdata['invitee'];
        $seen = [booking_manager::name_key($invitee->firstname, $invitee->lastname) => true];
        for ($i = 1; $i <= (int) $invitee->quota; $i++) {
            $first = trim($data['gfirst_' . $i] ?? '');
            $last = trim($data['glast_' . $i] ?? '');
            if ($first === '' && $last === '') {
                continue;
            }
            if ($first === '' || $last === '') {
                $errors[$first === '' ? 'gfirst_' . $i : 'glast_' . $i] = get_string('required');
                continue;
            }
            $key = booking_manager::name_key($first, $last);
            if (isset($seen[$key])) {
                $errors['glast_' . $i] = get_string('invguestduplicate', 'local_evenements');
            }
            $seen[$key] = true;
        }
        foreach (($this->_customdata['questions'] ?? []) as $question) {
            $error = question_elements::validate($data, 'q_' . $question->id, $question);
            if ($error) {
                $errors['q_' . $question->id] = $error;
            }
        }
        return $errors;
    }
}
