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
 * Library functions and callbacks.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serves a dashboard news story's image, uploaded via the filemanager on
 * classes/form/news_form.php and stored under local_communications_news::IMAGE_FILEAREA
 * keyed by the story's own id. Public to any logged-in user (require_login() only, no
 * capability check) - who actually sees a story is controlled by
 * \local_communications\local\news::get_active_list()'s targeting, not by file
 * permissions, the same as the campaign widget itself has no per-viewer capability gate.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_communications_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    require_once(__DIR__ . '/classes/local/news.php');
    if ($filearea !== \local_communications\local\news::IMAGE_FILEAREA) {
        return false;
    }

    require_login();

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = get_file_storage()->get_file(
        $context->id, 'local_communications', $filearea, $itemid, $filepath, $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Adds a link to this course's feedback report(s) to the course's navigation (its
 * Reports menu/dropdown) - but only when at least one course-focused campaign
 * ({@see \local_communications\local\campaigns::get_course_focused_for_course()}) is
 * currently relevant to this course; a purely sitewide campaign (dashboard, site
 * home, etc.) never appears here, only via its own campaign dashboard.
 *
 * One matching campaign links straight to that campaign's course-scoped report
 * (course_report.php); more than one links to course_campaigns.php, a small index of
 * just those campaigns for this course - every report is scoped to exactly one
 * campaign, there is no pooled "every campaign" view any more.
 *
 * Checked at the COURSE context (not system) so that a role granting
 * local/communications:viewreports only within this course still sees the link;
 * course_report.php/course_campaigns.php re-check the same course-level capability.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_communications_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/communications:viewreports', $context)) {
        return;
    }

    require_once(__DIR__ . '/classes/local/campaigns.php');
    $matches = \local_communications\local\campaigns::get_course_focused_for_course($course);
    if (!$matches) {
        return;
    }

    if (count($matches) === 1) {
        $url = new moodle_url('/local/communications/course_report.php', [
            'courseid' => $course->id,
            'campaignid' => $matches[0]->id,
        ]);
    } else {
        $url = new moodle_url('/local/communications/course_campaigns.php', ['courseid' => $course->id]);
    }

    $navigation->add(
        get_string('reportheading', 'local_communications'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        null,
        new pix_icon('i/report', '')
    );
}

/**
 * Adds a link to the Preferences page (user/preferences.php) for managing which
 * feedback campaigns this user has asked not to be shown again (the "click here
 * if you'd prefer not to be asked" link in the widget itself).
 *
 * Self-service only: never shown while viewing/editing someone else's preferences.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context $coursecontext
 */
function local_communications_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext) {
    global $USER;

    if ($USER->id != $user->id || isguestuser($user)) {
        return;
    }

    $url = new moodle_url('/local/communications/preferences.php');
    $navigation->add(
        get_string('preferences_link', 'local_communications'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_communications_preferences',
        new pix_icon('i/settings', '')
    );
}

/**
 * Adds a link to the user's own profile page listing the feedback they've
 * personally submitted (non-anonymous only - see my_submissions.php) - only ever
 * shown while viewing your own profile, since it's personal data, not something
 * one user browses for another.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 */
function local_communications_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (!$iscurrentuser || isguestuser($user)) {
        return;
    }

    $url = new moodle_url('/local/communications/my_submissions.php');
    $node = new core_user\output\myprofile\node(
        'miscellaneous',
        'local_communications_mysubmissions',
        get_string('mysubmissions_link', 'local_communications'),
        null,
        $url
    );
    $tree->add_node($node);
}
