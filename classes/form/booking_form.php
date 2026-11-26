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
 * Booking form: contact person, one block per named guest, questions, consent.
 *
 * Custom data: event, n (number of guests), categories, questions (asked at booking time).
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class booking_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $event = $this->_customdata['event'];
        $n = (int) $this->_customdata['n'];
        $categories = $this->_customdata['categories'];
        $questions = $this->_customdata['questions'] ?? [];

        $bookingquestions = array_filter($questions, function ($q) {
            return $q->scope === 'booking';
        });
        $guestquestions = array_filter($questions, function ($q) {
            return $q->scope === 'guest';
        });

        $mform->addElement('hidden', 'id', $event->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'n', $n);
        $mform->setType('n', PARAM_INT);

        $sessions = $this->_customdata['sessions'] ?? [];
        if ($sessions) {
            $mform->addElement('header', 'sessionheader', get_string('choosesession', 'local_evenements'));
            $mform->setExpanded('sessionheader', true);
            $sessionoptions = [];
            foreach ($sessions as $session) {
                $left = \local_evenements\booking_manager::session_remaining($session);
                $label = \local_evenements\booking_manager::session_label($session);
                if ($left !== null) {
                    $label .= $left > 0 ? ' (' . get_string('remaining', 'local_evenements', $left) . ')' :
                        ' (' . get_string('sessionfullshort', 'local_evenements') . ')';
                }
                $sessionoptions[$session->id] = $label;
            }
            $mform->addElement('select', 'sessionid', get_string('session', 'local_evenements'), $sessionoptions);
            $mform->setType('sessionid', PARAM_INT);
        }

        $mform->addElement('header', 'contactheader', get_string('yourdetails', 'local_evenements'));
        $mform->addElement('text', 'contactname', get_string('contactname', 'local_evenements'), ['size' => 40]);
        $mform->setType('contactname', PARAM_TEXT);
        $mform->addRule('contactname', null, 'required', null, 'client');
        $mform->addElement('text', 'email', get_string('contactemail', 'local_evenements'), ['size' => 40]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->addElement('text', 'email2', get_string('contactemailconfirm', 'local_evenements'), ['size' => 40]);
        $mform->setType('email2', PARAM_EMAIL);
        $mform->addRule('email2', null, 'required', null, 'client');

        $options = [];
        foreach ($categories as $category) {
            $options[$category->id] = format_string($category->name);
        }

        for ($i = 1; $i <= $n; $i++) {
            $mform->addElement('header', 'guestheader' . $i, get_string('guestn', 'local_evenements', $i));
            $mform->setExpanded('guestheader' . $i, true);

            $mform->addElement('text', 'guestfirstname_' . $i, get_string('firstname', 'local_evenements'), ['size' => 30]);
            $mform->setType('guestfirstname_' . $i, PARAM_TEXT);
            $mform->addRule('guestfirstname_' . $i, null, 'required', null, 'client');

            $mform->addElement('text', 'guestlastname_' . $i, get_string('lastname', 'local_evenements'), ['size' => 30]);
            $mform->setType('guestlastname_' . $i, PARAM_TEXT);
            $mform->addRule('guestlastname_' . $i, null, 'required', null, 'client');

            if ($options) {
                $mform->addElement('select', 'guesttype_' . $i, get_string('category', 'local_evenements'), $options);
                $mform->setType('guesttype_' . $i, PARAM_INT);
            }

            $mform->addElement('text', 'guestemail_' . $i, get_string('guestemail', 'local_evenements'), ['size' => 40]);
            $mform->setType('guestemail_' . $i, PARAM_EMAIL);

            foreach ($guestquestions as $question) {
                question_elements::add($mform, 'gq_' . $i . '_' . $question->id, $question);
            }
        }

        if ($bookingquestions) {
            $mform->addElement('header', 'questionsheader', get_string('questionsheader', 'local_evenements'));
            $mform->setExpanded('questionsheader', true);
            foreach ($bookingquestions as $question) {
                question_elements::add($mform, 'q_' . $question->id, $question);
            }
        }

        $mform->addElement('header', 'consentheader', get_string('consentheader', 'local_evenements'));
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

        $this->add_action_buttons(true, get_string('confirmbooking', 'local_evenements'));
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

        if (!empty($data['email']) && !empty($data['email2']) &&
                \core_text::strtolower($data['email']) !== \core_text::strtolower($data['email2'])) {
            $errors['email2'] = get_string('emailmismatch', 'local_evenements');
        }
        if (empty($data['consent'])) {
            $errors['consent'] = get_string('consentrequired', 'local_evenements');
        }

        $n = (int) $this->_customdata['n'];
        $event = $this->_customdata['event'];
        if (!empty($data['email']) && !empty($event->maxperperson)) {
            $left = \local_evenements\booking_manager::person_remaining($event, trim($data['email']));
            if ($left !== null && $n > $left) {
                $errors['email'] = get_string('personlimit', 'local_evenements',
                    (object) ['max' => (int) $event->maxperperson, 'left' => $left]);
            }
        }
        $sessions = $this->_customdata['sessions'] ?? [];
        if ($sessions) {
            $chosen = (int) ($data['sessionid'] ?? 0);
            if (!isset($sessions[$chosen])) {
                $errors['sessionid'] = get_string('invalidsession', 'local_evenements');
            } else {
                $left = \local_evenements\booking_manager::session_remaining($sessions[$chosen]);
                if ($left !== null && $n > $left) {
                    $errors['sessionid'] = get_string('sessionfull', 'local_evenements');
                }
            }
        }
        foreach (($this->_customdata['questions'] ?? []) as $question) {
            if ($question->scope === 'guest') {
                for ($i = 1; $i <= $n; $i++) {
                    $name = 'gq_' . $i . '_' . $question->id;
                    $error = question_elements::validate($data, $name, $question);
                    if ($error !== null) {
                        $errors[$name] = $error;
                    }
                }
            } else {
                $name = 'q_' . $question->id;
                $error = question_elements::validate($data, $name, $question);
                if ($error !== null) {
                    $errors[$name] = $error;
                }
            }
        }
        return $errors;
    }
}
