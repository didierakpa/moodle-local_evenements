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
 * Invitation list of an event (graduates, partners, teachers...): import, personal links, follow-up.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$filter = optional_param('st', '', PARAM_ALPHA);
$search = trim(optional_param('q', '', PARAM_TEXT));

require_login();
booking_manager::require_event_access($id);
$event = booking_manager::get_event($id);
global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/invitees.php', ['id' => $id]));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('tabinvitees', 'local_evenements'));
$PAGE->set_heading('');
$ok = \core\output\notification::NOTIFY_SUCCESS;

$getinvitee = function () use ($DB, $id) {
    return $DB->get_record('local_evenements_invitee', ['id' => required_param('i', PARAM_INT), 'eventid' => $id], '*', MUST_EXIST);
};

// Export (with the personal links, to send them by another channel).
if ($action === 'csv' && confirm_sesskey()) {
    $categories = booking_manager::get_categories($id);
    $rows = [['Nom', 'Prénom', 'E-mail', 'Catégorie', 'Détail', 'Invités autorisés', 'Statut', 'Lien personnel']];
    foreach ($DB->get_records('local_evenements_invitee', ['eventid' => $id], 'lastname, firstname') as $i) {
        $rows[] = [$i->lastname, $i->firstname, $i->email, $categories[$i->tickettypeid]->name ?? '', $i->detail, $i->quota,
            get_string('invstatus_' . booking_manager::invitee_status($i), 'local_evenements'),
            booking_manager::invitee_url($i)->out(false)];
    }
    $out = fopen('php://temp', 'w+');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $r) {
        fputcsv($out, array_map(function ($c) {
            return preg_match('/^[=+\-@\t\r]/', (string) $c) ? "'" . $c : $c;
        }, $r), ';');
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    send_file($csv, clean_filename('liste-' . format_string($event->name, true, ['escape' => false]) . '.csv'), 0, 0, true, true, 'text/csv');
}

if ($action !== '' && $action !== 'pdf' && confirm_sesskey()) {
    switch ($action) {
        case 'import':
            [$added, $dups, $errors] = booking_manager::import_invitees($event, optional_param('list', '', PARAM_RAW));
            $msg = get_string('invimported', 'local_evenements', (object) ['added' => $added, 'dups' => $dups]);
            if ($errors) {
                $msg .= ' ' . get_string('invimporterrors', 'local_evenements') . ' ' . implode(' ; ', array_slice($errors, 0, 8));
            }
            redirect($PAGE->url, $msg, null, $errors ? \core\output\notification::NOTIFY_WARNING : $ok);
        case 'add':
            $email = clean_param(optional_param('email', '', PARAM_RAW), PARAM_EMAIL);
            $first = trim(optional_param('firstname', '', PARAM_TEXT));
            $last = trim(optional_param('lastname', '', PARAM_TEXT));
            if ($first === '' || $last === '' || !validate_email($email)) {
                redirect($PAGE->url, get_string('invaddinvalid', 'local_evenements'), null, \core\output\notification::NOTIFY_ERROR);
            }
            $q = optional_param('quota', '', PARAM_RAW);
            $new = booking_manager::add_invitee($event, $first, $last, $email, trim(optional_param('category', '', PARAM_TEXT)),
                trim(optional_param('detail', '', PARAM_TEXT)), ($q !== '' && ctype_digit($q)) ? (int) $q : null);
            redirect($PAGE->url, $new ? get_string('invadded', 'local_evenements') : get_string('invexists', 'local_evenements'), null,
                $new ? $ok : \core\output\notification::NOTIFY_WARNING);
        case 'send':
            $i = $getinvitee();
            $sent = booking_manager::send_invitation($i, $event);
            redirect($PAGE->url, $sent ? get_string('invsent', 'local_evenements', s($i->email)) : get_string('invsendfailed', 'local_evenements'),
                null, $sent ? $ok : \core\output\notification::NOTIFY_ERROR);
        case 'sendnew':
        case 'sendremind':
            $n = 0;
            foreach ($DB->get_records('local_evenements_invitee', ['eventid' => $id, 'bookingid' => 0]) as $i) {
                if ($action === 'sendnew' && $i->timeinvited) {
                    continue;
                }
                if (booking_manager::send_invitation($i, $event)) {
                    $n++;
                }
            }
            redirect($PAGE->url, get_string('invsentmany', 'local_evenements', $n), null, $ok);
        case 'newlink':
            booking_manager::reset_invitee($getinvitee(), false);
            redirect($PAGE->url, get_string('invnewlinkdone', 'local_evenements'), null, $ok);
        case 'reset':
            $i = $getinvitee();
            if ($i->bookingid) {
                \local_evenements\participant_manager::log($id, (int) $i->bookingid, 0, 'cancel',
                    trim($i->firstname . ' ' . $i->lastname) . "\n" . get_string('loginviteereset', 'local_evenements'));
            }
            booking_manager::reset_invitee($i, true);
            redirect($PAGE->url, get_string('invresetdone', 'local_evenements'), null, $ok);
        case 'update':
            $i = $getinvitee();
            $i->quota = max(0, min(20, optional_param('quota', (int) $i->quota, PARAM_INT)));
            $DB->update_record('local_evenements_invitee', $i);
            redirect($PAGE->url, get_string('invupdated', 'local_evenements'), null, $ok);
        case 'delete':
            $i = $getinvitee();
            if ($i->bookingid) {
                \local_evenements\participant_manager::log($id, (int) $i->bookingid, 0, 'cancel',
                    trim($i->firstname . ' ' . $i->lastname) . "\n" . get_string('loginviteedeleted', 'local_evenements'));
                booking_manager::reset_invitee($i, true);
            }
            $DB->delete_records('local_evenements_invitee', ['id' => $i->id]);
            redirect($PAGE->url, get_string('invdeleted', 'local_evenements'), null, $ok);
    }
}

