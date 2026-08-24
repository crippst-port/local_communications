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

namespace local_communications\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Create/edit form for a dashboard news story.
 *
 * The image itself is handled outside this form (see edit_news.php's use of
 * file_prepare_standard_filemanager()/file_postupdate_standard_filemanager()) - this
 * class only declares the 'image' filemanager element and leaves storing its draft
 * area to the caller, the same division of responsibility formslib expects.
 *
 * @package     local_communications
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class news_form extends \moodleform {

    /**
     * File manager options for the story image - a single image file.
     *
     * @return array
     */
    public static function image_options(): array {
        return [
            'maxfiles' => 1,
            'subdirs' => 0,
            'accepted_types' => ['web_image'],
        ];
    }

    /**
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('news_title', 'local_communications'), ['maxlength' => 255]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'description',
            get_string('news_description', 'local_communications'),
            ['rows' => 3, 'cols' => 40]
        );
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'news_description', 'local_communications');

        $mform->addElement(
            'filemanager', 'image', get_string('news_image', 'local_communications'), null, self::image_options()
        );
        $mform->addHelpButton('image', 'news_image', 'local_communications');

        $mform->addElement('text', 'linkurl', get_string('news_link', 'local_communications'), ['maxlength' => 2048]);
        $mform->setType('linkurl', PARAM_URL);
        $mform->addHelpButton('linkurl', 'news_link', 'local_communications');

        $mform->addElement('advcheckbox', 'enabled', get_string('news_enabled', 'local_communications'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('text', 'sortorder', get_string('news_sortorder', 'local_communications'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $mform->addHelpButton('sortorder', 'news_sortorder', 'local_communications');

        $mform->addElement(
            'date_time_selector',
            'starttime',
            get_string('campaign_starttime', 'local_communications'),
            ['optional' => true]
        );

        $mform->addElement(
            'date_time_selector',
            'endtime',
            get_string('campaign_endtime', 'local_communications'),
            ['optional' => true]
        );

        $mform->addElement('header', 'targetingheader', get_string('campaign_targetingheader', 'local_communications'));

        $roleoptions = [];
        foreach (get_all_roles() as $role) {
            $roleoptions[$role->shortname] = role_get_name($role, \context_system::instance());
        }
        asort($roleoptions);
        $mform->addElement(
            'select',
            'targetroles',
            get_string('campaign_roles', 'local_communications'),
            $roleoptions,
            ['multiple' => true, 'size' => min(6, count($roleoptions))]
        );
        $mform->addHelpButton('targetroles', 'campaign_roles', 'local_communications');

        global $DB;
        $cohortoptions = [0 => get_string('campaign_cohort_none', 'local_communications')];
        $cohortoptions += $DB->get_records_menu('cohort', null, 'name', 'id, name');
        $mform->addElement('select', 'targetcohortid', get_string('campaign_cohort', 'local_communications'), $cohortoptions);

        $mform->addElement(
            'textarea',
            'targetuserids',
            get_string('campaign_users', 'local_communications'),
            ['rows' => 4, 'cols' => 40]
        );
        $mform->addHelpButton('targetuserids', 'campaign_users', 'local_communications');

        $this->add_action_buttons();
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['starttime']) && !empty($data['endtime']) && $data['endtime'] < $data['starttime']) {
            $errors['endtime'] = get_string('campaign_error_daterange', 'local_communications');
        }

        if (trim((string) $data['targetuserids']) !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $data['targetuserids']) as $line) {
                $line = trim($line);
                if ($line !== '' && !ctype_digit($line)) {
                    $errors['targetuserids'] = get_string('campaign_error_userids', 'local_communications');
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Pre-fills the form from a stored story record, converting its CSV targetroles
     * column into the array shape the multi-select element expects. The image itself
     * isn't included - see this class's docblock.
     *
     * @param \stdClass $record
     */
    public function set_data_from_record(\stdClass $record): void {
        $data = clone $record;
        $data->targetroles = array_filter(array_map('trim', explode(',', (string) ($record->targetroles ?? ''))));

        $this->set_data($data);
    }

    /**
     * Converts submitted form data back into the newline/CSV text format
     * local_communications_news actually stores. The image itself isn't included -
     * see this class's docblock.
     *
     * @return \stdClass
     */
    public function get_submitted_record(): \stdClass {
        $data = $this->get_data();

        $record = new \stdClass();
        $record->id = (int) $data->id;
        $record->title = trim($data->title);
        $record->description = trim((string) $data->description);
        $record->linkurl = trim((string) $data->linkurl);
        $record->enabled = $data->enabled ? 1 : 0;
        $record->sortorder = (int) $data->sortorder;
        $record->starttime = (int) $data->starttime;
        $record->endtime = (int) $data->endtime;
        $record->targetroles = implode(',', array_filter((array) $data->targetroles));
        $record->targetcohortid = (int) $data->targetcohortid;
        $record->targetuserids = trim((string) $data->targetuserids);

        return $record;
    }
}
