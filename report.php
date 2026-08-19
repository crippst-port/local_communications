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
 * Site-wide feedback overview: one row per course with sentiment counts and a
 * weighted average score, sortable so "best"/"worst" is just a column sort.
 * Each row drills down into that course's own feedback via course_report.php.
 *
 * Reachable from Site administration > Reports, gated by local/feedback:viewreports
 * held at the SYSTEM context. For a single course's own feedback, scoped to viewers who
 * only hold the capability within that course, see course_report.php instead - linked
 * from that course's own Reports menu.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/table/courses_summary_table.php');
require_once(__DIR__ . '/classes/local/stats.php');

use local_feedback\table\courses_summary_table;
use local_feedback\local\stats;

admin_externalpage_setup('local_feedback_report');

$download = optional_param('download', '', PARAM_ALPHA);
$tier = optional_param('tier', '', PARAM_ALPHA);
$trend = optional_param('trend', '', PARAM_ALPHA);
$reset = optional_param('reset', 0, PARAM_BOOL);

if ($reset) {
    $tier = '';
    $trend = '';
}

$urlparams = [];
if ($tier !== '') {
    $urlparams['tier'] = $tier;
}
if ($trend !== '') {
    $urlparams['trend'] = $trend;
}
$url = new moodle_url('/local/feedback/report.php', $urlparams);

$table = new courses_summary_table('local-feedback-courses-summary', $url, $download !== '', $tier, $trend);
$table->is_downloading($download, 'course_feedback_summary', get_string('reportheading_sitewide', 'local_feedback'));
$table->show_download_buttons_at([TABLE_P_TOP]);

if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('reportheading_sitewide', 'local_feedback'));
}

// Overall stats, across every course.
$counts = $DB->get_records_sql(
    'SELECT sentiment, COUNT(*) AS total FROM {local_feedback_submissions} GROUP BY sentiment'
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
    if ($total > 0) {
        $points = courses_summary_table::SCORE_POINTS;
        $weightedsum = $stats['happy'] * $points['happy'] + $stats['neutral'] * $points['neutral']
            + $stats['sad'] * $points['sad'];
        $avgscore = number_format($weightedsum / $total, 1) . ' / ' . max($points);
        echo html_writer::div(
            $avgscore,
            'local-feedback__stat-value',
            ['data-label' => get_string('report_stat_avgscore', 'local_feedback')]
        );

        $tiercounts = stats::get_course_tier_counts();
        echo html_writer::div(
            array_sum($tiercounts),
            'local-feedback__stat-value',
            ['data-label' => get_string('report_stat_coursecount', 'local_feedback')]
        );
        echo html_writer::div(
            $tiercounts['bad'],
            'local-feedback__stat-value local-feedback__stat-value--bad',
            [
                'data-label' => get_string('report_stat_needsattention', 'local_feedback'),
                'title' => get_string('report_needsattention_explain', 'local_feedback'),
            ]
        );
    }
    echo html_writer::end_div();

    if ($total === 0) {
        echo $OUTPUT->notification(get_string('report_nofeedback', 'local_feedback'), 'info');
    } else {
        echo html_writer::div(get_string('report_score_explain', 'local_feedback'), 'local-feedback__score-explain');

        // Filter the courses table below by score tier - "needs attention" vs "good" etc.
        echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'local-feedback__filters']);

        echo html_writer::start_tag('label', ['for' => 'local-feedback-filter-tier']);
        echo get_string('report_filtertier', 'local_feedback');
        echo html_writer::end_tag('label');

        $tieroptions = [
            '' => get_string('report_alltiers', 'local_feedback'),
            'bad' => get_string('tier_bad', 'local_feedback'),
            'okay' => get_string('tier_okay', 'local_feedback'),
            'good' => get_string('tier_good', 'local_feedback'),
        ];
        echo html_writer::select($tieroptions, 'tier', $tier, null, ['id' => 'local-feedback-filter-tier']);

        echo html_writer::start_tag('label', ['for' => 'local-feedback-filter-trend']);
        echo get_string('report_filtertrend', 'local_feedback');
        echo html_writer::end_tag('label');

        $trendoptions = [
            '' => get_string('report_alltrends', 'local_feedback'),
            'up' => get_string('report_trendoption_up', 'local_feedback'),
            'down' => get_string('report_trendoption_down', 'local_feedback'),
        ];
        echo html_writer::select($trendoptions, 'trend', $trend, null, ['id' => 'local-feedback-filter-trend']);

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('report_apply', 'local_feedback'),
            'class' => 'btn btn-primary',
        ]);
        echo html_writer::link(
            new moodle_url('/local/feedback/report.php', ['reset' => 1]),
            get_string('report_reset', 'local_feedback'),
            ['class' => 'btn btn-secondary']
        );
        echo html_writer::end_tag('form');
    }
}

if ($total > 0 || $table->is_downloading()) {
    $table->out(30, false);
}

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
