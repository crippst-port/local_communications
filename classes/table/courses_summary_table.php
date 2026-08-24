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

namespace local_communications\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * One row per course, with sentiment counts and a weighted average score - the
 * site-wide report's landing table.
 *
 * Raw counts alone don't let you compare fairly: a course with 20 happy/5 sad
 * responses and one with 2 happy/0 sad both "look good" by different measures.
 * The score (happy=5, neutral=3, sad=1, averaged across all of a course's
 * responses - the same idea as a star rating) normalises for response volume,
 * so sorting by it is a fairer "best/worst" ranking than sorting by any single
 * count. Every count/score column is independently sortable regardless, for
 * when a different cut is more useful (e.g. "most happy responses" by volume).
 * Each row drills down into that course's own feedback via course_report.php.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courses_summary_table extends \table_sql {

    /**
     * Score points awarded per sentiment when averaging, out of a 5-point scale.
     * Public so report.php can compute the same overall average for its stat card,
     * without the two drifting apart.
     */
    public const SCORE_POINTS = [
        'happy' => 5,
        'neutral' => 3,
        'sad' => 1,
    ];

    /**
     * Default (and fallback) for how many of a course's most recent responses form the
     * trend window, split into two equal halves (newest N/2 vs the N/2 before that) so a
     * shift in sentiment within that window shows as an up/down arrow next to the score.
     * Courses with fewer than this many responses in total have no arrow - not enough
     * recent history to call a direction rather than noise. Overridable via the
     * local_communications/trendwindow admin setting - see {@see get_trend_window()}.
     */
    public const TREND_WINDOW = 10;

    /**
     * Minimum gap (out of the 5-point score scale) between the two trend-window halves
     * before it counts as a real direction rather than noise.
     */
    public const TREND_THRESHOLD = 0.5;

    /**
     * How many of a course's most recent responses form this instance's trend window.
     * Resolved once in the constructor from the local_communications/trendwindow admin
     * setting, so col_trend() and the SQL built here always agree.
     */
    protected int $trendwindow;

    /**
     * The campaign this table instance is scoped to - every report is scoped to exactly
     * one campaign, so this is never 0 in practice, but col_actions() still needs it to
     * build a working link into course_report.php (which requires a campaignid).
     */
    protected int $campaignid;

    /**
     * @param string $uniqueid
     * @param \moodle_url $baseurl
     * @param bool $downloading Whether this instance is being built to serve a download
     *                          (e.g. CSV) rather than an HTML page. The "view feedback"
     *                          button column is dropped in that case - a link target has
     *                          no meaning in exported data. Callers must pass this before
     *                          calling is_downloading(), since column definition happens
     *                          here in the constructor, ahead of that call.
     * @param string $tier Restrict to courses in this score_tier() band ('bad', 'okay',
     *                     'good'), '' for all courses.
     * @param string $trendfilter Restrict to courses trending this way ('up' or 'down',
     *                            matching trend_direction()'s return value), '' for all.
     * @param int $categoryfilter Restrict to courses in this Moodle course category
     *                            (course.category), 0 for all courses.
     * @param int $campaignfilter Restrict every count/score/trend to responses collected
     *                            under this campaign, 0 for all responses regardless of
     *                            campaign.
     * @param array $sentimentlabels Sentiment button labels to show instead of the site
     *                                 defaults, keyed 'happy'/'neutral'/'sad' - a
     *                                 campaign's own overrides, see
     *                                 campaigns::get_sentiment_labels(). Missing keys
     *                                 fall back to the site default individually.
     */
    public function __construct(
        $uniqueid,
        \moodle_url $baseurl,
        bool $downloading = false,
        string $tier = '',
        string $trendfilter = '',
        int $categoryfilter = 0,
        int $campaignfilter = 0,
        array $sentimentlabels = []
    ) {
        parent::__construct($uniqueid);

        $this->campaignid = $campaignfilter;

        $columns = ['coursename', 'happycount', 'neutralcount', 'sadcount', 'totalcount', 'avgscore', 'trend'];
        $headers = [
            get_string('report_col_course', 'local_communications'),
            '😊 ' . ($sentimentlabels['happy'] ?? get_string('sentiment_happy', 'local_communications')),
            '😐 ' . ($sentimentlabels['neutral'] ?? get_string('sentiment_neutral', 'local_communications')),
            '😞 ' . ($sentimentlabels['sad'] ?? get_string('sentiment_sad', 'local_communications')),
            get_string('report_col_total', 'local_communications'),
            get_string('report_col_avgscore', 'local_communications'),
            get_string('report_col_trend', 'local_communications'),
        ];

        if (!$downloading) {
            $columns[] = 'actions';
            $headers[] = '';
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);

        // Worst-scoring courses first by default - the most actionable view - but every
        // column remains independently sortable in either direction.
        $this->sortable(true, 'avgscore', SORT_ASC);
        $this->collapsible(false);
        $this->no_sorting('actions');

        // The per-sentiment counts and score are aggregated here, in a derived table, so
        // the rest of table_sql's normal WHERE/ORDER BY/paging machinery can treat each
        // course as a single row - sorting by any column then just works as normal.
        // "* 1.0" forces float division of the score across MySQL/Postgres/MSSQL alike,
        // rather than truncating integer division on some of them.
        //
        // recentavg/olderavg split each course's TREND_WINDOW most recent responses into
        // two equal halves via ROW_NUMBER() - ranked newest-first, so rn 1..half is the
        // newer half and half+1..window the older one. "trend" then reduces that gap to
        // -1/0/1 (down/flat-or-not-enough-data/up), computed here rather than in PHP so
        // it's a real column table_sql's normal column sorting can ORDER BY directly, the
        // same way avgscore already does. olderavg only goes NULL below TREND_WINDOW/2
        // responses, so "trend" explicitly requires the full TREND_WINDOW too - otherwise
        // a course just past halfway would be judged on a lopsided 5-recent-vs-2-older
        // comparison.
        $happy = self::SCORE_POINTS['happy'];
        $neutral = self::SCORE_POINTS['neutral'];
        $sad = self::SCORE_POINTS['sad'];
        $this->trendwindow = self::get_trend_window();
        $window = $this->trendwindow;
        $half = intdiv($window, 2);
        $threshold = self::TREND_THRESHOLD;
        $submissionswhere = $campaignfilter ? ' WHERE campaignid = :campaignfilter' : '';
        $from = "(SELECT
                courseid, coursename, happycount, neutralcount, sadcount, totalcount, avgscore,
                recentavg, olderavg,
                CASE
                    WHEN totalcount < $window THEN 0
                    WHEN (recentavg - olderavg) >= $threshold THEN 1
                    WHEN (recentavg - olderavg) <= -$threshold THEN -1
                    ELSE 0
                END AS trend
             FROM (SELECT
                    courseid,
                    coursename,
                    SUM(CASE WHEN sentiment = 'happy' THEN 1 ELSE 0 END) AS happycount,
                    SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) AS neutralcount,
                    SUM(CASE WHEN sentiment = 'sad' THEN 1 ELSE 0 END) AS sadcount,
                    COUNT(*) AS totalcount,
                    (SUM(score) * 1.0) / COUNT(*) AS avgscore,
                    AVG(CASE WHEN rn <= $half THEN score END) AS recentavg,
                    AVG(CASE WHEN rn > $half AND rn <= $window THEN score END) AS olderavg
                 FROM (SELECT
                        courseid,
                        coursename,
                        sentiment,
                        CASE
                            WHEN sentiment = 'happy' THEN $happy
                            WHEN sentiment = 'neutral' THEN $neutral
                            WHEN sentiment = 'sad' THEN $sad
                            ELSE 0
                        END AS score,
                        ROW_NUMBER() OVER (PARTITION BY courseid ORDER BY timecreated DESC) AS rn
                     FROM {local_communications_submissions}$submissionswhere) scoredsubmissions
                 GROUP BY courseid, coursename) coursestats) feedbacksummary";

        // Only join out to {course} when actually filtering by category - a course
        // deleted since it received feedback has no row there, and the unfiltered view
        // still needs to show it (via the coursename/courseid denormalised onto each
        // submission), same as everywhere else in this table.
        if ($categoryfilter) {
            $from .= ' JOIN {course} c ON c.id = feedbacksummary.courseid';
        }

        $conditions = [];
        $params = [];
        if ($campaignfilter) {
            $params['campaignfilter'] = $campaignfilter;
        }
        $goodmin = max(self::SCORE_POINTS) - 1;
        $badmax = min(self::SCORE_POINTS) + 1;
        switch ($tier) {
            case 'bad':
                $conditions[] = 'avgscore <= :badmax';
                $params['badmax'] = $badmax;
                break;
            case 'good':
                $conditions[] = 'avgscore >= :goodmin';
                $params['goodmin'] = $goodmin;
                break;
            case 'okay':
                $conditions[] = 'avgscore > :badmax AND avgscore < :goodmin';
                $params['badmax'] = $badmax;
                $params['goodmin'] = $goodmin;
                break;
        }
        switch ($trendfilter) {
            case 'up':
                $conditions[] = 'trend = 1';
                break;
            case 'down':
                $conditions[] = 'trend = -1';
                break;
        }
        if ($categoryfilter) {
            $conditions[] = 'c.category = :coursecategory';
            $params['coursecategory'] = $categoryfilter;
        }
        $where = $conditions ? implode(' AND ', $conditions) : '1=1';

        $this->set_sql(
            'courseid, coursename, happycount, neutralcount, sadcount, totalcount, avgscore, recentavg, olderavg, trend',
            $from,
            $where,
            $params
        );
    }

    /**
     * How many recent responses form the trend window, from the local_communications/trendwindow
     * admin setting. Falls back to {@see TREND_WINDOW} if the setting is unset or has been
     * left in an unusable state (non-numeric, or too small to split into two halves) - an
     * admin fat-fingering the field shouldn't break the report.
     *
     * @return int
     */
    public static function get_trend_window(): int {
        $configured = get_config('local_communications', 'trendwindow');
        $window = (int) $configured;
        if ($window < 2) {
            return self::TREND_WINDOW;
        }
        return $window;
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    public function col_coursename($row): string {
        $url = new \moodle_url('/course/view.php', ['id' => $row->courseid]);
        return \html_writer::link($url, format_string($row->coursename ?: ('#' . $row->courseid)));
    }

    /**
     * Buckets a score into bad/okay/good, the same boundaries used for this column's
     * colour-coding, the "courses needing attention" stat, and the score-distribution
     * chart on report.php - kept in one place so those three never drift apart.
     *
     * @param float $score
     * @return string 'bad', 'okay' or 'good'
     */
    public static function score_tier(float $score): string {
        if ($score >= max(self::SCORE_POINTS) - 1) {
            return 'good';
        }
        if ($score <= min(self::SCORE_POINTS) + 1) {
            return 'bad';
        }
        return 'okay';
    }

    /**
     * Direction of travel over the course's TREND_WINDOW most recent responses - the newer
     * half scoring meaningfully higher ('up'), lower ('down'), or not enough differently
     * ('' - no arrow) than the older half. Reads the numeric "trend" SQL column (-1/0/1)
     * computed in the constructor, rather than recomputing the comparison here, so this and
     * the "trend" column's sort order can never disagree.
     *
     * @param \stdClass $row
     * @return string 'up', 'down' or ''
     */
    public static function trend_direction($row): string {
        $trend = (int) $row->trend;
        if ($trend > 0) {
            return 'up';
        }
        if ($trend < 0) {
            return 'down';
        }
        return '';
    }

    /**
     * The weighted average score out of 5, colour-tiered so the best/worst courses are
     * scannable at a glance rather than requiring the reader to compare raw numbers.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_avgscore($row): string {
        $score = (float) $row->avgscore;

        return \html_writer::span(
            number_format($score, 1) . ' / ' . max(self::SCORE_POINTS),
            'local-communications__score local-communications__score--' . self::score_tier($score)
        );
    }

    /**
     * Arrow showing a row's recent direction of travel (see trend_direction()), sortable
     * like any other column since "trend" is a real numeric column in the underlying query.
     * A dash means either the trend is flat, or there isn't a full $window of responses
     * yet to judge one from - the tooltip spells out which. A thin wrapper around
     * {@see render_trend_indicator()} so col_trend() doesn't have to pass $this->trendwindow
     * around itself; that method is what report.php's flat (non-course-focused) dashboard
     * calls directly for its own overall-trend stat card, since it has no per-course rows
     * to render this into a table column for.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_trend($row): string {
        return self::render_trend_indicator($row, $this->trendwindow);
    }

    /**
     * @param \stdClass $row Needs ->totalcount, ->trend, ->recentavg, ->olderavg - the
     *                        shape both this table's own query and
     *                        stats::get_campaign_trend() produce.
     * @param int $window The trend window that row was computed against (courses_summary_table::get_trend_window()).
     * @return string
     */
    public static function render_trend_indicator(\stdClass $row, int $window): string {
        if ((int) $row->totalcount < $window) {
            $label = get_string('report_trend_nodata', 'local_communications', $window);
            return \html_writer::span('–', 'local-communications__trend local-communications__trend--none', [
                'title' => $label,
                'aria-label' => $label,
            ]);
        }

        $a = (object) [
            'n' => intdiv($window, 2),
            'recentavg' => number_format((float) $row->recentavg, 1),
            'olderavg' => number_format((float) $row->olderavg, 1),
        ];

        $trend = self::trend_direction($row);
        if ($trend === '') {
            $label = get_string('report_trend_flat', 'local_communications', $a);
            return \html_writer::span('–', 'local-communications__trend local-communications__trend--none', [
                'title' => $label,
                'aria-label' => $label,
            ]);
        }

        $arrow = $trend === 'up' ? '▲' : '▼';
        $label = get_string('report_trend_' . $trend, 'local_communications', $a);
        return \html_writer::span($arrow, 'local-communications__trend local-communications__trend--' . $trend, [
            'title' => $label,
            'aria-label' => $label,
        ]);
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    public function col_actions($row): string {
        $url = new \moodle_url(
            '/local/communications/course_report.php', ['courseid' => $row->courseid, 'campaignid' => $this->campaignid]
        );
        return \html_writer::link($url, get_string('report_viewfeedback', 'local_communications'), ['class' => 'btn btn-secondary btn-sm']);
    }
}