$categories = booking_manager::get_categories($id);
$all = $DB->get_records('local_evenements_invitee', ['eventid' => $id], 'lastname ASC, firstname ASC');
$count = ['new' => 0, 'invited' => 0, 'confirmed' => 0, 'booked' => 0];
$reported = 0;
foreach ($all as $i) {
    $count[booking_manager::invitee_status($i)]++;
    $reported += $i->timereported && !$i->bookingid ? 1 : 0;
}
$total = count($all);

echo $OUTPUT->header();
echo \local_evenements\output\ui::event_header($event, 'invitees');

if (!booking_manager::is_list_event($event)) {
    echo $OUTPUT->notification(get_string('invnotlist', 'local_evenements'), \core\output\notification::NOTIFY_WARNING);
    echo html_writer::link(new moodle_url('/local/evenements/edit.php', ['id' => $id]), get_string('tabsettings', 'local_evenements'), ['class' => 'btn btn-primary mb-3']);
}
echo html_writer::tag('p', get_string('inviteshelp', 'local_evenements'), ['class' => 'evt-note']);

// Key figures.
$kpi = function (string $label, int $value, string $cls = '') {
    return html_writer::div(html_writer::div((string) $value, 'evt-kpi-value') . html_writer::div($label, 'evt-kpi-label'), 'evt-kpi ' . $cls);
};
echo html_writer::div(
    $kpi(get_string('invtotal', 'local_evenements'), $total) .
    $kpi(get_string('invstatus_booked', 'local_evenements'), $count['booked'], 'evt-kpi-ok') .
    $kpi(get_string('invstatus_confirmed', 'local_evenements'), $count['confirmed']) .
    $kpi(get_string('invnotbooked', 'local_evenements'), $total - $count['booked']), 'evt-kpis mb-3');

if ($reported) {
    echo $OUTPUT->notification(get_string('invreportedwarn', 'local_evenements', $reported), \core\output\notification::NOTIFY_WARNING);
}
$dups = booking_manager::duplicate_names($id);
if ($dups) {
    $names = implode(', ', array_map(function ($d) {
        return s($d->name) . ' (' . $d->total . ')';
    }, array_slice($dups, 0, 10)));
    echo $OUTPUT->notification(get_string('invduplicatewarn', 'local_evenements', $names), \core\output\notification::NOTIFY_WARNING);
}

