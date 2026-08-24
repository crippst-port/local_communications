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

/**
 * AJAX endpoint for the "click here if you'd prefer not to be asked" link in the
 * floating widget - records that this user never wants to see this campaign again.
 * A user can undo this later from their profile - see preferences.php.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
$configpath = __DIR__ . '/../../../config.php';
if (!file_exists($configpath)) {
    @header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'config.php not found']);
    exit(0);
}
require_once($configpath);

defined('MOODLE_INTERNAL') || die();

@ini_set('display_errors', '0');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
@ob_start();

try {
    header('Content-Type: application/json');

    require_login(null, false);

    $sesskey = required_param('sesskey', PARAM_ALPHANUM);
    if (!confirm_sesskey($sesskey)) {
        throw new moodle_exception('invalidsesskey', 'error');
    }

    $campaignid = required_param('campaignid', PARAM_INT);
    if ($campaignid > 0) {
        \local_feedback\local\dismissed_campaigns::dismiss($campaignid, $USER->id);
    }

    $extra = @ob_get_clean();
    if ($extra !== false && trim($extra) !== '') {
        debugging('Unexpected output before JSON response: ' . $extra, DEBUG_DEVELOPER);
    }

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    if (ob_get_length() !== false) {
        @ob_end_clean();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
