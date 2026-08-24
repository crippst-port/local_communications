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
 * Create/edit a single dashboard news story.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/local/news.php');
require_once(__DIR__ . '/classes/form/news_form.php');

use local_communications\local\news;
use local_communications\form\news_form;

$id = optional_param('id', 0, PARAM_INT);

// The externalpage is itself registered with local/communications:managenews (see
// settings.php), so this is the only capability check this page needs.
admin_externalpage_setup('local_communications_news');

$context = context_system::instance();
$listurl = new moodle_url('/local/communications/manage_news.php');
$url = new moodle_url('/local/communications/edit_news.php', $id ? ['id' => $id] : []);
$PAGE->set_url($url);

$story = null;
if ($id) {
    $story = news::get($id);
    if (!$story) {
        throw new moodle_exception('invalidrecord', 'error');
    }
}

$title = $story
    ? get_string('news_edit', 'local_communications', format_string($story->title))
    : get_string('news_create', 'local_communications');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$imageoptions = news_form::image_options();
$form = new news_form($url);

if ($form->is_cancelled()) {
    redirect($listurl);
} else if ($form->is_submitted() && $form->is_validated()) {
    $record = $form->get_submitted_record();

    // The image's permanent file area is keyed by the story's own id, so a new story
    // must exist in the database first - only then can its draft files be moved in.
    if ($story) {
        news::update($story->id, $record);
        $newsid = $story->id;
    } else {
        $newsid = news::create($record);
    }

    // Not using file_postupdate_standard_filemanager() here - it expects the form
    // field to be named "image_filemanager" (it reads $data->image_filemanager
    // internally), but this form's element is plain "image" to match
    // file_prepare_draft_area() being called directly below rather than via its
    // "_filemanager"-suffixed sibling file_prepare_standard_filemanager(). Saving the
    // draft area directly keeps both sides of the round-trip using the same field name.
    $submitted = $form->get_data();
    file_save_draft_area_files(
        $submitted->image,
        $context->id,
        'local_communications',
        news::IMAGE_FILEAREA,
        $newsid,
        $imageoptions
    );

    redirect($listurl, get_string('news_saved', 'local_communications'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($story) {
    $form->set_data_from_record($story);
    $draftitemid = file_get_submitted_draft_itemid('image');
    file_prepare_draft_area($draftitemid, $context->id, 'local_communications', news::IMAGE_FILEAREA, $story->id, $imageoptions);
} else {
    $draftitemid = file_get_submitted_draft_itemid('image');
    file_prepare_draft_area($draftitemid, null, 'local_communications', news::IMAGE_FILEAREA, null, $imageoptions);
}
$form->set_data((object) ['image' => $draftitemid]);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
