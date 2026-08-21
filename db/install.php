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
 * @package     local_feedback
 * @category    upgrade
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_local_feedback_install() {
    global $DB;

    // Seed one always-on, all-scope campaign so the widget behaves exactly like a
    // pre-campaign install out of the box - see the equivalent seeding in
    // xmldb_local_feedback_upgrade() for sites upgrading from before campaigns existed.
    $campaign = new stdClass();
    $campaign->name = get_string('defaultcampaignname', 'local_feedback');
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
    $campaign->categoryids = null;
    $campaign->pagetypepatterns = null;
    $campaign->targetroles = null;
    $campaign->targetcohortid = 0;
    $campaign->targetuserids = null;
    $campaign->timecreated = time();
    $campaign->timemodified = $campaign->timecreated;
    $campaign->usermodified = 0;
    $DB->insert_record('local_feedback_campaigns', $campaign);

    return true;
}
