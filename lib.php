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
 * Adds a link to this course's own feedback report to the course's navigation
 * (its Reports menu/dropdown). That page (course_report.php) is locked to this
 * course only - separate from the site-wide report under Site administration >
 * Reports, which shows every course.
 *
 * Checked at the COURSE context (not system) so that a role granting
 * local/feedback:viewreports only within this course still sees the link;
 * course_report.php re-checks the same course-level capability itself.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_feedback_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/feedback:viewreports', $context)) {
        return;
    }

    $url = new moodle_url('/local/feedback/course_report.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('reportheading', 'local_feedback'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        null,
        new pix_icon('i/report', '')
    );
}
