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
 * Unit tests for the categories class: the feedback topic label list a submitter can
 * tag their feedback with.
 *
 * @package     local_communications
 * @covers      \local_communications\local\categories
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class categories_test extends \advanced_testcase {

    public function test_get_list_falls_back_to_defaults_when_unset(): void {
        $this->resetAfterTest();

        unset_config('categories', 'local_communications');

        $this->assertSame(['Course layout', 'Course content', 'Assessment'], categories::get_list());
    }

    public function test_get_list_reads_site_setting(): void {
        $this->resetAfterTest();

        set_config('categories', "Teaching\nSupport\n\nResources", 'local_communications');

        $this->assertSame(['Teaching', 'Support', 'Resources'], categories::get_list());
    }

    public function test_get_list_for_campaign_uses_own_topics_when_set(): void {
        $this->resetAfterTest();

        set_config('categories', "Site default", 'local_communications');
        $campaign = (object) ['topics' => "Custom A\nCustom B"];

        $this->assertSame(['Custom A', 'Custom B'], categories::get_list_for_campaign($campaign));
    }

    public function test_get_list_for_campaign_falls_back_to_site_default(): void {
        $this->resetAfterTest();

        set_config('categories', "Site default", 'local_communications');

        $this->assertSame(['Site default'], categories::get_list_for_campaign((object) ['topics' => '']));
        $this->assertSame(['Site default'], categories::get_list_for_campaign(null));
    }

    public function test_get_default_setting_value(): void {
        $this->assertSame("Course layout\nCourse content\nAssessment", categories::get_default_setting_value());
    }
}
