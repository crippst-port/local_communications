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
 * Feedback report for a single course, locked to that course only.
 *
 * Linked from that course's own Reports menu (see local_feedback_extend_navigation_course()
 * in lib.php). Access is checked at the COURSE context, so a role granting
 * local/feedback:viewreports only within this course (not site-wide) is enough - and,
 * unlike report.php, there is no course selector here and no way to see any other course's
 * data from this page.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/table/submissions_table.php');
require_once(__DIR__ . '/classes/local/stats.php');

use local_feedback\table\submissions_table;
use local_feedback\local\stats;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($course->id);
require_capability('local/feedback:viewreports', $context);

$sentiment = optional_param('sentiment', '', PARAM_ALPHA);
$topic = optional_param('topic', '', PARAM_RAW_TRIMMED);
$reset = optional_param('reset', 0, PARAM_BOOL);
$download = optional_param('download', '', PARAM_ALPHA);

if ($reset) {
    $sentiment = '';
    $topic = '';
}

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/feedback/course_report.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('reportheading', 'local_feedback') . ': ' . format_string($course->fullname));
$PAGE->set_heading($course->fullname);

$urlparams = ['courseid' => $courseid];
if ($sentiment !== '') {
    $urlparams['sentiment'] = $sentiment;
}
if ($topic !== '') {
    $urlparams['topic'] = $topic;
}
$url = new moodle_url('/local/feedback/course_report.php', $urlparams);

$table = new submissions_table('local-feedback-course-submissions', $url, $courseid, $sentiment, true, $topic);
$table->is_downloading($download, 'course_feedback_' . $course->shortname, format_string($course->fullname));
$table->show_download_buttons_at([TABLE_P_TOP]);

if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('reportheading', 'local_feedback'));
}

// Summary stats, scoped to this course.
$counts = $DB->get_records_sql(
    'SELECT sentiment, COUNT(*) AS total FROM {local_feedback_submissions} WHERE courseid = :courseid GROUP BY sentiment',
    ['courseid' => $courseid]
);
$stats = ['happy' => 0, 'neutral' => 0, 'sad' => 0];
$total = 0;
foreach ($counts as $row) {
    if (isset($stats[$row->sentiment])) {
        $stats[$row->sentiment] = (int) $row->total;
    }
    $total += (int) $row->total;
}

if (!$table->is_downloading()) {
    echo html_writer::start_div('local-feedback__stats');
    echo html_writer::div($total, 'local-feedback__stat-value', ['data-label' => get_string('report_stat_total', 'local_feedback')]);
    foreach (['happy', 'neutral', 'sad'] as $key) {
        echo html_writer::div(
            $stats[$key],
            'local-feedback__stat-value',
            ['data-label' => get_string('report_stat_' . $key, 'local_feedback')]
        );
    }
    echo html_writer::end_div();

    // Filter form: sentiment and topic - the course itself is fixed by the page you're on.
    // Topic options are the topics this course has actually been given feedback on (rather
    // than the current admin-configured preset list), so a submission tagged with a preset
    // that's since been edited/removed, or free text via "Other", is always filterable.
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'local-feedback__filters']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

    echo html_writer::start_tag('label', ['for' => 'local-feedback-filter-sentiment']);
    echo get_string('report_filtersentiment', 'local_feedback');
    echo html_writer::end_tag('label');

    $sentimentoptions = [
        '' => get_string('report_allsentiments', 'local_feedback'),
        'happy' => get_string('sentiment_happy', 'local_feedback'),
        'neutral' => get_string('sentiment_neutral', 'local_feedback'),
        'sad' => get_string('sentiment_sad', 'local_feedback'),
    ];
    echo html_writer::select($sentimentoptions, 'sentiment', $sentiment, null, ['id' => 'local-feedback-filter-sentiment']);

    echo html_writer::start_tag('label', ['for' => 'local-feedback-filter-topic']);
    echo get_string('report_filtertopic', 'local_feedback');
    echo html_writer::end_tag('label');

    $topicoptions = ['' => get_string('report_alltopics', 'local_feedback')];
    foreach (stats::get_topic_breakdown($courseid) as $row) {
        if ($row->category !== null && $row->category !== '') {
            $topicoptions[$row->category] = s($row->category);
        } else {
            $topicoptions[submissions_table::TOPIC_UNSPECIFIED] = get_string('report_topic_unspecified', 'local_feedback');
        }
    }
    echo html_writer::select($topicoptions, 'topic', $topic, null, ['id' => 'local-feedback-filter-topic']);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('report_apply', 'local_feedback'),
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::link(
        new moodle_url('/local/feedback/course_report.php', ['courseid' => $courseid, 'reset' => 1]),
        get_string('report_reset', 'local_feedback'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_tag('form');

    if ($total === 0) {
        echo $OUTPUT->notification(get_string('report_nofeedback', 'local_feedback'), 'info');
    }
}

if ($total > 0 || $table->is_downloading()) {
    $table->out(30, false);
}

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
