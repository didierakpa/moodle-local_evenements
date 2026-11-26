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
 * Name badges of an event: team (staff, reception, volunteers), operators, co-organisers and guests.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\badge_pdf;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();
booking_manager::require_event_access($id);
$event = booking_manager::get_event($id);
global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/badges.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('tabbadges', 'local_evenements'));
$PAGE->set_heading('');

$ok = \core\output\notification::NOTIFY_SUCCESS;

if ($action === 'addstaff' && confirm_sesskey()) {
    $name = trim(optional_param('name', '', PARAM_TEXT));
    $role = trim(optional_param('role', '', PARAM_TEXT)) ?: get_string('badgedefaultrole', 'local_evenements');
    if ($name !== '') {
        $DB->insert_record('local_evenements_staff', (object) ['eventid' => $id, 'name' => \core_text::substr($name, 0, 255),
            'role' => \core_text::substr($role, 0, 100), 'timecreated' => time()]);
    }
    redirect($PAGE->url, get_string('badgestaffadded', 'local_evenements'), null, $ok);
}
if ($action === 'bulkstaff' && confirm_sesskey()) {
    $count = 0;
    $default = trim(optional_param('bulkrole', '', PARAM_TEXT)) ?: get_string('badgedefaultrole', 'local_evenements');
    foreach (preg_split('/\R/u', optional_param('bulk', '', PARAM_RAW)) as $line) {
        $line = trim(clean_param($line, PARAM_TEXT));
        if ($line === '') {
            continue;
        }
        $parts = preg_split('/\s*[;\t]\s*/u', $line, 2);
        $DB->insert_record('local_evenements_staff', (object) ['eventid' => $id, 'name' => \core_text::substr($parts[0], 0, 255),
            'role' => \core_text::substr(trim($parts[1] ?? '') ?: $default, 0, 100), 'timecreated' => time()]);
        $count++;
    }
    redirect($PAGE->url, get_string('badgestaffimported', 'local_evenements', $count), null, $ok);
}
if ($action === 'delstaff' && confirm_sesskey()) {
    $DB->delete_records('local_evenements_staff', ['id' => required_param('s', PARAM_INT), 'eventid' => $id]);
    redirect($PAGE->url, get_string('badgestaffdeleted', 'local_evenements'), null, $ok);
}

