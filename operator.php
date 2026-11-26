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
 * Door app (PWA): sign in with an access code, scan the tickets. Plain page without the Moodle theme,
 * installable on a phone. No Moodle account is needed.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

$base = new moodle_url('/local/evenements/');
$keys = ['ok', 'okoffline', 'used', 'usedat', 'wrong', 'cancelled', 'unknown', 'undone', 'undofail', 'pending', 'synced',
    'conflicts', 'online', 'offline', 'badcode', 'revoked', 'many', 'errnet', 'camunsupported', 'camdenied',
    'logoutpending', 'validate', 'confirmmanual', 'nomatch'];
$t = [];
foreach ($keys as $key) {
    $t[$key] = get_string('op_' . $key, 'local_evenements');
}
$cfg = [
    'api' => ($base . 'operator_api.php'),
    'sw' => ($base . 'operator-sw.js'),
    'jsqr' => file_exists(__DIR__ . '/pwa/jsqr.min.js') ? ($base . 'pwa/jsqr.min.js') : '',
    't' => $t,
];
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/evenements/operator.php'));
echo $OUTPUT->render_from_template('local_evenements/operator_app', [
    'lang' => current_language(),
    'base' => $base->out(false),
    'config' => json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
