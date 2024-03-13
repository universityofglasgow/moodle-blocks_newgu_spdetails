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
     * feedback is returned.
     * 
     * For a GCAT type course, the API is called and it should deal 
     * with the return values.
     * 
     * For a MyGrades course - we have the situation where if grades
     * haven't been imported/released, then it defaults to retrieving
     * this from gradebook. These tests should account for this, i.e.
     * we're only dealing with released grades from local_gugrades -
     * there isn't a notion of provisional grades.
     * 
     * For generic Gradebook courses, the data should be coming directly
     * from gradebook.
     */
    public function test_get_grade_status_and_feedback() {
        $userid = $this->student1->id;
        $sortorder = 'asc';

        /**
         * Check these attributes on a MyGrades course
         */
        $mygrades_summative_subcategory2 = $this->mygrades_summative_subcategory2->id;
        $mygrades_graded_items = $this->lib->retrieve_gradable_activities('current', $userid, 'duedate', $sortorder, $mygrades_summative_subcategory2);

        $this->assertIsArray($mygrades_graded_items);
        $this->assertCount(2, $mygrades_graded_items['coursedata']['assessmentitems']);

        // Check for the raw grade/provisional on the first assignment.
        $this->assertArrayHasKey('grade_provisional', $mygrades_graded_items['coursedata']['assessmentitems'][0]);
        $this->assertTrue($mygrades_graded_items['coursedata']['assessmentitems'][0]['grade_provisional']);
        // Check for the feedback.
        $this->assertStringContainsString(get_string('status_text_tobeconfirmed', 'block_newgu_spdetails'), $mygrades_graded_items['coursedata']['assessmentitems'][0]['grade_feedback']);

        // Check for an overridden grade.
        // Check for the feedback.

        // Check for the final grade.
        $this->assertArrayHasKey('grade_class', $mygrades_graded_items['coursedata']['assessmentitems'][1]);
        $this->assertFalse($mygrades_graded_items['coursedata']['assessmentitems'][1]['grade_provisional']);
        // Check for the feedback.
        $this->assertStringContainsString(get_string('status_text_viewfeedback', 'block_newgu_spdetails'), $mygrades_graded_items['coursedata']['assessmentitems'][1]['grade_feedback']);
        
        /** 
         * Check these attributes on a GCAT course
         */
        $gcat_summative_subcategory = $this->gcat_summative_subcategory->id;
        $gcat_graded_items = $this->lib->retrieve_gradable_activities('current', $userid, 'duedate', $sortorder, $gcat_summative_subcategory);
        
        $this->assertIsArray($gcat_graded_items);
        $this->assertCount(3, $gcat_graded_items['coursedata']['assessmentitems']);
        
        // Check for the raw grade/provisional on the first assignment.
        $this->assertArrayHasKey('grade_provisional', $gcat_graded_items['coursedata']['assessmentitems'][0]);
        $this->assertTrue($gcat_graded_items['coursedata']['assessmentitems'][0]['grade_provisional']);
        // Check for the feedback.
        $this->assertStringContainsString(get_string('status_text_tobeconfirmed', 'block_newgu_spdetails'), $gcat_graded_items['coursedata']['assessmentitems'][0]['grade_feedback']);

        // Check for an overridden grade.
        // Check for the feedback.

        // Check for the final grade.
        $this->assertArrayHasKey('grade_class', $gcat_graded_items['coursedata']['assessmentitems'][1]);
        $this->assertFalse($gcat_graded_items['coursedata']['assessmentitems'][1]['grade_provisional']);
        // Check for the feedback.
        $this->assertStringContainsString(get_string('readfeedback', 'block_gu_spdetails'), $gcat_graded_items['coursedata']['assessmentitems'][1]['grade_feedback']);

        /** 
         * Check these attributes on a Gradebook course
         */
        $gradebookcategory = $this->gradebookcategory->id;
        $gradebook_graded_items = $this->lib->retrieve_gradable_activities('current', $userid, 'duedate', $sortorder, $gradebookcategory);

        $this->assertIsArray($gradebook_graded_items);
        $this->assertCount(2, $gradebook_graded_items['coursedata']['assessmentitems']);

        // Check for the raw grade/provisional on the first assignment.
        $this->assertArrayHasKey('grade_provisional', $gradebook_graded_items['coursedata']['assessmentitems'][0]);
        $this->assertTrue($gradebook_graded_items['coursedata']['assessmentitems'][0]['grade_provisional']);
        // Check for the feedback.
        $this->assertStringContainsString(get_string('status_text_tobeconfirmed', 'block_newgu_spdetails'), $gradebook_graded_items['coursedata']['assessmentitems'][0]['grade_feedback']);

        // Check for an overridden grade.
        // Check for the feedback.

        // Check for the final grade.
        $this->assertArrayHasKey('grade_class', $gradebook_graded_items['coursedata']['assessmentitems'][1]);
        $this->assertFalse($gradebook_graded_items['coursedata']['assessmentitems'][1]['grade_provisional']);
        // Check for the feedback.
        $this->assertStringContainsString(get_string('status_text_viewfeedback', 'block_newgu_spdetails'), $gradebook_graded_items['coursedata']['assessmentitems'][1]['grade_feedback']);
    }
    
}
