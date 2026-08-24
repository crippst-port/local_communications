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
 * @package     local_communications
 * @category    string
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Communications';

// Capabilities.
$string['feedback:submit'] = 'Submit course feedback';
$string['feedback:viewreports'] = 'View collected course feedback reports';
$string['feedback:managecampaigns'] = 'Create, edit and delete feedback campaigns';

// Settings.
$string['settings_heading'] = 'Feedback widget settings';
$string['enabled'] = 'Enable feedback widget';
$string['enabled_desc'] = 'Show the floating feedback button on course pages so students can submit feedback.';
$string['categories_setting'] = 'Feedback areas';
$string['categories_setting_desc'] = 'The default areas students can optionally tag their feedback with, one per line, shown as buttons in the feedback widget alongside an "Other" option for free text. This is a fallback: any campaign can set its own list under Feedback campaigns, which is used instead whenever a campaign has one.';
$string['trendwindow_setting'] = 'Trend window';
$string['trendwindow_setting_desc'] = 'How many of a course\'s most recent responses to take into account when working out its trend arrow, split into two equal halves (newest half vs the half before that). Courses with fewer responses than this show no trend arrow. Must be at least 2.';
$string['newssettings_heading'] = 'News settings';
$string['newsenabled_setting'] = 'Enable news carousel';
$string['newsenabled_setting_desc'] = 'Show the news carousel on the dashboard when there are stories to show.';
$string['newsinterval_setting'] = 'Carousel rotation time';
$string['newsinterval_setting_desc'] = 'How long each story shows on the dashboard carousel before it automatically advances to the next one, in seconds. Must be at least 1.';

