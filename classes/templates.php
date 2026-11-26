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

namespace local_evenements;

/**
 * Ready-made event models: a complete draft event (text, categories, ticket design, questions)
 * that the organiser can edit, duplicate or delete.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class templates {
    /** @var array Model keys with colour, capacity, per-booking limit, weeks ahead and questions. */
    private const MODELS = [
        'jpo' => ['image' => 'jpo', 'color' => '#1d4e89', 'capacity' => 200, 'perbooking' => 5, 'weeks' => 10,
            'questions' => [['before', 'booking', 'choice', 1], ['after', 'booking', 'longtext', 0]]],
        'graduation' => ['image' => 'graduation', 'color' => '#8a1c3c', 'capacity' => 150, 'perbooking' => 4, 'weeks' => 30,
            'access' => 'list', 'guests' => 3,
            'questions' => [['before', 'booking', 'choice', 0], ['before', 'booking', 'choice', 0], ['after', 'booking',
                'longtext', 0]]],
        'forum' => ['image' => 'forum', 'color' => '#2e7d32', 'capacity' => 80, 'perbooking' => 3, 'weeks' => 14,
            'questions' => [['before', 'booking', 'text', 1], ['after', 'booking', 'longtext', 0]]],
    ];

    /**
     * Keys of the available models.
     *
     * @return string[]
     */
    public static function keys(): array {
        return array_keys(self::MODELS);
    }

    /**
     * Creates a draft event from a model.
     *
     * @param string $key Model key.
     * @param bool $prefix Add the "Model" prefix to the name (used for the examples created at install).
     * @return int Event id.
     */
    public static function create_event(string $key, bool $prefix = false): int {
        global $DB, $USER;
        $m = self::MODELS[$key];
        $str = function (string $id) use ($key) {
            return get_string('tpl_' . $key . '_' . $id, 'local_evenements');
        };
        $now = time();
        $start = strtotime('next saturday 14:00', $now + $m['weeks'] * WEEKSECS);

        $event = new \stdClass();
        $event->name = ($prefix ? get_string('tplprefix', 'local_evenements') : '') . $str('name');
        $event->description = $str('desc');
        $event->descriptionformat = FORMAT_HTML;
        $event->location = $str('place');
        $event->timestart = $start;
        $event->timeend = $start + 4 * HOURSECS;
        $event->openfrom = 0;
        $event->openuntil = 0;
        $event->capacity = $m['capacity'];
        $event->maxperbooking = $m['perbooking'];
        $event->maxperperson = 0;
        $event->status = booking_manager::STATUS_DRAFT;
        $event->ticketcolor = $m['color'];
        $event->ticketformat = 'compact';
        $event->imagetheme = $m['image'] ?? 'generic';
        $event->accessmode = $m['access'] ?? 'open';
        $event->guestquota = $m['guests'] ?? 3;
        $event->tickettitle = $str('ticket');
        $event->tickettext = $str('tickettext');
        $event->timecreated = $now;
        $event->timemodified = $now;
        $event->usermodified = $USER->id ?? 0;
        $eventid = $DB->insert_record('local_evenements_event', $event);

        booking_manager::save_categories($eventid, preg_split('/\R/', $str('categories')));

        $order = 0;
        foreach ($m['questions'] as $i => [$moment, $scope, $type, $required]) {
            $question = new \stdClass();
            $question->eventid = $eventid;
            $question->moment = $moment;
            $question->scope = $scope;
            $question->type = $type;
            $question->name = $str('q' . ($i + 1));
            $question->options = $type === 'choice' ? $str('q' . ($i + 1) . 'opts') : null;
            $question->isrequired = $required;
            $question->sortorder = ++$order;
            $DB->insert_record('local_evenements_question', $question);
        }
        return $eventid;
    }

    /**
     * Creates the example drafts once, on install or upgrade.
     */
    public static function seed(): void {
        global $DB;
        if ($DB->count_records('local_evenements_event') > 0) {
            return;
        }
        foreach (self::keys() as $key) {
            self::create_event($key, true);
        }
    }
}
