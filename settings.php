<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * New GU SP Details
 * @package    block_newgu_spdetails
 * @author     Shubhendra Diophode <shubhendra.doiphode@gmail.com>
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    global $DB;

    $sqlltitypes = "SELECT lt.id, lt.name, lt.course, c.fullname FROM {lti_types} lt, {course} c WHERE lt.course=c.id";
    $arrltitypes = $DB->get_records_sql($sqlltitypes);

    $settings->add(new admin_setting_heading('includeltilabel',
        get_string('includeltilabel', 'block_newgu_spdetails'), ''));

    foreach ($arrltitypes as $keyltitypes) {
        $settings->add(new admin_setting_configcheckbox('block_newgu_spdetails_include_' . $keyltitypes->id,
        $keyltitypes->name, '' , 0));
    }
}
