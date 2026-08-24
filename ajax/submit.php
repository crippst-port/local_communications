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
 * AJAX endpoint that stores a feedback submission from the floating widget.
 *
 * @package     local_communications
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

    if (!get_config('local_communications', 'enabled')) {
        throw new moodle_exception('error_generic', 'local_communications');
    }

    $sesskey = required_param('sesskey', PARAM_ALPHANUM);
    if (!confirm_sesskey($sesskey)) {
        throw new moodle_exception('invalidsesskey', 'error');
    }

    $courseid = required_param('courseid', PARAM_INT);
    $cmid = optional_param('cmid', 0, PARAM_INT);
    $campaignid = optional_param('campaignid', 0, PARAM_INT);
    $sentiment = required_param('sentiment', PARAM_ALPHA);
    $category = optional_param('category', '', PARAM_TEXT);
    $categoryother = optional_param('categoryother', 0, PARAM_BOOL) ? true : false;
    $feedbacktext = required_param('feedbacktext', PARAM_TEXT);
    $anonymous = optional_param('anonymous', 0, PARAM_BOOL) ? 1 : 0;
    $pagetype = optional_param('pagetype', '', PARAM_TEXT);
    $breadcrumb = optional_param('breadcrumb', '', PARAM_TEXT);
    $pageurl = optional_param('pageurl', '', PARAM_TEXT);
    $pagetitle = optional_param('pagetitle', '', PARAM_TEXT);
    $referrer = optional_param('referrer', '', PARAM_TEXT);
    $useragent = optional_param('useragent', '', PARAM_TEXT);
    $screenwidth = optional_param('screenwidth', 0, PARAM_INT);
    $screenheight = optional_param('screenheight', 0, PARAM_INT);
    $lang = optional_param('lang', '', PARAM_TEXT);

    if (!in_array($sentiment, ['happy', 'neutral', 'sad'], true)) {
        throw new invalid_parameter_exception('Invalid sentiment');
    }

    // Course/module/section identity is always re-derived server-side, never trusted from the client.
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($course->id);
    require_capability('local/communications:submit', $context);

    $cmname = null;
    $modname = null;
    $sectionname = null;
    $cm = null;
    if ($cmid) {
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($cmid);
        if ($cm) {
            $cmname = $cm->name;
            $modname = $cm->modname;
            $section = $cm->get_section_info();
            if ($section) {
                $sectionname = $section->name ?: get_string('sectionname', 'moodle', $section->section);
            }
        } else {
            $cmid = 0;
            $cm = null;
        }
    }

    // Re-derive which campaign is actually live server-side, rather than trusting the
    // client-supplied campaignid outright - it may have expired or been disabled while
    // the user was filling in the form. Falling back to 0 rather than rejecting the
    // submission keeps the feedback (still useful) instead of losing it over a stale id -
    // UNLESS the specific reason it's no longer live is that this user has just used up
    // its response limit, in which case the submission is actively rejected instead (the
    // whole point of a limit is to stop the extra response being recorded at all).
    $activecampaign = \local_communications\local\campaigns::get_active_for_context($course, $cm, $pagetype, $USER);
    if ($activecampaign && $activecampaign->id == $campaignid) {
        $recordcampaignid = $activecampaign->id;
        $recordcampaignname = $activecampaign->name;
    } else {
        $submittedcampaign = $campaignid ? \local_communications\local\campaigns::get($campaignid) : null;
        if (
            $submittedcampaign
            && \local_communications\local\campaigns::has_reached_response_limit($submittedcampaign, $USER->id, $course->id)
        ) {
            throw new moodle_exception('error_responselimit', 'local_communications');
        }
        $recordcampaignid = 0;
        $recordcampaignname = null;
    }

    // The category step is optional/skippable, so an empty value is fine - just not
    // recorded. A non-"Other" value must exactly match one of the labels actually shown
    // for the campaign the widget was loaded under - fetched by the raw submitted id
    // rather than $activecampaign above, so a campaign that expired/was disabled while
    // the user was still filling in the form doesn't wrongly reject a real preset label
    // (attribution already falls back to 0 in that case; this is only checking the
    // label is real, defending against a tampered request inventing an arbitrary one).
    $shownunder = $campaignid ? \local_communications\local\campaigns::get($campaignid) : null;
    $category = trim($category);
    if ($category !== '') {
        if ($categoryother) {
            $category = mb_substr($category, 0, 255);
        } else if (!in_array($category, \local_communications\local\categories::get_list_for_campaign($shownunder ?: null), true)) {
            throw new invalid_parameter_exception('Invalid category');
        }
    }

    $feedbacktext = trim($feedbacktext);
    if ($feedbacktext === '') {
        echo json_encode(['success' => false, 'error' => get_string('error_empty', 'local_communications')]);
        @ob_end_flush();
        exit(0);
    }

    $record = new stdClass();
    $record->userid = $anonymous ? 0 : $USER->id;
    $record->anonymous = $anonymous;
    $record->courseid = $course->id;
    $record->coursename = $course->fullname;
    $record->cmid = $cmid;
    $record->cmname = $cmname;
    $record->modname = $modname;
    $record->sectionname = $sectionname;
    $record->sentiment = $sentiment;
    $record->category = $category !== '' ? $category : null;
    $record->feedbacktext = $feedbacktext;
    $record->pageurl = clean_param($pageurl, PARAM_URL) ?: null;
    $record->pagetype = mb_substr($pagetype, 0, 255);
    $record->breadcrumb = $breadcrumb !== '' ? mb_substr($breadcrumb, 0, 1000) : null;
    $record->pagetitle = mb_substr($pagetitle, 0, 255);
    $record->referrer = $referrer !== '' ? mb_substr($referrer, 0, 2000) : null;
    $record->useragent = mb_substr($useragent, 0, 1000);
    $record->screenwidth = $screenwidth ?: null;
    $record->screenheight = $screenheight ?: null;
    $record->lang = mb_substr($lang, 0, 30);
    $record->campaignid = $recordcampaignid;
    $record->campaignname = $recordcampaignname;
    $record->timecreated = time();

    $DB->insert_record('local_communications_submissions', $record);

    // Recorded against the real user id regardless of $anonymous or whether this
    // campaign even has a response limit set - see campaigns::record_response().
    if ($recordcampaignid) {
        \local_communications\local\campaigns::record_response($recordcampaignid, $USER->id, $course->id);
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
