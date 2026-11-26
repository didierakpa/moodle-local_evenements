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
 * Participants of an event: list, search, actions on one or several tickets, change log.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;
use local_evenements\participant_manager;
use local_evenements\output\ui;

$id = required_param('id', PARAM_INT);
$search = trim(optional_param('q', '', PARAM_TEXT));
$filter = optional_param('st', '', PARAM_ALPHA);
$typefilter = optional_param('cat', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$showlog = optional_param('log', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
booking_manager::require_event_access($id, 'local/evenements:viewregistrations');
$canedit = booking_manager::can_manage($id);

$event = booking_manager::get_event($id);

$params = ['id' => $id];
if ($search !== '') {
    $params['q'] = $search;
}
if (in_array($filter, ['valid', 'used', 'cancelled', 'active'], true)) {
    $params['st'] = $filter;
} else {
    $filter = '';
}
if ($typefilter) {
    $params['cat'] = $typefilter;
}
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/registrations.php', $params));
ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('registrations', 'local_evenements'));
$PAGE->set_heading('');

global $DB;

// Bulk actions.
if ($action === 'bulk' && $canedit) {
    require_sesskey();
    $what = optional_param('bulkaction', '', PARAM_ALPHA);
    $ids = optional_param_array('tickets', [], PARAM_INT);
    if (!$ids || !in_array($what, ['cancel', 'restore', 'delete', 'resend', 'category', 'session'], true)) {
        redirect($PAGE->url, get_string('bulknothing', 'local_evenements'), null, \core\output\notification::NOTIFY_WARNING);
    }
    if ($what === 'delete' && !optional_param('confirm', 0, PARAM_BOOL)) {
        echo $OUTPUT->header();
        echo ui::event_header($event, 'registrations');
        $confirm = new moodle_url($PAGE->url, ['action' => 'bulk', 'bulkaction' => 'delete', 'confirm' => 1, 'sesskey' => sesskey()]);
        foreach (array_values($ids) as $n => $ticketid) {
            $confirm->param('tickets[' . $n . ']', (int) $ticketid);
        }
        echo $OUTPUT->confirm(get_string('bulkdeleteconfirm', 'local_evenements', count($ids)), $confirm, $PAGE->url);
        echo $OUTPUT->footer();
        exit;
    }
    $value = $what === 'category' ? optional_param('bulkcategory', 0, PARAM_INT) :
        ($what === 'session' ? optional_param('bulksession', 0, PARAM_INT) : 0);
    $report = participant_manager::bulk($event, $ids, $what, $value);
    $message = get_string('bulkdone', 'local_evenements', $report);
    if ($report->messages) {
        $message .= html_writer::alist(array_map('s', array_slice($report->messages, 0, 10)));
    }
    redirect($PAGE->url, $message, null, $report->failed ? \core\output\notification::NOTIFY_WARNING :
        \core\output\notification::NOTIFY_SUCCESS);
}

$categories = booking_manager::get_categories($id);
$sessions = booking_manager::get_sessions($id);

$where = ['t.eventid = :eventid'];
$sqlparams = ['eventid' => $id];
if ($filter === 'active') {
    $where[] = 't.status <> :cancelled';
    $sqlparams['cancelled'] = booking_manager::TICKET_CANCELLED;
} else if ($filter !== '') {
    $where[] = 't.status = :status';
    $sqlparams['status'] = $filter;
}
if ($typefilter) {
    $where[] = 't.tickettypeid = :typeid';
    $sqlparams['typeid'] = $typefilter;
}
if ($search !== '') {
    $likes = [];
    foreach (['t.firstname', 't.lastname', 't.email', 'b.contactname', 'b.email'] as $n => $field) {
        $likes[] = $DB->sql_like($field, ':q' . $n, false, false);
        $sqlparams['q' . $n] = '%' . $DB->sql_like_escape($search) . '%';
    }
    $where[] = '(' . implode(' OR ', $likes) . ')';
}
$sql = "SELECT t.id, t.bookingid, t.firstname, t.lastname, t.email, t.status, t.timeused, t.tickettypeid, t.note,
               b.contactname, b.email AS contactemail, b.timecreated AS booked, b.sessionid
          FROM {local_evenements_ticket} t
          JOIN {local_evenements_booking} b ON b.id = t.bookingid
         WHERE " . implode(' AND ', $where) . "
      ORDER BY t.lastname, t.firstname, t.id";
$rows = $DB->get_records_sql($sql, $sqlparams);

$counts = ['all' => 0, 'valid' => 0, 'used' => 0, 'cancelled' => 0];
foreach ($DB->get_records_sql("SELECT status, COUNT(id) AS n FROM {local_evenements_ticket} WHERE eventid = ? GROUP BY status",
        [$id]) as $count) {
    $counts[$count->status] = (int) $count->n;
    $counts['all'] += (int) $count->n;
}
$total = $counts['valid'] + $counts['used'];

echo $OUTPUT->header();
echo ui::event_header($event, 'registrations');
echo html_writer::tag('p', get_string('registrationssummary', 'local_evenements',
    (object) ['total' => $total, 'used' => $counts['used']]), ['class' => 'evt-note']);

// Toolbar.
$buttons = '';
if ($canedit) {
    $buttons .= html_writer::link(new moodle_url('/local/evenements/participant.php', ['id' => $id]),
        html_writer::tag('i', '', ['class' => 'fa-solid fa-user-plus me-1', 'aria-hidden' => 'true']) .
        get_string('participantadd', 'local_evenements'), ['class' => 'btn btn-primary me-2']);
}
$buttons .= html_writer::link(new moodle_url('/local/evenements/export.php', ['id' => $id]),
        get_string('exportcsv', 'local_evenements'), ['class' => 'btn btn-secondary me-2']) .
    html_writer::link(new moodle_url('/local/evenements/print.php', ['id' => $id]),
        get_string('printsheet', 'local_evenements'), ['class' => 'btn btn-secondary me-2', 'target' => '_blank']);
if ($canedit) {
    $buttons .= html_writer::link(new moodle_url('/local/evenements/moodleimport.php', ['id' => $id]),
        get_string('mimportmenu', 'local_evenements'), ['class' => 'btn btn-secondary me-2']);
    $buttons .= html_writer::link(new moodle_url('/local/evenements/import.php', ['id' => $id]),
        get_string('importguests', 'local_evenements'), ['class' => 'btn btn-secondary me-2']);
}
echo html_writer::div($buttons, 'mb-3 evt-toolbar');

// Filters.
$chips = '';
foreach (['' => 'all', 'active' => null, 'valid' => 'valid', 'used' => 'used', 'cancelled' => 'cancelled'] as $key => $countkey) {
    $label = $key === '' ? get_string('filterall', 'local_evenements') :
        ($key === 'active' ? get_string('filteractive', 'local_evenements') : get_string('ticketstatus_' . $key, 'local_evenements'));
    $n = $countkey ? $counts[$countkey] : $total;
    $url = new moodle_url($PAGE->url, ['st' => $key]);
    if ($key === '') {
        $url->remove_params('st');
    }
    $chips .= html_writer::link($url, s($label) . ' ' . html_writer::span($n, 'evt-chip-n'),
        ['class' => 'evt-chip' . ($filter === $key ? ' is-active' : '')]);
}
$form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
if ($filter !== '') {
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'st', 'value' => $filter]);
}
$form .= html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $search, 'class' => 'form-control form-control-sm',
    'placeholder' => get_string('participantsearch', 'local_evenements'), 'aria-label' => get_string('participantsearch', 'local_evenements')]);
