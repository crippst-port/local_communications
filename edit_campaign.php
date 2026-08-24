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
 * Create/edit a single feedback campaign.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/local/campaigns.php');
require_once(__DIR__ . '/classes/form/campaign_form.php');

use local_communications\local\campaigns;
use local_communications\form\campaign_form;

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_communications_campaigns');

// The admin_externalpage_setup() call above only enforces local/communications:viewreports now (see
// settings.php) - authoring campaigns needs the stricter capability, checked explicitly
// here rather than relying on that shared page id.
require_capability('local/communications:managecampaigns', context_system::instance());

$listurl = new moodle_url('/local/communications/manage_campaigns.php');
$url = new moodle_url('/local/communications/edit_campaign.php', $id ? ['id' => $id] : []);
$PAGE->set_url($url);

$campaign = null;
if ($id) {
    $campaign = campaigns::get($id);
    if (!$campaign) {
        throw new moodle_exception('invalidrecord', 'error');
    }
}

$title = $campaign
    ? get_string('campaign_edit', 'local_communications', format_string($campaign->name))
    : get_string('campaign_create', 'local_communications');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$form = new campaign_form($url);

if ($form->is_cancelled()) {
    redirect($listurl);
} else if ($form->is_submitted() && $form->is_validated()) {
    $record = $form->get_submitted_record();

    if ($campaign) {
        campaigns::update($campaign->id, $record);
    } else {
        campaigns::create($record);
    }

    redirect($listurl, get_string('campaign_saved', 'local_communications'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($campaign) {
    $form->set_data_from_record($campaign);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
