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
$s = function (string $id) {
    return s(get_string('op_' . $id, 'local_evenements'));
};
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
?>
<!doctype html>
<html lang="<?php echo s(current_language()); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1d4e89">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?php echo $s('shortname'); ?>">
<meta name="robots" content="noindex">
<title><?php echo $s('title'); ?></title>
<link rel="manifest" href="<?php echo $base . 'operator_manifest.php'; ?>">
<link rel="apple-touch-icon" href="<?php echo $base . 'pwa/apple-touch-icon.png'; ?>">
<link rel="icon" type="image/png" href="<?php echo $base . 'pwa/icon-192.png'; ?>">
<link rel="stylesheet" href="<?php echo $base . 'pwa/operator.css'; ?>">
</head>
<body>

<section id="login" class="hidden">
    <img src="<?php echo $base . 'pwa/icon-192.png'; ?>" alt="">
    <h1><?php echo $s('title'); ?></h1>
    <p><?php echo $s('loginhelp'); ?></p>
    <form id="loginform" autocomplete="off">
        <input type="text" id="code" placeholder="XXXX-XXXX-XXXX-XXXX" aria-label="<?php echo $s('code'); ?>" autocapitalize="characters" autocorrect="off" spellcheck="false" required>
        <button class="btn" type="submit"><?php echo $s('login'); ?></button>
    </form>
    <p class="error" id="loginerr" role="alert"></p>
    <button class="btn secondary hidden" id="btninstall" type="button"><?php echo $s('install'); ?></button>
    <p class="muted hidden" id="ioshint"><?php echo $s('ioshint'); ?></p>
</section>

<section id="app" class="hidden">
    <header class="bar">
        <div class="title"><strong id="evname"></strong><span id="opname"></span></div>
        <span class="dot" id="dot"></span><span id="netlabel" class="muted" style="color:#fff"></span>
    </header>
    <main>
        <div class="counter">
            <div class="big" id="count"></div>
            <div class="muted"><?php echo $s('entries'); ?></div>
            <div class="progress"><i id="bar"></i></div>
            <div class="sync" id="syncinfo"></div>
        </div>
        <div class="actions">
            <button class="btn" id="btnscan" type="button"><?php echo $s('scan'); ?></button>
            <div class="row">
                <button class="btn secondary" id="btnsearch" type="button"><?php echo $s('search'); ?></button>
                <button class="btn secondary" id="btnmanual" type="button"><?php echo $s('manual'); ?></button>
            </div>
        </div>
        <div class="panel recent">
            <h2><?php echo $s('recent'); ?></h2>
            <p class="muted" id="recentempty"><?php echo $s('norecent'); ?></p>
            <ul id="recentlist"></ul>
        </div>
        <p style="text-align:center"><button class="btn small secondary" id="btnlogout" type="button"><?php echo $s('logout'); ?></button></p>
    </main>
</section>

<div id="camera" class="hidden">
    <video id="video" playsinline muted></video>
    <div class="frame"></div>
    <div class="hint"><?php echo $s('camhint'); ?></div>
    <button class="btn close" id="camclose" type="button"><?php echo $s('stop'); ?></button>
</div>

<div id="search" class="hidden" style="position:fixed;inset:0;z-index:25;background:var(--bg);overflow:auto;padding:1rem;padding-top:calc(env(safe-area-inset-top) + 1rem)">
    <input type="search" id="q" placeholder="<?php echo $s('searchph'); ?>" aria-label="<?php echo $s('search'); ?>" autocomplete="off">
    <div class="panel results" style="margin-top:1rem"><ul id="searchlist"></ul></div>
    <button class="btn secondary" id="searchclose" type="button"><?php echo $s('close'); ?></button>
</div>

<div id="manual" class="hidden" style="position:fixed;inset:0;z-index:25;background:var(--bg);padding:1rem;padding-top:calc(env(safe-area-inset-top) + 1rem)">
    <form id="manualform">
        <input type="text" id="manualcode" placeholder="<?php echo $s('manualph'); ?>" aria-label="<?php echo $s('manual'); ?>" autocomplete="off" autocapitalize="off" spellcheck="false">
        <button class="btn" type="submit" style="margin-top:1rem"><?php echo $s('check'); ?></button>
    </form>
    <button class="btn secondary" id="manualclose" type="button" style="margin-top:.75rem"><?php echo $s('close'); ?></button>
</div>

<div id="result" class="hidden" role="alertdialog" aria-live="assertive">
    <div class="icon" id="rticon"></div>
    <div class="state" id="rtstate"></div>
    <div class="who" id="rtwho"></div>
    <div class="info" id="rtinfo"></div>
    <button class="btn" id="rtnext" type="button"><?php echo $s('next'); ?></button>
    <button class="btn ghost hidden" id="rtundo" type="button"><?php echo $s('undo'); ?></button>
</div>

<script>window.EVT_OP = <?php echo json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;</script>
<script src="<?php echo $base . 'pwa/operator.js'; ?>"></script>
</body>
</html>
