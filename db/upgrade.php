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
 * Upgrade steps for local_evenements.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_evenements_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026112202) {
        // Questions (before / after the event).
        $table = new xmldb_table('local_evenements_question');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('moment', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'before');
        $table->add_field('scope', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'booking');
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'text');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('options', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('isrequired', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('event-moment', XMLDB_INDEX_NOTUNIQUE, ['eventid', 'moment']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Answers.
        $table = new xmldb_table('local_evenements_answer');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('bookingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ticketid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('eventid', XMLDB_INDEX_NOTUNIQUE, ['eventid']);
        $table->add_index('questionid', XMLDB_INDEX_NOTUNIQUE, ['questionid']);
        $table->add_index('bookingid', XMLDB_INDEX_NOTUNIQUE, ['bookingid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026112202, 'local', 'evenements');
    }

    if ($oldversion < 2026112203) {
        $table = new xmldb_table('local_evenements_event');
        $field = new xmldb_field('maxperperson', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'maxperbooking');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026112203, 'local', 'evenements');
    }

    if ($oldversion < 2026112204) {
        $table = new xmldb_table('local_evenements_waitlist');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contactname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('quantity', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('token', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'waiting');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timenotified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('event-status', XMLDB_INDEX_NOTUNIQUE, ['eventid', 'status']);
        $table->add_index('token', XMLDB_INDEX_UNIQUE, ['token']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112204, 'local', 'evenements');
    }

    if ($oldversion < 2026112205) {
        $table = new xmldb_table('local_evenements_booking');
        $field = new xmldb_field('remindersent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'iphash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('surveysent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'remindersent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026112205, 'local', 'evenements');
    }

    if ($oldversion < 2026112206) {
        // New styles.css: nothing to change in the database.
        upgrade_plugin_savepoint(true, 2026112206, 'local', 'evenements');
    }

    if ($oldversion < 2026112207) {
        // New hook callback (navigation entries): nothing to change in the database.
        upgrade_plugin_savepoint(true, 2026112207, 'local', 'evenements');
    }

    if ($oldversion < 2026112208) {
        // The menu setting is now a choice (0 none, 1 managers only, 2 everybody): the old checkbox value 1 becomes managers only.
        upgrade_plugin_savepoint(true, 2026112208, 'local', 'evenements');
    }

    if ($oldversion < 2026112209) {
        upgrade_plugin_savepoint(true, 2026112209, 'local', 'evenements');
    }

    if ($oldversion < 2026112212) {
        \local_evenements\templates::seed();
        upgrade_plugin_savepoint(true, 2026112212, 'local', 'evenements');
    }

    if ($oldversion < 2026112213) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_evenements_event');
        $field = new xmldb_field('ticketfont', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'sans', 'ticketcolor');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_evenements_organiser');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('eventid', XMLDB_KEY_FOREIGN, ['eventid'], 'local_evenements_event', ['id']);
            $table->add_index('event-user', XMLDB_INDEX_UNIQUE, ['eventid', 'userid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112213, 'local', 'evenements');
    }

    if ($oldversion < 2026112214) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_evenements_ticket');
        $field = new xmldb_field('operatorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'usedby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_evenements_scanlog');
        $field = new xmldb_field('operatorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_evenements_operator');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('lastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('eventid', XMLDB_KEY_FOREIGN, ['eventid'], 'local_evenements_event', ['id']);
            $table->add_index('code', XMLDB_INDEX_UNIQUE, ['code']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112214, 'local', 'evenements');
    }

    if ($oldversion < 2026112216) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_evenements_event');
        $field = new xmldb_field('ticketformat', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'concert', 'ticketfont');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_evenements_staff');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('role', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('eventid', XMLDB_KEY_FOREIGN, ['eventid'], 'local_evenements_event', ['id']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112216, 'local', 'evenements');
    }

    if ($oldversion < 2026112500) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_evenements_event');
        $field = new xmldb_field('accessmode', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'open', 'ticketformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('guestquota', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '3', 'accessmode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_evenements_ticket');
        $field = new xmldb_field('note', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'operatorid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_evenements_tickettype');
        $field = new xmldb_field('info', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_evenements_invitee');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('namekey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('tickettypeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('detail', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('quota', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('token', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('bookingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeinvited', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeconfirmed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timereported', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('eventid', XMLDB_KEY_FOREIGN, ['eventid'], 'local_evenements_event', ['id']);
            $table->add_index('token', XMLDB_INDEX_UNIQUE, ['token']);
            $table->add_index('eventnamekey', XMLDB_INDEX_NOTUNIQUE, ['eventid', 'namekey']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112500, 'local', 'evenements');
    }

    if ($oldversion < 2026112600) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_evenements_event');
        $field = new xmldb_field('imagetheme', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'generic', 'ticketformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026112600, 'local', 'evenements');
    }

    if ($oldversion < 2026112700) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_evenements_changelog');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('bookingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('ticketid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('eventid', XMLDB_KEY_FOREIGN, ['eventid'], 'local_evenements_event', ['id']);
            $table->add_index('ticketid', XMLDB_INDEX_NOTUNIQUE, ['ticketid']);
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112700, 'local', 'evenements');
    }

    if ($oldversion < 2026112701) {
        // The standalone console of the first 0.13.0 builds was dropped: the console stays inside Moodle.
        unset_config('standaloneconsole', 'local_evenements');
        upgrade_plugin_savepoint(true, 2026112701, 'local', 'evenements');
    }

    if ($oldversion < 2026112800) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_evenements_event');
        $field = new xmldb_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'tickettext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_evenements_report');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('eventid', XMLDB_KEY_FOREIGN_UNIQUE, ['eventid'], 'local_evenements_event', ['id']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112800, 'local', 'evenements');
    }

    return true;
}