if ($action === 'pdf' && confirm_sesskey()) {
    $people = [];
    $add = function (string $full, string $role, $ticket = null) use (&$people) {
        [$first, $last] = badge_pdf::split_name($full);
        $people[] = ['first' => $first, 'last' => $last, 'role' => $role, 'ticket' => $ticket];
    };
    if (optional_param('src_staff', 0, PARAM_BOOL)) {
        foreach ($DB->get_records('local_evenements_staff', ['eventid' => $id], 'role ASC, name ASC') as $s) {
            $add($s->name, $s->role);
        }
    }
    if (optional_param('src_operators', 0, PARAM_BOOL)) {
        foreach ($DB->get_records('local_evenements_operator', ['eventid' => $id, 'status' => 'active'], 'name ASC') as $o) {
            $add($o->name, get_string('badgerole_entry', 'local_evenements'));
        }
    }
    if (optional_param('src_organisers', 0, PARAM_BOOL)) {
        $users = $DB->get_records_sql("SELECT u.* FROM {local_evenements_organiser} o JOIN {user} u ON u.id = o.userid
            WHERE o.eventid = :e ORDER BY u.lastname, u.firstname", ['e' => $id]);
        foreach ($users as $u) {
            $people[] = ['first' => $u->firstname, 'last' => $u->lastname,
                'role' => get_string('badgerole_organisation', 'local_evenements'), 'ticket' => null];
        }
    }
    if (optional_param('src_guests', 0, PARAM_BOOL)) {
        $cat = optional_param('cat', 0, PARAM_INT);
        $withqr = optional_param('qr', 0, PARAM_BOOL);
        $categories = booking_manager::get_categories($id);
        $params = ['e' => $id, 'cancelled' => booking_manager::TICKET_CANCELLED];
        $sql = "SELECT * FROM {local_evenements_ticket} WHERE eventid = :e AND status <> :cancelled";
        if ($cat) {
            $sql .= " AND tickettypeid = :c";
            $params['c'] = $cat;
        }
        foreach ($DB->get_records_sql($sql . " ORDER BY lastname, firstname", $params) as $t) {
            $role = isset($categories[$t->tickettypeid]) ? format_string($categories[$t->tickettypeid]->name) :
                get_string('badgerole_guest', 'local_evenements');
            $people[] = ['first' => $t->firstname, 'last' => $t->lastname, 'role' => $role, 'ticket' => $withqr ? $t : null];
        }
    }
    $blank = min(40, max(0, optional_param('blank', 0, PARAM_INT)));
    $blankrole = trim(optional_param('blankrole', '', PARAM_TEXT)) ?: get_string('badgedefaultrole', 'local_evenements');
    for ($i = 0; $i < $blank; $i++) {
        $people[] = ['first' => '', 'last' => '', 'role' => $blankrole, 'ticket' => null];
    }
    if (!$people) {
        redirect($PAGE->url, get_string('badgenobody', 'local_evenements'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $pdf = badge_pdf::generate($event, $people, optional_param('format', 'card', PARAM_ALPHA));
    send_file(
        $pdf,
        clean_filename('badges-' . format_string($event->name, true, ['escape' => false]) . '.pdf'),
        0,
        0,
        true,
        true,
        'application/pdf'
    );
}

$staff = $DB->get_records('local_evenements_staff', ['eventid' => $id], 'role ASC, name ASC');
$nop = $DB->count_records('local_evenements_operator', ['eventid' => $id, 'status' => 'active']);
$norg = $DB->count_records('local_evenements_organiser', ['eventid' => $id]);
$ntickets = booking_manager::count_tickets($id);
$categories = booking_manager::get_categories($id);

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'badges');
echo html_writer::tag('p', get_string('badgeshelp', 'local_evenements'), ['class' => 'evt-note']);

// Preview of the badge (HTML).
$color = $event->ticketcolor;
$sample = html_writer::div(
    html_writer::div(html_writer::span(format_string($event->name)), 'evt-bp-top', ['style' => 'background:' . $color]) .
    html_writer::div(userdate($event->timestart, get_string('strftimedatefullshort', 'langconfig')) .
        ($event->location ? ' · ' . s($event->location) : ''), 'evt-bp-info') .
    html_writer::div(html_writer::div('Marie', 'evt-bp-first') . html_writer::div('DUPONT', 'evt-bp-last'), 'evt-bp-name') .
    html_writer::div(get_string('badgerole_welcome', 'local_evenements'), 'evt-bp-role', ['style' => 'background:#2e7d32']),
    'evt-bp'
);

// Generator.
$sel = function (string $name, string $label, bool $checked, int $count) {
    return html_writer::div(
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $name, 'value' => 1, 'id' => 'evt-' . $name,
            'class' => 'form-check-input'] + ($checked ? ['checked' => 'checked'] : [])) .
        html_writer::tag(
            'label',
            $label . ' ' . html_writer::span((string) $count, 'evt-nav-badge'),
            ['for' => 'evt-' . $name, 'class' => 'form-check-label']
        ),
        'form-check'
    );
};
$catopts = [0 => get_string('badgeallcategories', 'local_evenements')];
foreach ($categories as $c) {
    $catopts[$c->id] = format_string($c->name);
}
$form = html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false)]);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'pdf']);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$form .= html_writer::tag('h2', get_string('badgegenerate', 'local_evenements'), ['class' => 'h5']);
$form .= $sel('src_staff', get_string('badgesrc_staff', 'local_evenements'), true, count($staff));
$form .= $sel('src_operators', get_string('badgesrc_operators', 'local_evenements'), true, $nop);
$form .= $sel('src_organisers', get_string('badgesrc_organisers', 'local_evenements'), true, $norg);
$form .= $sel('src_guests', get_string('badgesrc_guests', 'local_evenements'), false, $ntickets);
$form .= html_writer::div(
    html_writer::tag('label', get_string('badgeguestcat', 'local_evenements'), ['for' => 'evt-cat',
        'class' => 'form-label small']) .
    html_writer::select($catopts, 'cat', 0, false, ['id' => 'evt-cat', 'class' => 'form-select form-select-sm']) .
    html_writer::div(
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'qr', 'value' => 1, 'id' => 'evt-qr',
        'class' => 'form-check-input']) . html_writer::tag(
            'label',
            get_string('badgewithqr', 'local_evenements'),
            ['for' => 'evt-qr', 'class' => 'form-check-label small']
        ),
        'form-check mt-1'
    ),
    'ms-4 mb-2'
);
$form .= html_writer::div(
    html_writer::tag('label', get_string('badgeblank', 'local_evenements'), ['for' => 'evt-blank', 'class' => 'form-label small']) .
    html_writer::div(
        html_writer::empty_tag('input', ['type' => 'number', 'name' => 'blank', 'id' => 'evt-blank', 'value' => 0, 'min' => 0,
            'max' => 40, 'class' => 'form-control form-control-sm', 'style' => 'max-width:90px']) .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'blankrole', 'class' => 'form-control form-control-sm',
        'placeholder' => get_string(
            'badgerolelabel',
            'local_evenements'
        )]),
        'input-group input-group-sm'
    ),
    'mb-3'
);
$form .= html_writer::div(
    html_writer::tag('label', get_string('badgeformat', 'local_evenements'), ['for' => 'evt-format',
        'class' => 'form-label small']) .
    html_writer::select(
        ['card' => get_string('badgeformat_card', 'local_evenements'),
        'a6' => get_string('badgeformat_a6', 'local_evenements')],
        'format',
        'card',
        false,
        ['id' => 'evt-format', 'class' => 'form-select form-select-sm']
    ),
    'mb-3'
);
$form .= html_writer::tag('p', get_string('badgeformathelp', 'local_evenements'), ['class' => 'evt-note']);
$form .= html_writer::tag('button', html_writer::tag('i', '', ['class' => 'fa-solid fa-id-badge', 'aria-hidden' => 'true']) . ' ' .
    get_string('badgedownload', 'local_evenements'), ['type' => 'submit', 'class' => 'btn btn-primary']);
