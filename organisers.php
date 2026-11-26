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
 * Co-organisers of an event: Moodle users who can manage this event only.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();
$context = context_system::instance();
// Only site managers and the existing co-organisers of the event can change the team.
booking_manager::require_event_access($id);
$event = booking_manager::get_event($id);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/organisers.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('taborganisers', 'local_evenements'));
$PAGE->set_heading('');

global $DB;
if ($action === 'add' && confirm_sesskey()) {
    $login = trim(optional_param('who', '', PARAM_RAW));
    $user = false;
    if ($login !== '') {
        $user = $DB->get_record_select('user', "deleted = 0 AND suspended = 0 AND mnethostid = :h AND (" .
            $DB->sql_equal('email', ':e', false, false) . " OR " . $DB->sql_equal('username', ':u', false, false) . ")",
            ['h' => $CFG->mnet_localhost_id, 'e' => $login, 'u' => $login], '*', IGNORE_MULTIPLE);
    }
    if (!$user) {
        redirect($PAGE->url, get_string('organiserusernotfound', 'local_evenements'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    if (!booking_manager::is_organiser($id, (int) $user->id)) {
        $DB->insert_record('local_evenements_organiser', (object) ['eventid' => $id, 'userid' => $user->id,
            'timecreated' => time()]);
    }
    redirect($PAGE->url, get_string('organiseradded', 'local_evenements'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'remove' && confirm_sesskey()) {
    $DB->delete_records('local_evenements_organiser', ['eventid' => $id, 'userid' => required_param('u', PARAM_INT)]);
    redirect($PAGE->url, get_string('organiserremoved', 'local_evenements'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'organisers');
echo html_writer::tag('p', get_string('organishelp', 'local_evenements'), ['class' => 'evt-note']);

$users = $DB->get_records_sql("SELECT u.*, o.timecreated AS since FROM {local_evenements_organiser} o
    JOIN {user} u ON u.id = o.userid WHERE o.eventid = :e ORDER BY u.lastname, u.firstname", ['e' => $id]);
if ($users) {
    $rows = '';
    foreach ($users as $u) {
        $rows .= html_writer::tag('tr',
            html_writer::tag('td', s(fullname($u))) . html_writer::tag('td', s($u->email)) .
            html_writer::tag('td', html_writer::link(new moodle_url($PAGE->url, ['action' => 'remove', 'u' => $u->id,
                'sesskey' => sesskey()]), get_string('remove', 'local_evenements'), ['class' => 'btn btn-sm btn-outline-danger'])));
    }
    echo html_writer::tag('table', html_writer::tag('tbody', $rows), ['class' => 'evt-table mb-4']);
} else {
    echo html_writer::div(get_string('noorganisers', 'local_evenements'), 'evt-panel evt-empty mb-4');
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'evt-panel']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']);
echo html_writer::tag('label', get_string('organiserwho', 'local_evenements'), ['for' => 'evt-who', 'class' => 'form-label']);
echo html_writer::div(html_writer::empty_tag('input', ['type' => 'text', 'name' => 'who', 'id' => 'evt-who', 'required' => 'required',
    'class' => 'form-control']) . html_writer::tag('button', get_string('add'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    'input-group');
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
