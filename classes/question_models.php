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

defined('MOODLE_INTERNAL') || die();

/**
 * Ready-made questionnaires (before and after the event) that can be added to any event in one click.
 *
 * Texts come from the language files: qm_<model> (title), qm_<model>_desc, qm_<model>_q<N>, qm_<model>_q<N>opts.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_models {

    /**
     * Models: key => [moment, icon, questions[[type, scope, required]]].
     * Types: text, longtext, choice, multichoice.
     */
    public const MODELS = [
        'buffet' => ['before', 'utensils', [['choice', 'booking', 0], ['text', 'booking', 0]]],
        'access' => ['before', 'wheelchair', [['choice', 'booking', 0], ['text', 'booking', 0]]],
        'discovery' => ['before', 'bullhorn', [['choice', 'booking', 0], ['choice', 'booking', 0], ['text', 'booking', 0]]],
        'travel' => ['before', 'car', [['choice', 'booking', 0], ['choice', 'booking', 0]]],
        'partner' => ['before', 'handshake', [['text', 'booking', 1], ['text', 'booking', 0], ['choice', 'booking', 0]]],
        'guestinfo' => ['before', 'user-tag', [['choice', 'guest', 0], ['text', 'guest', 0]]],
        'satisfaction' => ['after', 'face-smile', [['choice', 'booking', 1], ['choice', 'booking', 0], ['longtext', 'booking', 0], ['longtext', 'booking', 0]]],
        'orientation' => ['after', 'compass', [['choice', 'booking', 0], ['text', 'booking', 0], ['choice', 'booking', 0]]],
        'ceremony' => ['after', 'award', [['choice', 'booking', 0], ['longtext', 'booking', 0]]],
        'nextyear' => ['after', 'calendar-plus', [['choice', 'booking', 0], ['longtext', 'booking', 0]]],
    ];

    /**
     * Number of questions of a model.
     *
     * @param string $key
     * @return int
     */
    public static function count(string $key): int {
        return count(self::MODELS[$key][2]);
    }

    /**
     * Texts of the questions of a model (in the current language).
     *
     * @param string $key
     * @return array[] [name, type, scope, required, options]
     */
    public static function questions(string $key): array {
        $out = [];
        foreach (self::MODELS[$key][2] as $i => [$type, $scope, $required]) {
            $n = $i + 1;
            $out[] = [
                'name' => get_string("qm_{$key}_q{$n}", 'local_evenements'),
                'type' => $type,
                'scope' => $scope,
                'required' => $required,
                'options' => in_array($type, ['choice', 'multichoice'], true) ? get_string("qm_{$key}_q{$n}opts", 'local_evenements') : '',
            ];
        }
        return $out;
    }

    /**
     * Add the questions of a model to an event (questions already present with the same text and moment are skipped).
     *
     * @param int $eventid
     * @param string $key
     * @return int Number of questions added
     */
    public static function add_to_event(int $eventid, string $key): int {
        global $DB;
        if (!isset(self::MODELS[$key])) {
            return 0;
        }
        $moment = self::MODELS[$key][0];
        $order = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {local_evenements_question} WHERE eventid = ?', [$eventid]);
        $added = 0;
        foreach (self::questions($key) as $q) {
            if ($DB->record_exists('local_evenements_question', ['eventid' => $eventid, 'moment' => $moment, 'name' => $q['name']])) {
                continue;
            }
            $DB->insert_record('local_evenements_question', (object) [
                'eventid' => $eventid, 'moment' => $moment, 'scope' => $moment === 'after' ? 'booking' : $q['scope'],
                'type' => $q['type'], 'name' => \core_text::substr($q['name'], 0, 255),
                'options' => $q['type'] === 'choice' || $q['type'] === 'multichoice' ? $q['options'] : null,
                'isrequired' => $q['required'], 'sortorder' => ++$order,
            ]);
            $added++;
        }
        return $added;
    }
}
