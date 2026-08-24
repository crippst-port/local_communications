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
 * Lets a user manage which feedback requests they see: a site-wide toggle to turn
 * off every campaign at once (see \local_communications\local\dismissed_campaigns::
 * is_global_optout()), plus re-enabling individual campaigns they've previously
 * asked not to be shown again (the "click here if you'd prefer not to be asked"
 * link in the widget). Always their own, self-service - linked from the
 * Preferences page (see local_communications_extend_navigation_user_settings() in
 * lib.php).
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/campaigns.php');
require_once(__DIR__ . '/classes/local/dismissed_campaigns.php');

use local_communications\local\campaigns;
use local_communications\local\dismissed_campaigns;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/local/communications/preferences.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('preferences_heading', 'local_communications'));
$PAGE->set_heading(fullname($USER));

if ($id = optional_param('reenable', 0, PARAM_INT)) {
    require_sesskey();
    dismissed_campaigns::undismiss($id, $USER->id);
    redirect(new moodle_url('/local/communications/preferences.php'), get_string('preferences_reenabled', 'local_communications'));
}
if (optional_param('reenableall', 0, PARAM_BOOL)) {
    require_sesskey();
    dismissed_campaigns::undismiss_all($USER->id);
    redirect(new moodle_url('/local/communications/preferences.php'), get_string('preferences_reenabled', 'local_communications'));
}
if (optional_param('disableall', 0, PARAM_BOOL)) {
    require_sesskey();
    dismissed_campaigns::set_global_optout($USER->id, true);
    redirect(new moodle_url('/local/communications/preferences.php'), get_string('preferences_disabledall', 'local_communications'));
}
if (optional_param('enableall', 0, PARAM_BOOL)) {
    require_sesskey();
    dismissed_campaigns::set_global_optout($USER->id, false);
    redirect(new moodle_url('/local/communications/preferences.php'), get_string('preferences_enabledall', 'local_communications'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('preferences_heading', 'local_communications'));

$globaloptout = dismissed_campaigns::is_global_optout($USER->id);

if ($globaloptout) {
    $enableallurl = new moodle_url('/local/communications/preferences.php', ['enableall' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->notification(get_string('preferences_globallydisabled', 'local_communications'), 'info');
    echo $OUTPUT->single_button($enableallurl, get_string('preferences_enableall', 'local_communications'), 'post');
} else {
    $disableallurl = new moodle_url('/local/communications/preferences.php', ['disableall' => 1, 'sesskey' => sesskey()]);
    echo html_writer::tag('p', get_string('preferences_disableall_intro', 'local_communications'));
    echo $OUTPUT->single_button($disableallurl, get_string('preferences_disableall', 'local_communications'), 'post');
}

$ids = dismissed_campaigns::get_ids($USER->id);

if (!$ids) {
    echo $OUTPUT->notification(get_string('preferences_none', 'local_communications'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->heading(get_string('preferences_bycampaign_heading', 'local_communications'), 3);
echo html_writer::tag('p', get_string('preferences_intro', 'local_communications'));

$table = new html_table();
$table->head = [get_string('campaign_name', 'local_communications'), ''];

foreach ($ids as $campaignid) {
    $campaign = campaigns::get($campaignid);
    $name = $campaign ? format_string($campaign->name) : get_string('campaign_deleted', 'local_communications');

    $reenableurl = new moodle_url('/local/communications/preferences.php', ['reenable' => $campaignid, 'sesskey' => sesskey()]);
    $button = $OUTPUT->single_button($reenableurl, get_string('preferences_reenable', 'local_communications'), 'post');

    $table->data[] = [$name, $button];
}

echo html_writer::table($table);

if (count($ids) > 1) {
    $reenableallurl = new moodle_url('/local/communications/preferences.php', ['reenableall' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($reenableallurl, get_string('preferences_reenableall', 'local_communications'), 'post');
}

echo $OUTPUT->footer();
