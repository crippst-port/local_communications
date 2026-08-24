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

namespace local_communications\local;

/**
 * Feedback campaigns: CRUD for the admin UI, plus the targeting/scheduling logic
 * that decides which campaign (if any) applies to a given page/user.
 *
 * {@see get_active_for_context()} is the single source of truth for that decision -
 * reused by hook_callbacks (to decide whether/what to render) and ajax/submit.php
 * (to re-validate before storing), so the two can never disagree about what was live.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaigns {
    /** @var array<int, \stdClass>|null In-request cache of enabled campaigns - a fresh page load always re-reads. */
    protected static ?array $enabledcache = null;

    /**
     * All campaigns, most recently created first - the admin management list.
     *
     * @return \stdClass[]
     */
    public static function get_all(): array {
        global $DB;

        return array_values($DB->get_records('local_communications_campaigns', null, 'timecreated DESC'));
    }

    /**
     * @param int $id
     * @return \stdClass|false
     */
    public static function get(int $id) {
        global $DB;

        return $DB->get_record('local_communications_campaigns', ['id' => $id]);
    }

    /**
     * @param \stdClass $data Every local_communications_campaigns column except id/timecreated/timemodified.
     * @return int The new campaign's id.
     */
    public static function create(\stdClass $data): int {
        global $DB, $USER;

        $data->timecreated = time();
        $data->timemodified = $data->timecreated;
        $data->usermodified = $USER->id;

        $id = $DB->insert_record('local_communications_campaigns', $data);
        self::$enabledcache = null;

        return $id;
    }

    /**
     * @param int $id
     * @param \stdClass $data
     */
    public static function update(int $id, \stdClass $data): void {
        global $DB, $USER;

        $data->id = $id;
        $data->timemodified = time();
        $data->usermodified = $USER->id;

        $DB->update_record('local_communications_campaigns', $data);
        self::$enabledcache = null;
    }

    /**
     * Removes the campaign itself only - submissions already collected under it keep
     * their denormalised campaignname snapshot and are never deleted or reattributed.
     * Its response-limit ledger rows are deleted though - unlike submissions, they carry
     * no reporting value and serve no purpose once the campaign they were limiting no
     * longer exists.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;

        $DB->delete_records('local_communications_campaigns', ['id' => $id]);
        $DB->delete_records('local_communications_campaign_responses', ['campaignid' => $id]);
        self::$enabledcache = null;
    }

    /**
     * Flips a campaign's enabled flag - the manage list's quick toggle action.
     *
     * @param int $id
     */
    public static function toggle(int $id): void {
        global $DB, $USER;

        $campaign = $DB->get_record('local_communications_campaigns', ['id' => $id], '*', MUST_EXIST);
        $DB->update_record('local_communications_campaigns', (object) [
            'id' => $id,
            'enabled' => $campaign->enabled ? 0 : 1,
            'timemodified' => time(),
            'usermodified' => $USER->id,
        ]);
        self::$enabledcache = null;
    }

    /**
     * The one enabled campaign, if any, that applies to this page/course/user right now -
     * the highest-priority (lowest priority value, ties broken by oldest id) match among
     * every enabled campaign whose date window, course-category, page-type, role, cohort
     * and explicit-user targeting all agree. Every targeting dimension left empty on a
     * campaign imposes no restriction, matching this plugin's existing "empty = don't
     * filter" convention (see categories::get_list()).
     *
     * @param \stdClass $course
     * @param \cm_info|null $cm
     * @param string $pagetype
     * @param \stdClass $user
     * @return \stdClass|null
     */
    public static function get_active_for_context(\stdClass $course, ?\cm_info $cm, string $pagetype, \stdClass $user): ?\stdClass {
        if (dismissed_campaigns::is_global_optout($user->id)) {
            return null;
        }

        $now = time();
        $best = null;

        foreach (self::get_enabled() as $campaign) {
            if ($campaign->starttime && $campaign->starttime > $now) {
                continue;
            }
            if ($campaign->endtime && $campaign->endtime < $now) {
                continue;
            }
            if (self::has_reached_max_responses($campaign)) {
                continue;
            }
            if (!self::matches_category($campaign, $course)) {
                continue;
            }
            if (!self::matches_pagetype($campaign, $pagetype)) {
                continue;
            }
            if (!self::matches_role($campaign, $course, $user)) {
                continue;
            }
            if (!self::matches_cohort($campaign, $user)) {
                continue;
            }
            if (!self::matches_user($campaign, $user)) {
                continue;
            }
            if (self::has_reached_response_limit($campaign, $user->id, $course->id)) {
                continue;
            }
            if (dismissed_campaigns::is_dismissed($campaign->id, $user->id)) {
                continue;
            }

            if (
                $best === null
                || $campaign->priority < $best->priority
                || ($campaign->priority == $best->priority && $campaign->id < $best->id)
            ) {
                $best = $campaign;
            }
        }

        return $best;
    }

    /**
     * Whether this campaign has collected its configured maximum number of responses -
     * a whole-campaign cutoff (0 = no limit), not a per-user one like
     * {@see has_reached_response_limit()}, so once it's hit the campaign simply stops
     * matching for everyone, the same as it expiring or being disabled - a submission
     * already in flight from before the cutoff was hit still falls back to an
     * unattributed response rather than being rejected (see ajax/submit.php), the same
     * grace already given to a campaign that expires or gets disabled mid-fill.
     *
     * Counts real rows in local_communications_submissions - the same number a campaign's own
     * report shows as "Total responses" - not the response-limit ledger, which exists
     * for a different purpose and can diverge from it (e.g. it isn't touched by
     * unattributed submissions at all).
     *
     * @param \stdClass $campaign
     * @return bool
     */
    public static function has_reached_max_responses(\stdClass $campaign): bool {
        global $DB;

        if (empty($campaign->maxresponses)) {
            return false;
        }

        return $DB->count_records('local_communications_submissions', ['campaignid' => $campaign->id]) >= $campaign->maxresponses;
    }

    /**
     * Whether this user has already used up this campaign's response limit -
     * 'none' (the default) never limits, 'daily' allows one response per calendar day
     * (from the user's own midnight, matching Moodle's usual "today" convention),
     * 'once' allows exactly one, ever.
     *
     * For a course-focused campaign, the limit scopes per-course - a student can respond
     * once (or once/day) in each course the campaign targets, rather than once across
     * every one of them; for a non-course-focused campaign, $courseid is ignored and the
     * limit is a single site-wide count, since "per course" wouldn't mean anything there.
     *
     * Checked against local_communications_campaign_responses, not local_communications_submissions
     * directly - the latter's userid is deliberately 0 for anonymous submissions, which
     * would make the limit trivially bypassable by ticking "submit anonymously". This
     * ledger always records the real user id regardless, see {@see record_response()}.
     *
     * @param \stdClass $campaign
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function has_reached_response_limit(\stdClass $campaign, int $userid, int $courseid): bool {
        global $DB;

        if (empty($campaign->responselimit) || $campaign->responselimit === 'none') {
            return false;
        }

        $params = ['campaignid' => $campaign->id, 'userid' => $userid];
        $where = 'campaignid = :campaignid AND userid = :userid';

        if (!empty($campaign->coursefocused)) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        if ($campaign->responselimit === 'daily') {
            $where .= ' AND timecreated >= :since';
            $params['since'] = usergetmidnight(time());
        }

        return $DB->record_exists_select('local_communications_campaign_responses', $where, $params);
    }

    /**
     * Records that this user has just submitted to this campaign, for
     * {@see has_reached_response_limit()} to count against next time - called
     * unconditionally by ajax/submit.php after a successful insert, regardless of
     * responselimit or the anonymous flag, so a limit added later still counts
     * responses collected before it existed.
     *
     * @param int $campaignid
     * @param int $userid
     * @param int $courseid
     */
    public static function record_response(int $campaignid, int $userid, int $courseid): void {
        global $DB;

        if (!$campaignid) {
            return;
        }

        $DB->insert_record('local_communications_campaign_responses', (object) [
            'campaignid' => $campaignid,
            'userid' => $userid,
            'courseid' => $courseid,
            'timecreated' => time(),
        ]);
    }

    /**
     * The course-focused campaigns currently relevant to this course - enabled, within
     * their date window, and category-targeting this course - for deciding what (if
     * anything) to link to from this course's own Reports menu. Deliberately skips
     * pagetype/role/cohort/user matching, unlike {@see get_active_for_context()}: this
     * answers "is this campaign relevant to this course at all", not "would this
     * specific viewer's widget show here right now".
     *
     * @param \stdClass $course
     * @return \stdClass[] Sorted by priority, then id.
     */
    public static function get_course_focused_for_course(\stdClass $course): array {
        $now = time();
        $matches = [];

        foreach (self::get_enabled() as $campaign) {
            if (empty($campaign->coursefocused)) {
                continue;
            }
            if ($campaign->starttime && $campaign->starttime > $now) {
                continue;
            }
            if ($campaign->endtime && $campaign->endtime < $now) {
                continue;
            }
            if (!self::matches_category($campaign, $course)) {
                continue;
            }
            $matches[] = $campaign;
        }

        usort($matches, function ($a, $b) {
            return $a->priority <=> $b->priority ?: $a->id <=> $b->id;
        });

        return $matches;
    }

    /**
     * @return \stdClass[]
     */
    protected static function get_enabled(): array {
        global $DB;

        if (self::$enabledcache === null) {
            self::$enabledcache = array_values($DB->get_records('local_communications_campaigns', ['enabled' => 1]));
        }

        return self::$enabledcache;
    }

    /**
     * @param \stdClass $campaign
     * @param \stdClass $course
     * @return bool
     */
    protected static function matches_category(\stdClass $campaign, \stdClass $course): bool {
        global $DB;

        $wanted = self::parse_int_list($campaign->categoryids ?? '');
        if (!$wanted) {
            return true;
        }

        $path = $DB->get_field('course_categories', 'path', ['id' => $course->category]);
        if ($path === false) {
            return false;
        }

        $ancestors = array_map('intval', array_filter(explode('/', $path), 'strlen'));

        return (bool) array_intersect($wanted, $ancestors);
    }

    /**
     * @param \stdClass $campaign
     * @param string $pagetype
     * @return bool
     */
    protected static function matches_pagetype(\stdClass $campaign, string $pagetype): bool {
        $patterns = self::parse_lines($campaign->pagetypepatterns ?? '');
        if (!$patterns) {
            return true;
        }

        foreach ($patterns as $pattern) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
            if (preg_match($regex, $pagetype)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \stdClass $campaign
     * @param \stdClass $course
     * @param \stdClass $user
     * @return bool
     */
    protected static function matches_role(\stdClass $campaign, \stdClass $course, \stdClass $user): bool {
        $wanted = self::parse_csv($campaign->targetroles ?? '');
        if (!$wanted) {
            return true;
        }

        $context = \context_course::instance($course->id);
        $roles = get_user_roles($context, $user->id, true);
        foreach ($roles as $role) {
            if (in_array($role->shortname, $wanted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \stdClass $campaign
     * @param \stdClass $user
     * @return bool
     */
    protected static function matches_cohort(\stdClass $campaign, \stdClass $user): bool {
        if (empty($campaign->targetcohortid)) {
            return true;
        }

        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        return cohort_is_member($campaign->targetcohortid, $user->id);
    }

    /**
     * @param \stdClass $campaign
     * @param \stdClass $user
     * @return bool
     */
    protected static function matches_user(\stdClass $campaign, \stdClass $user): bool {
        $wanted = self::parse_int_list($campaign->targetuserids ?? '');
        if (!$wanted) {
            return true;
        }

        return in_array((int) $user->id, $wanted, true);
    }

    /**
     * The sentiment button labels to show for this campaign - its own overrides where
     * set, falling back to the site-wide sentiment_happy/neutral/sad strings otherwise.
     * Used both by the widget (via categories::get_list_for_campaign()'s sibling logic
     * in hook_callbacks.php/app.js) and by reports, so a customised campaign's report
     * reads using the same words the submitter actually saw.
     *
     * @param \stdClass $campaign
     * @return array{happy: string, neutral: string, sad: string}
     */
    public static function get_sentiment_labels(\stdClass $campaign): array {
        return [
            'happy' => $campaign->labelhappy ?: get_string('sentiment_happy', 'local_communications'),
            'neutral' => $campaign->labelneutral ?: get_string('sentiment_neutral', 'local_communications'),
            'sad' => $campaign->labelsad ?: get_string('sentiment_sad', 'local_communications'),
        ];
    }

    /**
     * A short human-readable summary of a campaign's targeting, for the manage list.
     *
     * @param \stdClass $campaign
     * @return string
     */
    public static function describe_targeting(\stdClass $campaign): string {
        global $DB;

        $parts = [];

        $categoryids = self::parse_int_list($campaign->categoryids ?? '');
        if ($categoryids) {
            $names = $DB->get_records_list('course_categories', 'id', $categoryids, '', 'id, name');
            $labels = array_map(fn($id) => $names[$id]->name ?? "#$id", $categoryids);
            $parts[] = get_string('targetsummary_categories', 'local_communications', implode(', ', $labels));
        }

        $patterns = self::parse_lines($campaign->pagetypepatterns ?? '');
        if ($patterns) {
            $parts[] = get_string('targetsummary_pages', 'local_communications', implode(', ', $patterns));
        }

        $roles = self::parse_csv($campaign->targetroles ?? '');
        if ($roles) {
            $parts[] = get_string('targetsummary_roles', 'local_communications', implode(', ', $roles));
        }

        if (!empty($campaign->targetcohortid)) {
            $cohortname = $DB->get_field('cohort', 'name', ['id' => $campaign->targetcohortid]);
            $parts[] = get_string('targetsummary_cohort', 'local_communications', $cohortname ?: '#' . $campaign->targetcohortid);
        }

        $userids = self::parse_int_list($campaign->targetuserids ?? '');
        if ($userids) {
            $parts[] = get_string('targetsummary_users', 'local_communications', count($userids));
        }

        return $parts ? implode('; ', $parts) : get_string('targetsummary_everyone', 'local_communications');
    }

    /**
     * @param string $raw
     * @return string[]
     */
    protected static function parse_lines(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', $raw);

        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * @param string $raw
     * @return int[]
     */
    protected static function parse_int_list(string $raw): array {
        return array_map('intval', self::parse_lines($raw));
    }

    /**
     * @param string $raw
     * @return string[]
     */
    protected static function parse_csv(string $raw): array {
        $result = [];
        foreach (explode(',', $raw) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }
}
