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
 * Reads the list of feedback topic labels a submitter can tag their feedback with.
 *
 * Every campaign can set its own list; a campaign that leaves it empty falls back to
 * the site-wide local_communications/categories admin setting, so existing campaigns (and
 * sites with only the migrated "Default" campaign) keep working unchanged. Shared by
 * hook_callbacks (to populate the widget's buttons) and ajax/submit.php (to validate a
 * non-"Other" submission against the list actually shown), so the two never drift apart.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class categories {

    /** @var string[] Used if neither the campaign nor the site-wide setting have a list (e.g. straight after install). */
    protected const DEFAULTS = ['Course layout', 'Course content', 'Assessment'];

    /**
     * The site-wide default list, one per configured line, blank lines removed.
     *
     * @return string[]
     */
    public static function get_list(): array {
        $raw = (string) get_config('local_communications', 'categories');

        return self::parse($raw) ?: self::DEFAULTS;
    }

    /**
     * The list to show for a given campaign - that campaign's own list if it has set
     * one, otherwise the site-wide default.
     *
     * @param \stdClass|null $campaign
     * @return string[]
     */
    public static function get_list_for_campaign(?\stdClass $campaign): array {
        if ($campaign && !empty($campaign->topics)) {
            $parsed = self::parse($campaign->topics);
            if ($parsed) {
                return $parsed;
            }
        }

        return self::get_list();
    }

    /**
     * The default value for the site-wide admin setting itself.
     *
     * @return string
     */
    public static function get_default_setting_value(): string {
        return implode("\n", self::DEFAULTS);
    }

    /**
     * @param string $raw
     * @return string[]
     */
    protected static function parse(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', $raw);

        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }
}
