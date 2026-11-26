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

namespace local_evenements\output;

use local_evenements\booking_manager;

/**
 * Small reusable pieces of the public interface (date badge, availability, steps).
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {
    /**
     * Font Awesome icon, decorative.
     *
     * @param string $name Icon name without prefix, e.g. 'calendar'
     * @return string
     */
    public static function icon(string $name): string {
        return \html_writer::tag('i', '', ['class' => 'fa-regular fa-' . $name . ' evt-icon', 'aria-hidden' => 'true']);
    }

    /**
     * Calendar-like badge with the day and the month.
     *
     * @param \stdClass $event
     * @return string
     */
    public static function date_badge(\stdClass $event): string {
        return \html_writer::div(
            \html_writer::span(userdate($event->timestart, '%d'), 'evt-day') .
            \html_writer::span(userdate($event->timestart, '%b'), 'evt-month'),
            'evt-datebadge',
            ['aria-hidden' => 'true']
        );
    }

    /**
     * Date and place as an icon list.
     *
     * @param \stdClass $event
     * @return string
     */
    public static function meta(\stdClass $event): string {
        $items = \html_writer::tag('li', self::icon('calendar') . \html_writer::span(s(booking_manager::event_when($event))));
        if (!empty($event->location)) {
            $items .= \html_writer::tag('li', self::icon('map') . \html_writer::span(s($event->location)));
        }
        return \html_writer::tag('ul', $items, ['class' => 'evt-meta']);
    }

    /**
     * Availability: progress bar and text, or a badge when full or not open.
     *
     * @param \stdClass $event
     * @return string
     */
    public static function availability(\stdClass $event): string {
        $remaining = booking_manager::remaining($event);
        if (!booking_manager::is_open($event)) {
            return \html_writer::span(get_string('bookingnotopen', 'local_evenements'), 'badge bg-secondary text-white');
        }
        if ($remaining === 0) {
            return \html_writer::span(get_string('full', 'local_evenements'), 'badge bg-danger text-white');
        }
        if ($remaining === null) {
            return \html_writer::span(get_string('bookingopen', 'local_evenements'), 'badge bg-success text-white');
        }
        $capacity = max(1, (int) $event->capacity);
        $used = $capacity - $remaining;
        $percent = (int) round(100 * $used / $capacity);
        $class = $remaining <= max(3, (int) ceil($capacity * 0.1)) ? 'evt-bar-low' : '';
        $bar = \html_writer::div(
            \html_writer::div('', 'evt-bar-fill ' . $class, ['style' => 'width:' . $percent . '%']),
            'evt-bar',
            ['role' => 'progressbar', 'aria-valuenow' => $percent, 'aria-valuemin' => 0, 'aria-valuemax' => 100,
            'aria-label' => get_string(
                'remaining',
                'local_evenements',
                $remaining
            )]
        );
        return \html_writer::div($bar . \html_writer::span(
            get_string('remaining', 'local_evenements', $remaining),
            'evt-remaining ' . $class
        ), 'evt-availability');
    }

    /**
     * Page layout of the organiser pages (inside the Moodle theme). To call before the header.
     */
    public static function console_layout(): void {
        global $PAGE;
        $PAGE->set_pagelayout('standard');
        $PAGE->set_secondary_navigation(false);
    }

    /**
     * Table of the change log (participants).
     *
     * @param \stdClass[] $rows Lines from participant_manager::get_log()
     * @return string
     */
    public static function changelog_table(array $rows): string {
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        $table = new \html_table();
        $table->head = [get_string('date'), get_string('changelogwho', 'local_evenements'),
            get_string('changelogaction', 'local_evenements'), get_string('changelogdetails', 'local_evenements')];
        $table->attributes['class'] = 'table table-sm evt-changelog';
        foreach ($rows as $row) {
            $who = $row->userid ? fullname($row) : get_string('changelogsomeone', 'local_evenements');
            $details = trim((string) $row->details) !== '' ? nl2br(s($row->details)) :
                \html_writer::span(get_string('changelogerased', 'local_evenements'), 'text-muted');
            $table->data[] = [
                userdate($row->timecreated, $dateformat),
                s($who),
                \html_writer::span(get_string('changelog_' . $row->action, 'local_evenements'), 'evt-logaction evt-log-'
                    . $row->action),
                $details,
            ];
        }
        return \html_writer::table($table);
    }

    /**
     * Font Awesome solid icon for the organiser console.
     *
     * @param string $name
     * @return string
     */
    private static function sicon(string $name): string {
        return \html_writer::tag('i', '', ['class' => 'fa-solid fa-' . $name, 'aria-hidden' => 'true']);
    }

    /**
     * One link of the console menu.
     *
     * @param \moodle_url $url
     * @param string $label
     * @param string $icon
     * @param bool $active
     * @param string|int|null $badge
     * @param array $extra extra attributes (target...)
     * @return string
     */
    private static function navitem(
        \moodle_url $url,
        string $label,
        string $icon,
        bool $active,
        $badge = null,
        array $extra = []
    ): string {
        $content = self::sicon($icon) . \html_writer::span($label, 'evt-nav-label');
        if ($badge !== null && $badge !== '') {
            $content .= \html_writer::span((string) $badge, 'evt-nav-badge');
        }
        return \html_writer::link($url, $content, ['class' => 'evt-nav-item' . ($active ? ' is-active' : ''),
            'aria-current' => $active ? 'page' : null, 'data-label' => $label] + $extra);
    }

    /**
     * Collapsible group of the console menu.
     *
     * @param string $key
     * @param string $title
     * @param string $icon
     * @param string[] $items
     * @param bool $open
     * @return string
     */
    private static function navgroup(string $key, string $title, string $icon, array $items, bool $open): string {
        $items = array_filter($items);
        if (!$items) {
            return '';
        }
        return \html_writer::tag(
            'details',
            \html_writer::tag('summary', self::sicon($icon) . \html_writer::span($title, 'evt-nav-label') .
                self::sicon('chevron-down'), ['class' => 'evt-nav-group-title']) .
            \html_writer::div(implode('', $items), 'evt-nav-group-body'),
            ['class' => 'evt-nav-group', 'data-key' => $key] + ($open ? ['open' => 'open'] : [])
        );
    }

    /**
     * Side menu of the organiser console: global menu, or menu of one event (with an event card and live figures).
     * Also a command palette (Ctrl+K) and a drawer for phones.
     *
     * @param \stdClass|null $event Current event, null for the global pages
     * @param string $active Key of the current page
     * @return string
     */
    public static function console(?\stdClass $event, string $active): string {
        global $DB, $USER, $PAGE;
        $ctx = \context_system::instance();
        $ismanager = has_capability('local/evenements:manage', $ctx);
        $u = function (string $path, array $params = []) {
            return new \moodle_url('/local/evenements/' . $path, $params);
        };
        $palette = [];
        $push = function (string $label, \moodle_url $url, string $kind) use (&$palette) {
            $palette[] = ['l' => $label, 'u' => $url->out(false), 'k' => $kind];
        };

        $menu = '';
        if ($ismanager) {
            $menu .= \html_writer::link(
                $u('edit.php'),
                self::sicon('plus') . get_string('createevent', 'local_evenements'),
                ['class' => 'evt-cta']
            );
            $push(get_string('createevent', 'local_evenements'), $u('edit.php'), 'action');
        }

        if ($event === null) {
            $glabel = get_string('navevents', 'local_evenements');
            $items = [
                self::navitem($u('manage.php'), $glabel, 'calendar-days', $active === 'events'),
                $ismanager ? self::navitem(
                    $u('manage.php'),
                    get_string('navmodels', 'local_evenements'),
                    'clone',
                    false,
                    null,
                    ['data-anchor' => 'models']
                ) : '',
                self::navitem(
                    $u('exports.php'),
                    get_string('navexports', 'local_evenements'),
                    'file-export',
                    $active === 'exports'
                ),
            ];
            $menu .= implode('', array_filter($items));
            $menu .= self::navgroup('door', get_string('navdoor', 'local_evenements'), 'door-open', [
                self::navitem($u('scan.php'), get_string('scan', 'local_evenements'), 'qrcode', $active === 'scan'),
                self::navitem(
                    $u('operator.php'),
                    get_string('navapp', 'local_evenements'),
                    'mobile-screen',
                    false,
                    null,
                    ['target' => '_blank', 'rel' => 'noopener']
                ),
            ], true);
            if ($ismanager) {
                $menu .= \html_writer::div(get_string('navadvanced', 'local_evenements'), 'evt-nav-section');
                $menu .= self::navitem(
                    $u('preferences.php'),
                    get_string('generalsettings', 'local_evenements'),
                    'gear',
                    $active === 'prefs'
                );
            }
            foreach ([['manage.php', 'navevents'], ['exports.php', 'navexports'], ['scan.php', 'scan']] as [$path, $str]) {
                $push(get_string($str, 'local_evenements'), $u($path), 'page');
            }
            $head = '';
        } else {
            $id = (int) $event->id;
            $menu .= self::navitem(
                $u('manage.php'),
                get_string('navalltoevents', 'local_evenements'),
                'arrow-left',
                false,
                null,
                ['class' => 'evt-nav-item evt-nav-back']
            );
            // Event card with live figures.
            $ticketcount = booking_manager::count_tickets($id);
            $used = $DB->count_records('local_evenements_ticket', ['eventid' => $id, 'status' => booking_manager::TICKET_USED]);
            $waiting = $DB->count_records('local_evenements_waitlist', ['eventid' => $id, 'status' => 'waiting']);
            $imageurl = booking_manager::get_image_url($event);
            $now = time();
            $end = (int) ($event->timeend ?: $event->timestart + 4 * HOURSECS);
            $islive = $event->status !== booking_manager::STATUS_DRAFT && $now >= $event->timestart - HOURSECS
                && $now <= $end + HOURSECS;
            $capacity = (int) $event->capacity;
            $gauge = $capacity ? min(100, (int) round(100 * $ticketcount / $capacity)) : null;
            $card = \html_writer::div(
                ($imageurl ? \html_writer::empty_tag('img', ['src' => $imageurl, 'alt' => '', 'class' => 'evt-sidecard-img'])
                    : '') .
                \html_writer::div(
                    \html_writer::span(
                        get_string('status_' . $event->status, 'local_evenements'),
                        'evt-badge evt-badge-' . $event->status
                    ) .
                    ($islive ? \html_writer::span(get_string('navlive', 'local_evenements'), 'evt-live') : '') .
                    \html_writer::div(format_string($event->name), 'evt-sidecard-name') .
                    \html_writer::div(
                        userdate($event->timestart, get_string('strftimedatefullshort', 'langconfig')),
                        'evt-sidecard-date'
                    ) .
                    ($gauge !== null ? \html_writer::div(
                        \html_writer::div('', 'evt-sidecard-fill', ['style' => 'width:' . $gauge . '%']),
                        'evt-sidecard-gauge',
                        ['title' => $ticketcount . ' / ' . $capacity]
                    ) : '') .
                    ($islive || $used ? \html_writer::div(get_string(
                        'navinside',
                        'local_evenements',
                        (object) ['used' => $used, 'total' => $ticketcount]
                    ), 'evt-sidecard-live') : ''),
                    'evt-sidecard-body'
                ),
                'evt-sidecard'
            );
            $menu .= $card;

            $nsessions = $DB->count_records('local_evenements_session', ['eventid' => $id]);
            $nop = $DB->count_records('local_evenements_operator', ['eventid' => $id]);
            $norg = $DB->count_records('local_evenements_organiser', ['eventid' => $id]);
            $nq = $DB->count_records('local_evenements_question', ['eventid' => $id]);
            $ninv = $DB->count_records('local_evenements_invitee', ['eventid' => $id]);
            $islist = booking_manager::is_list_event($event);
            $links = [
                'invitees' => ['invitees.php', 'tabinvitees', 'address-book', $ninv ?: null],
                'dashboard' => ['dashboard.php', 'navoverview', 'gauge-high', null],
                'report' => ['report.php', 'report', 'chart-pie', null],
                'settings' => ['edit.php', 'tabsettings', 'sliders', null],
                'sessions' => ['sessions.php', 'tabsessions', 'clock', $nsessions ?: null],
                'registrations' => ['registrations.php', 'registrations', 'users', $ticketcount ?: null],
                'import' => ['import.php', 'importguests', 'file-import', null],
                'moodleimport' => ['moodleimport.php', 'mimportmenu', 'user-plus', null],
                'questions' => ['questions.php', 'questionnaires', 'list-check', $nq ?: null],
                'results' => ['results.php', 'results', 'chart-column', null],
                'message' => ['message.php', 'sendmessage', 'envelope', null],
                'operators' => ['operators.php', 'taboperators', 'mobile-screen', $nop ?: null],
                'organisers' => ['organisers.php', 'taborganisers', 'user-group', $norg ?: null],
                'badges' => ['badges.php', 'tabbadges', 'id-badge', null],
            ];
            $item = function (string $key) use ($links, $u, $id, $active) {
                [$path, $str, $icon, $badge] = $links[$key];
                return self::navitem(
                    $u($path, ['id' => $id]),
                    get_string($str, 'local_evenements'),
                    $icon,
                    $key === $active,
                    $badge
                );
            };
            $menu .= $item('dashboard');
            $menu .= $item('report');
            $menu .= self::navgroup(
                'ticketing',
                get_string('navticketing', 'local_evenements'),
                'ticket',
                [$item('settings'), $item('sessions')],
                in_array($active, ['settings', 'sessions'], true)
            );
            $menu .= self::navgroup(
                'guests',
                get_string('navparticipants', 'local_evenements'),
                'users',
                [$islist ? $item('invitees') : '', $item('registrations'), $item('moodleimport'), $item('import'),
                    self::navitem(
                        $u('print.php', ['id' => $id]),
                        get_string('printsheet', 'local_evenements'),
                        'print',
                        false,
                        null,
                        ['target' => '_blank', 'rel' => 'noopener']
                    ),
                    $waiting ? self::navitem(
                        $u('registrations.php', ['id' => $id]),
                        get_string('waitlist', 'local_evenements'),
                        'hourglass-half',
                        false,
                        $waiting
                    ) : ''],
                in_array($active, ['invitees', 'registrations', 'import', 'moodleimport'], true)
            );
            $menu .= self::navgroup(
                'forms',
                get_string('navforms', 'local_evenements'),
                'clipboard-question',
                [$item('questions'), $item('results')],
                in_array($active, ['questions', 'results'], true)
            );
            $menu .= self::navgroup('comm', get_string('navcomm', 'local_evenements'), 'paper-plane', [$item('message')], false);
            $menu .= self::navgroup('door', get_string('navdoor', 'local_evenements'), 'door-open', [
                self::navitem($u('scan.php'), get_string('scan', 'local_evenements'), 'qrcode', false),
                $item('operators'), $item('organisers'), $item('badges'),
            ], in_array($active, ['scan', 'operators', 'organisers', 'badges'], true));
            $menu .= \html_writer::div(get_string('navmore', 'local_evenements'), 'evt-nav-section');
            $menu .= self::navitem($u('export.php', ['id' => $id]), get_string('exportcsv', 'local_evenements'), 'file-csv', false);
            $menu .= self::navitem(
                $u('event.php', ['id' => $id]),
                get_string('viewpublicpage', 'local_evenements'),
                'arrow-up-right-from-square',
                false,
                null,
                ['target' => '_blank', 'rel' => 'noopener']
            );
            foreach ($links as $key => [$path, $str]) {
                if ($key === 'invitees' && !$islist) {
                    continue;
                }
                $push(
                    format_string($event->name) . ' › ' . get_string($str, 'local_evenements'),
                    $u($path, ['id' => $id]),
                    'page'
                );
            }
            $push(get_string('scan', 'local_evenements'), $u('scan.php'), 'page');
            $push(get_string('printsheet', 'local_evenements'), $u('print.php', ['id' => $id]), 'page');
        }

        // Other events: jump straight to their dashboard.
        $others = $DB->get_records('local_evenements_event', null, 'timestart DESC', 'id, name, status, timestart', 0, 60);
        $mine = $ismanager ? null : booking_manager::organiser_event_ids();
        foreach ($others as $key => $e) {
            if ($mine !== null && !in_array((int) $e->id, $mine, true)) {
                unset($others[$key]);
                continue;
            }
            $push(format_string($e->name), $u('dashboard.php', ['id' => $e->id]), 'event');
        }

        $brand = \html_writer::div(
            self::sicon('ticket') . \html_writer::span(get_string('pluginname', 'local_evenements')),
            'evt-brand'
        );
        $search = \html_writer::tag('button', self::sicon('magnifying-glass') .
            \html_writer::span(get_string('navsearch', 'local_evenements'), 'evt-nav-label') .
            \html_writer::tag('kbd', 'Ctrl K'), ['type' => 'button', 'class' => 'evt-search-btn', 'data-evt-palette' => '1']);
        $close = \html_writer::tag('button', self::sicon('xmark'), ['type' => 'button', 'class' => 'evt-side-close',
            'aria-label' => get_string('close', 'local_evenements')]);

        $aside = \html_writer::tag(
            'aside',
            \html_writer::div($brand . $close, 'evt-side-top') . $search .
            \html_writer::tag('nav', $menu, ['class' => 'evt-nav', 'aria-label' => get_string('eventmenu', 'local_evenements')]),
            ['class' => 'evt-side', 'id' => 'evt-side']
        );
        $mobilebar = \html_writer::div(
            \html_writer::tag(
                'button',
                self::sicon('bars') . \html_writer::span(get_string('navmenu', 'local_evenements')),
                ['type' => 'button', 'class' => 'evt-menubtn', 'aria-controls' => 'evt-side', 'aria-expanded' => 'false']
            ) .
            \html_writer::span(
                $event ? format_string($event->name) : get_string('manageevents', 'local_evenements'),
                'evt-mobiletitle'
            ) .
            \html_writer::tag('button', self::sicon('magnifying-glass'), ['type' => 'button', 'class' => 'evt-menubtn evt-iconbtn',
                'data-evt-palette' => '1', 'aria-label' => get_string('navsearch', 'local_evenements')]),
            'evt-mobilebar'
        );
        $paletteui = \html_writer::div(
            \html_writer::div(
                \html_writer::empty_tag('input', ['type' => 'search', 'id' => 'evt-pal-input', 'autocomplete' => 'off',
                    'placeholder' => get_string('navsearch', 'local_evenements'),
                    'aria-label' => get_string('navsearch', 'local_evenements')]) .
                \html_writer::tag('ul', '', ['id' => 'evt-pal-list', 'role' => 'listbox']),
                'evt-pal-box'
            ),
            'evt-pal hidden',
            ['id' => 'evt-pal', 'hidden' => 'hidden']
        );

        $aside = str_replace('class="evt-side"', 'class="evt-side" data-palette="' . s(json_encode(
            $palette,
            JSON_UNESCAPED_UNICODE
        )) . '"', $aside);
        $PAGE->requires->js_call_amd('local_evenements/console', 'init');
        return $aside . $mobilebar . $paletteui;
    }

    /**
     * Header of the organiser pages of an event: side menu (console), title, status and public link.
     *
     * @param \stdClass $event
     * @param string $active dashboard, registrations, questions, results, message, settings...
     * @return string
     */
    public static function event_header(\stdClass $event, string $active): string {
        $status = \html_writer::span(
            get_string('status_' . $event->status, 'local_evenements'),
            'evt-badge evt-badge-' . $event->status
        );
        $public = \html_writer::link(
            new \moodle_url('/local/evenements/event.php', ['id' => $event->id]),
            get_string('viewpublicpage', 'local_evenements'),
            ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']
        );

        return self::console($event, $active) . \html_writer::div(
            \html_writer::div(
                \html_writer::div(\html_writer::tag('h1', format_string($event->name)) . $status, 'evt-orgtitle') .
                \html_writer::div($public, 'evt-orgactions'),
                'evt-orghead'
            ),
            'evt-org'
        );
    }

    /**
     * Booking steps indicator.
     *
     * @param int $current 1 to 3
     * @return string
     */
    public static function steps(int $current): string {
        $labels = [get_string('stepchoose', 'local_evenements'), get_string('stepguests', 'local_evenements'),
            get_string('stepdone', 'local_evenements')];
        $out = '';
        foreach ($labels as $i => $label) {
            $n = $i + 1;
            $state = $n < $current ? 'is-done' : ($n === $current ? 'is-current' : '');
            $out .= \html_writer::tag(
                'li',
                \html_writer::span($n < $current ? '✓' : (string) $n, 'evt-stepnum') .
                \html_writer::span($label, 'evt-steplabel'),
                ['class' => 'evt-step ' . $state, 'aria-current' => $n === $current ? 'step' : null]
            );
        }
        return \html_writer::tag('ol', $out, ['class' => 'evt-steps']);
    }
}
