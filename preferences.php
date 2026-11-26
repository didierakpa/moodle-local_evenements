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

/**
 * General settings of the plugin, from the organiser space.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\form\preferences_form;

require_login();
$context = context_system::instance();
require_capability('local/evenements:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/preferences.php'));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('generalsettings', 'local_evenements'));
$PAGE->set_heading('');

$manageurl = new moodle_url('/local/evenements/manage.php');
$mform = new preferences_form($PAGE->url);

if ($mform->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $mform->get_data()) {
    foreach (['menuentry', 'reminderhours', 'editdeadlinehours', 'surveyemail', 'retentiondays', 'ratelimit'] as $name) {
        set_config($name, (int) $data->$name, 'local_evenements');
    }
    set_config('privacynotice', trim($data->privacynotice), 'local_evenements');
    redirect($manageurl, get_string('settingssaved', 'local_evenements'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$defaults = ['menuentry' => 1, 'reminderhours' => 24, 'editdeadlinehours' => 24, 'surveyemail' => 1, 'retentiondays' => 30,
    'ratelimit' => 10];
$current = [];
foreach ($defaults as $name => $default) {
    $value = get_config('local_evenements', $name);
    $current[$name] = ($value === false || $value === '') ? $default : (int) $value;
}
$notice = get_config('local_evenements', 'privacynotice');
$current['privacynotice'] = ($notice === false || trim((string) $notice) === '') ? get_string(
    'privacynotice_default',
    'local_evenements'
) : $notice;
$mform->set_data($current);

echo $OUTPUT->header();
echo \local_evenements\output\ui::console(null, 'prefs');
echo html_writer::link($manageurl, '← ' . get_string('manageevents', 'local_evenements'), ['class' => 'evt-back']);
echo html_writer::div(html_writer::tag('h1', get_string('generalsettings', 'local_evenements')), 'evt-pagehead');
$mform->display();
echo $OUTPUT->footer();
