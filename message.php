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
 * Send a message to everybody who booked an event.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\form\message_form;

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
booking_manager::require_event_access($id);

$event = booking_manager::get_event($id);
$manageurl = new moodle_url('/local/evenements/manage.php');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/message.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('sendmessage', 'local_evenements'));
$PAGE->set_heading('');

global $DB;
$recipients = $DB->count_records_sql("SELECT COUNT(DISTINCT " . 'LOWER(email)' . ")
    FROM {local_evenements_booking} WHERE eventid = :e AND status = 'active'", ['e' => $id]);

$mform = new message_form($PAGE->url, ['event' => $event, 'recipients' => $recipients]);

if ($mform->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $mform->get_data()) {
    $sent = booking_manager::send_group_message($event, trim($data->subject), trim($data->body));
    redirect(
        $manageurl,
        get_string('messagesent', 'local_evenements', $sent),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'message');
$mform->display();
echo $OUTPUT->footer();
