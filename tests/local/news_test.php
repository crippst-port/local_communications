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
 * Unit tests for the news class: CRUD plus the targeting logic that decides which
 * dashboard news stories the current user's carousel shows.
 *
 * @package     local_communications
 * @covers      \local_communications\local\news
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class news_test extends \advanced_testcase {

    /**
     * @param array $overrides
     * @return \stdClass
     */
    protected function news_data(array $overrides = []): \stdClass {
        return (object) array_merge([
            'title' => 'Test story',
            'description' => '',
            'linkurl' => '',
            'enabled' => 1,
            'sortorder' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'targetroles' => '',
            'targetcohortid' => 0,
            'targetuserids' => '',
        ], $overrides);
    }

    public function test_create_get_update_delete(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = news::create($this->news_data(['title' => 'Original']));
        $story = news::get($id);
        $this->assertSame('Original', $story->title);
        $this->assertGreaterThan(0, $story->timecreated);

        news::update($id, $this->news_data(['title' => 'Renamed']));
        $this->assertSame('Renamed', news::get($id)->title);

        news::delete($id);
        $this->assertFalse(news::get($id));
    }

    public function test_toggle_flips_enabled_flag(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = news::create($this->news_data(['enabled' => 1]));
        news::toggle($id);
        $this->assertEquals(0, news::get($id)->enabled);
        news::toggle($id);
        $this->assertEquals(1, news::get($id)->enabled);
    }

    public function test_get_all_orders_by_sortorder_then_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $second = news::create($this->news_data(['title' => 'Second', 'sortorder' => 2]));
        $first = news::create($this->news_data(['title' => 'First', 'sortorder' => 1]));

        $all = news::get_all();
        $this->assertEquals($first, $all[0]->id);
        $this->assertEquals($second, $all[1]->id);
    }

    public function test_get_active_list_matches_by_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $id = news::create($this->news_data());

        $active = news::get_active_list($user);
        $this->assertCount(1, $active);
        $this->assertEquals($id, $active[0]->id);
    }

    public function test_get_active_list_ignores_disabled_story(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        news::create($this->news_data(['enabled' => 0]));

        $this->assertCount(0, news::get_active_list($user));
    }

    public function test_get_active_list_respects_date_window(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $now = time();

        news::create($this->news_data(['starttime' => $now + DAYSECS]));
        $this->assertCount(0, news::get_active_list($user));
    }

    public function test_get_active_list_role_targeting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $manager = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id, \context_system::instance()->id);

        news::create($this->news_data(['targetroles' => 'manager']));

        $this->assertCount(1, news::get_active_list($manager));
        $this->assertCount(0, news::get_active_list($other));
    }

    public function test_get_active_list_cohort_targeting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $member = $this->getDataGenerator()->create_user();
        $nonmember = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $member->id);

        news::create($this->news_data(['targetcohortid' => $cohort->id]));

        $this->assertCount(1, news::get_active_list($member));
        $this->assertCount(0, news::get_active_list($nonmember));
    }

    public function test_get_active_list_explicit_user_targeting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $targeted = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        news::create($this->news_data(['targetuserids' => (string) $targeted->id]));

        $this->assertCount(1, news::get_active_list($targeted));
        $this->assertCount(0, news::get_active_list($other));
    }

    public function test_get_active_list_returns_every_match_in_carousel_order(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $second = news::create($this->news_data(['title' => 'Second', 'sortorder' => 2]));
        $first = news::create($this->news_data(['title' => 'First', 'sortorder' => 1]));

        $active = news::get_active_list($user);
        $this->assertCount(2, $active);
        $this->assertEquals($first, $active[0]->id);
        $this->assertEquals($second, $active[1]->id);
    }

    public function test_image_url_null_when_no_file_uploaded(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = news::create($this->news_data());
        $this->assertNull(news::image_url(news::get($id)));
    }

    public function test_image_url_returns_url_once_file_uploaded(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = news::create($this->news_data());

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_communications',
            'filearea' => news::IMAGE_FILEAREA,
            'itemid' => $id,
            'filepath' => '/',
            'filename' => 'image.png',
        ], 'fake image content');

        $url = news::image_url(news::get($id));
        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertStringContainsString('image.png', $url->out());
    }

    public function test_describe_targeting_everyone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = news::create($this->news_data());
        $this->assertSame(
            get_string('news_targetsummary_everyone', 'local_communications'),
            news::describe_targeting(news::get($id))
        );
    }
}
