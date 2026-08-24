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

namespace local_communications\local;

/**
 * Tracks which feedback campaigns a user has asked not to be shown again - the
 * "click here if you'd prefer not to be asked" link in the widget itself.
 *
 * Stored as a single Moodle user preference holding a comma-separated list of
 * campaign ids, rather than one preference per campaign: campaigns are created
 * and deleted freely by admins, so there's no fixed set of preference names to
 * declare up front the way a normal user preference would.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dismissed_campaigns {
    /** @var string The per-campaign user preference name. */
    protected const PREF = 'local_communications_neverask';

    /** @var string The site-wide opt-out user preference name - see {@see is_global_optout()}. */
    protected const PREF_ALL = 'local_communications_neverask_all';

    /**
     * Whether this user has turned off feedback requests everywhere, overriding any
     * individual campaign - the "Turn off feedback requests everywhere" toggle on
     * preferences.php, as distinct from dismissing one campaign at a time.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_global_optout(int $userid): bool {
        return \get_user_preferences(self::PREF_ALL, '0', $userid) === '1';
    }

    /**
     * @param int $userid
     * @param bool $optout
     */
    public static function set_global_optout(int $userid, bool $optout): void {
        if ($optout) {
            \set_user_preference(self::PREF_ALL, '1', $userid);
        } else {
            \unset_user_preference(self::PREF_ALL, $userid);
        }
    }

    /**
     * @param int $campaignid
     * @param int $userid
     * @return bool
     */
    public static function is_dismissed(int $campaignid, int $userid): bool {
        return in_array($campaignid, self::get_ids($userid), true);
    }

    /**
     * @param int $campaignid
     * @param int $userid
     */
    public static function dismiss(int $campaignid, int $userid): void {
        $ids = self::get_ids($userid);
        if (!in_array($campaignid, $ids, true)) {
            $ids[] = $campaignid;
            self::set_ids($ids, $userid);
        }
    }

    /**
     * Re-enables a single previously-dismissed campaign for this user.
     *
     * @param int $campaignid
     * @param int $userid
     */
    public static function undismiss(int $campaignid, int $userid): void {
        $ids = array_values(array_diff(self::get_ids($userid), [$campaignid]));
        self::set_ids($ids, $userid);
    }

    /**
     * Re-enables every campaign this user has dismissed.
     *
     * @param int $userid
     */
    public static function undismiss_all(int $userid): void {
        self::set_ids([], $userid);
    }

    /**
     * @param int $userid
     * @return int[] Campaign ids this user has dismissed, in no particular order.
     */
    public static function get_ids(int $userid): array {
        $raw = \get_user_preferences(self::PREF, '', $userid);
        if ($raw === '') {
            return [];
        }

        return array_values(array_unique(array_map('intval', explode(',', $raw))));
    }

    /**
     * @param int[] $ids
     * @param int $userid
     */
    protected static function set_ids(array $ids, int $userid): void {
        if (empty($ids)) {
            \unset_user_preference(self::PREF, $userid);
            return;
        }

        \set_user_preference(self::PREF, implode(',', $ids), $userid);
    }
}
