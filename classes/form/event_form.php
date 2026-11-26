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
 * Form to create or edit an event and to design its ticket.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_form extends \moodleform {

    /**
     * Options of the image file manager.
     *
     * @return array
     */
    public static function image_options(): array {
        return ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']];
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'general', get_string('formstep1', 'local_evenements'));
        $mform->setExpanded('general', true);
        $mform->addElement('static', 'step1help', '', get_string('formstep1help', 'local_evenements'));
        $mform->addElement('text', 'name', get_string('eventname', 'local_evenements'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string('eventdescription', 'local_evenements'),
            null, ['maxfiles' => 0, 'noclean' => false]);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('text', 'location', get_string('location', 'local_evenements'), ['size' => 60]);
        $mform->setType('location', PARAM_TEXT);

        $mform->addElement('date_time_selector', 'timestart', get_string('startdate', 'local_evenements'));
        $mform->addElement('date_time_selector', 'timeend', get_string('enddate', 'local_evenements'), ['optional' => true]);

        $mform->addElement('header', 'bookingheader', get_string('formstep2', 'local_evenements'));
        $mform->setExpanded('bookingheader', true);
        $mform->addElement('static', 'step2help', '', get_string('formstep2help', 'local_evenements'));
        $mform->addElement('select', 'status', get_string('status', 'local_evenements'), [
            'draft' => get_string('status_draft', 'local_evenements'),
            'open' => get_string('status_open', 'local_evenements'),
            'closed' => get_string('status_closed', 'local_evenements'),
        ]);
        $mform->addHelpButton('status', 'formstatus', 'local_evenements');
        $mform->addElement('date_time_selector', 'openfrom', get_string('openfrom', 'local_evenements'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'openuntil', get_string('openuntil', 'local_evenements'), ['optional' => true]);

        $mform->addElement('text', 'capacity', get_string('capacity', 'local_evenements'), ['size' => 6]);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);

        $mform->addElement('text', 'maxperbooking', get_string('maxperbooking', 'local_evenements'), ['size' => 4]);
        $mform->setType('maxperbooking', PARAM_INT);
        $mform->setDefault('maxperbooking', 6);

        $themes = [];
        foreach (\local_evenements\booking_manager::IMAGE_THEMES as $theme) {
            $themes[$theme] = get_string('imagetheme_' . $theme, 'local_evenements');
        }
        $mform->addElement('select', 'imagetheme', get_string('imagetheme', 'local_evenements'), $themes);
        $mform->setDefault('imagetheme', 'generic');
        $mform->addHelpButton('imagetheme', 'imagetheme', 'local_evenements');

        $mform->addElement('select', 'accessmode', get_string('accessmode', 'local_evenements'), [
            'open' => get_string('accessopen', 'local_evenements'),
            'list' => get_string('accesslist', 'local_evenements')]);
        $mform->setDefault('accessmode', 'open');
        $mform->addHelpButton('accessmode', 'accessmode', 'local_evenements');
        $mform->addElement('text', 'guestquota', get_string('guestquota', 'local_evenements'), ['size' => 4]);
        $mform->setType('guestquota', PARAM_INT);
        $mform->setDefault('guestquota', 3);
        $mform->hideIf('guestquota', 'accessmode', 'neq', 'list');

        $mform->addElement('text', 'maxperperson', get_string('maxperperson', 'local_evenements'), ['size' => 4]);
        $mform->setType('maxperperson', PARAM_INT);
        $mform->setDefault('maxperperson', 0);
        $mform->addHelpButton('maxperperson', 'maxperperson', 'local_evenements');

        $mform->addElement('textarea', 'categories', get_string('categories', 'local_evenements'),
            ['rows' => 5, 'cols' => 50]);
        $mform->setType('categories', PARAM_TEXT);
        $mform->addHelpButton('categories', 'categories', 'local_evenements');

        $mform->addElement('header', 'ticketheader', get_string('formstep3', 'local_evenements'));
        $mform->setExpanded('ticketheader', true);
        $mform->addElement('text', 'ticketcolor', get_string('ticketcolor', 'local_evenements'), ['size' => 10]);
        $mform->setType('ticketcolor', PARAM_RAW);
        $mform->setDefault('ticketcolor', '#1d4e89');
        $mform->addElement('select', 'ticketformat', get_string('ticketformat', 'local_evenements'), [
            'compact' => get_string('formatcompact', 'local_evenements'),
            'boarding' => get_string('formatboarding', 'local_evenements'),
            'concert' => get_string('formatconcert', 'local_evenements'),
        ]);
        $mform->addHelpButton('ticketformat', 'ticketformat', 'local_evenements');
        $mform->addElement('select', 'ticketfont', get_string('ticketfont', 'local_evenements'), [
            'sans' => get_string('fontsans', 'local_evenements'),
            'serif' => get_string('fontserif', 'local_evenements'),
            'mono' => get_string('fontmono', 'local_evenements'),
        ]);
        $mform->addElement('text', 'tickettitle', get_string('tickettitle', 'local_evenements'), ['size' => 40]);
        $mform->setType('tickettitle', PARAM_TEXT);
        $mform->addElement('textarea', 'tickettext', get_string('tickettext', 'local_evenements'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('tickettext', PARAM_TEXT);
        $mform->addElement('filemanager', 'eventimage', get_string('eventimage', 'local_evenements'), null,
            self::image_options());

        $mform->addElement('filemanager', 'ticketlogo', get_string('ticketlogo', 'local_evenements'), null,
            self::image_options());
        $mform->addHelpButton('ticketlogo', 'ticketlogo', 'local_evenements');

        $mform->addElement('static', 'ticketpreview', get_string('ticketpreview', 'local_evenements'),
            '<div id="evt-preview" class="evt-preview" aria-live="polite">' .
            '<div class="evt-pv-head"><span class="evt-pv-title"></span><span class="evt-pv-cat">' .
            get_string('ticketpreviewcat', 'local_evenements') . '</span></div>' .
            '<div class="evt-pv-body"><div class="evt-pv-name"></div><div class="evt-pv-where"></div>' .
            '<div class="evt-pv-guest">' . get_string('ticketpreviewguest', 'local_evenements') . '</div>' .
            '<div class="evt-pv-qr" aria-hidden="true"></div></div>' .
            '<div class="evt-pv-foot"></div></div>');

        global $PAGE;
        $PAGE->requires->js_amd_inline("
            (function() {
                var q = function(id) { return document.getElementById(id); };
                var box = q('evt-preview');
                if (!box) { return; }
                var update = function() {
                    var color = (q('id_ticketcolor').value || '').trim();
                    if (/^#[0-9a-fA-F]{6}$/.test(color)) { box.style.setProperty('--pv', color); }
                    var fm = q('id_ticketformat');
                    if (fm) { box.style.maxWidth = {compact: '400px', boarding: '470px', concert: '560px'}[fm.value]; }
                    var f = q('id_ticketfont');
                    if (f) { box.style.fontFamily = {sans: 'Arial, sans-serif', serif: 'Georgia, serif', mono: 'Courier New, monospace'}[f.value]; }
                    box.querySelector('.evt-pv-title').textContent = q('id_tickettitle').value || '" .
                        addslashes_js(get_string('ticketpreviewdefault', 'local_evenements')) . "';
                    box.querySelector('.evt-pv-name').textContent = q('id_name').value || '';
                    box.querySelector('.evt-pv-where').textContent = q('id_location').value || '';
                    box.querySelector('.evt-pv-foot').textContent = q('id_tickettext').value || '';
                };
                ['id_ticketformat', 'id_ticketfont', 'id_ticketcolor', 'id_tickettitle', 'id_name', 'id_location', 'id_tickettext'].forEach(function(id) {
                    var el = q(id);
                    if (el) { el.addEventListener('input', update); el.addEventListener('change', update); }
                });
                update();
            })();
        ");

        $this->add_action_buttons();
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

        if (!empty($data['timeend']) && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('errorenddate', 'local_evenements');
        }
        if (!empty($data['openfrom']) && !empty($data['openuntil']) && $data['openuntil'] <= $data['openfrom']) {
            $errors['openuntil'] = get_string('errorenddate', 'local_evenements');
        }
        if ((int) $data['capacity'] < 0) {
            $errors['capacity'] = get_string('errornumber', 'local_evenements');
        }
        if ((int) $data['maxperbooking'] < 1 || (int) $data['maxperbooking'] > 20) {
            $errors['maxperbooking'] = get_string('errormaxperbooking', 'local_evenements');
        }
        if ((int) $data['maxperperson'] < 0 || (int) $data['maxperperson'] > 100) {
            $errors['maxperperson'] = get_string('errormaxperperson', 'local_evenements');
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', trim($data['ticketcolor']))) {
            $errors['ticketcolor'] = get_string('invalidcolor', 'local_evenements');
        }
        return $errors;
    }
}
