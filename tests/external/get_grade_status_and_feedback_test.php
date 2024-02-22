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
 * Unit tests for the block_newgu_spdetails class.
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace blocks_newgu_spdetails\external;

 defined('MOODLE_INTERNAL') || die();
 
 global $CFG;
 
 require_once($CFG->dirroot .'/blocks/moodleblock.class.php');
 require_once($CFG->dirroot . '/blocks/newgu_spdetails/tests/external/newgu_spdetails_advanced_testcase.php');
 
 class get_grade_status_and_feedback_test extends \blocks_newgu_spdetails\external\newgu_spdetails_advanced_testcase {
    
    /**
     * Test that for a given assessment, the correct grade, status and 
     * feedback is returned. This is done for each course type.
     */
    public function test_get_grade_status_and_feedback() {
        $userid = $this->student1->id;
        $sortorder = 'asc';

        // Check these attributes on a MyGrades course
        $mygradescategoryid = $this->

        // Check these attributes on a GCAT course
        $summativecategoryid = $this->summativecategory->id;
        $returned = $this->lib->retrieve_gradable_activities('current', $userid, 'duedate', $sortorder, $summativecategoryid);

        // Check these attributes on a Gradebook course
        $tmp = 0;
    }
    
}
