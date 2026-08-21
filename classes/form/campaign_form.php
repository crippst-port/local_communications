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

namespace local_feedback\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/course/lib.php'); // For get_module_types_names().

/**
 * Create/edit form for a feedback campaign.
 *
 * Storage format vs form format differ for the multi-valued targeting fields (newline/CSV
 * text columns vs multi-select/autocomplete arrays, and - for page targeting - a bank of
 * individual "page_<key>" checkboxes plus an "allactivities" checkbox/"activityids" picker
 * pair, all standing in for entries in the stored pagetypepatterns list) -
 * {@see set_data_from_record()} and {@see get_submitted_record()} are the two conversion
 * points, so the rest of the plugin (campaigns::get_active_for_context() and friends) only
 * ever deals with the stored newline/CSV text format, unaware these friendlier controls exist.
 *
 * @package     local_feedback
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_form extends \moodleform {

    /**
     * Friendly checkbox key => the $PAGE->pagetype pattern it targets, confirmed against
     * core (my/index.php, index.php, course/view.php, course/index.php, user/index.php,
     * user/profile.php - grade report pages fall back to Moodle's default path-derived
     * pagetype since none of them call set_pagetype() explicitly).
     */
    protected const KEY_AREAS = [
        'dashboard' => 'my-index',
        'sitehome' => 'site-index',
        'coursepage' => 'course-view-*',
        'courselisting' => 'course-index-category',
        'participants' => 'course-view-participants',
        'grades' => 'grade-report-*',
        'profile' => 'user-profile',
    ];

    /** @var string The single pattern "target all activity types" contributes - covers every current and future activity type in one go, rather than enumerating each installed modname. */
    protected const ALL_ACTIVITIES_PATTERN = 'mod-*';

    /**
     * The pattern picking one specific activity type in the picker contributes - matches
     * every page under that activity (view, plus e.g. mod-quiz-attempt/-review), not just
     * its main view page, since Moodle derives mod pagetypes straight from the script path.
     *
     * @param string $modname
     * @return string
     */
    protected static function activity_pattern(string $modname): string {
        return 'mod-' . $modname . '-*';
    }

    /**
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('campaign_name', 'local_feedback'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('advcheckbox', 'enabled', get_string('campaign_enabled', 'local_feedback'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('advcheckbox', 'coursefocused', get_string('campaign_coursefocused', 'local_feedback'));
        $mform->setDefault('coursefocused', 1);
        $mform->addHelpButton('coursefocused', 'campaign_coursefocused', 'local_feedback');

        $mform->addElement('text', 'priority', get_string('campaign_priority', 'local_feedback'));
        $mform->setType('priority', PARAM_INT);
        $mform->setDefault('priority', 0);
        $mform->addHelpButton('priority', 'campaign_priority', 'local_feedback');

        $responselimitoptions = [
            'none' => get_string('campaign_responselimit_none', 'local_feedback'),
            'daily' => get_string('campaign_responselimit_daily', 'local_feedback'),
            'once' => get_string('campaign_responselimit_once', 'local_feedback'),
        ];
        $mform->addElement(
            'select', 'responselimit', get_string('campaign_responselimit', 'local_feedback'), $responselimitoptions
        );
        $mform->setDefault('responselimit', 'none');
        $mform->addHelpButton('responselimit', 'campaign_responselimit', 'local_feedback');

        $mform->addElement('text', 'maxresponses', get_string('campaign_maxresponses', 'local_feedback'), ['size' => 6]);
        $mform->setType('maxresponses', PARAM_INT);
        $mform->setDefault('maxresponses', 0);
        $mform->addHelpButton('maxresponses', 'campaign_maxresponses', 'local_feedback');

        $mform->addElement(
            'date_time_selector',
            'starttime',
            get_string('campaign_starttime', 'local_feedback'),
            ['optional' => true]
        );

        $mform->addElement(
            'date_time_selector',
            'endtime',
            get_string('campaign_endtime', 'local_feedback'),
            ['optional' => true]
        );

        $mform->addElement(
            'text',
            'modaltitle',
            get_string('campaign_modaltitle', 'local_feedback'),
            ['maxlength' => 255]
        );
        $mform->setType('modaltitle', PARAM_TEXT);
        $mform->addHelpButton('modaltitle', 'campaign_modaltitle', 'local_feedback');

        $mform->addElement(
            'textarea',
            'introtext',
            get_string('campaign_introtext', 'local_feedback'),
            ['rows' => 3, 'cols' => 40]
        );
        $mform->setType('introtext', PARAM_TEXT);
        $mform->addHelpButton('introtext', 'campaign_introtext', 'local_feedback');

        // Each input's own placeholder shows the site default it falls back to, so the
        // admin can see what they're overriding without leaving the form.
        $labelsgroup = [
            $mform->createElement('text', 'labelhappy', '', [
                'size' => 12, 'placeholder' => get_string('sentiment_happy', 'local_feedback'),
            ]),
            $mform->createElement('text', 'labelneutral', '', [
                'size' => 12, 'placeholder' => get_string('sentiment_neutral', 'local_feedback'),
            ]),
            $mform->createElement('text', 'labelsad', '', [
                'size' => 12, 'placeholder' => get_string('sentiment_sad', 'local_feedback'),
            ]),
        ];
        foreach (['labelhappy', 'labelneutral', 'labelsad'] as $labelfield) {
            $mform->setType($labelfield, PARAM_TEXT);
        }
        $mform->addGroup(
            $labelsgroup, 'labelsgroup', get_string('campaign_sentimentlabels', 'local_feedback'), ['&nbsp;&nbsp;'], false
        );
        $mform->addHelpButton('labelsgroup', 'campaign_sentimentlabels', 'local_feedback');

        $mform->addElement(
            'textarea',
            'topics',
            get_string('campaign_topics', 'local_feedback'),
            ['rows' => 4, 'cols' => 40]
        );
        $mform->addHelpButton('topics', 'campaign_topics', 'local_feedback');

        $mform->addElement('advcheckbox', 'skiptopicstep', get_string('campaign_skiptopicstep', 'local_feedback'));
        $mform->addHelpButton('skiptopicstep', 'campaign_skiptopicstep', 'local_feedback');
        $mform->hideIf('topics', 'skiptopicstep', 'checked');

        $mform->addElement('header', 'targetingheader', get_string('campaign_targetingheader', 'local_feedback'));

        // A searchable tag picker rather than a plain multi-select: sites can easily have
        // hundreds of categories, and scrolling a native listbox to ctrl/cmd-click through
        // them doesn't scale the way typing to filter does.
        $categoryoptions = \core_course_category::make_categories_list();
        $mform->addElement(
            'autocomplete',
            'categoryids',
            get_string('campaign_categories', 'local_feedback'),
            $categoryoptions,
            ['multiple' => true]
        );
        $mform->addHelpButton('categoryids', 'campaign_categories', 'local_feedback');

        $areagroup = [];
        foreach (array_keys(self::KEY_AREAS) as $key) {
            $areagroup[] = $mform->createElement(
                'advcheckbox', 'page_' . $key, '', get_string('campaign_page_' . $key, 'local_feedback')
            );
        }
        $mform->addGroup(
            $areagroup, 'pagesgroup', get_string('campaign_keyareas', 'local_feedback'), ['&nbsp;&nbsp;&nbsp;&nbsp;'], false
        );
        $mform->addHelpButton('pagesgroup', 'campaign_keyareas', 'local_feedback');

        $mform->addElement('advcheckbox', 'allactivities', get_string('campaign_allactivities', 'local_feedback'));
        $mform->addHelpButton('allactivities', 'campaign_allactivities', 'local_feedback');

        $activitynames = get_module_types_names();
        asort($activitynames);
        $mform->addElement(
            'autocomplete',
            'activityids',
            get_string('campaign_activities', 'local_feedback'),
            $activitynames,
            ['multiple' => true]
        );
        $mform->addHelpButton('activityids', 'campaign_activities', 'local_feedback');
        $mform->hideIf('activityids', 'allactivities', 'checked');

        $mform->addElement(
            'textarea',
            'pagetypepatterns',
            get_string('campaign_pagetypes', 'local_feedback'),
            ['rows' => 3, 'cols' => 40]
        );
        $mform->addHelpButton('pagetypepatterns', 'campaign_pagetypes', 'local_feedback');

        $roleoptions = [];
        foreach (get_all_roles() as $role) {
            $roleoptions[$role->shortname] = role_get_name($role, \context_system::instance());
        }
        asort($roleoptions);
        $mform->addElement(
            'select',
            'targetroles',
            get_string('campaign_roles', 'local_feedback'),
            $roleoptions,
            ['multiple' => true, 'size' => min(6, count($roleoptions))]
        );
        $mform->addHelpButton('targetroles', 'campaign_roles', 'local_feedback');

        global $DB;
        $cohortoptions = [0 => get_string('campaign_cohort_none', 'local_feedback')];
        $cohortoptions += $DB->get_records_menu('cohort', null, 'name', 'id, name');
        $mform->addElement('select', 'targetcohortid', get_string('campaign_cohort', 'local_feedback'), $cohortoptions);

        $mform->addElement(
            'textarea',
            'targetuserids',
            get_string('campaign_users', 'local_feedback'),
            ['rows' => 4, 'cols' => 40]
        );
        $mform->addHelpButton('targetuserids', 'campaign_users', 'local_feedback');

        $this->add_action_buttons();
    }

    /**
     * @param \stdClass $data
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['starttime']) && !empty($data['endtime']) && $data['endtime'] < $data['starttime']) {
            $errors['endtime'] = get_string('campaign_error_daterange', 'local_feedback');
        }

        if ((string) $data['maxresponses'] !== '' && (int) $data['maxresponses'] < 0) {
            $errors['maxresponses'] = get_string('campaign_error_maxresponses', 'local_feedback');
        }

        if (trim((string) $data['targetuserids']) !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $data['targetuserids']) as $line) {
                $line = trim($line);
                if ($line !== '' && !ctype_digit($line)) {
                    $errors['targetuserids'] = get_string('campaign_error_userids', 'local_feedback');
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Pre-fills the form from a stored campaign record, converting its newline/CSV
     * text columns into the array shape the multi-select elements expect.
     *
     * @param \stdClass $record
     */
    public function set_data_from_record(\stdClass $record): void {
        $data = clone $record;
        $data->categoryids = array_filter(array_map('intval', preg_split(
            '/\r\n|\r|\n/', (string) ($record->categoryids ?? '')
        )));
        $data->targetroles = array_filter(array_map('trim', explode(',', (string) ($record->targetroles ?? ''))));

        // Every stored pattern that matches a known key-area checkbox, the all-activities
        // pattern, or one specific installed activity type is pulled out into that control
        // instead of appearing in the free-text field; anything left over (custom patterns,
        // or ones from an activity type since uninstalled) stays there verbatim - no data
        // is ever silently dropped.
        $remaining = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($record->pagetypepatterns ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $remaining[] = $line;
            }
        }

        foreach (self::KEY_AREAS as $key => $pattern) {
            $index = array_search($pattern, $remaining, true);
            $data->{'page_' . $key} = $index !== false ? 1 : 0;
            if ($index !== false) {
                unset($remaining[$index]);
            }
        }

        $allindex = array_search(self::ALL_ACTIVITIES_PATTERN, $remaining, true);
        $data->allactivities = $allindex !== false ? 1 : 0;
        if ($allindex !== false) {
            unset($remaining[$allindex]);
        }

        $data->activityids = [];
        foreach (array_keys(get_module_types_names()) as $modname) {
            $index = array_search(self::activity_pattern($modname), $remaining, true);
            if ($index !== false) {
                $data->activityids[] = $modname;
                unset($remaining[$index]);
            }
        }

        $data->pagetypepatterns = implode("\n", $remaining);

        $this->set_data($data);
    }

    /**
     * Converts submitted form data back into the newline/CSV text format
     * local_feedback_campaigns actually stores.
     *
     * @return \stdClass
     */
    public function get_submitted_record(): \stdClass {
        $data = $this->get_data();

        $patterns = [];
        foreach (self::KEY_AREAS as $key => $pattern) {
            if (!empty($data->{'page_' . $key})) {
                $patterns[] = $pattern;
            }
        }
        if (!empty($data->allactivities)) {
            // Covers every activity type in one pattern, including ones installed later -
            // the individual picker is redundant (and hidden client-side) once this is on.
            $patterns[] = self::ALL_ACTIVITIES_PATTERN;
        } else {
            foreach ((array) $data->activityids as $modname) {
                $patterns[] = self::activity_pattern($modname);
            }
        }
        foreach (preg_split('/\r\n|\r|\n/', (string) $data->pagetypepatterns) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $patterns[] = $line;
            }
        }

        $record = new \stdClass();
        $record->id = (int) $data->id;
        $record->name = trim($data->name);
        $record->modaltitle = trim((string) $data->modaltitle);
        $record->introtext = trim((string) $data->introtext);
        $record->labelhappy = trim((string) $data->labelhappy);
        $record->labelneutral = trim((string) $data->labelneutral);
        $record->labelsad = trim((string) $data->labelsad);
        $record->enabled = $data->enabled ? 1 : 0;
        $record->coursefocused = $data->coursefocused ? 1 : 0;
        $record->responselimit = in_array($data->responselimit, ['none', 'daily', 'once'], true) ? $data->responselimit : 'none';
        $record->maxresponses = max(0, (int) $data->maxresponses);
        $record->priority = (int) $data->priority;
        $record->starttime = (int) $data->starttime;
        $record->endtime = (int) $data->endtime;
        $record->topics = trim((string) $data->topics);
        $record->skiptopicstep = $data->skiptopicstep ? 1 : 0;
        $record->categoryids = implode("\n", array_filter((array) $data->categoryids));
        $record->pagetypepatterns = implode("\n", $patterns);
        $record->targetroles = implode(',', array_filter((array) $data->targetroles));
        $record->targetcohortid = (int) $data->targetcohortid;
        $record->targetuserids = trim((string) $data->targetuserids);

        return $record;
    }
}
