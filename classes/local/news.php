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
 * Dashboard news stories: CRUD for the admin UI, plus the targeting logic that decides
 * which stories the current user's carousel shows.
 *
 * Unlike {@see campaigns}, which picks a single "best" match, {@see get_active_list()}
 * returns every story that currently matches - the carousel ticks through all of them,
 * it doesn't pick one winner. There's also no page/category targeting here: placement is
 * fixed to the dashboard, so that dimension campaigns has doesn't apply.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class news {
    /** File API: the component/filearea a story's image is stored under, keyed by the story's own id as itemid. */
    const IMAGE_FILEAREA = 'newsimage';

    /**
     * All stories, lowest sortorder (then oldest id) first - the admin management list.
     *
     * @return \stdClass[]
     */
    public static function get_all(): array {
        global $DB;

        return array_values($DB->get_records('local_communications_news', null, 'sortorder ASC, id ASC'));
    }

    /**
     * @param int $id
     * @return \stdClass|false
     */
    public static function get(int $id) {
        global $DB;

        return $DB->get_record('local_communications_news', ['id' => $id]);
    }

    /**
     * @param \stdClass $data Every local_communications_news column except id/timecreated/timemodified.
     * @return int The new story's id.
     */
    public static function create(\stdClass $data): int {
        global $DB, $USER;

        $data->timecreated = time();
        $data->timemodified = $data->timecreated;
        $data->usermodified = $USER->id;

        return $DB->insert_record('local_communications_news', $data);
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

        $DB->update_record('local_communications_news', $data);
    }

    /**
     * Removes the story and its image.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;

        get_file_storage()->delete_area_files(
            \context_system::instance()->id,
            'local_communications',
            self::IMAGE_FILEAREA,
            $id
        );
        $DB->delete_records('local_communications_news', ['id' => $id]);
    }

    /**
     * Flips a story's enabled flag - the manage list's quick toggle action.
     *
     * @param int $id
     */
    public static function toggle(int $id): void {
        global $DB, $USER;

        $story = $DB->get_record('local_communications_news', ['id' => $id], '*', MUST_EXIST);
        $DB->update_record('local_communications_news', (object) [
            'id' => $id,
            'enabled' => $story->enabled ? 0 : 1,
            'timemodified' => time(),
            'usermodified' => $USER->id,
        ]);
    }

    /**
     * Every enabled story currently in its date window and matching this user's
     * role/cohort/user targeting, in carousel order (sortorder then id). Every targeting
     * dimension left empty on a story imposes no restriction, matching campaigns' and
     * categories' existing "empty = don't filter" convention.
     *
     * @param \stdClass $user
     * @return \stdClass[]
     */
    public static function get_active_list(\stdClass $user): array {
        global $DB;

        $now = time();
        $matches = [];

        $stories = $DB->get_records('local_communications_news', ['enabled' => 1], 'sortorder ASC, id ASC');
        foreach ($stories as $story) {
            if ($story->starttime && $story->starttime > $now) {
                continue;
            }
            if ($story->endtime && $story->endtime < $now) {
                continue;
            }
            if (!self::matches_role($story, $user)) {
                continue;
            }
            if (!self::matches_cohort($story, $user)) {
                continue;
            }
            if (!self::matches_user($story, $user)) {
                continue;
            }

            $matches[] = $story;
        }

        return $matches;
    }

    /**
     * The story's image, if it has one uploaded.
     *
     * @param \stdClass $story
     * @return \moodle_url|null
     */
    public static function image_url(\stdClass $story): ?\moodle_url {
        $fs = get_file_storage();
        $context = \context_system::instance();

        $files = $fs->get_area_files(
            $context->id,
            'local_communications',
            self::IMAGE_FILEAREA,
            $story->id,
            'itemid, filepath, filename',
            false
        );
        $file = reset($files);
        if (!$file) {
            return null;
        }

        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_communications',
            self::IMAGE_FILEAREA,
            $story->id,
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * A short human-readable summary of a story's targeting, for the manage list.
     *
     * @param \stdClass $story
     * @return string
     */
    public static function describe_targeting(\stdClass $story): string {
        global $DB;

        $parts = [];

        $roles = self::parse_csv($story->targetroles ?? '');
        if ($roles) {
            $parts[] = get_string('targetsummary_roles', 'local_communications', implode(', ', $roles));
        }

        if (!empty($story->targetcohortid)) {
            $cohortname = $DB->get_field('cohort', 'name', ['id' => $story->targetcohortid]);
            $parts[] = get_string('targetsummary_cohort', 'local_communications', $cohortname ?: '#' . $story->targetcohortid);
        }

        $userids = self::parse_int_list($story->targetuserids ?? '');
        if ($userids) {
            $parts[] = get_string('targetsummary_users', 'local_communications', count($userids));
        }

        return $parts ? implode('; ', $parts) : get_string('news_targetsummary_everyone', 'local_communications');
    }

    /**
     * @param \stdClass $story
     * @param \stdClass $user
     * @return bool
     */
    protected static function matches_role(\stdClass $story, \stdClass $user): bool {
        $wanted = self::parse_csv($story->targetroles ?? '');
        if (!$wanted) {
            return true;
        }

        $roles = get_user_roles(\context_system::instance(), $user->id, true);
        foreach ($roles as $role) {
            if (in_array($role->shortname, $wanted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \stdClass $story
     * @param \stdClass $user
     * @return bool
     */
    protected static function matches_cohort(\stdClass $story, \stdClass $user): bool {
        if (empty($story->targetcohortid)) {
            return true;
        }

        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        return cohort_is_member($story->targetcohortid, $user->id);
    }

    /**
     * @param \stdClass $story
     * @param \stdClass $user
     * @return bool
     */
    protected static function matches_user(\stdClass $story, \stdClass $user): bool {
        $wanted = self::parse_int_list($story->targetuserids ?? '');
        if (!$wanted) {
            return true;
        }

        return in_array((int) $user->id, $wanted, true);
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