$post = function (string $act, string $label, array $extra = [], string $cls = 'btn btn-sm btn-outline-secondary', string $confirm = '') use ($PAGE) {
    $hidden = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $act]);
    foreach ($extra as $k => $v) {
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $k, 'value' => $v]);
    }
    return html_writer::tag('form', $hidden . html_writer::tag('button', $label, ['type' => 'submit', 'class' => $cls] +
        ($confirm !== '' ? ['onclick' => 'return confirm(' . json_encode($confirm) . ');'] : [])),
        ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'd-inline']);
};

// Bulk actions.
echo html_writer::div(
    $post('sendnew', get_string('invsendnew', 'local_evenements'), ['id' => $id], 'btn btn-primary', get_string('invconfirmbulk', 'local_evenements')) . ' ' .
    $post('sendremind', get_string('invsendremind', 'local_evenements'), ['id' => $id], 'btn btn-outline-primary', get_string('invconfirmbulk', 'local_evenements')) . ' ' .
    html_writer::link(new moodle_url($PAGE->url, ['action' => 'csv', 'sesskey' => sesskey()]), get_string('invexport', 'local_evenements'),
        ['class' => 'btn btn-outline-secondary']) . ' ' .
    html_writer::link(new moodle_url('/local/evenements/moodleimport.php', ['id' => $id]), get_string('mimportmenu', 'local_evenements'),
        ['class' => 'btn btn-outline-secondary']), 'd-flex flex-wrap gap-2 mb-3');

// Filters.
$filters = ['' => get_string('invall', 'local_evenements'), 'new' => get_string('invstatus_new', 'local_evenements'),
    'invited' => get_string('invstatus_invited', 'local_evenements'), 'confirmed' => get_string('invstatus_confirmed', 'local_evenements'),
    'booked' => get_string('invstatus_booked', 'local_evenements')];
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'd-flex flex-wrap gap-2 mb-2']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $search, 'class' => 'form-control',
    'style' => 'max-width:260px', 'placeholder' => get_string('invsearch', 'local_evenements'), 'aria-label' => get_string('invsearch', 'local_evenements')]);
echo html_writer::select($filters, 'st', $filter, false, ['class' => 'form-select', 'style' => 'max-width:200px', 'aria-label' => get_string('invstatus', 'local_evenements')]);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-outline-secondary']);
echo html_writer::end_tag('form');

