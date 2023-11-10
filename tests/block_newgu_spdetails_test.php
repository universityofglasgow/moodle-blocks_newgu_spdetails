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
 * Unit tests for the Student Dashboard block plugin.
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
require_once($CFG->dirroot .'/blocks/newgu_spdetails/block_newgu_spdetails.php');

/**
 * Test(s) for block_newgu_spdetails
 */
class block_newgu_spdetails_test extends advanced_testcase {

    /**
     * Set up our test conditions...
     * @return void
     * @throws dml_exception
     */
    public function setUp(): void
    {
        global $DB;
        $this->resetAfterTest(true);
        $spdetails = new block_newgu_spdetails();
        //$lib = new block_newgu_spdetails_external();

//        // Set up a student.
//        $student = $this->getDataGenerator()->create_user(['email' => 'student1@example.co.uk', 'username' => 'student1']);
//
//        // Create a "current" course and enrol the student.
//        $category = $this->getDataGenerator()->create_category();
//        $course = $this->getDataGenerator()->create_course(['fullname' => 'Student Dashboard Current Test Course', 'category' => $category->id]);
//        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->get_roleid());
//        $this->setUser($student);
//
//        // Set up a teacher.
//        $teacher = $this->getDataGenerator()->create_user(['email' => 'teacher1@example.co.uk', 'username' => 'teacher1']);
//        // Enrol the teacher on the current test course.
//        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $this->get_roleid('editingteacher'));
//        $this->setUser($teacher);
//
//        // Create the "gradable" activities
//        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
//        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
//        $survey = $this->getDataGenerator()->create_module('survey', ['course' => $course->id]);
//        $wiki = $this->getDataGenerator()->create_module('wiki', ['course' => $course->id]);
//        $workshop = $this->getDataGenerator()->create_module('workshop', ['course' => $course->id]);
//        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id,
//            'grade_forum' => 100]);
//        $forum2 = $this->getDataGenerator()->create_module('forum', ['course' => $course->id,
//            'grade_forum' => 100]);
//
//        $gradeitem = $this->getDataGenerator()->create_grade_item([
//            'itemtype' => 'mod',
//            'itemmodule' => 'quiz',
//            'courseid' => $course->id,
//            'iteminstance' => $quiz->id
//        ]);
//
//        $this->getDataGenerator()->create_grade_item(
//            array(
//                'itemtype' => 'mod',
//                'itemmodule' => 'forum',
//                'courseid' => $course->id,
//                'iteminstance' => $forum->id,
//                'itemnumber' => 0
//            )
//        );
//
//        $this->getDataGenerator()->create_grade_item(
//            array(
//                'itemtype' => 'mod',
//                'itemmodule' => 'forum',
//                'courseid' => $course->id,
//                'iteminstance' => $forum2->id,
//                'itemnumber' => 1
//            )
//        );
//
//        $DB->insert_record('grade_grades', [
//            'itemid' => $gradeitem->id,
//            'userid' => $student->id
//        ]);
//
//        // Create a "past" course for the test student.
//        $course_past = $this->getDataGenerator()->create_course(['fullname' => 'Student Dashboard Past Test Course', 'category' => $category->id]);
//        $pastdate = strtotime("last Monday");
//        $course_past->enddate = $pastdate;
//        $this->getDataGenerator()->enrol_user($student->id, $course_past->id, $this->get_roleid());
//        $this->setUser($student);
//        $this->course_past = $course_past;
//
//        $assign_past = $this->getDataGenerator()->create_module('assign', ['course' => $course_past->id]);
//        $quiz_past = $this->getDataGenerator()->create_module('quiz', ['course' => $course_past->id]);
//        $survey_past = $this->getDataGenerator()->create_module('survey', ['course' => $course_past->id]);
//        $wiki_past = $this->getDataGenerator()->create_module('wiki', ['course' => $course_past->id]);
//        $workshop_past = $this->getDataGenerator()->create_module('workshop', ['course' => $course_past->id]);
//        $forum_past = $this->getDataGenerator()->create_module('forum', ['course' => $course_past->id,
//            'grade_forum' => 100]);
//        $forum2_past = $this->getDataGenerator()->create_module('forum', ['course' => $course_past->id,
//            'grade_forum' => 100]);
//
//        $gradeitem_past = $this->getDataGenerator()->create_grade_item([
//            'itemtype' => 'mod',
//            'itemmodule' => 'quiz',
//            'courseid' => $course_past->id,
//            'iteminstance' => $quiz_past->id
//        ]);
//
//        $this->getDataGenerator()->create_grade_item(
//            [
//                'itemtype' => 'mod',
//                'itemmodule' => 'forum',
//                'courseid' => $course_past->id,
//                'iteminstance' => $forum_past->id,
//                'itemnumber' => 0
//            ]
//        );
//
//        $this->getDataGenerator()->create_grade_item(
//            [
//                'itemtype' => 'mod',
//                'itemmodule' => 'forum',
//                'courseid' => $course_past->id,
//                'iteminstance' => $forum2_past->id,
//                'itemnumber' => 1
//            ]
//        );
//
//        $DB->insert_record('grade_grades', [
//            'itemid' => $gradeitem_past->id,
//            'userid' => $student->id
//        ]);

        $this->spdetails = $spdetails;
        //$this->lib = $lib;

//        $this->student = $student;
//        $this->teacher = $teacher;
//
//        $this->course = $course;
//        $this->assign = $assign;
//        $this->survey = $survey;
//        $this->wiki = $wiki;
//        $this->workshop = $workshop;
//        $this->forum = $forum;
//
//        $this->course_past = $course_past;
//        $this->assign_past = $assign_past;
//        $this->survey_past = $survey_past;
//        $this->wiki_past = $wiki_past;
//        $this->workshop_past = $workshop_past;
//        $this->forum_past = $forum_past;
    }

