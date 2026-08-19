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
 * Plugin strings are defined here.
 *
 * @package     local_feedback
 * @category    string
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Feedback';

// Capabilities.
$string['feedback:submit'] = 'Submit course feedback';
$string['feedback:viewreports'] = 'View collected course feedback reports';

// Settings.
$string['settings_heading'] = 'Feedback widget settings';
$string['enabled'] = 'Enable feedback widget';
$string['enabled_desc'] = 'Show the floating feedback button on course pages so students can submit feedback.';
$string['categories_setting'] = 'Feedback areas';
$string['categories_setting_desc'] = 'The areas students can optionally tag their feedback with, one per line. Shown as buttons in the feedback widget, alongside an "Other" option for free text.';
$string['trendwindow_setting'] = 'Trend window';
$string['trendwindow_setting_desc'] = 'How many of a course\'s most recent responses to take into account when working out its trend arrow, split into two equal halves (newest half vs the half before that). Courses with fewer responses than this show no trend arrow. Must be at least 2.';

// Widget / trigger.
$string['triggerlabel'] = 'Feedback';
$string['triggeraria'] = 'Give feedback about this course';
$string['modaltitle'] = 'How are you finding this course?';

// Sentiments.
$string['sentiment_happy'] = 'Good';
$string['sentiment_neutral'] = 'Okay';
$string['sentiment_sad'] = 'Not great';

// Step 2: area/category. The preset options themselves come from the
// local_feedback/categories admin setting, not from strings here.
$string['category_heading'] = 'What is the topic of your feedback?';
$string['category_other'] = 'Other';
$string['category_other_placeholder'] = 'Tell us what area this is about...';
$string['category_skip'] = "Not sure / skip";
$string['continue'] = 'Continue';

// Step 3 prompts.
$string['prompt_happy'] = 'Great to hear! What did you like?';
$string['prompt_neutral'] = 'Thanks. Is there anything you would tell us about your experience?';
$string['prompt_sad'] = 'Sorry to hear that. What didn\'t you like?';
$string['placeholder_feedback'] = 'Type your feedback here...';
$string['anonymous_label'] = 'Submit anonymously';

// Actions.
$string['back'] = 'Back';
$string['submit'] = 'Submit feedback';
$string['submitting'] = 'Submitting...';
$string['close'] = 'Close';

// Step 3 / confirmation.
$string['thankyou_title'] = 'Thanks for your feedback!';
$string['thankyou_body'] = 'Your response has been recorded and will help us improve this course.';

// Errors.
$string['error_generic'] = 'Sorry, something went wrong submitting your feedback. Please try again.';
$string['error_empty'] = 'Please write a little about your experience before submitting.';

// Report page.
$string['reportheading'] = 'Course feedback report';
$string['reportheading_sitewide'] = 'Course feedback report (all courses)';
$string['report_nofeedback'] = 'No feedback has been submitted yet.';
$string['report_filtersentiment'] = 'Sentiment';
$string['report_allsentiments'] = 'All sentiments';
$string['report_apply'] = 'Filter';
$string['report_reset'] = 'Reset';
$string['report_col_time'] = 'Submitted';
$string['report_col_sentiment'] = 'Sentiment';
$string['report_col_category'] = 'Area';
$string['report_col_course'] = 'Course';
$string['report_col_avgscore'] = 'Score';
$string['report_stat_avgscore'] = 'Average score';
$string['report_col_activity'] = 'Activity';
$string['report_col_user'] = 'From';
$string['report_col_feedback'] = 'Feedback';
$string['report_col_page'] = 'Location';
$string['report_col_total'] = 'Total';
$string['report_col_trend'] = 'Trend';
$string['report_score_explain'] = 'Score is a weighted average of each course\'s responses (Good = 5, Okay = 3, Not great = 1), out of 5 - not just a raw count - so courses with different numbers of responses can be compared fairly. This reflects feedback submitted by students only, not staff or other stakeholders.';
$string['report_anonymous'] = 'Anonymous';
$string['report_stat_total'] = 'Total responses';
$string['report_stat_happy'] = 'Good';
$string['report_stat_neutral'] = 'Okay';
$string['report_stat_sad'] = 'Not great';
$string['report_stat_coursecount'] = 'Courses with feedback';
$string['report_stat_needsattention'] = 'Courses needing attention';
$string['report_needsattention_explain'] = 'Courses whose average score is 2 or below - i.e. overwhelmingly negative feedback.';
$string['report_filtertier'] = 'Course rating';
$string['report_alltiers'] = 'All courses';
$string['tier_bad'] = 'Needs attention';
$string['tier_okay'] = 'Okay';
$string['tier_good'] = 'Good';
$string['report_filtertrend'] = 'Trend';
$string['report_alltrends'] = 'All trends';
$string['report_trendoption_up'] = 'Trending up';
$string['report_trendoption_down'] = 'Trending down';
$string['report_trend_up'] = 'Trending up: the average score of the last {$a->n} responses ({$a->recentavg}/5) is higher than the {$a->n} before that ({$a->olderavg}/5).';
$string['report_trend_down'] = 'Trending down: the average score of the last {$a->n} responses ({$a->recentavg}/5) is lower than the {$a->n} before that ({$a->olderavg}/5).';
$string['report_trend_flat'] = 'Holding steady: the average score of the last {$a->n} responses ({$a->recentavg}/5) is about the same as the {$a->n} before that ({$a->olderavg}/5).';
$string['report_trend_nodata'] = 'Not enough feedback yet to show a trend - needs at least {$a} responses.';
$string['report_viewpage'] = 'View page';
$string['report_viewfeedback'] = 'View feedback';
$string['report_heading_bycategory'] = 'Scores by course category';
$string['report_col_coursecategory'] = 'Category';
$string['report_filtercategory'] = 'Course category';
$string['report_allcategories'] = 'All categories';
$string['report_topic_unspecified'] = 'Not specified';
$string['report_breakdown_showmore'] = 'Show {$a} more';
$string['report_filtertopic'] = 'Topic';
$string['report_alltopics'] = 'All topics';

// Privacy.
$string['privacy:metadata:local_feedback_submissions'] = 'Feedback submitted by users about courses.';
$string['privacy:metadata:local_feedback_submissions:userid'] = 'The ID of the user who submitted the feedback (0 if submitted anonymously).';
$string['privacy:metadata:local_feedback_submissions:anonymous'] = 'Whether the submission was made anonymously.';
$string['privacy:metadata:local_feedback_submissions:courseid'] = 'The course the feedback is about.';
$string['privacy:metadata:local_feedback_submissions:feedbacktext'] = 'The feedback text written by the user.';
$string['privacy:metadata:local_feedback_submissions:sentiment'] = 'The sentiment (happy/neutral/sad) chosen by the user.';
$string['privacy:metadata:local_feedback_submissions:category'] = 'The area the user said the feedback relates to (an admin-configured option, or free text they typed), if given.';
$string['privacy:metadata:local_feedback_submissions:pageurl'] = 'The URL the user was viewing when they gave feedback.';
$string['privacy:metadata:local_feedback_submissions:breadcrumb'] = 'The breadcrumb trail describing which page the user was viewing when they gave feedback.';
$string['privacy:metadata:local_feedback_submissions:useragent'] = 'The browser user agent string recorded at submission time.';
$string['privacy:metadata:local_feedback_submissions:timecreated'] = 'The time the feedback was submitted.';
