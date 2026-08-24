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

namespace local_communications\local;

use local_communications\table\courses_summary_table;

/**
 * Course-level (not response-level) statistics for a campaign's report.
 *
 * The stat cards and per-response counts on report.php describe individual pieces of
 * feedback; this describes the state of courses as a whole - e.g. how many courses,
 * not how many responses, are sitting in the "needs attention" score band - which is
 * the thing an admin scanning a course-focused campaign's report wants to know first.
 * Every method here takes an optional campaignid to scope to one campaign - every
 * report is scoped to exactly one campaign, there's no cross-campaign view any more.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stats {
    /**
     * Overall direction of travel across a campaign's most recent responses - the same
     * recent-half-vs-older-half comparison courses_summary_table computes per course,
     * done once across the whole set instead (or one course's slice of it, if given).
     * For a campaign's own top-level "Trend" stat card - a course-focused campaign's
     * report already shows trend per course in its table, but that table has nothing to
     * show it for a campaign that isn't course-focused (report.php), or once you've
     * drilled into a single course's own responses (course_report.php), which is
     * exactly where an overall figure like this is needed instead.
     *
     * @param int $campaignid
     * @param int $courseid Restrict to this course, 0 for every course the campaign touches.
     * @return \stdClass ->totalcount, ->recentavg, ->olderavg, ->trend (-1/0/1) - pass
     *                     straight to courses_summary_table::render_trend_indicator().
     */
    public static function get_campaign_trend(int $campaignid, int $courseid = 0): \stdClass {
        global $DB;

        $points = courses_summary_table::SCORE_POINTS;
        $happy = $points['happy'];
        $neutral = $points['neutral'];
        $sad = $points['sad'];
        $window = courses_summary_table::get_trend_window();
        $half = intdiv($window, 2);
        $threshold = courses_summary_table::TREND_THRESHOLD;

        $where = 'campaignid = :campaignid';
        $params = ['campaignid' => $campaignid];
        if ($courseid) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT
                    totalcount, recentavg, olderavg,
                    CASE
                        WHEN totalcount < $window THEN 0
                        WHEN (recentavg - olderavg) >= $threshold THEN 1
                        WHEN (recentavg - olderavg) <= -$threshold THEN -1
                        ELSE 0
                    END AS trend
                 FROM (SELECT
                        COUNT(*) AS totalcount,
                        AVG(CASE WHEN rn <= $half THEN score END) AS recentavg,
                        AVG(CASE WHEN rn > $half AND rn <= $window THEN score END) AS olderavg
                     FROM (SELECT
                            CASE
                                WHEN sentiment = 'happy' THEN $happy
                                WHEN sentiment = 'neutral' THEN $neutral
                                WHEN sentiment = 'sad' THEN $sad
                                ELSE 0
                            END AS score,
                            ROW_NUMBER() OVER (ORDER BY timecreated DESC) AS rn
                         FROM {local_communications_submissions}
                        WHERE $where) scoredsubmissions) trendsummary";

        $row = $DB->get_record_sql($sql, $params);

        return $row ?: (object) ['totalcount' => 0, 'recentavg' => 0, 'olderavg' => 0, 'trend' => 0];
    }

    /**
     * Every course that has at least one response, bucketed by score tier
     * (see courses_summary_table::score_tier), each with its own course count.
     *
     * @param int $campaignid Restrict to this campaign, 0 for all campaigns.
     * @return array{bad: int, okay: int, good: int}
     */
    public static function get_course_tier_counts(int $campaignid = 0): array {
        global $DB;

        $points = courses_summary_table::SCORE_POINTS;
        $happy = $points['happy'];
        $neutral = $points['neutral'];
        $sad = $points['sad'];

        $where = '1=1';
        $params = [];
        if ($campaignid) {
            $where = 'campaignid = :campaignid';
            $params['campaignid'] = $campaignid;
        }

        $sql = "SELECT courseid,
                    (SUM(CASE
                        WHEN sentiment = 'happy' THEN $happy
                        WHEN sentiment = 'neutral' THEN $neutral
                        WHEN sentiment = 'sad' THEN $sad
                        ELSE 0
                    END) * 1.0) / COUNT(*) AS avgscore
                  FROM {local_communications_submissions}
                 WHERE $where
                 GROUP BY courseid";

        $tiers = ['bad' => 0, 'okay' => 0, 'good' => 0];

        $rs = $DB->get_recordset_sql($sql, $params);
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
     * @param int $campaignid Restrict to this campaign, 0 for all campaigns.
     * @return array<int, \stdClass> Keyed by categoryid, each with categoryname,
     *                                happycount, neutralcount, sadcount, totalcount, avgscore.
     */
    public static function get_category_breakdown(int $campaignid = 0): array {
        global $DB;

        $points = courses_summary_table::SCORE_POINTS;
        $happy = $points['happy'];
        $neutral = $points['neutral'];
        $sad = $points['sad'];

        $where = '1=1';
        $params = [];
        if ($campaignid) {
            $where = 's.campaignid = :campaignid';
            $params['campaignid'] = $campaignid;
        }

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
                 FROM {local_communications_submissions} s
                 JOIN {course} c ON c.id = s.courseid
                 JOIN {course_categories} cc ON cc.id = c.category
                WHERE $where
                GROUP BY cc.id, cc.name
                ORDER BY avgscore ASC";

        return array_values($DB->get_records_sql($sql, $params));
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
     * @param int $campaignid Restrict to this campaign, 0 for all campaigns.
     * @return array<int, \stdClass> Each with category (null if skipped), happycount,
     *                                neutralcount, sadcount, totalcount, avgscore.
     */
    public static function get_topic_breakdown(int $courseid = 0, int $campaignid = 0): array {
        global $DB;

        $points = courses_summary_table::SCORE_POINTS;
        $happy = $points['happy'];
        $neutral = $points['neutral'];
        $sad = $points['sad'];

        $conditions = [];
        $params = [];
        if ($courseid) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($campaignid) {
            $conditions[] = 'campaignid = :campaignid';
            $params['campaignid'] = $campaignid;
        }
        $where = $conditions ? implode(' AND ', $conditions) : '1=1';

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
                 FROM {local_communications_submissions}
                WHERE $where
                GROUP BY category
                ORDER BY avgscore ASC";

        return array_values($DB->get_records_sql($sql, $params));
    }
}