// Campaigns.
$string['defaultcampaignname'] = 'Default';
$string['managecampaigns'] = 'Feedback campaigns';
$string['managecampaigns_intro'] = 'Campaigns control when and where the feedback widget shows, and to whom. A campaign with no targeting set runs everywhere, for everyone, all the time - add targeting to scope it down.';
$string['campaign_create'] = 'Create campaign';
$string['campaign_edit'] = 'Edit campaign: {$a}';
$string['campaign_saved'] = 'Campaign saved.';
$string['campaign_none'] = 'No campaigns yet.';
$string['campaign_name'] = 'Name';
$string['campaign_enabled'] = 'Enabled';
$string['campaign_coursefocused'] = 'Course-focused campaign';
$string['campaign_coursefocused_help'] = 'Checked: this campaign\'s report compares courses/categories against each other (like a league table), and it\'s linked from the Reports menu of every course it targets. Unchecked: it gets a flat, combined report instead with no course comparison, and isn\'t linked from any course - use this for a campaign that isn\'t about any specific course, e.g. one targeting the dashboard or site home.';
$string['campaign_responselimit'] = 'Response limit';
$string['campaign_responselimit_help'] = 'How often the same person may submit to this campaign. For a course-focused campaign this applies per course - once (or once/day) in each course it targets, not once across all of them combined; for a campaign that isn\'t course-focused, it\'s a single site-wide limit. Enforced even for anonymous submissions, by privately tracking that a response was made (not its content) against the real submitter - see this plugin\'s privacy policy summary for details.';
$string['campaign_responselimit_none'] = 'No limit';
$string['campaign_responselimit_daily'] = 'Once per day';
$string['campaign_responselimit_once'] = 'Once ever';
$string['campaign_maxresponses'] = 'Deactivate after';
$string['campaign_maxresponses_help'] = 'Once this many total responses have been collected (from anyone), the campaign stops showing to anyone - the same as it expiring or being manually disabled. Leave at 0 for no limit. A submission already open in someone\'s browser when the limit is hit is still accepted, just not counted against this campaign.';
$string['campaign_priority'] = 'Priority';
$string['campaign_priority_help'] = 'When more than one campaign matches the same page and user, only the campaign with the lowest priority number is shown - the widget only ever asks one question at a time. Campaigns with equal priority fall back to whichever was created first.';
$string['campaign_starttime'] = 'Start date';
$string['campaign_endtime'] = 'End date';
$string['campaign_error_daterange'] = 'The end date must be after the start date.';
$string['campaign_error_maxresponses'] = 'Enter 0 (no limit) or a positive number.';
$string['campaign_modaltitle'] = 'Modal title';
$string['campaign_modaltitle_help'] = 'The title shown at the top of the feedback widget for this campaign. Leave empty to use the site default ("How well does this module use Moodle?").';
$string['campaign_introtext'] = 'Intro text';
$string['campaign_introtext_help'] = 'A short message shown before the sentiment picker, explaining what this campaign is asking about. Leave empty to show nothing.';
$string['campaign_sentimentlabels'] = 'Sentiment button labels';
$string['campaign_sentimentlabels_help'] = 'Override the text shown under each sentiment face for this campaign - e.g. "Yes" / "Somewhat" / "No" instead of the site defaults shown as each box\'s placeholder. Leave any of them empty to use that default.';
$string['campaign_topics'] = 'Topic labels';
$string['campaign_topics_help'] = 'The areas respondents can optionally tag their feedback with under this campaign, one per line, shown as buttons alongside an "Other" option for free text. Leave empty to use the site-wide default list instead (Site administration > Plugins > Local plugins > Feedback). Ignored if "Skip the topic/area step" below is checked.';
$string['campaign_skiptopicstep'] = 'Skip the topic/area step';
$string['campaign_skiptopicstep_help'] = 'Goes straight from the sentiment buttons to the comment box, skipping the "what area is this about" step entirely. Use this when the campaign\'s own intro text and question already make the topic clear, so a generic area picker would be redundant.';
$string['campaign_targetingheader'] = 'Targeting';
$string['campaign_categories'] = 'Course categories';
$string['campaign_categories_help'] = 'Restrict this campaign to courses in these categories (including their subcategories). Leave empty to target every course.';
$string['campaign_keyareas'] = 'Key areas';
$string['campaign_keyareas_help'] = 'Restrict this campaign to these areas of the site. Leave every box unchecked to target every page.';
$string['campaign_page_dashboard'] = 'Dashboard';
$string['campaign_page_sitehome'] = 'Site home';
$string['campaign_page_coursepage'] = 'Course page';
$string['campaign_page_courselisting'] = 'Course/category listing';
$string['campaign_page_participants'] = 'Participants list';
$string['campaign_page_grades'] = 'Grades';
$string['campaign_page_profile'] = 'User profile';
$string['campaign_allactivities'] = 'Target all activity types';
$string['campaign_allactivities_help'] = 'Restrict this campaign to activity pages, of any type - including activity types installed later. Overrides the specific list below, which is hidden while this is checked.';
$string['campaign_activities'] = 'Specific activity types';
$string['campaign_activities_help'] = 'Restrict this campaign to pages within these specific activity types (their view page and any other page belonging to them, e.g. a quiz attempt or review page). Ignored if "Target all activity types" above is checked.';
$string['campaign_pagetypes'] = 'Other page type patterns (advanced)';
$string['campaign_pagetypes_help'] = 'For anything not covered by the key areas or activities above, one Moodle page type pattern per line. \'*\' matches any text, e.g. "course-view-*" for any course home page, or "mod-quiz-*" for anywhere in the quiz activity. Leave empty if the checkboxes above already cover what you need.';
$string['campaign_roles'] = 'Roles';
$string['campaign_roles_help'] = 'Restrict this campaign to users holding at least one of these roles in the course. Leave empty to target any role.';
$string['campaign_cohort'] = 'Cohort';
$string['campaign_cohort_none'] = 'Not restricted to a cohort';
$string['campaign_users'] = 'Specific users';
$string['campaign_users_help'] = 'Restrict this campaign to these specific user ids, one per line. Leave empty to not restrict by individual user.';
$string['campaign_error_userids'] = 'Enter one numeric user id per line.';
$string['campaign_status'] = 'Status';
$string['campaign_status_enabled'] = 'Enabled';
$string['campaign_status_disabled'] = 'Disabled';
$string['campaign_status_scheduled'] = 'Scheduled';
$string['campaign_status_ended'] = 'Ended';
$string['campaign_status_limitreached'] = 'Limit reached';
$string['campaign_window'] = 'Date window';
$string['campaign_window_start'] = 'From {$a}';
$string['campaign_window_end'] = 'to {$a}';
$string['campaign_window_unbounded'] = 'no limit';
$string['campaign_targeting'] = 'Targeting';
$string['campaign_responses'] = 'Responses';
$string['campaign_responses_of_max'] = '{$a->count} / {$a->max}';
$string['campaign_enable'] = 'Enable';
$string['campaign_disable'] = 'Disable';
$string['campaign_viewresponses'] = 'View responses';
$string['campaign_confirmdelete'] = 'Delete campaign "{$a}"? Responses already collected under it are kept, but the campaign itself cannot be recovered.';
$string['campaign_deleted'] = '(deleted campaign)';
$string['targetsummary_everyone'] = 'Everyone, every course, every page';
$string['targetsummary_categories'] = 'Categories: {$a}';
$string['targetsummary_pages'] = 'Pages: {$a}';
$string['targetsummary_roles'] = 'Roles: {$a}';
$string['targetsummary_cohort'] = 'Cohort: {$a}';
$string['targetsummary_users'] = '{$a} specific user(s)';

