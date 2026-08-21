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
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds a link to this course's feedback report(s) to the course's navigation (its
 * Reports menu/dropdown) - but only when at least one course-focused campaign
 * ({@see \local_feedback\local\campaigns::get_course_focused_for_course()}) is
 * currently relevant to this course; a purely sitewide campaign (dashboard, site
 * home, etc.) never appears here, only via its own campaign dashboard.
 *
 * One matching campaign links straight to that campaign's course-scoped report
 * (course_report.php); more than one links to course_campaigns.php, a small index of
 * just those campaigns for this course - every report is scoped to exactly one
 * campaign, there is no pooled "every campaign" view any more.
 *
 * Checked at the COURSE context (not system) so that a role granting
 * local/feedback:viewreports only within this course still sees the link;
 * course_report.php/course_campaigns.php re-check the same course-level capability.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_feedback_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/feedback:viewreports', $context)) {
        return;
    }

    require_once(__DIR__ . '/classes/local/campaigns.php');
    $matches = \local_feedback\local\campaigns::get_course_focused_for_course($course);
    if (!$matches) {
        return;
    }

    if (count($matches) === 1) {
        $url = new moodle_url('/local/feedback/course_report.php', [
            'courseid' => $course->id,
            'campaignid' => $matches[0]->id,
        ]);
    } else {
        $url = new moodle_url('/local/feedback/course_campaigns.php', ['courseid' => $course->id]);
    }

    $navigation->add(
        get_string('reportheading', 'local_feedback'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        null,
        new pix_icon('i/report', '')
    );
}
