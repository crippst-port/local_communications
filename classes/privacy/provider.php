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

namespace local_communications\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_communications.
 *
 * Anonymous submissions (userid = 0) are, by design, not linked back to any user in
 * local_communications_submissions and are therefore not returned or affected by any request
 * against that table. However, a campaign with a response limit needs to know a real
 * user submitted even when they chose to stay anonymous - local_communications_campaign_responses
 * records exactly that (which user, which campaign, when - nothing about the response
 * itself) purely to enforce the limit, and IS personal data: it's handled here at
 * CONTEXT_SYSTEM, separately from the course-scoped submissions handling below, since
 * it isn't tied to any single course.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\user_preference_provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            'local_communications_neverask',
            'privacy:metadata:preference:neverask'
        );
        $collection->add_user_preference(
            'local_communications_neverask_all',
            'privacy:metadata:preference:neverask_all'
        );

        $collection->add_database_table(
            'local_communications_submissions',
            [
                'userid' => 'privacy:metadata:local_communications_submissions:userid',
                'anonymous' => 'privacy:metadata:local_communications_submissions:anonymous',
                'courseid' => 'privacy:metadata:local_communications_submissions:courseid',
                'sentiment' => 'privacy:metadata:local_communications_submissions:sentiment',
                'category' => 'privacy:metadata:local_communications_submissions:category',
                'feedbacktext' => 'privacy:metadata:local_communications_submissions:feedbacktext',
                'pageurl' => 'privacy:metadata:local_communications_submissions:pageurl',
                'breadcrumb' => 'privacy:metadata:local_communications_submissions:breadcrumb',
                'useragent' => 'privacy:metadata:local_communications_submissions:useragent',
                'timecreated' => 'privacy:metadata:local_communications_submissions:timecreated',
            ],
            'privacy:metadata:local_communications_submissions'
        );

        $collection->add_database_table(
            'local_communications_campaign_responses',
            [
                'userid' => 'privacy:metadata:local_communications_campaign_responses:userid',
                'campaignid' => 'privacy:metadata:local_communications_campaign_responses:campaignid',
                'courseid' => 'privacy:metadata:local_communications_campaign_responses:courseid',
                'timecreated' => 'privacy:metadata:local_communications_campaign_responses:timecreated',
            ],
            'privacy:metadata:local_communications_campaign_responses'
        );

        return $collection;
    }

    /**
     * Export this user's "don't ask me again" campaign opt-outs, by campaign name -
     * a user preference (see \local_communications\local\dismissed_campaigns), not tied to
     * any single course/campaign context, so exported here rather than from
     * export_user_data().
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        \core_privacy\local\request\writer::export_user_preference(
            'local_communications',
            'neverask_all',
            \local_communications\local\dismissed_campaigns::is_global_optout($userid) ? get_string('yes') : get_string('no'),
            get_string('privacy:metadata:preference:neverask_all', 'local_communications')
        );

        $ids = \local_communications\local\dismissed_campaigns::get_ids($userid);
        if (empty($ids)) {
            return;
        }

        $names = [];
        foreach ($ids as $campaignid) {
            $campaign = \local_communications\local\campaigns::get($campaignid);
            $names[] = $campaign ? format_string($campaign->name) : get_string('campaign_deleted', 'local_communications');
        }

        \core_privacy\local\request\writer::export_user_preference(
            'local_communications',
            'neverask',
            implode(', ', $names),
            get_string('privacy:metadata:preference:neverask', 'local_communications')
        );
    }

    /**
     * Get the list of contexts containing user data for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {local_communications_submissions} lfs
                  JOIN {context} ctx ON ctx.instanceid = lfs.courseid AND ctx.contextlevel = :contextcourse
                 WHERE lfs.userid = :userid AND lfs.anonymous = 0";

        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        if ($DB->record_exists('local_communications_campaign_responses', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel === CONTEXT_COURSE) {
            $sql = "SELECT userid
                      FROM {local_communications_submissions}
                     WHERE courseid = :courseid AND anonymous = 0";

            $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
        } else if ($context->contextlevel === CONTEXT_SYSTEM) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_communications_campaign_responses}', []);
        }
    }

    /**
     * Export all user data for the given approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                $records = $DB->get_records('local_communications_submissions', [
                    'courseid' => $context->instanceid,
                    'userid' => $user->id,
                    'anonymous' => 0,
                ]);

                if (empty($records)) {
                    continue;
                }

                $data = [];
                foreach ($records as $record) {
                    $data[] = (object) [
                        'sentiment' => $record->sentiment,
                        'category' => $record->category,
                        'feedbacktext' => $record->feedbacktext,
                        'coursename' => $record->coursename,
                        'cmname' => $record->cmname,
                        'campaignname' => $record->campaignname,
                        'pageurl' => $record->pageurl,
                        'breadcrumb' => $record->breadcrumb,
                        'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_communications')],
                    (object) ['submissions' => $data]
                );
            } else if ($context->contextlevel === CONTEXT_SYSTEM) {
                $responses = $DB->get_records('local_communications_campaign_responses', ['userid' => $user->id]);
                if (empty($responses)) {
                    continue;
                }

                $data = [];
                foreach ($responses as $response) {
                    $campaignname = $DB->get_field('local_communications_campaigns', 'name', ['id' => $response->campaignid]);
                    $coursename = $DB->get_field('course', 'fullname', ['id' => $response->courseid]);
                    $data[] = (object) [
                        'campaignname' => $campaignname !== false ? $campaignname : null,
                        'coursename' => $coursename !== false ? $coursename : null,
                        'timecreated' => \core_privacy\local\request\transform::datetime($response->timecreated),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_communications'), get_string('privacy:campaignresponses', 'local_communications')],
                    (object) ['responses' => $data]
                );
            }
        }
    }

    /**
     * Delete all user data for all users within a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_COURSE) {
            $DB->delete_records('local_communications_submissions', ['courseid' => $context->instanceid]);
        } else if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_communications_campaign_responses');
        }
    }

    /**
     * Delete all user data for one user, within the given approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                $DB->delete_records('local_communications_submissions', [
                    'courseid' => $context->instanceid,
                    'userid' => $user->id,
                    'anonymous' => 0,
                ]);
            } else if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records('local_communications_campaign_responses', ['userid' => $user->id]);
            }
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel === CONTEXT_COURSE) {
            foreach ($userlist->get_userids() as $userid) {
                $DB->delete_records('local_communications_submissions', [
                    'courseid' => $context->instanceid,
                    'userid' => $userid,
                    'anonymous' => 0,
                ]);
            }
        } else if ($context->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) {
                $DB->delete_records('local_communications_campaign_responses', ['userid' => $userid]);
            }
        }
    }
}
