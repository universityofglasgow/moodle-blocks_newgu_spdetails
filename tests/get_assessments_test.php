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

namespace blocks\newgu_spdetails;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once('config.php');
require_once($CFG->dirroot .'/blocks/moodleblock.class.php');
require_once($CFG->dirroot .'/blocks/newgu_spdetails/classes/external.php');

class get_assessments_test extends advanced_testcase {
    
    /**
     * @var object $course
     */
    protected $course;

    /**
     * @var object $teacher
     */
    protected $teacher;

    /**
     * @var object $student
     */
    protected $student;

    /**
     * Add assignment grade
     * @param int $assignid
     * @param int $studentid
     * @param float $gradeval
     */
    protected function add_assignment_grade(int $assignid, int $studentid, float $gradeval) {
        global $USER, $DB;

        $submission = new \stdClass();
        $submission->assignment = $assignid;
        $submission->userid = $studentid;
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->latest = 0;
        $submission->attemptnumber = 0;
        $submission->groupid = 0;
        $submission->timecreated = time();
        $submission->timemodified = time();
        $DB->insert_record('assign_submission', $submission);

        $grade = new \stdClass();
        $grade->assignment = $assignid;
        $grade->userid = $studentid;
        $grade->timecreated = time();
        $grade->timemodified = time();
        $grade->grader = $USER->id;
        $grade->grade = $gradeval;
        $grade->attemptnumber = 0;
        $DB->insert_record('assign_grades', $grade);
    }
    
