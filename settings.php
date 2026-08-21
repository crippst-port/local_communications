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
 * Plugin administration pages are defined here.
 *
 * @package     local_feedback
 * @category    admin
 * @copyright   2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Registered unconditionally (like core report plugins) so that anyone holding
// local/feedback:viewreports can reach it, not only users with moodle/site:config.
// Every report is scoped to one campaign now - manage_campaigns.php (its list of
// campaigns, each linking to its own dashboard) is the front door for that, so this is
// the only page registered here; there's no pooled "every campaign" report any more.
// manage_campaigns.php itself further restricts the create/edit/toggle/delete actions
// to local/feedback:managecampaigns internally - this capability only gates viewing.
$ADMIN->add('reports', new admin_externalpage(
    'local_feedback_campaigns',
    get_string('managecampaigns', 'local_feedback'),
    new moodle_url('/local/feedback/manage_campaigns.php'),
    'local/feedback:viewreports'
));

if ($hassiteconfig) {
    require_once(__DIR__ . '/classes/local/categories.php');
    require_once(__DIR__ . '/classes/table/courses_summary_table.php');

    $settings = new admin_settingpage('local_feedback', get_string('pluginname', 'local_feedback'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_feedback/enabled',
        get_string('enabled', 'local_feedback'),
        get_string('enabled_desc', 'local_feedback'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_feedback/trendwindow',
        get_string('trendwindow_setting', 'local_feedback'),
        get_string('trendwindow_setting_desc', 'local_feedback'),
        \local_feedback\table\courses_summary_table::TREND_WINDOW,
        PARAM_INT,
        3
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_feedback/categories',
        get_string('categories_setting', 'local_feedback'),
        get_string('categories_setting_desc', 'local_feedback'),
        \local_feedback\local\categories::get_default_setting_value()
    ));
}
