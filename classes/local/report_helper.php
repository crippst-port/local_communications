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
 * Renders the small "score per X" breakdown tables (by course category, by feedback
 * topic) shared by report.php and course_report.php.
 *
 * A real site can have many course categories or free-text topics - showing all of them
 * unconditionally would make the report page as long as the data is wide, defeating the
 * point of a glanceable summary. So only the worst-scoring {@see DEFAULT_LIMIT} rows are
 * shown outright; the rest sit behind a native <details> disclosure, which needs no JS
 * and keeps the full data one click away rather than truncated.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_helper {

    /** How many worst-scoring rows to show before collapsing the rest behind "show more". */
    public const DEFAULT_LIMIT = 10;

    /**
     * @param array<int, \stdClass> $rows Each needs ->label (already formatted/escaped for
     *                                     output), ->totalcount and ->avgscore. Assumed
     *                                     pre-sorted worst-first, as stats::get_category_
     *                                     breakdown() and get_topic_breakdown() return.
     * @param string $labelheader Column header for the label column (e.g. "Category").
     * @param int $limit Rows to show before collapsing the rest behind "show more".
     * @return string HTML, or '' if there's nothing worth a table for (0 or 1 rows -
     *                 no comparison to make).
     */
    public static function render_score_breakdown(
        array $rows,
        string $labelheader,
        int $limit = self::DEFAULT_LIMIT
    ): string {
        if (count($rows) < 2) {
            return '';
        }

        $visible = array_slice($rows, 0, $limit);
        $rest = array_slice($rows, $limit);

        $out = self::build_table($visible, $labelheader);

        if ($rest) {
            $summary = \html_writer::tag(
                'summary',
                get_string('report_breakdown_showmore', 'local_feedback', count($rest))
            );
            $out .= \html_writer::tag(
                'details',
                $summary . self::build_table($rest, $labelheader),
                ['class' => 'local-feedback__breakdown-more']
            );
        }

        return $out;
    }

    /**
     * @param array<int, \stdClass> $rows
     * @param string $labelheader
     * @return string
     */
    protected static function build_table(array $rows, string $labelheader): string {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable local-feedback__breakdown';
        $table->head = [
            $labelheader,
            get_string('report_col_total', 'local_feedback'),
            get_string('report_col_avgscore', 'local_feedback'),
        ];

        foreach ($rows as $row) {
            $score = (float) $row->avgscore;
            $scorehtml = \html_writer::span(
                number_format($score, 1) . ' / ' . max(courses_summary_table::SCORE_POINTS),
                'local-feedback__score local-feedback__score--' . courses_summary_table::score_tier($score)
            );
            $table->data[] = [$row->label, $row->totalcount, $scorehtml];
        }

        return \html_writer::table($table);
    }
}
