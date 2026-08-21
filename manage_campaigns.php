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
 * Manage feedback campaigns: list, enable/disable, delete, and links to create/edit.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/local/campaigns.php');

use local_feedback\local\campaigns;

admin_externalpage_setup('local_feedback_campaigns');
$PAGE->requires->css('/local/feedback/styles.css');

// This page is reachable by anyone holding local/feedback:viewreports (see settings.php)
// so they can browse campaigns and reach their dashboards - but authoring campaigns is a
// separate, stricter capability, checked explicitly here rather than relying on the page's
// own (now broader) gate. $canmanage only controls which controls are rendered below; the
// action handler still re-checks it itself before actually mutating anything.
$canmanage = has_capability('local/feedback:managecampaigns', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if ($action && $id) {
    require_capability('local/feedback:managecampaigns', context_system::instance());
    require_sesskey();

    if ($action === 'toggle') {
        campaigns::toggle($id);
    } else if ($action === 'delete') {
        campaigns::delete($id);
    }

    redirect(new moodle_url('/local/feedback/manage_campaigns.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecampaigns', 'local_feedback'));

echo html_writer::div(
    get_string('managecampaigns_intro', 'local_feedback'),
    'local-feedback__campaigns-intro'
);

if ($canmanage) {
    echo html_writer::link(
        new moodle_url('/local/feedback/edit_campaign.php'),
        get_string('campaign_create', 'local_feedback'),
        ['class' => 'btn btn-primary local-feedback__campaigns-create']
    );
}

$campaigns = campaigns::get_all();

if (!$campaigns) {
    echo $OUTPUT->notification(get_string('campaign_none', 'local_feedback'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('campaign_name', 'local_feedback'),
    get_string('campaign_status', 'local_feedback'),
    get_string('campaign_window', 'local_feedback'),
    get_string('campaign_targeting', 'local_feedback'),
    get_string('campaign_responses', 'local_feedback'),
    '',
];

$now = time();
foreach ($campaigns as $campaign) {
    $status = $campaign->enabled
        ? get_string('campaign_status_enabled', 'local_feedback')
        : get_string('campaign_status_disabled', 'local_feedback');
    if ($campaign->enabled && $campaign->starttime && $campaign->starttime > $now) {
        $status = get_string('campaign_status_scheduled', 'local_feedback');
    } else if ($campaign->enabled && $campaign->endtime && $campaign->endtime < $now) {
        $status = get_string('campaign_status_ended', 'local_feedback');
    }

    $window = get_string('campaign_window_start', 'local_feedback', $campaign->starttime ? userdate($campaign->starttime) : get_string('campaign_window_unbounded', 'local_feedback'))
        . ' — '
        . get_string('campaign_window_end', 'local_feedback', $campaign->endtime ? userdate($campaign->endtime) : get_string('campaign_window_unbounded', 'local_feedback'));

    $responsecount = $DB->count_records('local_feedback_submissions', ['campaignid' => $campaign->id]);

    $actions = [];
    $actions[] = html_writer::link(
        new moodle_url('/local/feedback/report.php', ['campaignid' => $campaign->id]),
        get_string('campaign_viewresponses', 'local_feedback')
    );
    if ($canmanage) {
        $actions[] = html_writer::link(
            new moodle_url('/local/feedback/edit_campaign.php', ['id' => $campaign->id]),
            get_string('edit')
        );
        $actions[] = html_writer::link(
            new moodle_url('/local/feedback/manage_campaigns.php', [
                'action' => 'toggle', 'id' => $campaign->id, 'sesskey' => sesskey(),
            ]),
            $campaign->enabled ? get_string('campaign_disable', 'local_feedback') : get_string('campaign_enable', 'local_feedback')
        );
        $actions[] = $OUTPUT->action_link(
            new moodle_url('/local/feedback/manage_campaigns.php', [
                'action' => 'delete', 'id' => $campaign->id, 'sesskey' => sesskey(),
            ]),
            get_string('delete'),
            new confirm_action(get_string('campaign_confirmdelete', 'local_feedback', format_string($campaign->name)))
        );
    }

    $table->data[] = [
        format_string($campaign->name),
        $status,
        $window,
        campaigns::describe_targeting($campaign),
        $responsecount,
        implode(' | ', $actions),
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
