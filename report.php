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
 * A single campaign's feedback dashboard - every report is scoped to exactly one
 * campaign, there is no cross-campaign comparison (each campaign asks a different
 * question, of a different audience, about different pages).
 *
 * Course-focused campaigns (see the "Course-focused campaign" checkbox on
 * classes/form/campaign_form.php) get a course/category comparison view - the same
 * shape this page always showed before campaigns existed, just permanently locked to
 * one campaign's responses. Campaigns that aren't course-focused (a dashboard-only
 * survey, say) get a flat combined list instead, since ranking "courses" wouldn't mean
 * anything for them. For one specific course's own drill-down under one campaign, see
 * course_report.php - linked from that course's own Reports menu when a course-focused
 * campaign targets it.
 *
 * Reachable from manage_campaigns.php's "View responses" link; this page has no
 * campaign selector itself.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/table/courses_summary_table.php');
require_once(__DIR__ . '/classes/table/submissions_table.php');
require_once(__DIR__ . '/classes/local/stats.php');
require_once(__DIR__ . '/classes/local/report_helper.php');
require_once(__DIR__ . '/classes/local/campaigns.php');

use local_feedback\table\courses_summary_table;
use local_feedback\table\submissions_table;
use local_feedback\local\stats;
use local_feedback\local\report_helper;
use local_feedback\local\campaigns;

admin_externalpage_setup('local_feedback_campaigns');

$campaignid = required_param('campaignid', PARAM_INT);
$campaign = campaigns::get($campaignid);
if (!$campaign) {
    throw new moodle_exception('invalidrecord', 'error');
}

$heading = get_string('reportheading_campaign', 'local_feedback', format_string($campaign->name));
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->requires->css('/local/feedback/styles.css');

$labels = campaigns::get_sentiment_labels($campaign);

$download = optional_param('download', '', PARAM_ALPHA);
$reset = optional_param('reset', 0, PARAM_BOOL);

