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

    if ($oldversion < 2026082100) {
        $table = new xmldb_table('local_feedback_campaigns');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('priority', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('categoryids', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('pagetypepatterns', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('targetroles', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('targetcohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('targetuserids', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('enabled', XMLDB_INDEX_NOTUNIQUE, ['enabled']);

            $dbman->create_table($table);
        }

        $submissions = new xmldb_table('local_feedback_submissions');

        $campaignid = new xmldb_field('campaignid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'lang');
        if (!$dbman->field_exists($submissions, $campaignid)) {
            $dbman->add_field($submissions, $campaignid);
        }

        $campaignname = new xmldb_field('campaignname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'campaignid');
        if (!$dbman->field_exists($submissions, $campaignname)) {
            $dbman->add_field($submissions, $campaignname);
        }

        $index = new xmldb_index('campaignid', XMLDB_INDEX_NOTUNIQUE, ['campaignid']);
        if (!$dbman->index_exists($submissions, $index)) {
            $dbman->add_index($submissions, $index);
        }

        // Preserve existing behaviour with zero admin action: seed one always-on,
        // all-scope campaign carrying over the current global enabled setting, so
        // sites upgrading from before campaigns existed see no change in the widget.
        if (!$DB->record_exists('local_feedback_campaigns', [])) {
            $campaign = new stdClass();
            $campaign->name = get_string('defaultcampaignname', 'local_feedback');
            $campaign->enabled = get_config('local_feedback', 'enabled') ? 1 : 0;
            $campaign->priority = 0;
            $campaign->starttime = 0;
            $campaign->endtime = 0;
            $campaign->categoryids = null;
            $campaign->pagetypepatterns = null;
            $campaign->targetroles = null;
            $campaign->targetcohortid = 0;
            $campaign->targetuserids = null;
            $campaign->timecreated = time();
            $campaign->timemodified = $campaign->timecreated;
            $campaign->usermodified = 0;
            $DB->insert_record('local_feedback_campaigns', $campaign);
        }

        upgrade_plugin_savepoint(true, 2026082100, 'local', 'feedback');
    }

    if ($oldversion < 2026082101) {
        // Topic labels (previously site-wide only) become per-campaign, falling back to
        // the existing local_feedback/categories setting when a campaign leaves this
        // empty - see classes/local/categories.php::get_list_for_campaign().
        $table = new xmldb_table('local_feedback_campaigns');
        $field = new xmldb_field('topics', XMLDB_TYPE_TEXT, null, null, null, null, null, 'endtime');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082101, 'local', 'feedback');
    }

    if ($oldversion < 2026082102) {
        // Per-campaign modal title/intro text, both falling back to the existing fixed
        // "modaltitle" language string / no intro at all when a campaign leaves them empty.
        $table = new xmldb_table('local_feedback_campaigns');

        $modaltitle = new xmldb_field('modaltitle', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'name');
        if (!$dbman->field_exists($table, $modaltitle)) {
            $dbman->add_field($table, $modaltitle);
        }

        $introtext = new xmldb_field('introtext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'modaltitle');
        if (!$dbman->field_exists($table, $introtext)) {
            $dbman->add_field($table, $introtext);
        }

        upgrade_plugin_savepoint(true, 2026082102, 'local', 'feedback');
    }

    if ($oldversion < 2026082103) {
        // A second campaign "shape": skip the topic/area step entirely (going straight
        // from sentiment to comment) for campaigns whose own question already scopes the
        // context tightly enough that a generic area picker would be redundant. Plus
        // per-campaign overrides for the three sentiment button labels.
        $table = new xmldb_table('local_feedback_campaigns');

        $skiptopicstep = new xmldb_field('skiptopicstep', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'topics');
        if (!$dbman->field_exists($table, $skiptopicstep)) {
            $dbman->add_field($table, $skiptopicstep);
        }

        $labelhappy = new xmldb_field('labelhappy', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'skiptopicstep');
        if (!$dbman->field_exists($table, $labelhappy)) {
            $dbman->add_field($table, $labelhappy);
        }

        $labelneutral = new xmldb_field('labelneutral', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'labelhappy');
        if (!$dbman->field_exists($table, $labelneutral)) {
            $dbman->add_field($table, $labelneutral);
        }

        $labelsad = new xmldb_field('labelsad', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'labelneutral');
        if (!$dbman->field_exists($table, $labelsad)) {
            $dbman->add_field($table, $labelsad);
        }

        upgrade_plugin_savepoint(true, 2026082103, 'local', 'feedback');
    }

    if ($oldversion < 2026082104) {
        // Whether a campaign's report compares courses/categories and is linked from
        // matching courses' own Reports menus, or gets a flat course-less dashboard
        // instead - defaults ON (unlike other targeting-ish fields, which default to "no
        // restriction") so every existing campaign keeps showing in every course's Reports
        // menu after upgrade, exactly as it did before this column existed.
        $table = new xmldb_table('local_feedback_campaigns');
        $field = new xmldb_field('coursefocused', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'labelsad');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082104, 'local', 'feedback');
    }

    if ($oldversion < 2026082105) {
        // A campaign can now limit how often the same user may respond ('none', 'daily'
        // or 'once'). Enforcing this even for anonymous submissions needs a separate,
        // internal ledger keyed by the real user id - local_feedback_submissions.userid
        // is deliberately 0 for those, so it can't be used for this - see
        // classes/local/campaigns.php::has_reached_response_limit().
        $table = new xmldb_table('local_feedback_campaigns');
        $field = new xmldb_field('responselimit', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none', 'coursefocused');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $responses = new xmldb_table('local_feedback_campaign_responses');

        if (!$dbman->table_exists($responses)) {
            $responses->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $responses->add_field('campaignid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $responses->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $responses->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $responses->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $responses->add_index('campaignuser', XMLDB_INDEX_NOTUNIQUE, ['campaignid', 'userid']);

            $dbman->create_table($responses);
        }

        upgrade_plugin_savepoint(true, 2026082105, 'local', 'feedback');
    }

    if ($oldversion < 2026082106) {
        // A response limit now scopes per-course for a course-focused campaign (a
        // student can respond once per course they're in, rather than once across every
        // course the campaign touches) - see campaigns::has_reached_response_limit().
        $table = new xmldb_table('local_feedback_campaign_responses');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082106, 'local', 'feedback');
    }

    if ($oldversion < 2026082107) {
        // Auto-deactivate a campaign once it's collected a set number of responses - 0
        // (the default) means no limit. This is a whole-campaign cutoff, not a per-user
        // one, so it's checked alongside the date window in
        // campaigns::get_active_for_context(), not the response-limit ledger.
        $table = new xmldb_table('local_feedback_campaigns');
        $field = new xmldb_field('maxresponses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'responselimit');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082107, 'local', 'feedback');
    }

    return true;
}