if (count($categories) > 1) {
    $options = html_writer::tag('option', get_string('allcategories', 'local_evenements'), ['value' => 0]);
    foreach ($categories as $category) {
        $options .= html_writer::tag('option', format_string($category->name),
            ['value' => $category->id] + ((int) $category->id === $typefilter ? ['selected' => 'selected'] : []));
    }
    $form .= html_writer::tag('select', $options, ['name' => 'cat', 'class' => 'form-select form-select-sm custom-select',
        'aria-label' => get_string('category', 'local_evenements'), 'onchange' => 'this.form.submit()']);
}
$form .= html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary']);
echo html_writer::div(
    html_writer::div($chips, 'evt-chips') .
    html_writer::tag('form', $form, ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(), 'class' => 'evt-searchform']),
    'evt-filterbar');

// Table.
$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$table = new html_table();
$table->head = [];
if ($canedit) {
    $table->head[] = html_writer::empty_tag('input', ['type' => 'checkbox', 'class' => 'form-check-input', 'id' => 'evt-checkall',
        'aria-label' => get_string('selectall')]);
}
$table->head = array_merge($table->head, [
    get_string('participant', 'local_evenements'),
    get_string('category', 'local_evenements'),
], $sessions ? [get_string('session', 'local_evenements')] : [], [
    get_string('participantbookedby', 'local_evenements'),
    get_string('status', 'local_evenements'),
    get_string('timeused', 'local_evenements'),
], $canedit ? [html_writer::span(get_string('actions'), 'visually-hidden sr-only')] : []);
$table->attributes['class'] = 'table table-sm evt-ptable';
$table->responsive = false; // Our own wrapper, which lets the row menus overflow on wide screens.
foreach ($rows as $row) {
    $editurl = new moodle_url('/local/evenements/participant.php', ['id' => $id, 't' => $row->id]);
    $name = html_writer::span(s(core_text::strtoupper($row->lastname)) . ' ' . s($row->firstname), 'evt-pname');
    if ($canedit) {
        $name = html_writer::link($editurl, $name);
    }
    $name .= ((string) $row->email !== '' ? html_writer::div(s($row->email), 'evt-psub') : '') .
        ((string) $row->note !== '' ? html_writer::div(s($row->note), 'evt-psub') : '');
    $cells = [];
    if ($canedit) {
        $cells[] = html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'tickets[]', 'value' => $row->id,
            'form' => 'evt-bulkform', 'class' => 'form-check-input evt-check',
            'aria-label' => s($row->firstname . ' ' . $row->lastname)]);
    }
    $cells[] = $name;
    $cells[] = s(isset($categories[$row->tickettypeid]) ? format_string($categories[$row->tickettypeid]->name) : '');
    if ($sessions) {
        $cells[] = isset($sessions[$row->sessionid]) ? s(booking_manager::session_label($sessions[$row->sessionid])) : '';
    }
    $cells[] = s($row->contactname) . html_writer::div(s($row->contactemail), 'evt-psub');
    $cells[] = html_writer::span(get_string('ticketstatus_' . $row->status, 'local_evenements'),
        'badge evt-tstatus evt-tstatus-' . $row->status);
    $cells[] = $row->timeused ? userdate($row->timeused, $dateformat) : '';
    if ($canedit) {
        $act = function (string $a) use ($editurl) {
            return new moodle_url($editurl, ['action' => $a, 'sesskey' => sesskey()]);
        };
        $links = html_writer::link($editurl, get_string('edit'), ['class' => 'evt-rowmenu-item']);
        if ($row->status === booking_manager::TICKET_CANCELLED) {
            $links .= html_writer::link($act('restore'), get_string('participantrestore', 'local_evenements'), ['class' => 'evt-rowmenu-item']);
        } else {
            $links .= html_writer::link($act('resend'), get_string('participantresendbtn', 'local_evenements'), ['class' => 'evt-rowmenu-item']);
            $links .= html_writer::link($act('cancel'), get_string('participantcancel', 'local_evenements'), ['class' => 'evt-rowmenu-item']);
        }
        $links .= html_writer::link(new moodle_url($editurl, ['action' => 'delete']), get_string('participantdelete', 'local_evenements'),
            ['class' => 'evt-rowmenu-item text-danger']);
        // A <details> menu: works without the Bootstrap scripts, which the console layout may not load.
        $cells[] = html_writer::tag('details',
            html_writer::tag('summary', html_writer::tag('i', '', ['class' => 'fa-solid fa-ellipsis', 'aria-hidden' => 'true']) .
                html_writer::span(get_string('actions'), 'visually-hidden sr-only'), ['class' => 'btn btn-sm btn-outline-secondary']) .
            html_writer::div($links, 'evt-rowmenu-list'),
            ['class' => 'evt-rowmenu']);
    }
    $table->rowclasses[] = $row->status === booking_manager::TICKET_CANCELLED ? 'evt-row-cancelled' : '';
    $table->data[] = $cells;
}

