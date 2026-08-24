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
 * Unit tests for the dismissed_campaigns class: which campaigns a user has asked
 * not to be shown again, plus the site-wide "never ask" opt-out.
 *
 * @package     local_communications
 * @covers      \local_communications\local\dismissed_campaigns
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dismissed_campaigns_test extends \advanced_testcase {

    public function test_campaign_starts_not_dismissed(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        $this->assertFalse(dismissed_campaigns::is_dismissed(1, $userid));
        $this->assertSame([], dismissed_campaigns::get_ids($userid));
    }

    public function test_dismiss_then_undismiss(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        dismissed_campaigns::dismiss(5, $userid);
        $this->assertTrue(dismissed_campaigns::is_dismissed(5, $userid));
        $this->assertSame([5], dismissed_campaigns::get_ids($userid));

        dismissed_campaigns::undismiss(5, $userid);
        $this->assertFalse(dismissed_campaigns::is_dismissed(5, $userid));
        $this->assertSame([], dismissed_campaigns::get_ids($userid));
    }

    public function test_dismiss_is_idempotent(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        dismissed_campaigns::dismiss(5, $userid);
        dismissed_campaigns::dismiss(5, $userid);

        $this->assertSame([5], dismissed_campaigns::get_ids($userid));
    }

    public function test_dismiss_multiple_campaigns_independently(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        dismissed_campaigns::dismiss(5, $userid);
        dismissed_campaigns::dismiss(9, $userid);

        $this->assertEqualsCanonicalizing([5, 9], dismissed_campaigns::get_ids($userid));

        dismissed_campaigns::undismiss(5, $userid);
        $this->assertSame([9], dismissed_campaigns::get_ids($userid));
    }

    public function test_undismiss_all(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        dismissed_campaigns::dismiss(5, $userid);
        dismissed_campaigns::dismiss(9, $userid);
        dismissed_campaigns::undismiss_all($userid);

        $this->assertSame([], dismissed_campaigns::get_ids($userid));
    }

    public function test_dismissals_are_per_user(): void {
        $this->resetAfterTest();
        $userid1 = (int) $this->getDataGenerator()->create_user()->id;
        $userid2 = (int) $this->getDataGenerator()->create_user()->id;

        dismissed_campaigns::dismiss(5, $userid1);

        $this->assertTrue(dismissed_campaigns::is_dismissed(5, $userid1));
        $this->assertFalse(dismissed_campaigns::is_dismissed(5, $userid2));
    }

    public function test_global_optout_defaults_to_off(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        $this->assertFalse(dismissed_campaigns::is_global_optout($userid));
    }

    public function test_global_optout_toggle(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        dismissed_campaigns::set_global_optout($userid, true);
        $this->assertTrue(dismissed_campaigns::is_global_optout($userid));

        dismissed_campaigns::set_global_optout($userid, false);
        $this->assertFalse(dismissed_campaigns::is_global_optout($userid));
    }
}