$form .= html_writer::end_tag('form');

echo html_writer::div(
    html_writer::div($form, 'evt-panel') .
    html_writer::div(html_writer::tag('h2', get_string('badgepreview', 'local_evenements'), ['class' => 'h5']) . $sample .
        html_writer::tag('p', get_string('badgecolorshelp', 'local_evenements'), ['class' => 'evt-note mt-2']), 'evt-panel'),
    'evt-badge-grid mb-4'
);

// Team list.
echo html_writer::tag('h2', get_string('badgeteam', 'local_evenements'), ['class' => 'h5']);
if ($staff) {
    $rows = '';
    foreach ($staff as $s) {
        $rows .= html_writer::tag(
            'tr',
            html_writer::tag('td', s($s->name)) .
            html_writer::tag('td', html_writer::span(
                s($s->role),
                'evt-role-chip',
                ['style' => 'background:rgb(' . implode(',', badge_pdf::role_color(
                    $s->role,
                    \local_evenements\ticket_pdf::hex_to_rgb($color)
                )) . ')']
            )) .
            html_writer::tag('td', html_writer::link(
                new moodle_url($PAGE->url, ['action' => 'delstaff', 's' => $s->id,
                'sesskey' => sesskey()]),
                get_string('remove', 'local_evenements'),
                ['class' => 'btn btn-sm btn-outline-danger']
            ))
        );
    }
    echo html_writer::tag('table', html_writer::tag('tbody', $rows), ['class' => 'evt-table mb-3']);
} else {
    echo html_writer::div(get_string('badgenostaff', 'local_evenements'), 'evt-panel evt-empty mb-3');
}

$f1 = html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'evt-panel']);
$f1 .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$f1 .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addstaff']);
$f1 .= html_writer::tag('h3', get_string('badgeaddone', 'local_evenements'), ['class' => 'h6']);
$f1 .= html_writer::div(
    html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'required' => 'required', 'class' => 'form-control',
        'placeholder' => get_string('badgenameph', 'local_evenements'), 'aria-label' => get_string(
            'badgenameph',
            'local_evenements'
        )]) .
    html_writer::empty_tag('input', ['type' => 'text', 'name' => 'role', 'class' => 'form-control', 'list' => 'evt-roles',
        'placeholder' => get_string('badgerolelabel', 'local_evenements'), 'aria-label' => get_string(
            'badgerolelabel',
            'local_evenements'
        )]) .
    html_writer::tag('button', get_string('add'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    'input-group'
);
$roles = ['Organisation', 'Staff', 'Accueil', 'Bénévole', 'Intervenant', 'Presse', 'VIP', 'Sécurité'];
$f1 .= html_writer::tag('datalist', implode('', array_map(function ($r) {
    return html_writer::tag('option', '', ['value' => $r]);
}, $roles)), ['id' => 'evt-roles']);
$f1 .= html_writer::end_tag('form');

$f2 = html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'evt-panel']);
$f2 .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$f2 .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'bulkstaff']);
$f2 .= html_writer::tag('h3', get_string('badgeaddmany', 'local_evenements'), ['class' => 'h6']);
$f2 .= html_writer::tag('textarea', '', ['name' => 'bulk', 'rows' => 5, 'class' => 'form-control mb-2',
    'placeholder' => "Claire MARTIN ; Accueil\nPaul DURAND ; Bénévole\nSophie LEROY", 'aria-label' => get_string(
        'badgeaddmany',
        'local_evenements'
    )]);
$f2 .= html_writer::div(
    html_writer::empty_tag('input', ['type' => 'text', 'name' => 'bulkrole', 'class' => 'form-control',
        'placeholder' => get_string('badgebulkrole', 'local_evenements')]) .
    html_writer::tag('button', get_string('badgeimport', 'local_evenements'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    'input-group'
);
$f2 .= html_writer::end_tag('form');
echo html_writer::div($f1 . $f2, 'evt-badge-grid');

echo $OUTPUT->footer();