if (!$rows) {
    echo $OUTPUT->notification(get_string($search !== '' || $filter !== '' || $typefilter ? 'participantnone' : 'participantempty',
        'local_evenements'), 'info');
} else {
    echo html_writer::div(html_writer::table($table), 'table-responsive evt-ptable-wrap');
}

// Bulk action bar (appears when at least one ticket is ticked).
if ($canedit && $rows) {
    $opts = ['' => get_string('bulkchoose', 'local_evenements'),
        'resend' => get_string('bulkresend', 'local_evenements'),
        'cancel' => get_string('bulkcancel', 'local_evenements'),
        'restore' => get_string('bulkrestore', 'local_evenements')];
    if (count($categories) > 1) {
        $opts['category'] = get_string('bulkcategory', 'local_evenements');
    }
    if (count($sessions) > 1) {
        $opts['session'] = get_string('bulksession', 'local_evenements');
    }
    $opts['delete'] = get_string('bulkdelete', 'local_evenements');
    $select = '';
    foreach ($opts as $value => $label) {
        $select .= html_writer::tag('option', $label, ['value' => $value]);
    }
    $extra = '';
    if (isset($opts['category'])) {
        $o = '';
        foreach ($categories as $category) {
            $o .= html_writer::tag('option', format_string($category->name), ['value' => $category->id]);
        }
        $extra .= html_writer::tag('select', $o, ['name' => 'bulkcategory', 'class' => 'form-select form-select-sm custom-select evt-bulk-extra',
            'data-for' => 'category', 'hidden' => 'hidden', 'aria-label' => get_string('category', 'local_evenements')]);
    }
    if (isset($opts['session'])) {
        $o = '';
        foreach ($sessions as $session) {
            $o .= html_writer::tag('option', s(booking_manager::session_label($session)), ['value' => $session->id]);
        }
        $extra .= html_writer::tag('select', $o, ['name' => 'bulksession', 'class' => 'form-select form-select-sm custom-select evt-bulk-extra',
            'data-for' => 'session', 'hidden' => 'hidden', 'aria-label' => get_string('session', 'local_evenements')]);
    }
    $inner = html_writer::span('', 'evt-bulk-count', ['id' => 'evt-bulk-count']) .
        html_writer::tag('select', $select, ['name' => 'bulkaction', 'id' => 'evt-bulk-action',
            'class' => 'form-select form-select-sm custom-select', 'aria-label' => get_string('actions')]) .
        $extra .
        html_writer::tag('button', get_string('bulkapply', 'local_evenements'), ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'bulk']);
    echo html_writer::tag('form', $inner, ['method' => 'post', 'action' => $PAGE->url->out(false), 'id' => 'evt-bulkform',
        'class' => 'evt-bulkbar', 'hidden' => 'hidden']);
    $one = json_encode(get_string('bulkselectedone', 'local_evenements'));
    $many = json_encode(get_string('bulkselected', 'local_evenements', '{n}'));
    echo html_writer::script(<<<JS
(function() {
    var form = document.getElementById('evt-bulkform'), all = document.getElementById('evt-checkall');
    if (!form) { return; }
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.evt-check'));
    var count = document.getElementById('evt-bulk-count'), action = document.getElementById('evt-bulk-action');
    function update() {
        var n = boxes.filter(function(b) { return b.checked; }).length;
        form.hidden = n === 0;
        count.textContent = n === 1 ? $one : $many.replace('{n}', n);
        if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
        boxes.forEach(function(b) { var tr = b.closest('tr'); if (tr) { tr.classList.toggle('is-selected', b.checked); } });
    }
    boxes.forEach(function(b) { b.addEventListener('change', update); });
    if (all) { all.addEventListener('change', function() { boxes.forEach(function(b) { b.checked = all.checked; }); update(); }); }
    action.addEventListener('change', function() {
        form.querySelectorAll('.evt-bulk-extra').forEach(function(s) { s.hidden = s.getAttribute('data-for') !== action.value; });
    });
    form.addEventListener('submit', function(e) { if (!action.value) { e.preventDefault(); action.focus(); } });
    // Only one row menu open at a time; a click elsewhere closes it.
    var menus = document.querySelectorAll('.evt-rowmenu');
    menus.forEach(function(d) { d.addEventListener('toggle', function() { if (d.open) { menus.forEach(function(o) { if (o !== d) { o.open = false; } }); } }); });
    document.addEventListener('click', function(e) { menus.forEach(function(d) { if (d.open && !d.contains(e.target)) { d.open = false; } }); });
    update();
})();
JS);
}

