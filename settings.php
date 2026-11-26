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
 * Admin settings for local_evenements.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_evenements_manage',
        get_string('manageevents', 'local_evenements'),
        new moodle_url('/local/evenements/manage.php'),
        'local/evenements:manage'
    ));

    $settings = new admin_settingpage('local_evenements', get_string('pluginname', 'local_evenements'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configstoredfile(
            'local_evenements/defaultlogo',
            get_string('defaultlogo', 'local_evenements'),
            get_string('defaultlogo_desc', 'local_evenements'),
            'defaultlogo',
            0,
            ['accepted_types' => ['.png', '.jpg', '.jpeg', '.gif'], 'maxfiles' => 1]
        ));

        $settings->add(new admin_setting_configtext(
            'local_evenements/retentiondays',
            get_string('retentiondays', 'local_evenements'),
            get_string('retentiondays_desc', 'local_evenements'),
            30,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_evenements/ratelimit',
            get_string('ratelimit', 'local_evenements'),
            get_string('ratelimit_desc', 'local_evenements'),
            10,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configselect(
            'local_evenements/menuentry',
            get_string('menuentry', 'local_evenements'),
            get_string('menuentry_desc', 'local_evenements'),
            1,
            [
                0 => get_string('menuentry_none', 'local_evenements'),
                1 => get_string('menuentry_managers', 'local_evenements'),
                2 => get_string('menuentry_everyone', 'local_evenements'),
            ]
        ));

        $settings->add(new admin_setting_configtext(
            'local_evenements/reminderhours',
            get_string('reminderhours', 'local_evenements'),
            get_string('reminderhours_desc', 'local_evenements'),
            24,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_evenements/surveyemail',
            get_string('surveyemail', 'local_evenements'),
            get_string('surveyemail_desc', 'local_evenements'),
            1
        ));

        $settings->add(new admin_setting_configtextarea(
            'local_evenements/privacynotice',
            get_string('privacynotice', 'local_evenements'),
            get_string('privacynotice_desc', 'local_evenements'),
            get_string('privacynotice_default', 'local_evenements')
        ));
    }
}
