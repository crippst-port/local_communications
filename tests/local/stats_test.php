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

namespace local_communications\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the stats class: course-level aggregate statistics for a campaign's
 * report (trend, score-tier counts, category/topic breakdowns).
 *
 * @package     local_communications
 * @covers      \local_communications\local\stats
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stats_test extends \advanced_testcase {

    /**
     * @param array $overrides
     */
    protected function insert_submission(array $overrides = []): int {
        global $DB;

        $record = (object) array_merge([
            'userid' => 0,
            'anonymous' => 1,
            'courseid' => 1,
            'coursename' => 'Course',
            'cmid' => 0,
            'sentiment' => 'happy',
            'category' => null,
            'feedbacktext' => 'Feedback',
            'campaignid' => 0,
            'campaignname' => null,
            'timecreated' => time(),
        ], $overrides);

        return (int) $DB->insert_record('local_communications_submissions', $record);
    }

    public function test_get_campaign_trend_with_no_submissions(): void {
        $this->resetAfterTest();

        $result = stats::get_campaign_trend(999);

        $this->assertEquals(0, $result->totalcount);
        $this->assertEquals(0, $result->trend);
    }

    public function test_get_campaign_trend_detects_upward_shift(): void {
        $this->resetAfterTest();
        set_config('trendwindow', 4, 'local_communications');

        $campaignid = 1;
        $base = time() - 100;
        // Oldest half: sad (score 1). Newest half: happy (score 5).
        $this->insert_submission(['campaignid' => $campaignid, 'sentiment' => 'sad', 'timecreated' => $base + 1]);
        $this->insert_submission(['campaignid' => $campaignid, 'sentiment' => 'sad', 'timecreated' => $base + 2]);
        $this->insert_submission(['campaignid' => $campaignid, 'sentiment' => 'happy', 'timecreated' => $base + 3]);
        $this->insert_submission(['campaignid' => $campaignid, 'sentiment' => 'happy', 'timecreated' => $base + 4]);

        $result = stats::get_campaign_trend($campaignid);

        $this->assertEquals(4, $result->totalcount);
        $this->assertEquals(1, $result->trend);
    }

    public function test_get_campaign_trend_below_window_reports_no_trend(): void {
        $this->resetAfterTest();
        set_config('trendwindow', 10, 'local_communications');

        $campaignid = 1;
        $this->insert_submission(['campaignid' => $campaignid, 'sentiment' => 'happy']);
        $this->insert_submission(['campaignid' => $campaignid, 'sentiment' => 'sad']);

        $result = stats::get_campaign_trend($campaignid);

        $this->assertEquals(2, $result->totalcount);
        $this->assertEquals(0, $result->trend);
    }

    public function test_get_campaign_trend_can_be_scoped_to_one_course(): void {
        $this->resetAfterTest();
        set_config('trendwindow', 4, 'local_communications');

        $campaignid = 1;
        $base = time() - 100;
        // Course 10: all sad. Course 20: all happy. Scoping to course 10 must not be
        // dragged up by course 20's rows.
        for ($i = 0; $i < 4; $i++) {
            $this->insert_submission([
                'campaignid' => $campaignid,
                'courseid' => 10,
                'sentiment' => 'sad',
                'timecreated' => $base + $i,
            ]);
            $this->insert_submission([
                'campaignid' => $campaignid,
                'courseid' => 20,
                'sentiment' => 'happy',
                'timecreated' => $base + $i,
            ]);
        }

        $result = stats::get_campaign_trend($campaignid, 10);
        $this->assertEquals(4, $result->totalcount);
        $this->assertEquals(1.0, (float) $result->recentavg);
    }

    public function test_get_course_tier_counts_buckets_courses_correctly(): void {
        $this->resetAfterTest();

        // Course 1: all happy -> good. Course 2: all sad -> bad. Course 3: all neutral -> okay.
        $this->insert_submission(['courseid' => 1, 'sentiment' => 'happy']);
        $this->insert_submission(['courseid' => 1, 'sentiment' => 'happy']);
        $this->insert_submission(['courseid' => 2, 'sentiment' => 'sad']);
        $this->insert_submission(['courseid' => 3, 'sentiment' => 'neutral']);

        $tiers = stats::get_course_tier_counts();

        $this->assertEquals(1, $tiers['good']);
        $this->assertEquals(1, $tiers['bad']);
        $this->assertEquals(1, $tiers['okay']);
    }

    public function test_get_course_tier_counts_can_be_scoped_to_a_campaign(): void {
        $this->resetAfterTest();

        $this->insert_submission(['courseid' => 1, 'campaignid' => 1, 'sentiment' => 'happy']);
        $this->insert_submission(['courseid' => 2, 'campaignid' => 2, 'sentiment' => 'sad']);

        $tiers = stats::get_course_tier_counts(1);

        $this->assertEquals(1, $tiers['good']);
        $this->assertEquals(0, $tiers['bad']);
    }

    public function test_get_category_breakdown_groups_by_course_category_worst_first(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $goodcat = $this->getDataGenerator()->create_category();
        $badcat = $this->getDataGenerator()->create_category();
        $goodcourse = $this->getDataGenerator()->create_course(['category' => $goodcat->id]);
        $badcourse = $this->getDataGenerator()->create_course(['category' => $badcat->id]);

        $this->insert_submission(['courseid' => $goodcourse->id, 'sentiment' => 'happy']);
        $this->insert_submission(['courseid' => $badcourse->id, 'sentiment' => 'sad']);

        $breakdown = stats::get_category_breakdown();

        $this->assertCount(2, $breakdown);
        // Worst-scoring category first.
        $this->assertEquals($badcat->id, $breakdown[0]->categoryid);
        $this->assertEquals(1, $breakdown[0]->sadcount);
        $this->assertEquals($goodcat->id, $breakdown[1]->categoryid);
        $this->assertEquals(1, $breakdown[1]->happycount);
    }

    public function test_get_category_breakdown_can_be_scoped_to_a_campaign(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        $this->insert_submission(['courseid' => $course->id, 'campaignid' => 1, 'sentiment' => 'happy']);
        $this->insert_submission(['courseid' => $course->id, 'campaignid' => 2, 'sentiment' => 'sad']);

        $breakdown = stats::get_category_breakdown(1);

        $this->assertCount(1, $breakdown);
        $this->assertEquals(1, $breakdown[0]->totalcount);
        $this->assertEquals(1, $breakdown[0]->happycount);
    }

    public function test_get_topic_breakdown_groups_by_submitted_topic(): void {
        $this->resetAfterTest();

        $this->insert_submission(['courseid' => 1, 'category' => 'Assessment', 'sentiment' => 'sad']);
        $this->insert_submission(['courseid' => 1, 'category' => 'Course content', 'sentiment' => 'happy']);
        $this->insert_submission(['courseid' => 1, 'category' => null, 'sentiment' => 'neutral']);

        $breakdown = stats::get_topic_breakdown(1);

        $this->assertCount(3, $breakdown);
        // Worst-scoring topic first.
        $this->assertEquals('Assessment', $breakdown[0]->category);
        $this->assertEquals('Course content', $breakdown[2]->category);
    }

    public function test_get_topic_breakdown_can_be_scoped_to_course_and_campaign(): void {
        $this->resetAfterTest();

        $this->insert_submission(['courseid' => 1, 'campaignid' => 1, 'category' => 'Assessment', 'sentiment' => 'happy']);
        $this->insert_submission(['courseid' => 2, 'campaignid' => 1, 'category' => 'Assessment', 'sentiment' => 'sad']);
        $this->insert_submission(['courseid' => 1, 'campaignid' => 2, 'category' => 'Assessment', 'sentiment' => 'sad']);

        $breakdown = stats::get_topic_breakdown(1, 1);

        $this->assertCount(1, $breakdown);
        $this->assertEquals(1, $breakdown[0]->totalcount);
        $this->assertEquals(1, $breakdown[0]->happycount);
    }
}
