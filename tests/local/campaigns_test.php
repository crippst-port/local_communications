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
 * Unit tests for the campaigns class: CRUD plus the targeting/scheduling logic that
 * decides which campaign (if any) applies to a given page/user.
 *
 * @package     local_communications
 * @covers      \local_communications\local\campaigns
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class campaigns_test extends \advanced_testcase {

    /**
     * The plugin seeds one always-on, all-scope "Default" campaign on install (see
     * db/install.php) so a fresh site behaves like the pre-campaign widget out of the
     * box. Targeting tests need a clean slate so that seeded campaign can't also match.
     */
    protected function clear_campaigns(): void {
        global $DB;
        $DB->delete_records('local_communications_campaigns');
    }

    /**
     * @param array $overrides
     * @return \stdClass
     */
    protected function campaign_data(array $overrides = []): \stdClass {
        return (object) array_merge([
            'name' => 'Test campaign',
            'modaltitle' => '',
            'introtext' => '',
            'enabled' => 1,
            'priority' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'topics' => '',
            'skiptopicstep' => 0,
            'labelhappy' => '',
            'labelneutral' => '',
            'labelsad' => '',
            'coursefocused' => 1,
            'responselimit' => 'none',
            'maxresponses' => 0,
            'categoryids' => '',
            'pagetypepatterns' => '',
            'targetroles' => '',
            'targetcohortid' => 0,
            'targetuserids' => '',
        ], $overrides);
    }

    public function test_create_get_update_delete(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data(['name' => 'Original']));
        $campaign = campaigns::get($id);
        $this->assertSame('Original', $campaign->name);
        $this->assertGreaterThan(0, $campaign->timecreated);
        $this->assertSame($campaign->timecreated, $campaign->timemodified);

        campaigns::update($id, $this->campaign_data(['name' => 'Renamed']));
        $this->assertSame('Renamed', campaigns::get($id)->name);

        campaigns::delete($id);
        $this->assertFalse(campaigns::get($id));
    }

    public function test_delete_removes_response_ledger_but_keeps_submissions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data());
        campaigns::record_response($id, 42, 1);
        $DB->insert_record('local_communications_submissions', (object) [
            'userid' => 42,
            'anonymous' => 0,
            'courseid' => 1,
            'cmid' => 0,
            'sentiment' => 'happy',
            'feedbacktext' => 'Great!',
            'campaignid' => $id,
            'campaignname' => 'Test campaign',
            'timecreated' => time(),
        ]);

        campaigns::delete($id);

        $this->assertEquals(0, $DB->count_records('local_communications_campaign_responses', ['campaignid' => $id]));
        $this->assertEquals(1, $DB->count_records('local_communications_submissions', ['campaignid' => $id]));
    }

    public function test_toggle_flips_enabled_flag(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data(['enabled' => 1]));
        campaigns::toggle($id);
        $this->assertEquals(0, campaigns::get($id)->enabled);
        campaigns::toggle($id);
        $this->assertEquals(1, campaigns::get($id)->enabled);
    }

    public function test_get_all_orders_most_recently_created_first(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $id1 = campaigns::create($this->campaign_data(['name' => 'First']));
        $id2 = campaigns::create($this->campaign_data(['name' => 'Second']));
        $DB->set_field('local_communications_campaigns', 'timecreated', 100, ['id' => $id1]);
        $DB->set_field('local_communications_campaigns', 'timecreated', 200, ['id' => $id2]);

        $all = campaigns::get_all();
        $this->assertEquals($id2, $all[0]->id);
        $this->assertEquals($id1, $all[1]->id);
    }

    public function test_get_active_for_context_matches_by_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $id = campaigns::create($this->campaign_data());

        $active = campaigns::get_active_for_context($course, null, 'course-view', $user);
        $this->assertNotNull($active);
        $this->assertEquals($id, $active->id);
    }

    public function test_get_active_for_context_ignores_disabled_campaign(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        campaigns::create($this->campaign_data(['enabled' => 0]));

        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $user));
    }

    public function test_get_active_for_context_respects_date_window(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $future = campaigns::create($this->campaign_data(['starttime' => $now + DAYSECS]));
        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $user));
        campaigns::delete($future);

        $expired = campaigns::create($this->campaign_data(['endtime' => $now - DAYSECS]));
        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $user));
        campaigns::delete($expired);

        $current = campaigns::create($this->campaign_data([
            'starttime' => $now - DAYSECS,
            'endtime' => $now + DAYSECS,
        ]));
        $active = campaigns::get_active_for_context($course, null, 'course-view', $user);
        $this->assertNotNull($active);
        $this->assertEquals($current, $active->id);
    }

    public function test_get_active_for_context_category_targeting_matches_descendants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $parent = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $othercourse = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        campaigns::create($this->campaign_data(['categoryids' => (string) $parent->id]));

        $this->assertNotNull(campaigns::get_active_for_context($course, null, 'course-view', $user));
        $this->assertNull(campaigns::get_active_for_context($othercourse, null, 'course-view', $user));
    }

    public function test_get_active_for_context_pagetype_wildcard(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        campaigns::create($this->campaign_data(['pagetypepatterns' => "mod-forum-*"]));

        $this->assertNotNull(campaigns::get_active_for_context($course, null, 'mod-forum-view', $user));
        $this->assertNull(campaigns::get_active_for_context($course, null, 'mod-quiz-view', $user));
    }

    public function test_get_active_for_context_role_targeting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        campaigns::create($this->campaign_data(['targetroles' => 'student']));

        $this->assertNotNull(campaigns::get_active_for_context($course, null, 'course-view', $student));
        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $teacher));
    }

    public function test_get_active_for_context_cohort_targeting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $member = $this->getDataGenerator()->create_user();
        $nonmember = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $member->id);

        campaigns::create($this->campaign_data(['targetcohortid' => $cohort->id]));

        $this->assertNotNull(campaigns::get_active_for_context($course, null, 'course-view', $member));
        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $nonmember));
    }

    public function test_get_active_for_context_explicit_user_targeting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $targeted = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        campaigns::create($this->campaign_data(['targetuserids' => (string) $targeted->id]));

        $this->assertNotNull(campaigns::get_active_for_context($course, null, 'course-view', $targeted));
        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $other));
    }

    public function test_get_active_for_context_priority_and_id_tiebreak(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $low = campaigns::create($this->campaign_data(['name' => 'Low priority number', 'priority' => 5]));
        $high = campaigns::create($this->campaign_data(['name' => 'High priority number', 'priority' => 10]));

        // Lower priority value wins.
        $active = campaigns::get_active_for_context($course, null, 'course-view', $user);
        $this->assertEquals($low, $active->id);

        // Equal priority: oldest (lowest) id wins.
        campaigns::update($high, $this->campaign_data(['priority' => 5]));
        $active = campaigns::get_active_for_context($course, null, 'course-view', $user);
        $this->assertEquals($low, $active->id);
    }

    public function test_get_active_for_context_global_optout(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        campaigns::create($this->campaign_data());

        dismissed_campaigns::set_global_optout((int) $user->id, true);

        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $user));
    }

    public function test_get_active_for_context_skips_dismissed_campaign(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $id = campaigns::create($this->campaign_data());

        dismissed_campaigns::dismiss($id, (int) $user->id);

        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $user));
    }

    public function test_has_reached_max_responses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data(['maxresponses' => 2]));
        $campaign = campaigns::get($id);
        $this->assertFalse(campaigns::has_reached_max_responses($campaign));

        for ($i = 0; $i < 2; $i++) {
            $DB->insert_record('local_communications_submissions', (object) [
                'userid' => 0,
                'anonymous' => 1,
                'courseid' => 1,
                'cmid' => 0,
                'sentiment' => 'happy',
                'feedbacktext' => 'Text',
                'campaignid' => $id,
                'campaignname' => 'Test campaign',
                'timecreated' => time(),
            ]);
        }

        $this->assertTrue(campaigns::has_reached_max_responses(campaigns::get($id)));
    }

    public function test_get_active_for_context_stops_matching_once_max_responses_hit(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $id = campaigns::create($this->campaign_data(['maxresponses' => 1]));

        $DB->insert_record('local_communications_submissions', (object) [
            'userid' => 0,
            'anonymous' => 1,
            'courseid' => $course->id,
            'cmid' => 0,
            'sentiment' => 'happy',
            'feedbacktext' => 'Text',
            'campaignid' => $id,
            'campaignname' => 'Test campaign',
            'timecreated' => time(),
        ]);

        $this->assertNull(campaigns::get_active_for_context($course, null, 'course-view', $user));
    }

    public function test_has_reached_response_limit_none_never_limits(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data(['responselimit' => 'none']));
        $campaign = campaigns::get($id);
        campaigns::record_response($id, 7, 1);

        $this->assertFalse(campaigns::has_reached_response_limit($campaign, 7, 1));
    }

    public function test_has_reached_response_limit_once(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data(['responselimit' => 'once', 'coursefocused' => 0]));
        $campaign = campaigns::get($id);

        $this->assertFalse(campaigns::has_reached_response_limit($campaign, 7, 1));
        campaigns::record_response($id, 7, 1);
        $this->assertTrue(campaigns::has_reached_response_limit($campaign, 7, 1));
        // Non-course-focused: the limit is site-wide, so a different course still counts.
        $this->assertTrue(campaigns::has_reached_response_limit($campaign, 7, 999));
        // A different user is unaffected.
        $this->assertFalse(campaigns::has_reached_response_limit($campaign, 8, 1));
    }

    public function test_has_reached_response_limit_once_is_scoped_per_course_when_course_focused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data(['responselimit' => 'once', 'coursefocused' => 1]));
        $campaign = campaigns::get($id);

        campaigns::record_response($id, 7, 1);
        $this->assertTrue(campaigns::has_reached_response_limit($campaign, 7, 1));
        // A different course is unaffected because the campaign is course-focused.
        $this->assertFalse(campaigns::has_reached_response_limit($campaign, 7, 2));
    }

    public function test_has_reached_response_limit_daily_resets_after_midnight(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data(['responselimit' => 'daily', 'coursefocused' => 0]));
        $campaign = campaigns::get($id);

        campaigns::record_response($id, 7, 1);
        $this->assertTrue(campaigns::has_reached_response_limit($campaign, 7, 1));

        // Back-date the response to before today's midnight - the daily limit should have reset.
        $DB->set_field(
            'local_communications_campaign_responses',
            'timecreated',
            usergetmidnight(time()) - 1,
            ['campaignid' => $id, 'userid' => 7]
        );
        $this->assertFalse(campaigns::has_reached_response_limit($campaign, 7, 1));
    }

    public function test_record_response_ignores_zero_campaignid(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        campaigns::record_response(0, 7, 1);
        $this->assertEquals(0, $DB->count_records('local_communications_campaign_responses'));
    }

    public function test_get_course_focused_for_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_campaigns();

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        $focused = campaigns::create($this->campaign_data(['coursefocused' => 1, 'categoryids' => (string) $category->id]));
        campaigns::create($this->campaign_data(['coursefocused' => 0]));

        $matches = campaigns::get_course_focused_for_course($course);
        $this->assertCount(1, $matches);
        $this->assertEquals($focused, $matches[0]->id);
    }

    public function test_get_sentiment_labels_falls_back_to_site_strings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data());
        $labels = campaigns::get_sentiment_labels(campaigns::get($id));

        $this->assertSame(get_string('sentiment_happy', 'local_communications'), $labels['happy']);
        $this->assertSame(get_string('sentiment_neutral', 'local_communications'), $labels['neutral']);
        $this->assertSame(get_string('sentiment_sad', 'local_communications'), $labels['sad']);
    }

    public function test_get_sentiment_labels_uses_campaign_overrides(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data([
            'labelhappy' => 'Loving it',
            'labelneutral' => 'Meh',
            'labelsad' => 'Not great',
        ]));
        $labels = campaigns::get_sentiment_labels(campaigns::get($id));

        $this->assertSame('Loving it', $labels['happy']);
        $this->assertSame('Meh', $labels['neutral']);
        $this->assertSame('Not great', $labels['sad']);
    }

    public function test_describe_targeting_everyone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data());
        $this->assertSame(
            get_string('targetsummary_everyone', 'local_communications'),
            campaigns::describe_targeting(campaigns::get($id))
        );
    }

    public function test_describe_targeting_summarises_roles(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = campaigns::create($this->campaign_data(['targetroles' => 'student,editingteacher']));
        $description = campaigns::describe_targeting(campaigns::get($id));

        $this->assertStringContainsString('student', $description);
        $this->assertStringContainsString('editingteacher', $description);
    }
}
