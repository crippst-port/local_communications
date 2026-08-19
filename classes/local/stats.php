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

namespace local_feedback\local;

use local_feedback\table\courses_summary_table;

defined('MOODLE_INTERNAL') || die();

/**
 * Course-level (not response-level) statistics for the site-wide report.
 *
 * The stat cards and per-response counts on report.php describe individual pieces of
 * feedback; this describes the state of courses as a whole - e.g. how many courses,
 * not how many responses, are sitting in the "needs attention" score band - which is
 * the thing a site admin scanning the whole site actually wants to know first.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stats {

    /**
     * Every course that has at least one response, bucketed by score tier
     * (see courses_summary_table::score_tier), each with its own course count.
     *
     * @return array{bad: int, okay: int, good: int}
     */
    public static function get_course_tier_counts(): array {
        global $DB;

        $points = courses_summary_table::SCORE_POINTS;
        $happy = $points['happy'];
        $neutral = $points['neutral'];
        $sad = $points['sad'];

        $sql = "SELECT courseid,
                    (SUM(CASE
                        WHEN sentiment = 'happy' THEN $happy
                        WHEN sentiment = 'neutral' THEN $neutral
                        WHEN sentiment = 'sad' THEN $sad
                        ELSE 0
                    END) * 1.0) / COUNT(*) AS avgscore
                  FROM {local_feedback_submissions}
                 GROUP BY courseid";

        $tiers = ['bad' => 0, 'okay' => 0, 'good' => 0];

        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $row) {
            $tiers[courses_summary_table::score_tier((float) $row->avgscore)]++;
        }
        $rs->close();

        return $tiers;
    }

    /**
     * Response counts and weighted average score per Moodle course category, worst-scoring
     * first - the "which department needs the most attention" view for the top of the
     * site-wide report.
     *
     * Joins live to {course}/{course_categories}, unlike the rest of this report which
     * reads the courseid/coursename denormalised onto each submission - so a course
     * deleted since it received feedback (no longer having a category to report against)
     * drops out of this breakdown even though it still appears in the main courses table.
     * A course that has since moved category is counted under its current one.
     *
     * @return array<int, \stdClass> Keyed by categoryid, each with categoryname,
     *                                happycount, neutralcount, sadcount, totalcount, avgscore.
     */
    public static function get_category_breakdown(): array {
        global $DB;

        $points = courses_summary_table::SCORE_POINTS;
        $happy = $points['happy'];
        $neutral = $points['neutral'];
        $sad = $points['sad'];

        $sql = "SELECT cc.id AS categoryid,
                    cc.name AS categoryname,
                    SUM(CASE WHEN s.sentiment = 'happy' THEN 1 ELSE 0 END) AS happycount,
                    SUM(CASE WHEN s.sentiment = 'neutral' THEN 1 ELSE 0 END) AS neutralcount,
                    SUM(CASE WHEN s.sentiment = 'sad' THEN 1 ELSE 0 END) AS sadcount,
                    COUNT(*) AS totalcount,
                    (SUM(CASE
                        WHEN s.sentiment = 'happy' THEN $happy
                        WHEN s.sentiment = 'neutral' THEN $neutral
                        WHEN s.sentiment = 'sad' THEN $sad
                        ELSE 0
                    END) * 1.0) / COUNT(*) AS avgscore
                 FROM {local_feedback_submissions} s
                 JOIN {course} c ON c.id = s.courseid
                 JOIN {course_categories} cc ON cc.id = c.category
                GROUP BY cc.id, cc.name
                ORDER BY avgscore ASC";

        return array_values($DB->get_records_sql($sql));
    }

    /**
     * Response counts and weighted average score per feedback topic (the submitter-chosen
     * "category" column - e.g. Assessment, Content, or free text via "Other"), worst-scoring
     * first. Restricting to one course is what makes this actionable - it's the "what's
     * actually driving this course's score" view linked from each row of the site-wide
     * report; called with no course to get the same breakdown site-wide instead.
     *
     * Topic text is exactly what was stored at submission time (admin-configured presets
     * can be edited/removed later without touching past responses), so free text via
     * "Other" naturally lands in its own one-off bucket rather than merging with anything.
     *
     * @param int $courseid Restrict to this course, 0 for all courses.
     * @return array<int, \stdClass> Each with category (null if skipped), happycount,
     *                                neutralcount, sadcount, totalcount, avgscore.
     */
    public static function get_topic_breakdown(int $courseid = 0): array {
        global $DB;

        $points = courses_summary_table::SCORE_POINTS;
        $happy = $points['happy'];
        $neutral = $points['neutral'];
        $sad = $points['sad'];

        $where = '1=1';
        $params = [];
        if ($courseid) {
            $where = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT category,
                    SUM(CASE WHEN sentiment = 'happy' THEN 1 ELSE 0 END) AS happycount,
                    SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) AS neutralcount,
                    SUM(CASE WHEN sentiment = 'sad' THEN 1 ELSE 0 END) AS sadcount,
                    COUNT(*) AS totalcount,
                    (SUM(CASE
                        WHEN sentiment = 'happy' THEN $happy
                        WHEN sentiment = 'neutral' THEN $neutral
                        WHEN sentiment = 'sad' THEN $sad
                        ELSE 0
                    END) * 1.0) / COUNT(*) AS avgscore
                 FROM {local_feedback_submissions}
                WHERE $where
                GROUP BY category
                ORDER BY avgscore ASC";

        return array_values($DB->get_records_sql($sql, $params));
    }
}
