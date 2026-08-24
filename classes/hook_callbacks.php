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

namespace local_communications;

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_head_html_generation;
use core\hook\output\before_standard_top_of_body_html_generation;
use local_communications\local\campaigns;
use local_communications\local\news;

/**
 * Hook callback handlers for local_communications.
 *
 * @package     local_communications
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

        if (!get_config('local_communications', 'enabled')) {
            return null;
        }

        if (!isloggedin() || isguestuser()) {
            return null;
        }

        // Never show the widget on the plugin's own admin report page.
        if (strpos((string) $PAGE->pagetype, 'local-communications') === 0) {
            return null;
        }

        // The global $COURSE always resolves to a real course object - the site/front-page course
        // (id == SITEID) on pages not tied to a specific course, e.g. the dashboard or site
        // home - so a campaign can legitimately target those via its key-area checkboxes.
        // Actual page scoping is entirely campaigns::get_active_for_context()'s job below;
        // this is only a safety net against $COURSE somehow not being set at all.
        if (empty($COURSE->id)) {
            return null;
        }

        $context = \context_course::instance($COURSE->id, IGNORE_MISSING);
        if (!$context || !has_capability('local/communications:submit', $context)) {
            return null;
        }

        return $context;
    }

    /**
     * The campaign, if any, that applies to the current page/course/user - the widget
     * only ever shows when one does. Delegates the actual targeting/scheduling decision
     * to {@see campaigns::get_active_for_context()}, which ajax/submit.php also calls to
     * re-validate at submission time, so the two can never disagree about what was live.
     *
     * @return \stdClass|null
     */
    protected static function get_matching_campaign(): ?\stdClass {
        global $PAGE, $COURSE, $USER;

        $context = self::get_display_context();
        if (!$context) {
            return null;
        }

        $course = $COURSE;
        $cm = $PAGE->cm ?? null;

        return campaigns::get_active_for_context($course, $cm, (string) $PAGE->pagetype, $USER);
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
     * The dashboard news stories, if any, that apply to the current user - the carousel
     * only ever shows when at least one does. Empty on any page other than the
     * dashboard itself; placement is fixed, unlike campaigns' page targeting.
     *
     * Pagetype alone can't tell the dashboard apart from "My courses" - my/courses.php
     * sets the exact same pagetype ('my-index') as my/index.php, only its pagelayout
     * ('mycourses' vs 'mydashboard') differs. Pagelayout alone isn't unique either -
     * message/index.php also uses 'mydashboard'. Only the combination of both
     * identifies the dashboard specifically.
     *
     * @return \stdClass[]
     */
    protected static function get_dashboard_news(): array {
        global $PAGE, $USER;

        if (!get_config('local_communications', 'newsenabled')) {
            return [];
        }

        if ((string) $PAGE->pagetype !== 'my-index' || (string) $PAGE->pagelayout !== 'mydashboard') {
            return [];
        }

        if (!isloggedin() || isguestuser()) {
            return [];
        }

        return news::get_active_list($USER);
    }

    /**
     * Registers the widget's/carousel's CSS. Must happen before <head> is printed, so
     * this cannot be done from the before_footer_html_generation or
     * before_standard_top_of_body_html_generation hooks below - by the time either of
     * those fires, <head> has already been output.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if (!self::get_matching_campaign() && !self::get_dashboard_news()) {
            return;
        }

        $PAGE->requires->css('/local/communications/styles.css');
    }

    /**
     * Injects the floating feedback button into the footer of course pages.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE, $OUTPUT, $COURSE;

        $campaign = self::get_matching_campaign();
        if (!$campaign) {
            return;
        }

        $context = \context_course::instance($COURSE->id);
        $cm = $PAGE->cm ?? null;

        $params = [
            'courseid' => (int) $COURSE->id,
            'cmid' => $cm ? (int) $cm->id : 0,
            'contextid' => (int) $context->id,
            'campaignid' => (int) $campaign->id,
            'campaignmodaltitle' => (string) ($campaign->modaltitle ?? ''),
            'campaignintro' => (string) ($campaign->introtext ?? ''),
            'skiptopicstep' => (bool) $campaign->skiptopicstep,
            'labelhappy' => (string) ($campaign->labelhappy ?? ''),
            'labelneutral' => (string) ($campaign->labelneutral ?? ''),
            'labelsad' => (string) ($campaign->labelsad ?? ''),
            'pagetype' => (string) $PAGE->pagetype,
            'pageurl' => (string) $PAGE->url->out(false),
            'breadcrumb' => self::get_breadcrumb_trail(),
            'categories' => \local_communications\local\categories::get_list_for_campaign($campaign),
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
            'neverask_prefix',
            'neverask_linktext',
        ], 'local_communications');

        $html = $OUTPUT->render_from_template('local_communications/floating_button', [
            'triggerlabel' => get_string('triggerlabel', 'local_communications'),
            'triggeraria' => get_string('triggeraria', 'local_communications'),
        ]);
        $hook->add_html($html);

        $PAGE->requires->js_call_amd('local_communications/app', 'init', [$params]);
    }

    /**
     * Renders the dashboard news carousel right after <body> - theme-agnostic, unlike
     * targeting a specific theme's markup, but that puts it above the theme's own
     * header, which on a theme with a fixed/sticky header leaves it partially hidden
     * behind that header rather than sitting in the page as a proper block.
     * local_communications/news_carousel relocates it client-side to right before
     * #page-content when present (Boost and Boost-derived themes), falling back to
     * staying right here - still fully working - on any theme without it, rather than
     * this method guessing at theme-specific markup itself. Rendered server-side as
     * plain markup either way, so the first story is visible even if JS fails to load;
     * the module only handles relocation and the timer/dot rotation on top of what's
     * already in the DOM.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $OUTPUT;

        $stories = self::get_dashboard_news();
        if (!$stories) {
            return;
        }

        $count = count($stories);
        $slides = [];
        foreach (array_values($stories) as $index => $story) {
            $imageurl = news::image_url($story);
            $linkurl = trim((string) $story->linkurl);

            $slides[] = [
                'index' => $index,
                'active' => $index === 0,
                'imageurl' => $imageurl ? $imageurl->out(false) : null,
                'title' => format_string($story->title),
                'description' => format_text($story->description ?? '', FORMAT_PLAIN),
                'linkurl' => $linkurl !== '' ? $linkurl : null,
                'dotaria' => get_string('news_dotaria', 'local_communications', (object) [
                    'index' => $index + 1, 'count' => $count,
                ]),
                'slidearia' => get_string('news_slidearia', 'local_communications', (object) [
                    'index' => $index + 1, 'count' => $count,
                ]),
            ];
        }

        $html = $OUTPUT->render_from_template('local_communications/news_carousel', [
            'stories' => $slides,
            'hasmultiple' => $count > 1,
            'carousellabel' => get_string('news_carousellabel', 'local_communications'),
            'prevlabel' => get_string('news_prev', 'local_communications'),
            'nextlabel' => get_string('news_next', 'local_communications'),
            'pauselabel' => get_string('news_pause', 'local_communications'),
            'playlabel' => get_string('news_play', 'local_communications'),
        ]);
        $hook->add_html($html);

        // At least 1 second regardless of what's stored - a 0 or negative interval
        // would otherwise spin the carousel as fast as setInterval() can fire.
        $intervalseconds = max(1, (int) get_config('local_communications', 'newsinterval'));

        // CSS is requested from before_standard_head_html_generation above, not here -
        // by the time this hook fires, <head> has already been output.
        $PAGE->requires->js_call_amd('local_communications/news_carousel', 'init', [
            ['intervalms' => $intervalseconds * 1000],
        ]);
    }
}
