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
}
