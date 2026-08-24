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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     local_communications
 * @category    upgrade
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom code to be run on installing the plugin.
 *
 * local_communications is a rename of local_feedback, not a fresh plugin - if that
 * old plugin's tables are still on this site (it hasn't been uninstalled yet), every
 * row is carried over here, preserving ids so the campaignid columns linking
 * submissions/responses to campaigns stay valid, before local_feedback is uninstalled
 * separately. A site with no local_feedback history at all (a genuinely new install)
 * falls back to seeding one always-on, all-scope campaign instead, exactly as
 * local_feedback used to on a fresh install.
 */
function xmldb_local_communications_install() {
    global $DB;

    $dbman = $DB->get_manager();

    if ($dbman->table_exists('local_feedback_campaigns')) {
        // Campaigns first - submissions and responses both reference campaignid.
        foreach ($DB->get_records('local_feedback_campaigns') as $record) {
            $DB->import_record('local_communications_campaigns', $record);
        }
        foreach ($DB->get_records('local_feedback_submissions') as $record) {
            $DB->import_record('local_communications_submissions', $record);
        }
        if ($dbman->table_exists('local_feedback_campaign_responses')) {
            foreach ($DB->get_records('local_feedback_campaign_responses') as $record) {
                $DB->import_record('local_communications_campaign_responses', $record);
            }
        }

        // import_record() inserts the given id directly rather than via the sequence,
        // so the sequence itself is now behind the highest migrated id - fix that up
        // before anything new gets inserted.
        $dbman->reset_sequence('local_communications_campaigns');
        $dbman->reset_sequence('local_communications_submissions');
        $dbman->reset_sequence('local_communications_campaign_responses');

        // Per-user "don't ask me again" preferences (see
        // \local_communications\local\dismissed_campaigns) live in the core
        // user_preferences table, not a table this plugin owns, so they survive
        // local_feedback being uninstalled regardless - just under their old names
        // until renamed here.
        $DB->set_field('user_preferences', 'name', 'local_communications_neverask', ['name' => 'local_feedback_neverask']);
        $DB->set_field('user_preferences', 'name', 'local_communications_neverask_all', ['name' => 'local_feedback_neverask_all']);

        // Site admin settings (enabled/trendwindow/categories) - carried over so a
        // customised value isn't silently replaced by this plugin's own defaults, which
        // Moodle applies automatically to any setting with no config_plugins row yet.
        // 'version' is deliberately skipped - that's local_feedback's own version
        // number, nothing to do with local_communications' (which core manages itself).
        foreach ($DB->get_records('config_plugins', ['plugin' => 'local_feedback']) as $config) {
            if ($config->name === 'version') {
                continue;
            }
            set_config($config->name, $config->value, 'local_communications');
        }

        return true;
    }

    // No local_feedback history on this site - seed one always-on, all-scope campaign
    // so the widget behaves exactly like a pre-campaign install out of the box.
    $campaign = new stdClass();
    $campaign->name = get_string('defaultcampaignname', 'local_communications');
    $campaign->modaltitle = null;
    $campaign->introtext = null;
    $campaign->enabled = 1;
    $campaign->priority = 0;
    $campaign->starttime = 0;
    $campaign->endtime = 0;
    $campaign->topics = null;
    $campaign->skiptopicstep = 0;
    $campaign->labelhappy = null;
    $campaign->labelneutral = null;
    $campaign->labelsad = null;
    $campaign->coursefocused = 1;
    $campaign->responselimit = 'none';
    $campaign->maxresponses = 0;
    $campaign->categoryids = null;
    $campaign->pagetypepatterns = null;
    $campaign->targetroles = null;
    $campaign->targetcohortid = 0;
    $campaign->targetuserids = null;
    $campaign->timecreated = time();
    $campaign->timemodified = $campaign->timecreated;
    $campaign->usermodified = 0;
    $DB->insert_record('local_communications_campaigns', $campaign);

    return true;
}
