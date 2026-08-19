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

namespace local_feedback\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_feedback.
 *
 * Anonymous submissions (userid = 0) are, by design, not linked back to any
 * user and are therefore not returned or affected by any of these requests.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_feedback_submissions',
            [
                'userid' => 'privacy:metadata:local_feedback_submissions:userid',
                'anonymous' => 'privacy:metadata:local_feedback_submissions:anonymous',
                'courseid' => 'privacy:metadata:local_feedback_submissions:courseid',
                'sentiment' => 'privacy:metadata:local_feedback_submissions:sentiment',
                'category' => 'privacy:metadata:local_feedback_submissions:category',
                'feedbacktext' => 'privacy:metadata:local_feedback_submissions:feedbacktext',
                'pageurl' => 'privacy:metadata:local_feedback_submissions:pageurl',
                'breadcrumb' => 'privacy:metadata:local_feedback_submissions:breadcrumb',
                'useragent' => 'privacy:metadata:local_feedback_submissions:useragent',
                'timecreated' => 'privacy:metadata:local_feedback_submissions:timecreated',
            ],
            'privacy:metadata:local_feedback_submissions'
        );

        return $collection;
    }

    /**
     * Get the list of contexts containing user data for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {local_feedback_submissions} lfs
                  JOIN {context} ctx ON ctx.instanceid = lfs.courseid AND ctx.contextlevel = :contextcourse
                 WHERE lfs.userid = :userid AND lfs.anonymous = 0";

        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $sql = "SELECT userid
                  FROM {local_feedback_submissions}
                 WHERE courseid = :courseid AND anonymous = 0";

        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
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
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $records = $DB->get_records('local_feedback_submissions', [
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
                    'pageurl' => $record->pageurl,
                    'breadcrumb' => $record->breadcrumb,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_feedback')],
                (object) ['submissions' => $data]
            );
        }
    }

    /**
     * Delete all user data for all users within a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $DB->delete_records('local_feedback_submissions', ['courseid' => $context->instanceid]);
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
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $DB->delete_records('local_feedback_submissions', [
                'courseid' => $context->instanceid,
                'userid' => $user->id,
                'anonymous' => 0,
            ]);
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
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('local_feedback_submissions', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
                'anonymous' => 0,
            ]);
        }
    }
}