$skey = $search !== '' ? booking_manager::name_key($search, '') : '';
$rows = '';
$shown = 0;
foreach ($all as $i) {
    $status = booking_manager::invitee_status($i);
    if ($filter !== '' && $status !== $filter) {
        continue;
    }
    if ($skey !== '' && strpos($i->namekey, $skey) === false && stripos($i->email, $search) === false) {
        continue;
    }
    if (++$shown > 300) {
        continue;
    }
    $link = booking_manager::invitee_url($i)->out(false);
    $name = s($i->lastname) . ' ' . s($i->firstname) . ($i->timereported && !$i->bookingid ?
        ' ' . html_writer::span('⚠ ' . get_string('invreported', 'local_evenements'), 'badge bg-warning text-dark') : '');
    $actions = $post('send', get_string('invsendone', 'local_evenements'), ['id' => $id, 'i' => $i->id]) . ' ' .
        html_writer::tag('details', html_writer::tag('summary', get_string('invmore', 'local_evenements'), ['class' => 'btn btn-sm btn-outline-secondary']) .
            html_writer::div(
                html_writer::empty_tag('input', ['type' => 'text', 'readonly' => 'readonly', 'value' => $link, 'class' => 'form-control form-control-sm mb-1',
                    'onfocus' => 'this.select()', 'aria-label' => get_string('invlink', 'local_evenements')]) .
                $post('newlink', get_string('invnewlink', 'local_evenements'), ['id' => $id, 'i' => $i->id], 'btn btn-sm btn-outline-secondary', get_string('invconfirmnewlink', 'local_evenements')) . ' ' .
                ($i->bookingid ? html_writer::link(new moodle_url('/local/evenements/registrations.php', ['id' => $id, 'q' => $i->email]),
                    get_string('invtickets', 'local_evenements'), ['class' => 'btn btn-sm btn-outline-primary']) . ' ' : '') .
                ($i->bookingid ? $post('reset', get_string('invreset', 'local_evenements'), ['id' => $id, 'i' => $i->id], 'btn btn-sm btn-outline-warning', get_string('invconfirmreset', 'local_evenements')) . ' ' : '') .
                $post('delete', get_string('delete'), ['id' => $id, 'i' => $i->id], 'btn btn-sm btn-outline-danger', get_string('invconfirmdelete', 'local_evenements')),
                'evt-invmore'), ['class' => 'd-inline-block align-top']);
    $rows .= html_writer::tag('tr',
        html_writer::tag('td', $name) .
        html_writer::tag('td', s($categories[$i->tickettypeid]->name ?? '') . ($i->detail ? html_writer::div(s($i->detail), 'evt-note') : '')) .
        html_writer::tag('td', s($i->email)) .
        html_writer::tag('td', (string) (int) $i->quota) .
        html_writer::tag('td', html_writer::span(get_string('invstatus_' . $status, 'local_evenements'), 'evt-badge evt-inv-' . $status) .
            ($i->timeinvited ? html_writer::div(userdate($i->timeinvited, get_string('strftimedatetimeshort', 'langconfig')), 'evt-note') : '')) .
        html_writer::tag('td', $actions));
}
if ($rows) {
    echo html_writer::tag('div', html_writer::tag('table', html_writer::tag('thead', html_writer::tag('tr',
        html_writer::tag('th', get_string('invname', 'local_evenements')) . html_writer::tag('th', get_string('invcategory', 'local_evenements')) .
        html_writer::tag('th', get_string('email')) . html_writer::tag('th', get_string('invquota', 'local_evenements')) .
        html_writer::tag('th', get_string('invstatus', 'local_evenements')) . html_writer::tag('th', ''))) .
        html_writer::tag('tbody', $rows), ['class' => 'evt-table']), ['class' => 'table-responsive mb-4']);
} else {
    echo html_writer::div(get_string('invempty', 'local_evenements'), 'evt-panel evt-empty mb-4');
}

// Import and single add.
$hidden = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
$f1 = html_writer::tag('form', $hidden . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'import']) .
    html_writer::tag('h3', get_string('invimport', 'local_evenements'), ['class' => 'h6']) .
    html_writer::tag('p', get_string('invimporthelp', 'local_evenements'), ['class' => 'evt-note']) .
    html_writer::tag('textarea', '', ['name' => 'list', 'rows' => 7, 'class' => 'form-control mb-2', 'aria-label' => get_string('invimport', 'local_evenements'),
        'placeholder' => "MARTIN ; Léa ; lea.martin@example.org ; Diplômé ; Bac Pro AGOrA ; 3\nDURAND ; Paul ; paul@example.org ; Partenaire ; Entreprise Airbus ; 1"]) .
    html_writer::tag('button', get_string('invimportbtn', 'local_evenements'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'evt-panel']);
$field = function (string $name, string $label, string $type = 'text', bool $req = false) {
    return html_writer::div(html_writer::tag('label', $label, ['for' => 'evt-inv-' . $name, 'class' => 'form-label small mb-0']) .
        html_writer::empty_tag('input', ['type' => $type, 'name' => $name, 'id' => 'evt-inv-' . $name, 'class' => 'form-control form-control-sm'] +
            ($req ? ['required' => 'required'] : [])), 'mb-2');
};
$f2 = html_writer::tag('form', $hidden . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']) .
    html_writer::tag('h3', get_string('invaddone', 'local_evenements'), ['class' => 'h6']) .
    $field('lastname', get_string('lastname'), 'text', true) . $field('firstname', get_string('firstname'), 'text', true) .
    $field('email', get_string('email'), 'email', true) . $field('category', get_string('invcategory', 'local_evenements')) .
    $field('detail', get_string('invdetail', 'local_evenements')) . $field('quota', get_string('invquota', 'local_evenements'), 'number') .
    html_writer::tag('button', get_string('add'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'evt-panel']);
echo html_writer::div($f1 . $f2, 'evt-badge-grid');
echo $OUTPUT->footer();
