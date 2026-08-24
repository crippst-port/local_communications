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

declare(strict_types=1);

namespace local_communications\table;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for courses_summary_table's shared static helpers: the score tiering and
 * trend-direction logic reused by the site-wide report, the score-distribution chart
 * and stats::get_campaign_trend()'s own "overall trend" stat card.
 *
 * @package     local_communications
 * @covers      \local_communications\table\courses_summary_table
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class courses_summary_table_test extends \advanced_testcase {

    public function test_score_tier_boundaries(): void {
        $this->assertSame('good', courses_summary_table::score_tier(5.0));
        $this->assertSame('good', courses_summary_table::score_tier(4.0));
        $this->assertSame('okay', courses_summary_table::score_tier(3.9));
        $this->assertSame('okay', courses_summary_table::score_tier(2.1));
        $this->assertSame('bad', courses_summary_table::score_tier(2.0));
        $this->assertSame('bad', courses_summary_table::score_tier(1.0));
    }

    public function test_trend_direction_reads_numeric_trend_column(): void {
        $this->assertSame('up', courses_summary_table::trend_direction((object) ['trend' => 1]));
        $this->assertSame('down', courses_summary_table::trend_direction((object) ['trend' => -1]));
        $this->assertSame('', courses_summary_table::trend_direction((object) ['trend' => 0]));
    }

    public function test_get_trend_window_falls_back_when_unset(): void {
        $this->resetAfterTest();
        unset_config('trendwindow', 'local_communications');

        $this->assertSame(courses_summary_table::TREND_WINDOW, courses_summary_table::get_trend_window());
    }

    public function test_get_trend_window_falls_back_when_too_small(): void {
        $this->resetAfterTest();
        set_config('trendwindow', 1, 'local_communications');

        $this->assertSame(courses_summary_table::TREND_WINDOW, courses_summary_table::get_trend_window());
    }

    public function test_get_trend_window_uses_configured_value(): void {
        $this->resetAfterTest();
        set_config('trendwindow', 20, 'local_communications');

        $this->assertSame(20, courses_summary_table::get_trend_window());
    }

    public function test_render_trend_indicator_shows_no_data_below_window(): void {
        $row = (object) ['totalcount' => 3, 'trend' => 1, 'recentavg' => 5, 'olderavg' => 1];

        $html = courses_summary_table::render_trend_indicator($row, 10);

        $this->assertStringContainsString('local-communications__trend--none', $html);
    }

    public function test_render_trend_indicator_shows_up_and_down_arrows(): void {
        $up = (object) ['totalcount' => 10, 'trend' => 1, 'recentavg' => 5, 'olderavg' => 1];
        $down = (object) ['totalcount' => 10, 'trend' => -1, 'recentavg' => 1, 'olderavg' => 5];
        $flat = (object) ['totalcount' => 10, 'trend' => 0, 'recentavg' => 3, 'olderavg' => 3];

        $this->assertStringContainsString('local-communications__trend--up', courses_summary_table::render_trend_indicator($up, 10));
        $this->assertStringContainsString(
            'local-communications__trend--down',
            courses_summary_table::render_trend_indicator($down, 10)
        );
        $this->assertStringContainsString(
            'local-communications__trend--none',
            courses_summary_table::render_trend_indicator($flat, 10)
        );
    }
}
