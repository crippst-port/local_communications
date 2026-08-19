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

namespace local_feedback;

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callback handlers for local_feedback.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Whether the feedback widget should be shown on the current page.
     *
     * @return \context_course|null the course context to use, or null if the widget should not show.
     */
    protected static function get_display_context(): ?\context_course {
        global $PAGE, $COURSE;

        if (!get_config('local_feedback', 'enabled')) {
            return null;
        }

        if (!isloggedin() || isguestuser()) {
            return null;
        }

        // Never show the widget on the plugin's own admin report page.
        if (strpos((string) $PAGE->pagetype, 'local-feedback') === 0) {
            return null;
        }

        // Feedback is scoped to a specific course, so only show it inside a real course.
        if (empty($COURSE->id) || $COURSE->id == SITEID) {
            return null;
        }

        $context = \context_course::instance($COURSE->id, IGNORE_MISSING);
        if (!$context || !has_capability('local/feedback:submit', $context)) {
            return null;
        }

        return $context;
    }

    /**
     * Builds a human-readable trail describing exactly where on the course the
     * widget was opened - the course home page, a specific activity, or any
     * other course-area page (participants, grades, blog, etc.) - using
     * Moodle's own breadcrumb, so it's accurate wherever the widget shows up
     * without needing per-page-type handling here.
     *
     * @return string e.g. "Tom Test Course › Grades › User report"
     */
    protected static function get_breadcrumb_trail(): string {
        global $PAGE;

        $labels = [];
        foreach ($PAGE->navbar->get_items() as $item) {
            $text = trim((string) $item->text);
            if ($text !== '') {
                $labels[] = $text;
            }
        }

        return implode(' › ', $labels);
    }

    /**
     * Registers the widget's CSS. Must happen before <head> is printed, so this
     * cannot be done from the before_footer_html_generation hook below.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if (!self::get_display_context()) {
            return;
        }

        $PAGE->requires->css('/local/feedback/styles.css');
    }

    /**
     * Injects the floating feedback button into the footer of course pages.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE, $OUTPUT, $COURSE;

        $context = self::get_display_context();
        if (!$context) {
            return;
        }

        $cm = $PAGE->cm ?? null;

        $params = [
            'courseid' => (int) $COURSE->id,
            'cmid' => $cm ? (int) $cm->id : 0,
            'contextid' => (int) $context->id,
            'pagetype' => (string) $PAGE->pagetype,
            'pageurl' => (string) $PAGE->url->out(false),
            'breadcrumb' => self::get_breadcrumb_trail(),
            'categories' => \local_feedback\local\categories::get_list(),
        ];

        $PAGE->requires->strings_for_js([
            'sentiment_happy',
            'sentiment_neutral',
            'sentiment_sad',
            'prompt_happy',
            'prompt_neutral',
            'prompt_sad',
            'placeholder_feedback',
            'anonymous_label',
            'back',
            'submit',
            'submitting',
            'thankyou_title',
            'thankyou_body',
            'close',
            'error_generic',
            'error_empty',
            'triggerlabel',
            'triggeraria',
            'modaltitle',
            'category_heading',
            'category_other',
            'category_other_placeholder',
            'category_skip',
            'continue',
        ], 'local_feedback');

        $html = $OUTPUT->render_from_template('local_feedback/floating_button', [
            'triggerlabel' => get_string('triggerlabel', 'local_feedback'),
            'triggeraria' => get_string('triggeraria', 'local_feedback'),
        ]);
        $hook->add_html($html);

        $PAGE->requires->js_call_amd('local_feedback/app', 'init', [$params]);
    }
}
