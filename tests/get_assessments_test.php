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
     * 
     * Our tests will need to cover the 3 course types - namely:
     * 1) MyGrades
     * 2) GCAT
     * 3) GradeBook
     * 
     * @return void
     * @throws dml_exception
     */
    public function setUp(): void
    {
        global $DB;

        $this->resetAfterTest(true);

        $lib = new \block_newgu_spdetails_external();
        $this->lib = $lib;

        /** Create a "current" course - this will be a GCAT style course to start... */
        $lastmonth = mktime(0, 0, 0, date("m")-1, date("d"), date("Y"));
        $nextyear  = mktime(0, 0, 0, date("m"), date("d"), date("Y")+1);
        $gcatcourse = $this->getDataGenerator()->create_course([
            'fullname' => 'GCAT 2023 TW - Existing GCAT', 
            'shortname' => 'GCAT2023TWEX',
            'startdate' => $lastmonth,
            'enddate' => $nextyear
        ]);

        // Add some grading categories..
        $summativecategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Summative - Converting Points to 22 point Scale - 25% Course Weighting', 
            'courseid' => $gcatcourse->id, 
            'aggregation' => 10
        ]);

        $summative_subcategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Average of assignments - Sub components - Simple Weighted Mean', 
            'courseid' => $gcatcourse->id, 
            'parent' => $summativecategory->id
        ]);

        $formativecategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Formative activities', 
            'courseid' => $gcatcourse->id, 
            'parent' => $summativecategory->parent
        ]);

        // Howard's API adds some additional field members...
        $gcatcourse->firstlevel[] = [
            'id' => $summativecategory->id,
            'fullname' => $summativecategory->fullname
        ];

        $gcatcourse->firstlevel[] = [
            'id' => $formativecategory->id,
            'fullname' => $formativecategory->fullname
        ];
        $gcatcourse->gugradesenabled = false;
        $gcatcourse->gcatenabled = true;
        $gcatcontext = \context_course::instance($gcatcourse->id);

        // But we also need to mock the "enabled" state in the 
        // customfield_x tables for GCAT type courses.
        $cfcparams = [
            'name' => 'GCAT Options'
        ];
        $this->getDataGenerator()->create_custom_field_category($cfcparams);
        $cfparams = [
            'name' => 'Show assessments on Student Dashboard',
            'shortname' => 'show_on_studentdashboard',
            'type' => 'checkbox',
            'category' => 'GCAT Options'
        ];
        $this->getDataGenerator()->create_custom_field($cfparams);

        
        $sqlshortname = $DB->sql_compare_text('shortname');
        $cfid = $DB->get_field('customfield_field', 'id', ["$sqlshortname" => 'show_on_studentdashboard']);

        // There's no method for creating customfield_data entries so...
        $now  = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        $cfdparams = [
            'fieldid' => $cfid,
            'instanceid' => $gcatcourse->id,
            'intvalue' => 1,
            'value' => 1,
            'valueformat' => 0,
            'contextid' => $gcatcontext->id,
            'timecreated' => $now,
            'timemodified' => $now
        ];
        $DB->insert_record('customfield_data', $cfdparams);

        // We need to assign some roles (and by extension capabilities)...
        
        // Set up, enrol and assign role for a teacher...
        $teacher = $this->getDataGenerator()->create_user(['email' => 'teacher1@example.co.uk', 'username' => 'teacher1']);
        $this->getDataGenerator()->enrol_user($teacher->id, $gcatcourse->id, $this->get_roleid('editingteacher'));
        $this->getDataGenerator()->role_assign('editingteacher', $teacher->id, $gcatcontext);
        
        // Set up, enrol and assign role for a student...
        $student1 = $this->getDataGenerator()->create_user(['email' => 'student1@example.co.uk', 'username' => 'student1']);
        $this->getDataGenerator()->enrol_user($student1->id, $gcatcourse->id, $this->get_roleid());
        $this->getDataGenerator()->role_assign('student', $student1->id, $gcatcontext);

        // This will be the logged in user to begin with.
        $this->setUser($student1);

        // Create some "gradable" activities...
        $assignment1 = $this->getDataGenerator()->create_module('assign', ['name' => 'Assessment 1', 'grade' => 50, 'course' => $gcatcourse->id]);
        $assignment2 = $this->getDataGenerator()->create_module('assign', ['name' => 'Assessment 2(i)', 'grade' => 20,  'course' => $gcatcourse->id]);
        $assignment3 = $this->getDataGenerator()->create_module('assign', ['name' => 'Assessment 2(ii)', 'grade' => 30,  'course' => $gcatcourse->id]);
        $groupassignment1 = $this->getDataGenerator()->create_module('assign', ['name' => 'Group Assessment', 'teamsubmission' => 1, 'course' => $gcatcourse->id]);

        // Create some "gradable" items. Assignments to begin with...
        $assessmentitem1 = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Assessment 1',
            'courseid' => $gcatcourse->id,
            'categoryid' => $summativecategory->id,
            'gradetype' => 1,
            'grademax' => 50.0,
            'iteminstance' => $assignment1->id
        ]);

        $gradeditem1 = $this->add_assignment_grade($assessmentitem1->id, $student1->id, 35);

        $assessmentitem2 = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Assessment 2(i)',
            'courseid' => $gcatcourse->id,
            'categoryid' => $summativecategory->id,
            'gradetype' => 1,
            'grademax' => 20.0,
            'iteminstance' => $assignment2->id
        ]);

        $assessmentitem3 = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Assessment 2(ii)',
            'courseid' => $gcatcourse->id,
            'categoryid' => $summativecategory->id,
            'gradetype' => 1,
            'grademax' => 30.0,
            'iteminstance' => $assignment3->id
        ]);

        $gradeditem2 = $this->add_assignment_grade($assessmentitem3->id, $student1->id, 12.5);

        $groupassessmentitem = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Group Assessment 1',
            'courseid' => $gcatcourse->id,
            'categoryid' => $summative_subcategory->id,
            'gradetype' => 2,
            'grademax' => 23.0,
            'aggregationcoef' => 0.20000,
            'iteminstance' => $groupassignment1->id,
        ]);

        /** Gradebook type course. */
        $gradebookcourse = $this->getDataGenerator()->create_course([
            'fullname' => 'GCAT 2023 TW - Gradebook Grades Only', 
            'shortname' => 'GCAT2023TWGB',
            'startdate' => $lastmonth,
            'enddate' => $nextyear
        ]);
        $gradebookcontext = \context_course::instance($gradebookcourse->id);

        // Add a grading category..
        $gradebookcategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'SPS5022 Oral Presentation 2022-2023', 
            'courseid' => $gradebookcourse->id
        ]);

        $gradebookcourse->firstlevel[] = [
            'id' => $gradebookcategory->id,
            'fullname' => $gradebookcategory->fullname
        ];
        $gradebookcourse->gugradesenabled = false;
        $gradebookcourse->gcatenabled = false;

        // Enrol the teacher...
        $this->getDataGenerator()->enrol_user($teacher->id, $gradebookcourse->id, $this->get_roleid('editingteacher'));
        $this->getDataGenerator()->role_assign('editingteacher', $teacher->id, $gradebookcontext);

        // Enrol the student
        $this->getDataGenerator()->enrol_user($student1->id, $gradebookcourse->id, $this->get_roleid());
        $this->getDataGenerator()->role_assign('student', $student1->id, $gradebookcontext);

        $assignment4 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'SPS5022 Essay - FINAL - Thursday 12th', 
            'grade' => 4, 
            'course' => $gradebookcourse->id
        ]);

        $assessmentitem4 = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Assessment 1',
            'courseid' => $gradebookcourse->id,
            'categoryid' => $gradebookcategory->id,
            'grademax' => 23.00000,
            'iteminstance' => $assignment4->id
        ]);

        /** Create a MyGrades type course */
        $gugradescourse = $this->getDataGenerator()->create_course([
            'fullname' => 'GCAT 2023 TW - New GCAT', 
            'shortname' => 'GCAT2023TWNW',
            'startdate' => $lastmonth,
            'enddate' => $nextyear
        ]);

        // We also need to mock "enable" this as a MyGrades type course.
        $gugradesparams = [
            'courseid' => $gugradescourse->id,
            'name' => 'enabledashboard',
            'value' => 1
        ];
        $DB->insert_record('local_gugrades_config', $gugradesparams);

        // Add some grading categories..
        $gugrades_summativecategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Summative Assessments', 
            'courseid' => $gugradescourse->id, 
            'aggregation' => 10
        ]);
        $gugrades_subcategory = $this->getDataGenerator()->create_grade_category(['fullname' => 'WM Grade Category with Resits (10%)', 'courseid' => $gugradescourse->id, 'parent' => $gugrades_summativecategory->id]);
        $gugrades_formativecategory = $this->getDataGenerator()->create_grade_category(['fullname' => 'Sub-Category B Assignments (Resits - highest grade)', 'courseid' => $gugradescourse->id, 'parent' => $gugrades_summativecategory->parent]);
        
        // Create some "gradable" activities...
        $assignment5 = $this->getDataGenerator()->create_module('assign', ['name' => 'Assessment A2', 'grade' => 12, 'course' => $gugradescourse->id]);
        $assessmentitem5 = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Assessment 1',
            'courseid' => $gugradescourse->id,
            'categoryid' => $gugrades_subcategory->id,
            'grademax' => 23,
            'iteminstance' => $assignment5->id
        ]);

        // Howard's API adds some additional data...
        $gugradescourse->firstlevel[] = [
            'id' => $gugrades_summativecategory->id,
            'fullname' => $gugrades_summativecategory->fullname
        ];
        $gugradescourse->gugradesenabled = true;
        $gugradescourse->gcatenabled = false;

        // Create some context...
        $gugradescontext = \context_course::instance($gugradescourse->id);

        // Enrol the teacher...
        $this->getDataGenerator()->enrol_user($teacher->id, $gugradescourse->id, $this->get_roleid('editingteacher'));
        $this->getDataGenerator()->role_assign('editingteacher', $teacher->id, $gugradescontext);

        // Enrol the student
        $this->getDataGenerator()->enrol_user($student1->id, $gugradescourse->id, $this->get_roleid());
        $this->getDataGenerator()->role_assign('student', $student1->id, $gugradescontext);

        /** Create a "past" course for the test student(s). */
        $lastmonth = mktime(0, 0, 0, date("m")-1, date("d"), date("Y"));
        $tmp_course = $this->getDataGenerator()->create_course([
            'fullname' => 'Student Dashboard Test Gradebook Course - Past',
            'shortname' => 'SDTGBCP1',
            'startdate' => $lastmonth
        ]);
        $course_past = $DB->get_record('course', ['id' => $tmp_course->id], '*', MUST_EXIST);
        $pastdate = strtotime("last Monday");
        $course_past->enddate = $pastdate;
        $DB->update_record('course', $course_past);

        $this->getDataGenerator()->enrol_user($student1->id, $course_past->id, $this->get_roleid());
        $this->course_past = $course_past;

        $gradecategory_past = $this->getDataGenerator()->create_grade_category(['courseid' => $course_past->id]);
        $summativecategory_past = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Summative category - past', 
            'courseid' => $course_past->id, 
            'parent' => $gradecategory_past->id
        ]);

        // Howard's API adds some additional data...
        $course_past->firstlevel[] = [
            'id' => $summativecategory_past->id,
            'fullname' => $summativecategory_past->fullname
        ];
        $course_past->gugradesenabled = false;
        $course_past->gcatenabled = false;

        $assignment_past = $this->getDataGenerator()->create_module('assign', ['name' => 'Past Assessment 1', 'grade' => 50, 'course' => $course_past->id]);
        // $quiz_past = $this->getDataGenerator()->create_module('quiz', ['course' => $course_past->id]);
        // $survey_past = $this->getDataGenerator()->create_module('survey', ['course' => $course_past->id]);
        // $wiki_past = $this->getDataGenerator()->create_module('wiki', ['course' => $course_past->id]);
        // $workshop_past = $this->getDataGenerator()->create_module('workshop', ['course' => $course_past->id]);
        // $forum_past = $this->getDataGenerator()->create_module('forum', ['course' => $course_past->id, 'grade_forum' => 100]);

        $assessmentitem1_past = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'itemname' => 'Past Assessment 1',
            'courseid' => $course_past->id,
            'categoryid' => $summativecategory_past->id,
            'gradetype' => 1,
            'grademax' => 50.0,
            'iteminstance' => $assignment_past->id
        ]);

        // Add an assignment grade.
        $assignmentgrade1_past = $this->add_assignment_grade($assignment_past->id, $student1->id, 95.5);

        // $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'quiz',
        //     'courseid' => $course->id,
        //     'categoryid' => $summativecategory->id,
        //     'iteminstance' => $quiz->id
        // ]);

        // $survey = $this->getDataGenerator()->create_module('survey', ['course' => $course->id]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'survey',
        //     'courseid' => $course->id,
        //     'categoryid' => $summativecategory->id,
        //     'iteminstance' => $survey->id
        // ]);

        // $wiki = $this->getDataGenerator()->create_module('wiki', ['course' => $course->id]);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'wiki',
        //     'courseid' => $course->id,
        //     'categoryid' => $summativecategory->id,
        //     'iteminstance' => $wiki->id
        // ]);

        // $workshop = $this->getDataGenerator()->create_module('workshop', ['course' => $course->id, 'name' => 'A workshop']);

        // $this->getDataGenerator()->create_grade_item([
        //     'itemtype' => 'mod',
        //     'itemmodule' => 'workshop',
        //     'courseid' => $course->id,
        //     'categoryid' => $summativecategory->id,
        //     'iteminstance' => $workshop->id
        // ]);

        // $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id,'grade_forum' => 100]);

        // $this->getDataGenerator()->create_grade_item([
        //         'itemtype' => 'mod',
        //         'itemmodule' => 'forum',
        //         'courseid' => $course->id,
        //         'categoryid' => $summativecategory->id,
        //         'iteminstance' => $forum->id,
        //         'itemnumber' => 0
        // ]);

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

        $this->gcatcourse = $gcatcourse;
        $this->summativecategory = $summativecategory;
        $this->summative_subcategory = $summative_subcategory;
        $this->formativecategory = $formativecategory;
        $this->assignment1 = $assignment1;
        $this->assignment2 = $assignment2;
        $this->assignment3 = $assignment3;
        $this->assessmentitem1 = $assessmentitem1;
        $this->assessmentitem2 = $assessmentitem2;
        $this->assessmentitem3 = $assessmentitem3;

        $this->gradebookcourse = $gradebookcourse;
        $this->gradebookcategory = $gradebookcategory;
        $this->assignment4 = $assignment4;
        $this->assessmentitem4 = $assessmentitem4;

        $this->gugradescourse = $gugradescourse;
        $this->gugrades_subcategory = $gugrades_subcategory;
        $this->assignment5 = $assignment5;
        $this->assessmentitem5 = $assessmentitem5;

        $this->course_past = $course_past;
        $this->summativecategory_past = $summativecategory_past;
        $this->assignment_past = $assignment_past;
        $this->assessmentitem1_past = $assessmentitem1_past;
        $this->assignmentgrade1_past = $assignmentgrade1_past;
        // $this->survey = $survey;
        // $this->wiki = $wiki;
        // $this->workshop = $workshop;
        // $this->forum = $forum;
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
     * Course "type" is irrelevant for this test - so we just pick a type.
     */
    public function test_retrieve_gradable_activities_current_courses() {
        $userid = $this->student1->id;
        $sortorder = 'asc';
        $summativecategoryid = $this->summativecategory->id;
        $returned = $this->lib->retrieve_gradable_activities(null, $userid, null, $sortorder, $summativecategoryid);

        $this->assertIsArray($returned);
        $this->assertArrayHasKey('coursedata',$returned);
        $this->assertCount(3, $returned['coursedata']['assessmentitems']);
    }

    /**
     * Test that only past courses are returned.
     */
    public function test_retrieve_gradable_activities_past_courses() {
        $userid = $this->student1->id;
        $sortorder = 'asc';
        $summativecategory_pastid = $this->summativecategory_past->id;
        $returned = $this->lib->retrieve_gradable_activities(null, $userid, null, $sortorder, $summativecategory_pastid);

        $this->assertIsArray($returned);
        $this->assertArrayHasKey('coursedata',$returned);
        $this->assertCount(1, $returned['coursedata']['assessmentitems']);
    }

    /**
     * Test the different course types that can be in use in the system
     */
    public function test_retrieve_gradable_activities_by_course_type() {
        $userid = $this->student1->id;
        $sortorder = 'asc';

        /** MyGrades course type */
        $gugradessubcategoryid = $this->gugrades_subcategory->id;
        $returned = $this->lib->retrieve_gradable_activities(null, $userid, null, $sortorder, $gugradessubcategoryid);
        $this->assertEquals($this->gugradescourse->gugradesenabled, $returned['coursedata']['assessmentitems'][0]['gugradesenabled']);

        /** GCAT course type */
        $gcatsubcategoryid = $this->summative_subcategory->id;
        $returned = $this->lib->retrieve_gradable_activities(null, $userid, null, $sortorder, $gcatsubcategoryid);
        $this->assertEquals($this->gcatcourse->gcatenabled, $returned['coursedata']['assessmentitems'][0]['gcatenabled']);

        /** Gradebook course type */
        $gradebookcategoryid = $this->gradebookcategory->id;
        $returned = $this->lib->retrieve_gradable_activities(null, $userid, null, $sortorder, $gradebookcategoryid);
        $this->assertEquals(true, $returned['coursedata']['assessmentitems'][0]['gradebookenabled']);
    }

    /**
     * Test of the components of the course that get returned.
     */
    public function test_get_course_components() {
        $returned = $this->lib->get_course_components([$this->gcatcourse], true);

        $this->assertIsArray($returned);
        $this->assertArrayHasKey('coursedata',$returned);
        $this->assertIsString($returned['coursedata'][0]['coursename']);
        $this->assertEquals($this->gcatcourse->shortname, $returned['coursedata'][0]['coursename']);

        $this->assertIsArray($returned['coursedata'][0]['subcategories']);
        $this->assertArrayHasKey('subcategories',$returned['coursedata'][0]);
        $this->assertEquals($this->summativecategory->fullname, $returned['coursedata'][0]['subcategories'][0]['name']);
        $this->assertEquals('Summative', $returned['coursedata'][0]['subcategories'][0]['assessmenttype']);
        $this->assertEquals('—', $returned['coursedata'][0]['subcategories'][0]['subcatweight']);
    }

    /**
     * Test of the context checking when viewing the dashboard as the student
     * and as another user, teacher or other member of staff for example
     */
    public function test_retrieve_gradable_activities_capability_check() {

    }

    /**
     * Test of the language string settings against mock assessment types and weighting.
     */
    public function test_return_assessmenttype() {
        $lang = 'block_newgu_spdetails';
        $expected1 = get_string("formative", $lang);
        $expected2 = get_string("summative", $lang);
        $expected3 = get_string("emptyvalue", $lang);

        $this->assertEquals($expected1, $this->lib->return_assessmenttype("12312 formative", 0));
        $this->assertEquals($expected2, $this->lib->return_assessmenttype("12312 summative", 1));
        $this->assertEquals($expected2, $this->lib->return_assessmenttype("123123 summative", 0));
        $this->assertEquals($expected3, $this->lib->return_assessmenttype(time(), 0));
    }

    /**
     * Test that the correct weighting for a given course 'type' is returned.
     */
    public function test_return_weight() {
        $aggregationcoef = 10;
        $expected1 = round($aggregationcoef, 2).'%';
        $this->assertEquals($expected1, $this->lib->return_weight($aggregationcoef));

        $aggregationcoef = 1;
        $expected2 = round($aggregationcoef * 100, 2).'%';
        $this->assertEquals($expected2, $this->lib->return_weight($aggregationcoef));

        $aggregationcoef = 0;
        $expected3 = '—';
        $this->assertEquals($expected3, $this->lib->return_weight($aggregationcoef));
    }

    /**
     * Test that for a given assessment, the correct grade is returned.
     */
    public function test_get_gradestatus() {

    }

    public function test_return_gradefeedback() {

    }
}