// Waiting list.
$waiting = $DB->get_records('local_evenements_waitlist', ['eventid' => $id], 'id ASC');
if ($waiting) {
    echo $OUTPUT->heading(get_string('waitlist', 'local_evenements'), 3);
    $wtable = new html_table();
    $wtable->head = [
        get_string('contactname', 'local_evenements'),
        get_string('contactemail', 'local_evenements'),
        get_string('waitlistquantity', 'local_evenements'),
        get_string('status', 'local_evenements'),
        get_string('date'),
    ];
    $wtable->attributes['class'] = 'table table-sm';
    foreach ($waiting as $w) {
        $wtable->data[] = [
            s($w->contactname),
            s($w->email),
            (int) $w->quantity,
            get_string('waitliststatus_' . $w->status, 'local_evenements'),
            userdate($w->timecreated, $dateformat),
        ];
    }
    echo html_writer::table($wtable);
}

// Change log.
$limit = $showlog ? 0 : 15;
$log = participant_manager::get_log($id, $limit ? $limit + 1 : 0);
if ($log) {
    echo html_writer::start_tag('details', ['class' => 'evt-logbox', 'id' => 'changelog'] + ($showlog ? ['open' => 'open'] : []));
    echo html_writer::tag('summary', get_string('changelog', 'local_evenements') . ' ' .
        html_writer::span(get_string('changelog_help', 'local_evenements'), 'evt-psub d-inline'));
    $more = $limit && count($log) > $limit;
    echo ui::changelog_table($more ? array_slice($log, 0, $limit) : $log);
    if ($more) {
        echo html_writer::link(new moodle_url($PAGE->url, ['log' => 1], 'changelog'), get_string('changelogall', 'local_evenements'));
    }
    echo html_writer::end_tag('details');
}

echo $OUTPUT->footer();
