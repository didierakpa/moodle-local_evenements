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
 * Web app manifest of the door app.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

$base = (new moodle_url('/local/evenements/'))->out(false);
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode([
    'name' => get_string('op_title', 'local_evenements'),
    'short_name' => get_string('op_shortname', 'local_evenements'),
    'description' => get_string('op_loginhelp', 'local_evenements'),
    'lang' => current_language(),
    'start_url' => $base . 'operator.php',
    'scope' => $base,
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#f4f6f9',
    'theme_color' => '#1d4e89',
    'icons' => [
        ['src' => $base . 'pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . 'pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . 'pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
