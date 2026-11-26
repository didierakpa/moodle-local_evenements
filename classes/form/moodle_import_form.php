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
use local_evenements\moodle_import;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Choose people in Moodle (cohort or course) to add to an event.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_import_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $event = $this->_customdata['event'];
        $islist = booking_manager::is_list_event($event);
        $cohorts = moodle_import::cohort_options();

        $mform->addElement('hidden', 'id', $event->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'sourcehdr', get_string('mimportsource', 'local_evenements'));
        $sources = [];
        if ($cohorts) {
            $sources[] = $mform->createElement('radio', 'source', '', get_string('mimportcohort', 'local_evenements'), 'cohort');
        }
        $sources[] = $mform->createElement('radio', 'source', '', get_string('mimportcourse', 'local_evenements'), 'course');
        $mform->addGroup($sources, 'sourcegroup', get_string('mimportfrom', 'local_evenements'), ' ', false);
        $mform->setDefault('source', $cohorts ? 'cohort' : 'course');

        if ($cohorts) {
            $mform->addElement('select', 'cohortid', get_string('mimportcohort', 'local_evenements'), $cohorts);
            $mform->hideIf('cohortid', 'source', 'neq', 'cohort');
        }
        $mform->addElement('course', 'courseid', get_string('mimportcourse', 'local_evenements'), ['multiple' => false]);
        $mform->hideIf('courseid', 'source', 'neq', 'course');
        $roles = [0 => get_string('mimportallroles', 'local_evenements')] + moodle_import::role_options();
        $mform->addElement('select', 'roleid', get_string('mimportrole', 'local_evenements'), $roles);
        $student = get_archetype_roles('student');
        if ($student) {
            $mform->setDefault('roleid', reset($student)->id);
        }
        $mform->hideIf('roleid', 'source', 'neq', 'course');

        $mform->addElement('header', 'targethdr', get_string($islist ? 'mimporttargetlist' : 'mimporttarget', 'local_evenements'));
        $categories = booking_manager::get_categories((int) $event->id);
        if ($islist) {
            $mform->addElement('text', 'category', get_string('category', 'local_evenements'), ['size' => 30]);
            $mform->setType('category', PARAM_TEXT);
            $mform->setDefault('category', get_string('invcategorydefault', 'local_evenements'));
            $mform->addElement('text', 'detail', get_string('mimportdetail', 'local_evenements'), ['size' => 40]);
            $mform->setType('detail', PARAM_TEXT);
            $mform->addElement('static', 'listhelp', '', \html_writer::span(
                get_string('mimportlisthelp', 'local_evenements'),
                'text-muted small'
            ));
        } else {
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
            $sessions = booking_manager::get_sessions((int) $event->id);
            if ($sessions) {
                $options = [];
                foreach ($sessions as $session) {
                    $options[$session->id] = booking_manager::session_label($session);
                }
                $mform->addElement('select', 'sessionid', get_string('session', 'local_evenements'), $options);
                $mform->setType('sessionid', PARAM_INT);
            }
            $mform->addElement('advcheckbox', 'sendmail', '', get_string('mimportsend', 'local_evenements'));
            $mform->addElement('advcheckbox', 'force', '', get_string('participantforce', 'local_evenements'));
            $mform->addElement('static', 'help', '', \html_writer::span(
                get_string('mimporthelp', 'local_evenements'),
                'text-muted small'
            ));
        }

        $buttons = [
            $mform->createElement('submit', 'previewbutton', get_string('mimportpreview', 'local_evenements')),
            $mform->createElement('submit', 'submitbutton', get_string('mimportadd', 'local_evenements')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * A course or a cohort must be chosen.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['source'] ?? '') === 'course' && empty($data['courseid'])) {
            $errors['courseid'] = get_string('required');
        }
        if (($data['source'] ?? '') === 'cohort' && empty($data['cohortid'])) {
            $errors['sourcegroup'] = get_string('required');
        }
        return $errors;
    }
}
