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

namespace local_feedback\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Table listing feedback submissions, shared by the per-course and site-wide report pages.
 *
 * Access scoping (which courses a viewer is allowed to see) is enforced by the calling
 * page, not here: report.php always passes $courseid = 0 (site-wide, no restriction) and
 * course_report.php always passes a specific, capability-checked $courseid.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submissions_table extends \table_sql {

    /** @var array Emoji + string lookup per sentiment value. */
    protected const SENTIMENT_ICONS = [
        'happy' => '😊',
        'neutral' => '😐',
        'sad' => '😞',
    ];

    /**
     * Sentinel passed as $topic to filter for responses with no topic set (category IS
     * NULL or ''), distinct from '' which means "don't filter by topic at all" - chosen
     * unlikely to collide with a real submitted topic, preset or free text via "Other".
     */
    public const TOPIC_UNSPECIFIED = '__unspecified__';

    /**
     * @param string $uniqueid
     * @param \moodle_url $baseurl
     * @param int $courseid Restrict to this course, 0 for all courses.
     * @param string $sentiment Filter by sentiment, '' for all.
     * @param bool $hidecoursecolumn Omit the course column (used on the per-course report,
     *                                where every row is the same course).
     * @param string $topic Filter by topic (the "category" column) - an exact match
     *                       against submitted text, {@see TOPIC_UNSPECIFIED} for
     *                       responses with none set, or '' for all.
     */
    public function __construct(
        $uniqueid,
        \moodle_url $baseurl,
        int $courseid = 0,
        string $sentiment = '',
        bool $hidecoursecolumn = false,
        string $topic = ''
    ) {
        parent::__construct($uniqueid);

        $columns = ['timecreated', 'sentiment', 'category', 'coursename', 'activity', 'user', 'feedbacktext', 'page'];
        $headers = [
            get_string('report_col_time', 'local_feedback'),
            get_string('report_col_sentiment', 'local_feedback'),
            get_string('report_col_category', 'local_feedback'),
            get_string('report_col_course', 'local_feedback'),
            get_string('report_col_activity', 'local_feedback'),
            get_string('report_col_user', 'local_feedback'),
            get_string('report_col_feedback', 'local_feedback'),
            get_string('report_col_page', 'local_feedback'),
        ];

        if ($hidecoursecolumn) {
            $courseindex = array_search('coursename', $columns, true);
            unset($columns[$courseindex], $headers[$courseindex]);
            $columns = array_values($columns);
            $headers = array_values($headers);
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);

        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->collapsible(false);
        $this->no_sorting('activity');
        $this->no_sorting('feedbacktext');
        $this->no_sorting('page');
        $this->no_sorting('user');

        $where = ['1=1'];
        $params = [];

        if ($courseid) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        if ($sentiment !== '') {
            $where[] = 'sentiment = :sentiment';
            $params['sentiment'] = $sentiment;
        }

        if ($topic === self::TOPIC_UNSPECIFIED) {
            $where[] = "(category IS NULL OR category = '')";
        } else if ($topic !== '') {
            $where[] = 'category = :topic';
            $params['topic'] = $topic;
        }

        $this->set_sql(
            'id, userid, anonymous, courseid, coursename, cmid, cmname, modname, sectionname,
             sentiment, category, feedbacktext, pageurl, pagetype, breadcrumb, pagetitle, timecreated',
            '{local_feedback_submissions}',
            implode(' AND ', $where),
            $params
        );
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    public function col_timecreated($row): string {
        return userdate($row->timecreated);
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    public function col_sentiment($row): string {
        $icon = self::SENTIMENT_ICONS[$row->sentiment] ?? '';
        $label = get_string('sentiment_' . $row->sentiment, 'local_feedback');
        return $icon . ' ' . $label;
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    public function col_category($row): string {
        return $row->category ? s($row->category) : '-';
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
     * @param \stdClass $row
     * @return string
     */
    public function col_activity($row): string {
        if (empty($row->cmid)) {
            return $row->sectionname ? format_string($row->sectionname) : '-';
        }

        $url = new \moodle_url('/mod/' . $row->modname . '/view.php', ['id' => $row->cmid]);
        $label = format_string($row->cmname ?: $row->modname);

        return \html_writer::link($url, $label);
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    public function col_user($row): string {
        if ($row->anonymous || empty($row->userid)) {
            return get_string('report_anonymous', 'local_feedback');
        }

        $user = \core_user::get_user($row->userid);
        if (!$user) {
            return get_string('report_anonymous', 'local_feedback');
        }

        $url = new \moodle_url('/user/profile.php', ['id' => $row->userid]);
        return \html_writer::link($url, fullname($user));
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    public function col_feedbacktext($row): string {
        return \html_writer::tag('div', s($row->feedbacktext), ['class' => 'local-feedback__report-text']);
    }

    /**
     * Shows Moodle's own breadcrumb trail (e.g. "Course > Grades > User report") as the
     * primary description of where on the course the feedback was submitted from - the
     * course home page, an activity, or any other course-area page - falling back to the
     * page title/type for rows submitted before the breadcrumb was captured.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_page($row): string {
        $label = $row->breadcrumb ?: ($row->pagetitle ?: $row->pagetype);

        if (empty($row->pageurl)) {
            return $label ? s($label) : '-';
        }

        return \html_writer::link(
            $row->pageurl,
            format_string($label ?: get_string('report_viewpage', 'local_feedback')),
            ['target' => '_blank', 'rel' => 'noopener']
        );
    }
}
