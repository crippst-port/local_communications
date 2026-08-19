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

/**
 * Reads the admin-configured list of feedback areas (categories).
 *
 * Shared by hook_callbacks (to populate the widget's buttons) and ajax/submit.php
 * (to validate a non-"Other" submission against the current list), so the two
 * never drift apart.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class categories {

    /** @var string[] Used if the admin setting is empty (e.g. straight after install). */
    protected const DEFAULTS = ['Course layout', 'Course content', 'Assessment'];

    /**
     * The configured list of areas, one per configured line, blank lines removed.
     *
     * @return string[]
     */
    public static function get_list(): array {
        $raw = (string) get_config('local_feedback', 'categories');
        $lines = preg_split('/\r\n|\r|\n/', $raw);

        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result ?: self::DEFAULTS;
    }

    /**
     * The default value for the admin setting itself.
     *
     * @return string
     */
    public static function get_default_setting_value(): string {
        return implode("\n", self::DEFAULTS);
    }
}