if ($campaign->coursefocused) {
    // ---- Course-focused: course/category comparison, scoped to this campaign only. ----
    $tier = optional_param('tier', '', PARAM_ALPHA);
    $trend = optional_param('trend', '', PARAM_ALPHA);
    $category = optional_param('category', 0, PARAM_INT);
    $catsort = optional_param('catsort', 'score', PARAM_ALPHA);
    $catdir = optional_param('catdir', 'asc', PARAM_ALPHA);

    if ($reset) {
        $tier = '';
        $trend = '';
        $category = 0;
    }

    $urlparams = ['campaignid' => $campaignid];
    if ($tier !== '') {
        $urlparams['tier'] = $tier;
    }
    if ($trend !== '') {
        $urlparams['trend'] = $trend;
    }
    if ($category) {
        $urlparams['category'] = $category;
    }
    $url = new moodle_url('/local/feedback/report.php', $urlparams);

    $table = new courses_summary_table(
        'local-feedback-courses-summary', $url, $download !== '', $tier, $trend, $category, $campaignid, $labels
    );
    $table->is_downloading($download, 'campaign_feedback_summary', $heading);
    $table->show_download_buttons_at([TABLE_P_TOP]);

    if (!$table->is_downloading()) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading($heading);
        echo report_helper::render_widget_preview($campaign);
    }

    $counts = $DB->get_records_sql(
        'SELECT sentiment, COUNT(*) AS total FROM {local_feedback_submissions} WHERE campaignid = :campaignid GROUP BY sentiment',
        ['campaignid' => $campaignid]
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
        if ($total > 0) {
            echo html_writer::div(
                get_string('report_score_explain', 'local_feedback', (object) $labels), 'local-feedback__score-explain'
            );
        }

        echo html_writer::start_div('local-feedback__stats');
        echo html_writer::div(
            $total, 'local-feedback__stat-value', ['data-label' => get_string('report_stat_total', 'local_feedback')]
        );
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

            $tiercounts = stats::get_course_tier_counts($campaignid);
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

            $categorybreakdown = stats::get_category_breakdown($campaignid);
            foreach ($categorybreakdown as $row) {
                $row->label = format_string($row->categoryname);
            }
            $categoryperformancehtml = report_helper::render_category_performance($categorybreakdown, $url, $catsort, $catdir);
            if ($categoryperformancehtml !== '') {
                echo html_writer::div(
                    html_writer::div(
                        get_string('report_heading_categoryperformance', 'local_feedback'), 'local-feedback__stat-card-title'
                    ) . $categoryperformancehtml,
                    'local-feedback__stat-card local-feedback__stat-card--breakdown'
                );
            }
        }
        echo html_writer::end_div();

        if ($total === 0) {
            echo $OUTPUT->notification(get_string('report_nofeedback', 'local_feedback'), 'info');
        } else {
            // Filter the courses table below by score tier - "needs attention" vs "good" etc.
            echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'local-feedback__filters']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'campaignid', 'value' => $campaignid]);

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

            echo html_writer::start_tag('label', ['for' => 'local-feedback-filter-category']);
            echo get_string('report_filtercategory', 'local_feedback');
            echo html_writer::end_tag('label');

            $categoryoptions = [0 => get_string('report_allcategories', 'local_feedback')];
            foreach ($categorybreakdown as $row) {
                $categoryoptions[$row->categoryid] = $row->label;
            }
            echo html_writer::select($categoryoptions, 'category', $category, null, ['id' => 'local-feedback-filter-category']);

            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => get_string('report_apply', 'local_feedback'),
                'class' => 'btn btn-primary',
            ]);
            echo html_writer::link(
                new moodle_url('/local/feedback/report.php', ['campaignid' => $campaignid, 'reset' => 1]),
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
} else {
    // ---- Not course-focused: flat combined list, no course/category comparison. ----
    $sentiment = optional_param('sentiment', '', PARAM_ALPHA);
    $topic = optional_param('topic', '', PARAM_RAW_TRIMMED);

    if ($reset) {
        $sentiment = '';
        $topic = '';
    }

    $urlparams = ['campaignid' => $campaignid];
    if ($sentiment !== '') {
        $urlparams['sentiment'] = $sentiment;
    }
    if ($topic !== '') {
        $urlparams['topic'] = $topic;
    }
    $url = new moodle_url('/local/feedback/report.php', $urlparams);

    $table = new submissions_table(
        'local-feedback-campaign-submissions', $url, 0, $sentiment, false, $topic, $campaignid, $labels
    );
    $table->is_downloading($download, 'campaign_feedback', $heading);
    $table->show_download_buttons_at([TABLE_P_TOP]);

    if (!$table->is_downloading()) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading($heading);
        echo report_helper::render_widget_preview($campaign);
    }

    $counts = $DB->get_records_sql(
        'SELECT sentiment, COUNT(*) AS total FROM {local_feedback_submissions} WHERE campaignid = :campaignid GROUP BY sentiment',
        ['campaignid' => $campaignid]
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
        // Trend renders first (before the four counts) purely so the grid's default
        // left-to-right, row-by-row auto-placement lands it in column 1 - where the
        // "--trend" modifier below makes it span both rows - and flows the other four
        // into the resulting 2x2 block beside it, rather than needing every card's
        // position pinned explicitly.
        echo html_writer::start_div('local-feedback__stats local-feedback__stats--withtrend');
        if ($total > 0) {
            $trendrow = stats::get_campaign_trend($campaignid);
            echo html_writer::div(
                courses_summary_table::render_trend_indicator($trendrow, courses_summary_table::get_trend_window()),
                'local-feedback__stat-value local-feedback__stat-value--trend',
                ['data-label' => get_string('report_col_trend', 'local_feedback')]
            );
        }
        echo html_writer::div(
            $total, 'local-feedback__stat-value', ['data-label' => get_string('report_stat_total', 'local_feedback')]
        );
        foreach (['happy', 'neutral', 'sad'] as $key) {
            echo html_writer::div(
                $stats[$key],
                'local-feedback__stat-value',
                ['data-label' => $labels[$key]]
            );
        }
        echo html_writer::end_div();

        // Filter form: sentiment and topic - there is no course selector here, this
        // campaign isn't scoped to any single course (that's what "not course-focused"
        // means) so responses from every course it happened to be seen on are pooled.
        echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'local-feedback__filters']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'campaignid', 'value' => $campaignid]);

        echo html_writer::start_tag('label', ['for' => 'local-feedback-filter-sentiment']);
        echo get_string('report_filtersentiment', 'local_feedback');
        echo html_writer::end_tag('label');

        $sentimentoptions = [
            '' => get_string('report_allsentiments', 'local_feedback'),
            'happy' => $labels['happy'],
            'neutral' => $labels['neutral'],
            'sad' => $labels['sad'],
        ];
        echo html_writer::select($sentimentoptions, 'sentiment', $sentiment, null, ['id' => 'local-feedback-filter-sentiment']);

        echo html_writer::start_tag('label', ['for' => 'local-feedback-filter-topic']);
        echo get_string('report_filtertopic', 'local_feedback');
        echo html_writer::end_tag('label');

        $topicoptions = ['' => get_string('report_alltopics', 'local_feedback')];
        foreach (stats::get_topic_breakdown(0, $campaignid) as $row) {
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
            new moodle_url('/local/feedback/report.php', ['campaignid' => $campaignid, 'reset' => 1]),
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
}
