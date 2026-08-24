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
 * Index of the course-focused campaigns currently active for one course - only reached
 * when more than one applies to a given course (see local_communications_extend_navigation_course()
 * in lib.php); with exactly one, that link goes straight to course_report.php instead.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/campaigns.php');

use local_communications\local\campaigns;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($course->id);
require_capability('local/communications:viewreports', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/communications/course_campaigns.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('reportheading', 'local_communications') . ': ' . format_string($course->fullname));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/local/communications/styles.css');

$campaigns = campaigns::get_course_focused_for_course($course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportheading', 'local_communications'));

if (!$campaigns) {
    echo $OUTPUT->notification(get_string('campaign_none', 'local_communications'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('ul', ['class' => 'local-communications__campaign-list']);
foreach ($campaigns as $campaign) {
    $url = new moodle_url('/local/communications/course_report.php', ['courseid' => $courseid, 'campaignid' => $campaign->id]);
    echo html_writer::tag('li', html_writer::link($url, format_string($campaign->name)));
}
echo html_writer::end_tag('ul');

echo $OUTPUT->footer();
