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
 * Shared rendering helpers for campaign reports (report.php/course_report.php).
 *
 * render_category_performance() draws the "Category performance" card on a
 * course-focused campaign's report - a glance at which course categories are scoring
 * best/worst, without listing every category on the page itself the way an early
 * version of this card did (unworkable on a site with many categories). The
 * at-a-glance view is a fixed best-3/worst-5 split; the full list sits behind a native
 * <details> disclosure, sortable by clicking a column header, so it's out of the way
 * until wanted rather than always taking up page space.
 *
 * render_widget_preview() draws the "what respondents saw" card - the campaign's
 * effective modal title and intro text.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_helper {

    /** How many best-scoring categories the compact view shows. */
    protected const BEST_COUNT = 3;

    /** How many worst-scoring categories the compact view shows. */
    protected const WORST_COUNT = 5;

    /** Sortable columns for the full table, mapped to how each is compared. */
    protected const SORT_COLUMNS = ['category', 'total', 'score'];

    /**
     * A small preview of what respondents actually saw when the widget opened for this
     * campaign - its effective modal title (the campaign's own override, or the site
     * default) and intro text, if it set one - shown once at the top of a campaign's
     * report so the numbers below are read with the actual question in mind. Rendered
     * as plain escaped text throughout, matching how the widget itself displays both
     * (see amd/src/app.js, which sets them via jQuery's .text()) - never as markup.
     *
     * @param \stdClass $campaign
     * @return string
     */
    public static function render_widget_preview(\stdClass $campaign): string {
        $title = $campaign->modaltitle ?: get_string('modaltitle', 'local_feedback');

        $out = \html_writer::div(get_string('report_widgetpreview', 'local_feedback'), 'local-feedback__widgetpreview-label');
        $out .= \html_writer::div(format_string($title), 'local-feedback__widgetpreview-title');
        if (!empty($campaign->introtext)) {
            $out .= \html_writer::tag('p', s($campaign->introtext), ['class' => 'local-feedback__widgetpreview-intro']);
        }

        return \html_writer::div($out, 'local-feedback__widgetpreview');
    }

    /**
     * @param array<int, \stdClass> $rows Each needs ->categoryid, ->label (already
     *                                     formatted/escaped for output), ->totalcount and
     *                                     ->avgscore. Assumed pre-sorted worst-first, as
     *                                     stats::get_category_breakdown() returns.
     * @param \moodle_url $baseurl Current page URL (with any other filters already
     *                             applied) to build the column-sort links against.
     * @param string $sort Which column the full table is sorted by - 'category', 'total'
     *                      or 'score'; anything else falls back to 'score'.
     * @param string $dir 'asc' or 'desc'; anything else falls back to 'asc'.
     * @return string HTML, or '' if there's nothing worth showing (0 or 1 rows - no
     *                 comparison to make).
     */
    public static function render_category_performance(
        array $rows,
        \moodle_url $baseurl,
        string $sort,
        string $dir
    ): string {
        if (count($rows) < 2) {
            return '';
        }

        if (!in_array($sort, self::SORT_COLUMNS, true)) {
            $sort = 'score';
        }
        $dir = $dir === 'desc' ? 'desc' : 'asc';

        return self::build_compact_lists($rows) . self::build_full_table($rows, $baseurl, $sort, $dir);
    }

    /**
     * The always-visible part of the card: best {@see BEST_COUNT} and worst
     * {@see WORST_COUNT}, each category appearing in at most one list (a site with few
     * enough categories that the two would otherwise overlap just shows fewer "best").
     *
     * @param array<int, \stdClass> $rows Worst-first.
     * @return string
     */
    protected static function build_compact_lists(array $rows): string {
        $worst = array_slice($rows, 0, self::WORST_COUNT);
        $usedids = array_map(fn($row) => $row->categoryid, $worst);

        $best = [];
        foreach (array_reverse($rows) as $row) {
            if (count($best) >= self::BEST_COUNT) {
                break;
            }
            if (!in_array($row->categoryid, $usedids, true)) {
                $best[] = $row;
            }
        }

        $out = '';
        if ($best) {
            $out .= self::build_mini_list($best, get_string('report_topperforming', 'local_feedback'), 'best');
        }
        if ($worst) {
            $out .= self::build_mini_list($worst, get_string('report_lowscoring', 'local_feedback'), 'worst');
        }

        return \html_writer::div($out, 'local-feedback__category-lists');
    }

    /**
     * @param array<int, \stdClass> $rows
     * @param string $heading
     * @param string $modifier BEM-style modifier for styling hooks ('best'/'worst').
     * @return string
     */
    protected static function build_mini_list(array $rows, string $heading, string $modifier): string {
        $items = '';
        foreach ($rows as $row) {
            $items .= \html_writer::tag(
                'li',
                \html_writer::span($row->label, 'local-feedback__category-list-label') . self::score_pill($row)
            );
        }

        return \html_writer::div(
            \html_writer::div($heading, 'local-feedback__category-list-heading')
            . \html_writer::tag('ul', $items, ['class' => 'local-feedback__category-list']),
            'local-feedback__category-list-group local-feedback__category-list-group--' . $modifier
        );
    }

    /**
     * The full list, sorted by whichever column was clicked, tucked behind a <details> so
     * it doesn't compete with the compact view for space until an admin wants it.
     *
     * @param array<int, \stdClass> $rows
     * @param \moodle_url $baseurl
     * @param string $sort Already validated against {@see SORT_COLUMNS}.
     * @param string $dir Already validated as 'asc' or 'desc'.
     * @return string
     */
    protected static function build_full_table(array $rows, \moodle_url $baseurl, string $sort, string $dir): string {
        $columns = [
            'category' => get_string('report_col_coursecategory', 'local_feedback'),
            'total' => get_string('report_col_total', 'local_feedback'),
            'score' => get_string('report_col_avgscore', 'local_feedback'),
        ];

        $sorted = $rows;
        usort($sorted, function ($a, $b) use ($sort) {
            return match ($sort) {
                'category' => $a->label <=> $b->label,
                'total' => $a->totalcount <=> $b->totalcount,
                default => $a->avgscore <=> $b->avgscore,
            };
        });
        if ($dir === 'desc') {
            $sorted = array_reverse($sorted);
        }

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable local-feedback__breakdown';
        $table->head = [];
        foreach ($columns as $key => $label) {
            $newdir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
            $sorturl = new \moodle_url($baseurl, ['catsort' => $key, 'catdir' => $newdir]);
            $arrow = '';
            if ($sort === $key) {
                $arrow = ' ' . ($dir === 'asc' ? '▲' : '▼');
            }
            $table->head[] = \html_writer::link($sorturl, $label . $arrow);
        }

        foreach ($sorted as $row) {
            $table->data[] = [$row->label, $row->totalcount, self::score_pill($row)];
        }

        // A sort click is a full page reload - without forcing this open on a non-default
        // sort, the admin's click would re-collapse the very table they just sorted.
        $attributes = ['class' => 'local-feedback__breakdown-more'];
        if ($sort !== 'score' || $dir !== 'asc') {
            $attributes['open'] = 'open';
        }

        $summary = \html_writer::tag('summary', get_string('report_viewall', 'local_feedback', count($rows)));

        return \html_writer::tag('details', $summary . \html_writer::table($table), $attributes);
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    protected static function score_pill(\stdClass $row): string {
        $score = (float) $row->avgscore;
        return \html_writer::span(
            number_format($score, 1) . ' / ' . max(courses_summary_table::SCORE_POINTS),
            'local-feedback__score local-feedback__score--' . courses_summary_table::score_tier($score)
        );
    }
}
