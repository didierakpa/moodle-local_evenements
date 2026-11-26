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
 * Door operators of an event: volunteers who scan the tickets with the app (PWA), without a Moodle account.
 * Each operator has an access code, given as a link or QR code.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$opid = optional_param('op', 0, PARAM_INT);

require_login();
booking_manager::require_event_access($id);
$event = booking_manager::get_event($id);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/operators.php', ['id' => $id]));
if ($action === 'print') {
    $PAGE->set_pagelayout('embedded');
} else {
    \local_evenements\output\ui::console_layout();
}
$PAGE->set_secondary_navigation(false);
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('taboperators', 'local_evenements'));
$PAGE->set_heading('');

global $DB;
$appurl = new moodle_url('/local/evenements/operator.php');
$invite = function (\stdClass $op) use ($appurl) {
    return new moodle_url($appurl, ['k' => $op->code]);
};
$qr = function (string $url, int $size = 5) {
    $barcode = new TCPDF2DBarcode($url, 'QRCODE,M');
    return $barcode->getBarcodeSVGcode($size, $size, 'black');
};

if ($action === 'add' && confirm_sesskey()) {
    $name = trim(optional_param('opname', '', PARAM_TEXT));
    if ($name !== '') {
        $op = booking_manager::create_operator($id, $name);
        redirect(
            new moodle_url($PAGE->url, ['show' => $op->id]),
            get_string('operatoradded', 'local_evenements'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect($PAGE->url);
}
$operator = $opid ? $DB->get_record('local_evenements_operator', ['id' => $opid, 'eventid' => $id]) : false;
if ($operator && confirm_sesskey()) {
    if ($action === 'revoke' || $action === 'activate') {
        $DB->set_field('local_evenements_operator', 'status', $action === 'revoke' ? 'revoked' : 'active', ['id' => $operator->id]);
        redirect($PAGE->url, get_string('operatorupdated', 'local_evenements'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'newcode') {
        $DB->set_field('local_evenements_operator', 'code', booking_manager::new_operator_code(), ['id' => $operator->id]);
        redirect(
            new moodle_url($PAGE->url, ['show' => $operator->id]),
            get_string('operatornewcode', 'local_evenements'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    if ($action === 'delete') {
        $DB->delete_records('local_evenements_operator', ['id' => $operator->id]);
        redirect($PAGE->url, get_string('operatordeleted', 'local_evenements'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$operators = $DB->get_records('local_evenements_operator', ['eventid' => $id], 'name ASC');

if ($action === 'print') {
    $PAGE->requires->js_call_amd('local_evenements/console', 'initActions');
    echo $OUTPUT->header();
    echo html_writer::div(html_writer::tag(
        'button',
        get_string('printnow', 'local_evenements'),
        ['type' => 'button', 'class' => 'btn btn-primary', 'data-evt-print' => '1']
    ), 'evt-print-actions');
    echo html_writer::start_div('evt-badges');
    foreach ($operators as $op) {
        if ($op->status !== 'active') {
            continue;
        }
        echo html_writer::div(
            html_writer::div(format_string($event->name), 'evt-badge-event') .
            html_writer::div($qr($invite($op)->out(false), 4), 'evt-badge-qr') .
            html_writer::div(s($op->name), 'evt-badge-name') .
            html_writer::div(booking_manager::format_operator_code($op->code), 'evt-badge-code') .
            html_writer::div(get_string('operatorbadgehelp', 'local_evenements'), 'evt-badge-help'),
            'evt-badge-card'
        );
    }
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'operators');
echo html_writer::tag('p', get_string('operatorshelp', 'local_evenements'), ['class' => 'evt-note']);

// Steps to give to the volunteers.
echo html_writer::div(
    html_writer::tag('h2', get_string('operatorsteps', 'local_evenements'), ['class' => 'h5']) .
    html_writer::tag(
        'ol',
        html_writer::tag('li', get_string('operatorstep1', 'local_evenements')) .
        html_writer::tag('li', get_string('operatorstep2', 'local_evenements', $appurl->out(false))) .
        html_writer::tag('li', get_string('operatorstep3', 'local_evenements'))
    ),
    'evt-panel mb-3'
);

$show = optional_param('show', 0, PARAM_INT);
if ($show && isset($operators[$show])) {
    $op = $operators[$show];
    $url = $invite($op)->out(false);
    echo html_writer::div(
        html_writer::tag('h2', get_string('operatorinvitation', 'local_evenements', s($op->name)), ['class' => 'h5']) .
        html_writer::div($qr($url, 6), 'evt-qr-big') .
        html_writer::tag('p', get_string('operatorcode', 'local_evenements') . ' : ' .
            html_writer::tag('strong', booking_manager::format_operator_code($op->code))) .
        html_writer::empty_tag('input', ['type' => 'text', 'readonly' => 'readonly', 'class' => 'form-control form-control-sm',
            'value' => $url, 'data-evt-select' => '1']) .
        html_writer::tag('p', get_string('operatorinvitationhelp', 'local_evenements'), ['class' => 'evt-note mt-2']),
        'evt-panel mb-3 evt-invite'
    );
}

if ($operators) {
    $rows = '';
    foreach ($operators as $op) {
        $entries = $DB->count_records('local_evenements_ticket', ['eventid' => $id, 'operatorid' => $op->id,
            'status' => booking_manager::TICKET_USED]);
        $active = booking_manager::operator_active($op, $event);
        $badge = html_writer::span(get_string($op->status === 'active' ? ($active ? 'operatoractive' : 'operatorexpired') :
            'operatorrevoked', 'local_evenements'), 'evt-badge evt-badge-' . ($op->status === 'active' && $active ? 'open'
                : 'closed'));
        $link = function (string $act, string $label, string $class = 'btn-outline-secondary') use ($PAGE, $op) {
            return html_writer::link(
                new moodle_url($PAGE->url, ['action' => $act, 'op' => $op->id, 'sesskey' => sesskey()]),
                $label,
                ['class' => 'btn btn-sm ' . $class . ' me-1 mb-1']
            );
        };
        $rows .= html_writer::tag(
            'tr',
            html_writer::tag('td', html_writer::span(s($op->name), 'evt-name')) .
            html_writer::tag('td', $badge) .
            html_writer::tag('td', $op->lastseen ? userdate($op->lastseen, get_string('strftimedatetimeshort', 'langconfig'))
                : '-') .
            html_writer::tag('td', $entries) .
            html_writer::tag(
                'td',
                html_writer::link(
                    new moodle_url($PAGE->url, ['show' => $op->id]),
                    get_string('operatorshowqr', 'local_evenements'),
                    ['class' => 'btn btn-sm btn-primary me-1 mb-1']
                ) .
                ($op->status === 'active' ? $link('revoke', get_string('operatorrevoke', 'local_evenements')) :
                    $link('activate', get_string('operatoractivate', 'local_evenements'))) .
                $link('newcode', get_string('operatornewcodebtn', 'local_evenements')) .
                $link('delete', get_string('delete'), 'btn-outline-danger')
            )
        );
    }
    echo html_writer::tag('table', html_writer::tag('thead', html_writer::tag(
        'tr',
        html_writer::tag('th', get_string('operatorname', 'local_evenements')) . html_writer::tag('th', get_string(
            'status',
            'local_evenements'
        )) .
        html_writer::tag('th', get_string('operatorlastseen', 'local_evenements')) .
        html_writer::tag('th', get_string('statcheckedin', 'local_evenements')) . html_writer::tag('th', '')
    )) .
        html_writer::tag('tbody', $rows), ['class' => 'evt-table mb-3']);
    echo html_writer::div(html_writer::link(
        new moodle_url($PAGE->url, ['action' => 'print']),
        get_string('operatorprint', 'local_evenements'),
        ['class' => 'btn btn-secondary', 'target' => '_blank']
    ), 'mb-4');
} else {
    echo html_writer::div(get_string('nooperators', 'local_evenements'), 'evt-panel evt-empty mb-4');
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'evt-panel']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']);
echo html_writer::tag('label', get_string('operatornewlabel', 'local_evenements'), ['for' => 'evt-opname',
    'class' => 'form-label']);
echo html_writer::div(html_writer::empty_tag('input', ['type' => 'text', 'name' => 'opname', 'id' => 'evt-opname',
    'required' => 'required',
    'maxlength' => 100, 'class' => 'form-control']) . html_writer::tag(
        'button',
        get_string('add'),
        ['type' => 'submit', 'class' => 'btn btn-primary']
    ), 'input-group');
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
