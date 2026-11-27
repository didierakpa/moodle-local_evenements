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
 * Management page: list of all events with their actions.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evenements\booking_manager;

require_login();
$context = context_system::instance();
// Site managers see everything; co-organisers only see (and manage) the events they were given.
$ismanager = has_capability('local/evenements:manage', $context);
$myevents = booking_manager::organiser_event_ids();
if (!$ismanager && !$myevents) {
    require_capability('local/evenements:manage', $context);
}

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evenements/manage.php'));
\local_evenements\output\ui::console_layout();
$PAGE->add_body_class('evt-organiser');
$PAGE->set_title(get_string('manageevents', 'local_evenements'));
$PAGE->set_heading('');

// Duplication.
if ($action === 'duplicate' && $id && $ismanager && confirm_sesskey()) {
    $newid = booking_manager::duplicate_event($id);
    redirect(
        new moodle_url('/local/evenements/edit.php', ['id' => $newid]),
        get_string('eventduplicated', 'local_evenements'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// New event from a ready-made model.
if ($action === 'fromtemplate' && $ismanager && confirm_sesskey()) {
    $key = required_param('model', PARAM_ALPHANUMEXT);
    if (in_array($key, \local_evenements\templates::keys(), true)) {
        $newid = \local_evenements\templates::create_event($key);
        redirect(
            new moodle_url('/local/evenements/dashboard.php', ['id' => $newid]),
            get_string('eventfromtemplate', 'local_evenements'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Deletion, with confirmation.
if ($action === 'delete' && $id && $ismanager) {
    $event = booking_manager::get_event($id);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        booking_manager::delete_event($id);
        redirect(
            $PAGE->url,
            get_string('eventdeleted', 'local_evenements'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('deleteconfirm', 'local_evenements', format_string($event->name)),
        new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $PAGE->url
    );
    echo $OUTPUT->footer();
    exit;
}

global $DB;
$allevents = $DB->get_records('local_evenements_event', null, 'timestart DESC, id DESC');
if (!$ismanager) {
    $allevents = array_filter($allevents, function ($e) use ($myevents) {
        return in_array((int) $e->id, $myevents, true);
    });
}
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$query = trim(optional_param('q', '', PARAM_TEXT));
$now = time();
$ispast = function ($e) use ($now) {
    return booking_manager::is_past($e);
};
$counts = ['all' => count($allevents), 'open' => 0, 'draft' => 0, 'closed' => 0, 'past' => 0];
foreach ($allevents as $e) {
    if ($ispast($e)) {
        $counts['past']++;
    } else if (isset($counts[$e->status])) {
        $counts[$e->status]++;
    }
}
$events = array_filter($allevents, function ($e) use ($filter, $query, $ispast) {
    if ($query !== '' && \core_text::strpos(\core_text::strtolower($e->name), \core_text::strtolower($query)) === false) {
        return false;
    }
    switch ($filter) {
        case 'past':
            return $ispast($e);
        case 'open':
        case 'draft':
        case 'closed':
            return !$ispast($e) && $e->status === $filter;
        default:
            return true;
    }
});

echo $OUTPUT->header();
echo \local_evenements\output\ui::console(null, 'events');
echo html_writer::div(
    html_writer::div(html_writer::tag('h1', get_string('organiserspace', 'local_evenements')) .
        html_writer::tag('p', get_string('organiserintro', 'local_evenements')), 'evt-pagehead mb-0') .
    html_writer::div(
        ($ismanager ? html_writer::link(
            new moodle_url('/local/evenements/edit.php'),
            '+ ' . get_string('createevent', 'local_evenements'),
            ['class' => 'btn btn-primary btn-lg']
        ) : '') .
        html_writer::link(
            new moodle_url('/local/evenements/scan.php'),
            get_string('scan', 'local_evenements'),
            ['class' => 'btn btn-outline-secondary']
        ) .
        html_writer::link(
            new moodle_url('/local/evenements/index.php'),
            get_string('publicpage', 'local_evenements'),
            ['class' => 'btn btn-outline-secondary']
        ) .
        ($ismanager ? html_writer::link(
            new moodle_url('/local/evenements/preferences.php'),
            get_string('generalsettings', 'local_evenements'),
            ['class' => 'btn btn-outline-secondary']
        ) : ''),
        'd-flex flex-wrap gap-2 align-items-center'
    ),
    'd-flex flex-wrap justify-content-between align-items-center gap-3 mb-4'
);

$totalbooked = 0;
$opencount = 0;
$waitingcount = 0;
foreach ($allevents as $e) {
    $totalbooked += booking_manager::count_tickets((int) $e->id);
    $opencount += ($e->status === 'open') ? 1 : 0;
    $waitingcount += booking_manager::count_waiting((int) $e->id);
}
$stats = [
    [count($allevents), get_string('statevents', 'local_evenements')],
    [$opencount, get_string('statopen', 'local_evenements')],
    [$totalbooked, get_string('statticketsall', 'local_evenements')],
    [$waitingcount, get_string('statwaiting', 'local_evenements')],
];
echo html_writer::start_div('evt-stats');
foreach ($stats as [$value, $label]) {
    echo html_writer::div(html_writer::div($value, 'evt-stat-value') . html_writer::div($label, 'evt-stat-label'), 'evt-stat');
}
echo html_writer::end_div();

if (!$allevents) {
    echo html_writer::div(html_writer::tag('h2', get_string('noeventsyet', 'local_evenements')) .
        html_writer::tag('p', get_string('noeventshelp', 'local_evenements'), ['class' => 'evt-note']) .
        html_writer::link(
            new moodle_url('/local/evenements/edit.php'),
            '+ ' . get_string('createevent', 'local_evenements'),
            ['class' => 'btn btn-primary btn-lg']
        ), 'evt-panel text-center py-5');
}

$cards = '';
foreach (\local_evenements\templates::keys() as $key) {
    $cards .= html_writer::div(
        html_writer::tag('strong', get_string('tpl_' . $key . '_name', 'local_evenements')) .
        html_writer::div(get_string('tpl_' . $key . '_short', 'local_evenements'), 'evt-note') .
        html_writer::link(
            new moodle_url($PAGE->url, ['action' => 'fromtemplate', 'model' => $key, 'sesskey' => sesskey()]),
            get_string('usemodel', 'local_evenements'),
            ['class' => 'btn btn-sm btn-outline-primary mt-2']
        ),
        'evt-model'
    );
}
if ($ismanager) {
    echo html_writer::div(html_writer::tag('h2', get_string('startfrommodel', 'local_evenements')) .
    html_writer::tag('p', get_string('startfrommodelhelp', 'local_evenements'), ['class' => 'evt-note']) .
    html_writer::div($cards, 'evt-models'), 'evt-panel mb-4', ['id' => 'models']);
}

if ($allevents) {
    $tabs = '';
    foreach (['all', 'open', 'draft', 'closed', 'past'] as $f) {
        $tabs .= html_writer::link(
            new moodle_url($PAGE->url, ['filter' => $f, 'q' => $query]),
            get_string('filter_' . $f, 'local_evenements') . html_writer::tag('small', $counts[$f]),
            ['class' => 'evt-filter' . ($filter === $f ? ' is-active' : '')]
        );
    }
    $searchform = html_writer::tag(
        'form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'filter', 'value' => $filter]) .
        html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $query,
            'class' => 'form-control form-control-sm', 'placeholder' => get_string('searchevents', 'local_evenements'),
            'aria-label' => get_string('searchevents', 'local_evenements')]),
        ['method' => 'get', 'action' => $PAGE->url->out(false)]
    );
    echo html_writer::div($tabs . $searchform, 'evt-filters');
    if (!$events) {
        echo html_writer::div(get_string('noeventsfilter', 'local_evenements'), 'evt-panel evt-empty');
    }
}

$table = new html_table();
$table->head = [
    get_string('eventname', 'local_evenements'),
    get_string('date'),
    get_string('status', 'local_evenements'),
    get_string('ticketsbooked', 'local_evenements'),
    get_string('actions', 'local_evenements'),
];
$table->attributes['class'] = 'evt-table';

foreach ($events as $event) {
    $count = booking_manager::count_tickets((int) $event->id);
    $booked = $event->capacity ? $count . ' / ' . $event->capacity : (string) $count;

    $mainlinks = [
        html_writer::link(
            new moodle_url('/local/evenements/dashboard.php', ['id' => $event->id]),
            get_string('opendashboard', 'local_evenements'),
            ['class' => 'btn btn-sm btn-primary']
        ),
    ];
    $more = [
        html_writer::link(
            new moodle_url('/local/evenements/event.php', ['id' => $event->id]),
            get_string('preview', 'local_evenements'),
            ['class' => 'dropdown-item']
        ),
    ];
    if (true) {
        $mainlinks[] = html_writer::link(
            new moodle_url('/local/evenements/registrations.php', ['id' => $event->id]),
            get_string('registrations', 'local_evenements'),
            ['class' => 'btn btn-sm btn-outline-secondary']
        );
        $more[] = html_writer::link(
            new moodle_url('/local/evenements/export.php', ['id' => $event->id]),
            get_string('exportcsv', 'local_evenements'),
            ['class' => 'dropdown-item']
        );
    }
    $more[] = html_writer::link(
        new moodle_url('/local/evenements/questions.php', ['id' => $event->id]),
        get_string('questionnaires', 'local_evenements'),
        ['class' => 'dropdown-item']
    );
    if (true) {
        $more[] = html_writer::link(
            new moodle_url('/local/evenements/results.php', ['id' => $event->id]),
            get_string('results', 'local_evenements'),
            ['class' => 'dropdown-item']
        );
    }
    $more[] = html_writer::link(
        new moodle_url('/local/evenements/message.php', ['id' => $event->id]),
        get_string('sendmessage', 'local_evenements'),
        ['class' => 'dropdown-item']
    );
    if ($ismanager) {
        $more[] = html_writer::link(
            new moodle_url(
                $PAGE->url,
                ['action' => 'duplicate', 'id' => $event->id, 'sesskey' => sesskey()]
            ),
            get_string('duplicate', 'local_evenements'),
            ['class' => 'dropdown-item']
        );
        $more[] = html_writer::link(
            new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $event->id]),
            get_string('delete'),
            ['class' => 'dropdown-item text-danger']
        );
    }
    $mainlinks[] = html_writer::div(
        html_writer::tag('button', get_string('moreactions', 'local_evenements'), ['type' => 'button',
            'class' => 'btn btn-sm btn-outline-secondary dropdown-toggle', 'data-bs-toggle' => 'dropdown',
            'aria-expanded' => 'false']) .
        html_writer::div(implode('', $more), 'dropdown-menu'),
        'dropdown d-inline-block'
    );
    $links = $mainlinks;

    $table->data[] = [
        html_writer::link(new moodle_url('/local/evenements/dashboard.php', ['id' => $event->id]), format_string($event->name), [
            'class' => 'evt-name']),
        userdate($event->timestart, get_string('strftimedatefullshort', 'langconfig')),
        \local_evenements\output\ui::status_badge($event),
        $booked,
        html_writer::div(implode('', $links), 'd-flex flex-wrap gap-1'),
    ];
}
if ($events) {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