    /**
     * Utility function to provide the roleId
     *
     * @param $archetype
     * @return mixed
     * @throws dml_exception
     */
//    public function get_roleid($archetype = 'student') {
//        global $DB;
//
//        $role = $DB->get_record("role", ['archetype' => $archetype]);
//        return $role->id;
//    }

    /**
     * Check that has_config returns a bool
     */
    public function test_has_config() {
        $returned = $this->spdetails->has_config();
        $this->assertIsBool($returned);
    }

    /**
     * @return void
     */
    public function test_applicable_formats() {
        $returned = $this->spdetails->applicable_formats();
        $this->assertEquals($returned, ['my' => true]);
    }

    /**
     * @return void
     * @throws dml_exception
     */
    public function test_get_content() {

        $returned = $this->spdetails->get_content();
        $this->assertEmpty($returned->text);
    }

//    public function test_return_current_enrolledcourses() {
//
//        // The data generator can only set a start date on courses.
//        $this->assertEquals([$this->course->id, $this->course_past->id], $this->lib->return_enrolledcourses($this->student->id, 'current'));
//    }
//
//    public function test_return_past_enrolledcourses() {
//
//        $this->assertIsArray($this->lib->return_enrolledcourses($this->student->id, 'past'));
//    }
//
//    public function test_return_student_enrolledcourses() {
//
//        // Because the dataGenerator->create_course() is limited, we can't set an 'enddate' and test for
//        $this->assertCount(2, $this->lib->return_enrolledcourses($this->student->id, 'current'));
//        $this->assertCount(2, $this->lib->return_enrolledcourses($this->student->id, 'all'));
//    }
//
//    public function test_return_staff_enrolledcourses() {
//
//        $this->assertCount(1, $this->lib->return_enrolledcourses($this->teacher->id, 'current', 'staff'));
//    }
//
//    public function test_return_isstudent() {
//
//        $this->assertTrue($this->lib->return_isstudent($this->course->id, $this->student->id));
//
//        $this->assertFalse($this->lib->return_isstudent($this->course->id, $this->teacher->id));
//    }
//
//    public function test_return_gradestatus() {
//
//    }
}