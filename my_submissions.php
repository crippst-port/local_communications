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
 * Lets a user see the feedback they've personally submitted (non-anonymous only -
 * an anonymous submission is stored with userid 0 and is never linked back to who
 * made it anywhere, including here). Self-service only, linked from the user's own
 * profile page - see local_communications_myprofile_navigation() in lib.php.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/table/submissions_table.php');

use local_communications\table\submissions_table;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}

$download = optional_param('download', '', PARAM_ALPHA);

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/local/communications/my_submissions.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('mysubmissions_heading', 'local_communications'));
$PAGE->set_heading(fullname($USER));
$PAGE->requires->css('/local/communications/styles.css');

$table = new submissions_table(
    'local-communications-my-submissions',
    new moodle_url('/local/communications/my_submissions.php'),
    0,
    '',
    false,
    '',
    0,
    [],
    $USER->id,
    true
);
$table->is_downloading($download, 'my_feedback');
$table->show_download_buttons_at([TABLE_P_TOP]);

$total = $DB->count_records('local_communications_submissions', ['userid' => $USER->id]);

if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('mysubmissions_heading', 'local_communications'));

    if ($total === 0) {
        echo $OUTPUT->notification(get_string('mysubmissions_none', 'local_communications'), 'info');
    }
}

if ($total > 0 || $table->is_downloading()) {
    $table->out(30, false);
}

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
