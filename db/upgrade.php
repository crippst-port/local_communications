<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     local_feedback
 * @category    upgrade
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_feedback upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_feedback_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081901) {
        $table = new xmldb_table('local_feedback_submissions');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('anonymous', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('coursename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('cmname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('modname', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('sectionname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('sentiment', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('feedbacktext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('pageurl', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('pagetype', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('pagetitle', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('referrer', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('useragent', XMLDB_TYPE_CHAR, '1000', null, null, null, null);
            $table->add_field('screenwidth', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('screenheight', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('lang', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('sentiment', XMLDB_INDEX_NOTUNIQUE, ['sentiment']);
            $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026081901, 'local', 'feedback');
    }

    if ($oldversion < 2026081910) {
        $table = new xmldb_table('local_feedback_submissions');
        $field = new xmldb_field('breadcrumb', XMLDB_TYPE_CHAR, '1000', null, null, null, null, 'pagetype');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026081910, 'local', 'feedback');
    }

    if ($oldversion < 2026081912) {
        $table = new xmldb_table('local_feedback_submissions');
        $field = new xmldb_field('category', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'sentiment');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026081912, 'local', 'feedback');
    }

    if ($oldversion < 2026081913) {
        // Categories became admin-configurable free text (e.g. "Course layout") rather
        // than fixed short keys ("layout"), so the column needs to be wide enough for
        // that - and for the free-text "Other" option.
        $table = new xmldb_table('local_feedback_submissions');
        $field = new xmldb_field('category', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'sentiment');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026081913, 'local', 'feedback');
    }

    return true;
}
