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
 * Manage dashboard news stories: list, enable/disable, delete, and links to create/edit.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/local/news.php');

use local_communications\local\news;

// The externalpage is itself registered with local/communications:managenews (see
// settings.php), so this is the only capability check this page needs.
admin_externalpage_setup('local_communications_news');
$PAGE->requires->css('/local/communications/styles.css');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if ($action && $id) {
    require_sesskey();

    if ($action === 'toggle') {
        news::toggle($id);
    } else if ($action === 'delete') {
        news::delete($id);
    }

    redirect(new moodle_url('/local/communications/manage_news.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managenews', 'local_communications'));

echo html_writer::div(
    get_string('managenews_intro', 'local_communications'),
    'local-communications__campaigns-intro'
);

echo html_writer::link(
    new moodle_url('/local/communications/edit_news.php'),
    get_string('news_create', 'local_communications'),
    ['class' => 'btn btn-primary local-communications__campaigns-create']
);

$stories = news::get_all();

if (!$stories) {
    echo $OUTPUT->notification(get_string('news_none', 'local_communications'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    '',
    get_string('news_title', 'local_communications'),
    get_string('campaign_status', 'local_communications'),
    get_string('campaign_window', 'local_communications'),
    get_string('campaign_targeting', 'local_communications'),
    '',
];

$now = time();
foreach ($stories as $story) {
    $imageurl = news::image_url($story);
    $thumbnail = $imageurl
        ? html_writer::empty_tag('img', [
            'src' => $imageurl, 'alt' => '', 'class' => 'local-communications__news-thumbnail',
        ])
        : '';

    $status = $story->enabled
        ? get_string('campaign_status_enabled', 'local_communications')
        : get_string('campaign_status_disabled', 'local_communications');
    if ($story->enabled && $story->starttime && $story->starttime > $now) {
        $status = get_string('campaign_status_scheduled', 'local_communications');
    } else if ($story->enabled && $story->endtime && $story->endtime < $now) {
        $status = get_string('campaign_status_ended', 'local_communications');
    }

    $window = get_string(
        'campaign_window_start',
        'local_communications',
        $story->starttime ? userdate($story->starttime) : get_string('campaign_window_unbounded', 'local_communications')
    )
        . ' — '
        . get_string(
            'campaign_window_end',
            'local_communications',
            $story->endtime ? userdate($story->endtime) : get_string('campaign_window_unbounded', 'local_communications')
        );

    $actions = [];
    $actions[] = html_writer::link(
        new moodle_url('/local/communications/edit_news.php', ['id' => $story->id]),
        get_string('edit')
    );
    $actions[] = html_writer::link(
        new moodle_url('/local/communications/manage_news.php', [
            'action' => 'toggle', 'id' => $story->id, 'sesskey' => sesskey(),
        ]),
        $story->enabled ? get_string('campaign_disable', 'local_communications') : get_string('campaign_enable', 'local_communications')
    );
    $actions[] = $OUTPUT->action_link(
        new moodle_url('/local/communications/manage_news.php', [
            'action' => 'delete', 'id' => $story->id, 'sesskey' => sesskey(),
        ]),
        get_string('delete'),
        new confirm_action(get_string('news_confirmdelete', 'local_communications', format_string($story->title)))
    );

    $table->data[] = [
        $thumbnail,
        format_string($story->title),
        $status,
        $window,
        news::describe_targeting($story),
        implode(' | ', $actions),
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
