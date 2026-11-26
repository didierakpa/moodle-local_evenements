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

/**
 * Builds and checks the form element of a question (shared by the booking form and the survey).
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_elements {
    /**
     * Add the element that matches the type of the question.
     *
     * @param \MoodleQuickForm $mform
     * @param string $name Element name
     * @param \stdClass $question
     */
    public static function add(\MoodleQuickForm $mform, string $name, \stdClass $question): void {
        $label = format_string($question->name);
        $options = booking_manager::get_question_options($question);

        switch ($question->type) {
            case 'longtext':
                $mform->addElement('textarea', $name, $label, ['rows' => 3, 'cols' => 50]);
                $mform->setType($name, PARAM_TEXT);
                break;
            case 'choice':
                $mform->addElement('select', $name, $label, ['' => get_string('choosedots')] + $options);
                $mform->setType($name, PARAM_TEXT);
                break;
            case 'multichoice':
                $mform->addElement(
                    'select',
                    $name,
                    $label,
                    $options,
                    ['multiple' => 'multiple', 'size' => max(2, min(6, count($options)))]
                );
                $mform->setType($name, PARAM_TEXT);
                break;
            default:
                $mform->addElement('text', $name, $label, ['size' => 50]);
                $mform->setType($name, PARAM_TEXT);
        }
    }

    /**
     * Check that a required question has been answered.
     *
     * @param array $data Submitted data
     * @param string $name Element name
     * @param \stdClass $question
     * @return string|null Error message, or null when fine
     */
    public static function validate(array $data, string $name, \stdClass $question): ?string {
        if (empty($question->isrequired)) {
            return null;
        }
        $answer = booking_manager::clean_answer($question, $data[$name] ?? '');
        return $answer === '' ? get_string('required') : null;
    }
}