// Dashboard news.
$string['managenews'] = 'Dashboard news';
$string['managenews_intro'] = 'News stories appear as a carousel at the top of the dashboard. A story with no targeting set shows to everyone - add targeting to scope it down.';
$string['news_create'] = 'Create story';
$string['news_edit'] = 'Edit story: {$a}';
$string['news_saved'] = 'Story saved.';
$string['news_none'] = 'No news stories yet.';
$string['news_title'] = 'Headline';
$string['news_description'] = 'Short description';
$string['news_description_help'] = 'Shown under the headline in the carousel slide. Keep it brief - long descriptions are clipped.';
$string['news_image'] = 'Image';
$string['news_image_help'] = 'Shown alongside the headline in the carousel slide. Optional, but a story with no image still shows its headline and description.';
$string['news_link'] = 'Link';
$string['news_link_help'] = 'Where the slide goes when clicked. Leave empty for a slide that isn\'t clickable.';
$string['news_enabled'] = 'Enabled';
$string['news_sortorder'] = 'Sort order';
$string['news_sortorder_help'] = 'Stories appear in the carousel lowest number first. Stories sharing the same number fall back to whichever was created first.';
$string['news_confirmdelete'] = 'Delete story "{$a}"? This cannot be undone.';
$string['news_dotaria'] = 'Show story {$a->index} of {$a->count}';
$string['news_targetsummary_everyone'] = 'Everyone';
$string['news_carousellabel'] = 'News';
$string['news_prev'] = 'Previous story';
$string['news_next'] = 'Next story';
$string['news_pause'] = 'Pause carousel';
$string['news_play'] = 'Play carousel';

// Widget / trigger.
$string['triggerlabel'] = 'Feedback';
$string['triggeraria'] = 'Give feedback about how this module uses Moodle';
$string['neverask_prefix'] = "From time to time we'd like to be able to gather feedback from our users which we'll use to improve Moodle, ";
$string['neverask_linktext'] = "click here if you'd prefer not to be asked.";

// User preferences (profile page).
$string['preferences_link'] = 'Feedback prompts';
$string['preferences_heading'] = "Feedback prompts you've turned off";
$string['preferences_intro'] = "You won't be asked for feedback under these campaigns any more. Re-enable any of them below.";
$string['preferences_none'] = "You haven't turned off feedback requests for anything.";
$string['preferences_reenable'] = 'Re-enable';
$string['preferences_reenableall'] = 'Re-enable all';
$string['preferences_reenabled'] = 'Feedback requests re-enabled.';
$string['preferences_bycampaign_heading'] = 'Turned off for specific campaigns';
$string['preferences_disableall'] = 'Turn off feedback requests everywhere';
$string['preferences_disableall_intro'] = "Don't want to be asked for feedback at all? You can turn it off site-wide instead of campaign by campaign.";
$string['preferences_disabledall'] = "Feedback requests turned off everywhere. You won't be asked for feedback on any page.";
$string['preferences_enableall'] = 'Turn feedback requests back on';
$string['preferences_enabledall'] = 'Feedback requests turned back on.';
$string['preferences_globallydisabled'] = "You've turned off feedback requests everywhere - you won't be asked for feedback on any page.";

// My submissions (profile page).
$string['mysubmissions_link'] = 'My feedback';
$string['mysubmissions_heading'] = 'Feedback you\'ve submitted';
$string['mysubmissions_none'] = "You haven't submitted any feedback yet - anonymous submissions don't appear here, since they're not linked back to you anywhere.";
$string['modaltitle'] = 'How well does this module use Moodle?';

// Sentiments.
$string['sentiment_happy'] = 'Good';
$string['sentiment_neutral'] = 'Okay';
$string['sentiment_sad'] = 'Not great';

// Step 2: area/category. The preset options themselves come from the
// local_communications/categories admin setting, not from strings here.
$string['category_heading'] = 'What is your feedback about?';
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
$string['error_responselimit'] = 'You\'ve already given feedback for this - thanks, we\'ve got it.';