    /**
     * Set up our test conditions...
     * @return void
     * @throws dml_exception
     */
    public function setUp(): void
    {
        global $DB;

        $this->resetAfterTest(true);

        $lib = new \block_newgu_spdetails_external();
        $this->lib = $lib;

        // Create a "current" course...
        $lastmonth = mktime(0, 0, 0, date("m")-1, date("d"), date("Y"));
        $nextyear  = mktime(0, 0, 0, date("m"), date("d"), date("Y")+1);
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Student Dashboard Test Course - Current', 
            'shortname' => 'SDTC-C',
            'startdate' => $lastmonth,
            'enddate' => $nextyear
        ]);

        // Add some grading categories..
        $gradecategory = $this->getDataGenerator()->create_grade_category(['fullname' => '?', 'courseid' => $course->id]);
        $summativecategory = $this->getDataGenerator()->create_grade_category(['fullname' => 'Summative category', 'courseid' => $course->id, 'parent' => $gradecategory->id]);
        $subcategory = $this->getDataGenerator()->create_grade_category(['fullname' => 'Summative sub category', 'courseid' => $course->id, 'parent' => $summativecategory->id]);
        $formativecategory = $this->getDataGenerator()->create_grade_category(['fullname' => 'Formative category', 'courseid' => $course->id, 'parent' => $gradecategory->id]);
        
        // Set up and enrol a teacher...
        $teacher = $this->getDataGenerator()->create_user(['email' => 'teacher1@example.co.uk', 'username' => 'teacher1']);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $this->get_roleid('editingteacher'));
        
        // Set up and enrol a student...
        $student1 = $this->getDataGenerator()->create_user(['email' => 'student1@example.co.uk', 'username' => 'student1']);
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $this->get_roleid());

        // This will be the logged in user to begin with.
        $this->setUser($student1);

        // Create some "gradeable" activities...
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        // $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        // $survey = $this->getDataGenerator()->create_module('survey', ['course' => $course->id]);
        // $wiki = $this->getDataGenerator()->create_module('wiki', ['course' => $course->id]);
        // $workshop = $this->getDataGenerator()->create_module('workshop', ['course' => $course->id, 'name' => 'A workshop']);
        // $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id,'grade_forum' => 100]);

        // Create some "gradeable" items...
        $assessmentitem1 = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Assessment 1',
            'courseid' => $course->id,
            'categoryid' => $subcategory->id,
            'grademax' => 50.0,
            'iteminstance' => $assignment->id
        ]);

        $assessmentitem2 = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Assessment 2(i)',
            'courseid' => $course->id,
            'categoryid' => $subcategory->id,
            'grademax' => 20.0,
            'iteminstance' => $assignment->id
        ]);

        $assessmentitem3 = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Assessment 2(ii)',
            'courseid' => $course->id,
            'categoryid' => $subcategory->id,
            'grademax' => 30.0,
            'iteminstance' => $assignment->id
        ]);

        $subcategoryitem = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'category',
            'courseid' => $course->id,
            'iteminstance' => $subcategory->id,
            'gradetype' => 2,
            'aggregationcoef' => 0.75000
        ]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'quiz',
        //     'courseid' => $course->id,
        //     'categoryid' => $summativecategory->id,
        //     'iteminstance' => $quiz->id
        // ]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'survey',
        //     'courseid' => $course->id,
        //     'categoryid' => $summativecategory->id,
        //     'iteminstance' => $survey->id
        // ]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'wiki',
        //     'courseid' => $course->id,
        //     'categoryid' => $summativecategory->id,
        //     'iteminstance' => $wiki->id
        // ]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'workshop',
        //     'courseid' => $course->id,
        //     'categoryid' => $summativecategory->id,
        //     'iteminstance' => $workshop->id
        // ]);

        // $this->getDataGenerator()->create_grade_item([
        //         'itemtype' => 'mod',
        //         'itemmodule' => 'forum',
        //         'courseid' => $course->id,
        //         'categoryid' => $summativecategory->id,
        //         'iteminstance' => $forum->id,
        //         'itemnumber' => 0
        // ]);

        // Create a "past" course for the test student(s).
        $tmp_course = $this->getDataGenerator()->create_course(['fullname' => 'Student Dashboard Test Course - Past']);
        $course_past = $DB->get_record('course', ['id' => $tmp_course->id], '*', MUST_EXIST);
        $pastdate = strtotime("last Monday");
        $course_past->enddate = $pastdate;
        $DB->update_record('course', $course_past);
        $this->getDataGenerator()->enrol_user($student1->id, $course_past->id, $this->get_roleid());
        $this->course_past = $course_past;

        $category_past = $this->getDataGenerator()->create_grade_category(['fullname' => '?', 'courseid' => $course_past->id]);
        $summativecategory_past = $this->getDataGenerator()->create_grade_category(['fullname' => 'Summative category - past', 'courseid' => $course->id, 'parent' => $category_past->id]);

        $assignment_past = $this->getDataGenerator()->create_module('assign', ['course' => $course_past->id]);
        // $quiz_past = $this->getDataGenerator()->create_module('quiz', ['course' => $course_past->id]);
        // $survey_past = $this->getDataGenerator()->create_module('survey', ['course' => $course_past->id]);
        // $wiki_past = $this->getDataGenerator()->create_module('wiki', ['course' => $course_past->id]);
        // $workshop_past = $this->getDataGenerator()->create_module('workshop', ['course' => $course_past->id]);
        // $forum_past = $this->getDataGenerator()->create_module('forum', ['course' => $course_past->id, 'grade_forum' => 100]);

        $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Past Assessment 1',
            'courseid' => $course_past->id,
            'categoryid' => $summativecategory_past->id,
            'grademax' => 30.0,
            'iteminstance' => $assignment_past->id
        ]);

        // Add an assignment grade.
        $this->add_assignment_grade($assignment_past->id, $student1->id, 95.5);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'quiz',
        //     'courseid' => $course_past->id,
        //     'categoryid' => $summativecategory_past->id,
        //     'iteminstance' => $quiz_past->id
        // ]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'survey',
        //     'courseid' => $course_past->id,
        //     'categoryid' => $summativecategory_past->id,
        //     'iteminstance' => $survey_past->id
        // ]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'wiki',
        //     'courseid' => $course_past->id,
        //     'categoryid' => $summativecategory_past->id,
        //     'iteminstance' => $wiki_past->id
        // ]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'workshop',
        //     'courseid' => $course_past->id,
        //     'categoryid' => $summativecategory_past->id,
        //     'iteminstance' => $workshop_past->id
        // ]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'forum',
        //     'courseid' => $course_past->id,
        //     'iteminstance' => $forum_past->id,
        //     'itemnumber' => 0
        // ]);

        $this->student1 = $student1;
        $this->teacher = $teacher;

        $this->course = $course;
        $this->summativecategory = $summativecategory;
        $this->formativecategory = $formativecategory;
        $this->subcategory = $subcategory;
        $this->assignment = $assignment;
        $this->assessmentitem1 = $assessmentitem1;
        $this->assessmentitem2 = $assessmentitem2;
        $this->assessmentitem3 = $assessmentitem3;
        // $this->survey = $survey;
        // $this->wiki = $wiki;
        // $this->workshop = $workshop;
        // $this->forum = $forum;

        $this->course_past = $course_past;
        $this->summativecategory_past = $summativecategory_past;
        $this->assignment_past = $assignment_past;
        // $this->survey_past = $survey_past;
        // $this->wiki_past = $wiki_past;
        // $this->workshop_past = $workshop_past;
        // $this->forum_past = $forum_past;
    }

    /**
     * Utility function to provide the roleId
     *
     * @param $archetype
     * @return mixed
     * @throws dml_exception
     */
    public function get_roleid($archetype = 'student') {
        global $DB;

        $role = $DB->get_record("role", ['archetype' => $archetype]);
        return $role->id;
    }

    /**
     * Test that only current courses are returned.
     */
    public function test_retrieve_gradeable_activities_current_courses() {
        $userid = $this->student1->id;
        $sortorder = 'asc';
        $subcategoryid = $this->subcategory->id;
        $coursetype = 'gcatenabled';
        $returned = $this->lib->retrieve_gradable_activities(null, $userid, null, $sortorder, $subcategoryid, $coursetype);

        $this->assertIsArray($returned);
        $this->assertArrayHasKey('coursedata',$returned);
        $this->assertCount(3, $returned['coursedata']['assessmentitems']);
    }

    /**
     * Test that only past courses are returned.
     */
    public function test_retrieve_gradeable_activities_past_courses() {
        $userid = $this->student1->id;
        $sortorder = 'asc';
        $subcategoryid = $this->summativecategory_past->id;
        $coursetype = 'gcatenabled';
        $returned = $this->lib->retrieve_gradable_activities(null, $userid, null, $sortorder, $subcategoryid, $coursetype);

        $this->assertIsArray($returned);
        $this->assertArrayHasKey('coursedata',$returned);
        $this->assertCount(1, $returned['coursedata']['assessmentitems']);
    }

    /**
     * 
     */
    public function test_return_course_components() {
        $returned = $this->lib->return_course_components($this->course, true);

        $this->assertIsArray($returned);
        $this->assertArrayHasKey('coursedata',$returned);
        $this->assertIsString($returned['coursedata']['coursename']);
        $this->assertEquals($this->course->fullname, $returned['coursedata']['coursename']);
        $this->assertEquals($this->course->startdate, $returned['coursedata']['startdate']);
        $this->assertEquals($this->course->enddate, $returned['coursedata']['enddate']);

        $this->assertIsArray($returned['subcategories']);
        $this->assertArrayHasKey('coursedata',$returned);
        $this->assertEquals($this->subcategory->fullname, $returned['subcategories'][0]['name']);
        $this->assertEquals($this->subcategory->assessmenttype, $returned['subcategories'][0]['assessmenttype']);
        $this->assertEquals($this->subcategory->subcatweight, $returned['subcategories'][0]['subcatweight']);
        $this->assertEquals($this->subcategory->coursetype, $returned['subcategories'][0]['coursetype']);
    }

    /**
     * 
     */
    public function test_return_assessmenttype() {
        $lang = 'block_newgu_spdetails';

        $expected1 = get_string("formative", $lang);
        $expected2 = get_string("summative", $lang);
        $expected3 = get_string("emptyvalue", $lang);

        $this->assertEquals($expected1, $this->lib->return_assessmenttype("12312 formative", 0));
        $this->assertEquals($expected2, $this->lib->return_assessmenttype("12312 summative", 1));
        $this->assertEquals($expected3, $this->lib->return_assessmenttype("123123 summative", 0));
        $this->assertEquals($expected3, $this->lib->return_assessmenttype(time(), 0));
    }

    /**
     * Test that the correct weighting for a given course 'type' is returned.
     */
    public function test_return_weight() {
        $lang = 'block_newgu_spdetails';
        $assessmenttype = get_string('summative', $lang);
        $aggregation = '10';
        $aggregationcoef = 2;
        $aggregationcoef2 = 0;
        $weight = ($aggregation == '10') ? (($aggregationcoef > 1) ? $aggregationcoef : $aggregationcoef * 100) :
                      $aggregationcoef2 * 100;

        $expected1 = round($aggregationcoef, 2).'%';
        $this->assertEquals($expected1, $this->lib->return_weight($assessmenttype, $aggregation,
                                                                  $aggregationcoef, $aggregationcoef2, ""));

        $aggregationcoef = 1;
        $expected2 = round($aggregationcoef * 100, 2).'%';
        $this->assertEquals($expected2, $this->lib->return_weight($assessmenttype, $aggregation,
                                                                  $aggregationcoef, $aggregationcoef2, ""));

        $aggregation = '1';
        $expected3 = '100%';
        $this->assertEquals($expected3, $this->lib->return_weight($assessmenttype, $aggregation,
                                                                  $aggregationcoef, $aggregationcoef2, ""));

        $aggregationcoef = 0;
        $expected4 = '—';
        $this->assertEquals($expected4, $this->lib->return_weight($assessmenttype, $aggregation,
                                                                $aggregationcoef, $aggregationcoef2, ""));
    }

    public function test_return_gradestatus() {

    }

    public function test_return_gradefeedback() {

    }
}