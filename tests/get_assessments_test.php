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

defined('MOODLE_INTERNAL') || die();

global $CFG, $DB;

require_once('config.php');
require_once($CFG->dirroot .'/blocks/moodleblock.class.php');
require_once($CFG->dirroot .'/blocks/newgu_spdetails/classes/external.php');

class get_assessments_test extends advanced_testcase {
    /**
     * Set up our test conditions...
     * @return void
     * @throws dml_exception
     */
    public function setUp(): void
    {
        global $DB;

        $this->resetAfterTest(true);

        $lib = new block_newgu_spdetails_external();
        $this->lib = $lib;

        // Set up a student.
        $student = $this->getDataGenerator()->create_user(['email' => 'student1@example.co.uk', 'username' => 'student1']);

        // Create a "current" course and enrol the student.
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Student Dashboard Current Test Course', 'category' => $category->id]);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->get_roleid());
        $this->setUser($student);

        // Set up a teacher.
        $teacher = $this->getDataGenerator()->create_user(['email' => 'teacher1@example.co.uk', 'username' => 'teacher1']);
        // Enrol the teacher on the current test course.
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $this->get_roleid('editingteacher'));
        $this->setUser($teacher);

        // Create the "gradable" activities
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $survey = $this->getDataGenerator()->create_module('survey', ['course' => $course->id]);
        $wiki = $this->getDataGenerator()->create_module('wiki', ['course' => $course->id]);
        $workshop = $this->getDataGenerator()->create_module('workshop', ['course' => $course->id]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id,
            'grade_forum' => 100]);
        $forum2 = $this->getDataGenerator()->create_module('forum', ['course' => $course->id,
            'grade_forum' => 100]);

        $gradeitem = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'courseid' => $course->id,
            'iteminstance' => $quiz->id
        ]);

        $this->getDataGenerator()->create_grade_item(
            [
                'itemtype' => 'mod',
                'itemmodule' => 'forum',
                'courseid' => $course->id,
                'iteminstance' => $forum->id,
                'itemnumber' => 0
            ]
        );

        $this->getDataGenerator()->create_grade_item(
            [
                'itemtype' => 'mod',
                'itemmodule' => 'forum',
                'courseid' => $course->id,
                'iteminstance' => $forum2->id,
                'itemnumber' => 1
            ]
        );

        $DB->insert_record('grade_grades', [
            'itemid' => $gradeitem->id,
            'userid' => $student->id
        ]);

        // Create a "past" course for the test student.
        $course_past = $this->getDataGenerator()->create_course(['fullname' => 'Student Dashboard Past Test Course', 'category' => $category->id]);
        $pastdate = strtotime("last Monday");
        $course_past->enddate = $pastdate;
        $this->getDataGenerator()->enrol_user($student->id, $course_past->id, $this->get_roleid());
        $this->setUser($student);
        $this->course_past = $course_past;

        $assign_past = $this->getDataGenerator()->create_module('assign', ['course' => $course_past->id]);
        $quiz_past = $this->getDataGenerator()->create_module('quiz', ['course' => $course_past->id]);
        $survey_past = $this->getDataGenerator()->create_module('survey', ['course' => $course_past->id]);
        $wiki_past = $this->getDataGenerator()->create_module('wiki', ['course' => $course_past->id]);
        $workshop_past = $this->getDataGenerator()->create_module('workshop', ['course' => $course_past->id]);
        $forum_past = $this->getDataGenerator()->create_module('forum', ['course' => $course_past->id,
            'grade_forum' => 100]);
        $forum2_past = $this->getDataGenerator()->create_module('forum', ['course' => $course_past->id,
            'grade_forum' => 100]);

        $gradeitem_past = $this->getDataGenerator()->create_grade_item([
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'courseid' => $course_past->id,
            'iteminstance' => $quiz_past->id
        ]);

        $this->getDataGenerator()->create_grade_item(
            [
                'itemtype' => 'mod',
                'itemmodule' => 'forum',
                'courseid' => $course_past->id,
                'iteminstance' => $forum_past->id,
                'itemnumber' => 0
            ]
        );

        $this->getDataGenerator()->create_grade_item(
            [
                'itemtype' => 'mod',
                'itemmodule' => 'forum',
                'courseid' => $course_past->id,
                'iteminstance' => $forum2_past->id,
                'itemnumber' => 1
            ]
        );

        $DB->insert_record('grade_grades', [
            'itemid' => $gradeitem_past->id,
            'userid' => $student->id
        ]);

        $this->student = $student;
        $this->teacher = $teacher;

        $this->course = $course;
        $this->assign = $assign;
        $this->survey = $survey;
        $this->wiki = $wiki;
        $this->workshop = $workshop;
        $this->forum = $forum;

        $this->course_past = $course_past;
        $this->assign_past = $assign_past;
        $this->survey_past = $survey_past;
        $this->wiki_past = $wiki_past;
        $this->workshop_past = $workshop_past;
        $this->forum_past = $forum_past;
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
     * 
     */
    // public function test_return_current_enrolledcourses() {

    //     // The data generator can only set a start date on courses.
    //     $this->assertEquals([$this->course->id, $this->course_past->id], $this->lib->return_enrolledcourses($this->student->id, 'current'));
    // }

    /**
     * 
     */
    // public function test_return_past_enrolledcourses() {

    //     $this->assertIsArray($this->lib->return_enrolledcourses($this->student->id, 'past'));
    // }

    /**
     * 
     */
    // public function test_return_student_enrolledcourses() {

    //     // Because the dataGenerator->create_course() is limited, we can't set an 'enddate' and test for
    //     $this->assertCount(2, $this->lib->return_enrolledcourses($this->student->id, 'current'));
    //     $this->assertCount(2, $this->lib->return_enrolledcourses($this->student->id, 'all'));
    // }

    /**
     * 
     */
    // public function test_return_staff_enrolledcourses() {
    //     $this->assertCount(1, $this->lib->return_enrolledcourses($this->teacher->id, 'current', 'staff'));
    // }

    /**
     * 
     */
    // public function test_return_isstudent() {

    //     $this->assertTrue($this->lib->return_isstudent($this->course->id, $this->student->id));
    //     $this->assertFalse($this->lib->return_isstudent($this->course->id, $this->teacher->id));
    // }


    /**
     * 
     */
    public function test_retrieve_assessments() {

    }

    /**
     * 
     */
    public function test_retrieve_gradeable_activities() {
        global $DB;

        // Setting up student.
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        // Creating course.
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->get_roleid());

        $assign = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));

        $activetab = 'past';
        $userid = $student->id;
        $sortby = 'coursetitle';
        $sortorder = 'ASC';
        $returned1 = $this->lib->retrieve_gradable_activities($activetab, $userid, $sortby, $sortorder, null);

        $this->assertEquals(array(), $returned1);

        // Subcategory.
        $subcategory = $this->getDataGenerator()->create_grade_category(array('courseid' => $course->id));

        // Assign to subcategory.
        $gradeitem = $DB->get_record("grade_items", ['itemmodule' => 'assign', 'iteminstance' => $assign->id]);
        $gradeitem->categoryid = $subcategory->id;
        $DB->update_record("grade_items", $gradeitem);

        $returned3 = $this->lib->retrieve_gradable_activities('current', $userid, $sortby, $sortorder, $subcategory->id);
        $this->assertEquals($assign->name, $returned3[0]->assessmentname);
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
        $this->assertEquals($expected2, $this->lib->return_assessmenttype("123123 summative", 0));
        $this->assertEquals($expected2, $this->lib->return_assessmenttype("12312 formative", 1));
        $this->assertEquals($expected3, $this->lib->return_assessmenttype(time(), 0));
    }

    /**
     * 
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
        $expected3 = '—';
        $this->assertEquals($expected3, $this->lib->return_weight($assessmenttype, $aggregation,
                                                                  $aggregationcoef, $aggregationcoef2, ""));
    }

    public function test_return_duedate() {

    }

    public function test_return_gradestatus() {

    }

    public function test_return_grade() {

    }

    public function test_return_gradefeedback() {

    }
}