// Report page.
$string['reportheading'] = 'Course feedback report';
$string['reportheading_campaign'] = 'Feedback report: {$a}';
$string['reportheading_course_campaign'] = '{$a->campaign} feedback: {$a->course}';
$string['report_nofeedback'] = 'No feedback has been submitted yet.';
$string['report_filtersentiment'] = 'Sentiment';
$string['report_allsentiments'] = 'All sentiments';
$string['report_apply'] = 'Filter';
$string['report_reset'] = 'Reset';
$string['report_col_time'] = 'Submitted';
$string['report_col_sentiment'] = 'Sentiment';
$string['report_col_category'] = 'Area';
$string['report_col_campaign'] = 'Campaign';
$string['report_col_course'] = 'Course';
$string['report_col_avgscore'] = 'Score';
$string['report_stat_avgscore'] = 'Average score';
$string['report_col_activity'] = 'Activity';
$string['report_col_user'] = 'From';
$string['report_col_feedback'] = 'Feedback';
$string['report_col_page'] = 'Location';
$string['report_col_total'] = 'Total';
$string['report_col_trend'] = 'Trend';
$string['report_score_explain'] = 'Score is a weighted average of each course\'s responses ({$a->happy} = 5, {$a->neutral} = 3, {$a->sad} = 1), out of 5 - not just a raw count - so courses with different numbers of responses can be compared fairly. This reflects feedback submitted by students only, not staff or other stakeholders.';
$string['report_widgetpreview'] = 'What respondents saw';
$string['report_anonymous'] = 'Anonymous';
$string['report_stat_total'] = 'Total responses';
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
$string['report_heading_categoryperformance'] = 'Category performance';
$string['report_topperforming'] = 'Top performing';
$string['report_lowscoring'] = 'Low feedback score';
$string['report_viewall'] = 'View all {$a} categories';
$string['report_col_coursecategory'] = 'Category';
$string['report_filtercategory'] = 'Course category';
$string['report_allcategories'] = 'All categories';
$string['report_topic_unspecified'] = 'Not specified';
$string['report_filtertopic'] = 'Topic';
$string['report_alltopics'] = 'All topics';

// Privacy.
$string['privacy:metadata:local_communications_submissions'] = 'Feedback submitted by users about courses.';
$string['privacy:metadata:local_communications_submissions:userid'] = 'The ID of the user who submitted the feedback (0 if submitted anonymously).';
$string['privacy:metadata:local_communications_submissions:anonymous'] = 'Whether the submission was made anonymously.';
$string['privacy:metadata:local_communications_submissions:courseid'] = 'The course the feedback is about.';
$string['privacy:metadata:local_communications_submissions:feedbacktext'] = 'The feedback text written by the user.';
$string['privacy:metadata:local_communications_submissions:sentiment'] = 'The sentiment (happy/neutral/sad) chosen by the user.';
$string['privacy:metadata:local_communications_submissions:category'] = 'The area the user said the feedback relates to (an admin-configured option, or free text they typed), if given.';
$string['privacy:metadata:local_communications_submissions:pageurl'] = 'The URL the user was viewing when they gave feedback.';
$string['privacy:metadata:local_communications_submissions:breadcrumb'] = 'The breadcrumb trail describing which page the user was viewing when they gave feedback.';
$string['privacy:metadata:local_communications_submissions:useragent'] = 'The browser user agent string recorded at submission time.';
$string['privacy:metadata:local_communications_submissions:timecreated'] = 'The time the feedback was submitted.';
$string['privacy:metadata:local_communications_campaign_responses'] = 'A record that a user responded to a campaign, used only to enforce that campaign\'s response limit - not the response itself, which (if submitted anonymously) is not linked back to the user anywhere else.';
$string['privacy:metadata:local_communications_campaign_responses:userid'] = 'The ID of the user who responded, even if their response was submitted anonymously.';
$string['privacy:metadata:local_communications_campaign_responses:campaignid'] = 'The campaign responded to.';
$string['privacy:metadata:local_communications_campaign_responses:courseid'] = 'The course the response was made under, used to scope per-course response limits.';
$string['privacy:metadata:local_communications_campaign_responses:timecreated'] = 'The time the response was submitted.';
$string['privacy:campaignresponses'] = 'Campaign response limit records';
$string['privacy:metadata:preference:neverask'] = 'Whether this user has asked not to be shown the feedback widget for particular campaigns.';
$string['privacy:metadata:preference:neverask_all'] = 'Whether this user has turned off feedback requests everywhere.';
