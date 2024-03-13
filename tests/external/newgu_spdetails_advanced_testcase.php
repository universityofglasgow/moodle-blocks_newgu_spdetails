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
 * Custom class for setting up our course types, gradebook, activities and assignments.
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace blocks_newgu_spdetails\external;

use externallib_advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Class containing setUp, activities and other utility methods.
 */
class newgu_spdetails_advanced_testcase extends externallib_advanced_testcase {
    
    /**
     * @var object $course
     */
    protected $course;

    /**
     * @var object $teacher
     */
    protected $teacher;

    /**
     * @var object $student1
     */
    protected $student1;

    /**
     * @var object $lib
     */
    protected $lib;

    /**
     * @var object $courseapi
     */
    protected $courseapi;

    /**
     * @var object $activityapi
     */
    protected $activityapi;

    /**
     * @var obejct $mygradescourse
     */
    protected $mygradescourse;

    /**
     * @var object $gcatcourse
     */
    protected $gcatcourse;

    /**
     * @var object $gradebookcourse
     */
    protected $gradebookcourse;

    /**
     * Get gradeitemid
     * @param string $itemtype
     * @param string $itemmodule
     * @param int $iteminstance
     * @return int
     */
    protected function get_grade_item(string $itemtype, string $itemmodule, int $iteminstance) {
        global $DB;

        $params = [
            'iteminstance' => $iteminstance,
        ];
        if ($itemtype) {
            $params['itemtype'] = $itemtype;
        }
        if ($itemmodule) {
            $params['itemmodule'] = $itemmodule;
        }
        $gradeitem = $DB->get_record('grade_items', $params, '*', MUST_EXIST);

        return $gradeitem->id;
    }

    /**
     * Add assignment grade
     * @param int $assignid
     * @param int $studentid
     * @param int $graderid
     * @param float $gradeval,
     * @param string $status
     */
    protected function add_assignment_grade(int $assignid, int $studentid, int $graderid, float $gradeval, string $status = ASSIGN_SUBMISSION_STATUS_NEW) {
        global $USER, $DB;

        $submission = new \stdClass();
        $submission->assignment = $assignid;
        $submission->userid = $studentid;
        $submission->status = $status;
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
        $grade->grader = $graderid;
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
    protected function setUp(): void {
        global $DB;

        $this->resetAfterTest(true);

        $lib = new \block_newgu_spdetails\api();
        $this->lib = $lib;

        $courseapi = new \block_newgu_spdetails\course();
        $this->courseapi = $courseapi;

        $activityapi = new \block_newgu_spdetails\activity();
        $this->activityapi = $activityapi;

        /** Lets add some scales that each course can use ... */
        // Schedule A
        $scaleitems = 'H:0, G2:1, G1:2, F3:3, F2:4, F1:5, E3:6, E2:7, E1:8, D3:9, D2:10, D1:11,
            C3:12, C2:13, C1:14, B3:15, B2:16, B1:17, A5:18, A4:19, A3:20, A2:21, A1:22';

        // Schedule B scale.
        $scaleitemsb = 'H, G0, F0, E0, D0, C0, B0, A0';

        /** 
         * Create a "current" course.
         * This will be a GCAT style course to start... 
         */
        $lastmonth = mktime(0, 0, 0, date("m")-1, date("d"), date("Y"));
        $nextyear  = mktime(0, 0, 0, date("m"), date("d"), date("Y")+1);
        $gcatcourse = $this->getDataGenerator()->create_course([
            'fullname' => 'GCAT 2023 TW - Existing GCAT', 
            'shortname' => 'GCAT2023TWEX',
            'startdate' => $lastmonth,
            'enddate' => $nextyear
        ]);

        // Add some grading categories..
        $gcat_summativecategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Summative - Converting Points to 22 point Scale - 25% Course Weighting', 
            'courseid' => $gcatcourse->id, 
            'aggregation' => 10
        ]);

        $gcat_summative_subcategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Average of assignments - Sub components - Simple Weighted Mean', 
            'courseid' => $gcatcourse->id, 
            'parent' => $gcat_summativecategory->id
        ]);

        $gcat_formativecategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Formative activities', 
            'courseid' => $gcatcourse->id, 
            'parent' => $gcat_summativecategory->parent
        ]);

        // Howard's API adds some additional field members...
        $gcatcourse->firstlevel[] = [
            'id' => $gcat_summativecategory->id,
            'fullname' => $gcat_summativecategory->fullname
        ];

        $gcatcourse->firstlevel[] = [
            'id' => $gcat_formativecategory->id,
            'fullname' => $gcat_formativecategory->fullname
        ];
        $gcatcourse->mygradesenabled = false;
        $gcatcourse->gcatenabled = true;
        $gcatcontext = \context_course::instance($gcatcourse->id);

        // But we also need to mock the "gcat enabled" state in the 
        // customfield_x tables for GCAT type courses.
        $cfcparams = [
            'name' => 'GCAT Options',
            'component' => 'core_course',
            'area' => 'course'
        ];
        $this->getDataGenerator()->create_custom_field_category($cfcparams);
        $configdata = '{"required":"0","uniquevalues":"0","checkbydefault":"0","locked":"0","visibility":"0"}';
        $cfparams = [
            'name' => 'Show assessments on Student Dashboard',
            'shortname' => 'show_on_studentdashboard',
            'type' => 'checkbox',
            'category' => 'GCAT Options',
            'configdata' => $configdata
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

        // Add the grading scales...
        $gcat_scale1 = $this->getDataGenerator()->create_scale([
            'name' => 'UofG 22 point scale',
            'scale' => $scaleitems,
            'courseid' => $gcatcourse->id,
        ]);
        $gcat_scale2 = $this->getDataGenerator()->create_scale([
            'name' => 'UofG Schedule B',
            'scale' => $scaleitemsb,
            'courseid' => $gcatcourse->id,
        ]);
        
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
        $due_date1 = mktime(date("H"), date("i"), date("s"), date("m")+1, date("d")+1, date("Y"));
        $due_date2 = mktime(date("H"), date("i"), date("s"), date("m")+1, date("d")+2, date("Y"));
        $due_date3 = mktime(date("H"), date("i"), date("s"), date("m")+1, date("d")+3, date("Y"));
        $assignment1 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Assessment 1',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $gcatcourse->id,
            'duedate' => $due_date1,
            'gradetype' => 2,
            'grademax' => 50.0,
            'scaleid' => $gcat_scale1->id,
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            'Provisional Grade',
            $gcat_summativecategory->id,
            $gcat_scale1->id,
            2,
            $assignment1->id
        ];
        $DB->execute("UPDATE {grade_items} SET itemname = ?, categoryid = ?, idnumber = ?, gradetype = ? WHERE iteminstance = ?", $params);

        $gradeditem1 = $this->add_assignment_grade($assignment1->id, $student1->id, $teacher->id, 35, ASSIGN_SUBMISSION_STATUS_SUBMITTED);

        $gradeitemid1 = $this->get_grade_item('', 'assign', $assignment1->id);
        
        // No idea if this is the correct way to do this...
        // Create a "provisional" grade for the first assignment...
        $DB->insert_record('grade_grades', [
            'itemid' => $gradeitemid1,
            'userid' => $student1->id,
            'rawgrade' => 18,
            'finalgrade' => 18,
        ]);
        
        // This is our Provisional grade assignment
        $assignment2 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Assessment 2(i)',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $gcatcourse->id,
            'duedate' => $due_date2,
            'gradetype' => 2,
            'grademax' => 20.0,
            'scaleid' => $gcat_scale1->id,
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            'Provisional Grade',
            $gcat_summative_subcategory->id,
            $gcat_scale1->id,
            2,
            $assignment2->id
        ];
        $DB->execute("UPDATE {grade_items} SET itemname = ?, categoryid = ?, idnumber = ?, gradetype = ? WHERE iteminstance = ?", $params);

        $gradeditem2 = $this->add_assignment_grade($assignment2->id, $student1->id, $teacher->id, 18, ASSIGN_SUBMISSION_STATUS_SUBMITTED);

        /** No idea why, but the last call to create_module has just created a number of
         * grade_grade entries, when it didn't previously, meaning this now flakes out 
         * with a DUPLICATE KEY error.
         */
        $gradeitemid2 = $this->get_grade_item('', 'assign', $assignment2->id);
        // This assignment has been given a final grade...
        // $DB->insert_record('grade_grades', [
        //     'itemid' => $gradeitemid2,
        //     'userid' => $student1->id,
        //     'finalgrade' => 22,
        //     'information' => 'This is a GCAT assessed final grade',
        //     'feedback' => 'You have attained the required level according to the GCAT formula.'
        // ]);
        /** Set another Provisional Grade as this item is 1 of 2 in a subcategory... */
        $params = [
            18,
            18,
            $gradeitemid2
        ];
        $DB->execute("UPDATE {grade_grades} SET rawgrade = ?, finalgrade = ? WHERE itemid = ?", $params);

        // This is our Final grade assignment
        $assignment3 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Assessment 3',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $gcatcourse->id,
            'duedate' => $due_date3,
            'gradetype' => 2,
            'grademax' => 30.0,
            'scaleid' => $gcat_scale1->id,
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            $gcat_summative_subcategory->id,
            $assignment3->id
        ];
        $DB->execute("UPDATE {grade_items} SET categoryid = ? WHERE iteminstance = ?", $params);

        $gradeditem3 = $this->add_assignment_grade($assignment3->id, $student1->id, $teacher->id, 12.5, ASSIGN_SUBMISSION_STATUS_NEW);

        $gradeitemid3 = $this->get_grade_item('', 'assign', $assignment3->id);

        // This assignment has an overridden grade...
        // $DB->insert_record('grade_grades', [
        //     'itemid' => $gradeitemid3,
        //     'userid' => $student1->id,
        //     'overridden' => 1,
        //     'rawgrade' => 22,
        //     'finalgrade' => 21,
        //     'information' => 'The grade for Assessment 3 was overridden',
        //     'feedback' => 'There were some issues with the final submission.',
        //     'usermodified' => $teacher->id
        // ]);
        $params = [
            1,
            22,
            21,
            'This is a GCAT assessed final grade',
            'You have attained the required level according to the GCAT formula.',
            $teacher->id,
            $gradeitemid3
        ];
        $DB->execute("UPDATE {grade_grades} SET overridden = ?, rawgrade = ?, finalgrade = ?, information = ?, feedback = ?, usermodified = ? WHERE itemid = ?", $params);

        $groupassignment1 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Group Assessment 1',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $gcatcourse->id,
            'gradetype' => 2,
            'grademax' => 23.0,
            'aggregationcoef' => 0.20000,
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            $gcat_summative_subcategory->id,
            $groupassignment1->id
        ];
        $DB->execute("UPDATE {grade_items} SET categoryid = ? WHERE iteminstance = ?", $params);

        /** 
         * Create a MyGrades type course.
         * We are loosely creating gradable items in local_gugrade_grades
         * on the basis that the dashboard will be pulling data from there.
         * To refine the setup for this, we would need to mock grade items 
         * that don't have an entry and are therefore returned from gradebook
         * instead. This would be to simulate gradable items that have yet
         * to be imported/marked/released.
         */
        $mygradescourse = $this->getDataGenerator()->create_course([
            'fullname' => 'MyGrades Test Course', 
            'shortname' => 'MYGRADE-TW1',
            'startdate' => $lastmonth,
            'enddate' => $nextyear
        ]);

        // We also need to mock "enable" this as a MyGrades type course.
        $mygradesparams = [
            'courseid' => $mygradescourse->id,
            'name' => 'enabledashboard',
            'value' => 1
        ];
        $DB->insert_record('local_gugrades_config', $mygradesparams);

        // Add some grading categories..
        $mygrades_summativecategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Summative Assessments', 
            'courseid' => $mygradescourse->id, 
            'aggregation' => 10
        ]);
        $mygrades_summative_subcategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Assessments Month 1 - Summative - WM', 
            'courseid' => $mygradescourse->id, 
            'parent' => $mygrades_summativecategory->id
        ]);
        $mygrades_summative_subcategory2 = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Sub-Category B Assignments (Resits - highest grade)', 
            'courseid' => $mygradescourse->id, 
            'parent' => $mygrades_summative_subcategory->id
        ]);
        $mygrades_formativecategory = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Formative Assessments', 
            'courseid' => $mygradescourse->id, 
            'parent' => $mygrades_summativecategory->parent
        ]);
        
        // Add the grading scales...
        $mygrades_scale1 = $this->getDataGenerator()->create_scale([
            'name' => 'UofG 22 point scale',
            'scale' => $scaleitems,
            'courseid' => $mygradescourse->id,
        ]);
        $mygrades_scaleb1 = $this->getDataGenerator()->create_scale([
            'name' => 'UofG Schedule B',
            'scale' => $scaleitemsb,
            'courseid' => $mygradescourse->id,
        ]);

        // Create some context...
        $mygradescontext = \context_course::instance($mygradescourse->id);

        // Enrol the teacher...
        $this->getDataGenerator()->enrol_user($teacher->id, $mygradescourse->id, $this->get_roleid('editingteacher'));
        $this->getDataGenerator()->role_assign('editingteacher', $teacher->id, $mygradescontext);

        // Enrol the student
        $this->getDataGenerator()->enrol_user($student1->id, $mygradescourse->id, $this->get_roleid());
        $this->getDataGenerator()->role_assign('student', $student1->id, $mygradescontext);

        // Create some "gradable" activities...
        $due_date4 = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+1, date("Y"));
        $due_date5 = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+7, date("Y"));
        $due_date6 = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+14, date("Y"));
        $due_date7 = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+25, date("Y"));
        $assignment4 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Assessment A - Month 1',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $mygradescourse->id,
            'duedate' => $due_date4,
            'gradetype' => 2,
            'grademax' => 50,
            'scaleid' => $mygrades_scale1->id
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            $mygrades_summative_subcategory->id,
            $assignment4->id
        ];
        $DB->execute("UPDATE {grade_items} SET categoryid = ? WHERE iteminstance = ?", $params);

        $gradeditem4 = $this->add_assignment_grade($assignment4->id, $student1->id, $teacher->id, 40, ASSIGN_SUBMISSION_STATUS_NEW);

        $DB->insert_record('grade_grades', [
            'itemid' => $assignment4->id,
            'userid' => $student1->id,
            'rawgrade' => 21,
        ]);
        
        // We're not doing anything else with assignment4 as we only
        // want to test if gradetype=[PROVISIONAL|RELEASED] on these
        // next two items.
        
        $assignment5 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Assessment B1 - Month 1',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $mygradescourse->id,
            'duedate' => $due_date5,
            'gradetype' => 2,
            'grademax' => 75,
            'scaleid' => $mygrades_scale1->id
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            $mygrades_summative_subcategory2->id,
            $assignment5->id
        ];
        $DB->execute("UPDATE {grade_items} SET categoryid = ? WHERE iteminstance = ?", $params);

        $gradeditem5 = $this->add_assignment_grade($assignment5->id, $student1->id, $teacher->id, 70, ASSIGN_SUBMISSION_STATUS_NEW);

        $DB->insert_record('grade_grades', [
            'itemid' => $assignment5->id,
            'userid' => $student1->id,
            'rawgrade' => 13,
        ]);

        // This could be completely wrong of course...
        // Create a "provisional" grade for the first assignment...
        $gradeitemid5 = $this->get_grade_item('', 'assign', $assignment5->id);
        $DB->insert_record('local_gugrades_grade', [
            'courseid' => $mygradescourse->id,
            'gradeitemid' => $gradeitemid5,
            'userid' => $student1->id,
            'rawgrade' => 13,
            'gradetype' => 'PROVISIONAL',
            'columnid' => 0,
            'iscurrent' => 1,
            'auditby' => 0,
            'audittimecreated' => $now,
        ]);

        $assignment6 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Assessment B1 - Month 1 (Resit)',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $mygradescourse->id,
            'duedate' => $due_date7,
            'gradetype' => 2,
            'grademax' => 100,
            'scaleid' => $mygrades_scale1->id
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            $mygrades_summative_subcategory2->id,
            $assignment6->id
        ];
        $DB->execute("UPDATE {grade_items} SET categoryid = ? WHERE iteminstance = ?", $params);
        
        $gradeditem6 = $this->add_assignment_grade($assignment6->id, $student1->id, $teacher->id, 75, ASSIGN_SUBMISSION_STATUS_NEW);

        // This assignment has been given a final grade...
        $gradeitemid6 = $this->get_grade_item('', 'assign', $assignment6->id);
        $DB->insert_record('local_gugrades_grade', [
            'courseid' => $mygradescourse->id,
            'gradeitemid' => $gradeitemid6,
            'userid' => $student1->id,
            'displaygrade' => 'A0',
            'gradetype' => 'RELEASED',
            'columnid' => 0,
            'iscurrent' => 1,
            'auditby' => 0,
            'audittimecreated' => $now,
        ]);

        $DB->insert_record('grade_grades', [
            'itemid' => $assignment6->id,
            'userid' => $student1->id,
            'rawgrade' => 21,
            'finalgrade' => 22,
        ]);

        // Howard's API adds some additional data...
        $mygradescourse->firstlevel[] = [
            'id' => $mygrades_summativecategory->id,
            'fullname' => $mygrades_summativecategory->fullname
        ];
        $mygradescourse->mygradesenabled = true;
        $mygradescourse->gcatenabled = false;

        /** 
         * Regular Gradebook type course. 
         */
        $gradebookcourse = $this->getDataGenerator()->create_course([
            'fullname' => 'Gradebook Test Course - TW1', 
            'shortname' => 'GRADEBOOK-TW1',
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
        $gradebookcourse->mygradesenabled = false;
        $gradebookcourse->gcatenabled = false;

        // Add the grading scales...
        $gradebook_scale1 = $this->getDataGenerator()->create_scale([
            'name' => 'UofG 22 point scale',
            'scale' => $scaleitems,
            'courseid' => $gradebookcourse->id,
        ]);
        $gradebook_scaleb1 = $this->getDataGenerator()->create_scale([
            'name' => 'UofG Schedule B',
            'scale' => $scaleitemsb,
            'courseid' => $gradebookcourse->id,
        ]);

        // Enrol the teacher...
        $this->getDataGenerator()->enrol_user($teacher->id, $gradebookcourse->id, $this->get_roleid('editingteacher'));
        $this->getDataGenerator()->role_assign('editingteacher', $teacher->id, $gradebookcontext);

        // Enrol the student
        $this->getDataGenerator()->enrol_user($student1->id, $gradebookcourse->id, $this->get_roleid());
        $this->getDataGenerator()->role_assign('student', $student1->id, $gradebookcontext);

        $due_date7 = mktime(date("H"), date("i"), date("s"), date("m")+1, date("d")+7, date("Y"));
        $due_date8 = mktime(date("H"), date("i"), date("s"), date("m")+1, date("d")+8, date("Y"));
        $assignment7 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'SPS5022 Essay - FINAL - Thursday 12th',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $gradebookcourse->id,
            'grademax' => 100.00000,
            'gradetype' => 2,
            'scaleid' => $gradebook_scale1->id
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            $gradebookcategory->id,
            2,
            $gradebook_scale1->id,
            $assignment7->id
        ];
        $DB->execute("UPDATE {grade_items} SET categoryid = ?, gradetype = ?, scaleid = ?  WHERE iteminstance = ?", $params);

        $gradeditem7 = $this->add_assignment_grade($assignment7->id, $student1->id, $teacher->id, 75, ASSIGN_SUBMISSION_STATUS_NEW);
        $gradeitemid7 = $this->get_grade_item('', 'assign', $assignment7->id);
        // This assignment has been given a final grade...
        $DB->insert_record('grade_grades', [
            'itemid' => $gradeitemid7,
            'userid' => $student1->id,
            'finalgrade' => 21,
            'rawscaleid' => $gradebook_scale1->id,
            'information' => 'This is a Gradebook assessed final grade',
            'feedback' => 'You have attained the required level according to the Gradebook formula.'
        ]);



        // This is our Provisional grade assignment
        $assignment8 = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Assessment 8',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $gradebookcourse->id,
            'duedate' => $due_date8,
            'gradetype' => 2,
            'grademax' => 20.0,
            'scaleid' => $gradebook_scale1->id,
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            $gradebookcategory->id,
            2,
            $gradebook_scale1->id,
            $assignment8->id
        ];
        $DB->execute("UPDATE {grade_items} SET categoryid = ?, gradetype = ?, scaleid = ? WHERE iteminstance = ?", $params);

        $gradeditem8 = $this->add_assignment_grade($assignment8->id, $student1->id, $teacher->id, 14, ASSIGN_SUBMISSION_STATUS_SUBMITTED);

        /** No idea why, but the last call to create_module has just created a number of
         * grade_grade entries, when it didn't previously, meaning this now flakes out 
         * with a DUPLICATE KEY error.
         */
        $gradeitemid8 = $this->get_grade_item('', 'assign', $assignment8->id);
        /** Set a Provisional Grade as this item is 1 of 2 in a subcategory... */
        $params = [
            18,
            $gradebook_scale1->id,
            $gradeitemid8
        ];
        $DB->execute("UPDATE {grade_grades} SET rawgrade = ?, rawscaleid = ? WHERE itemid = ?", $params);


        

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

        $gradecategory_past = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Summative Category - Past', 
            'courseid' => $course_past->id
        ]);
        $summativecategory_past = $this->getDataGenerator()->create_grade_category([
            'fullname' => 'Average of assignments - past', 
            'courseid' => $course_past->id, 
            'parent' => $gradecategory_past->id
        ]);

        // Howard's API adds some additional data...
        $course_past->firstlevel[] = [
            'id' => $summativecategory_past->id,
            'fullname' => $summativecategory_past->fullname
        ];
        $course_past->mygradesenabled = false;
        $course_past->gcatenabled = false;

        $assignment_past = $this->getDataGenerator()->create_module('assign', [
            'name' => 'Past Assessment 1', 
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'course' => $course_past->id,
            'gradetype' => 2,
            'grademax' => 100.00, 
            'scaleid' => $gradebook_scale1->id
        ]);
        // create_module gives us stuff for free, however, it doesn't set the categoryid correctly :-(
        $params = [
            $summativecategory_past->id,
            $assignment_past->id
        ];
        $DB->execute("UPDATE {grade_items} SET categoryid = ? WHERE iteminstance = ?", $params);

        // Add a past assignment grade.
        $assignmentgrade1_past = $this->add_assignment_grade($assignment_past->id, $student1->id, $teacher->id, 95.5, ASSIGN_SUBMISSION_STATUS_SUBMITTED);

        // This assignment has been given a final grade...
        $DB->insert_record('grade_grades', [
            'itemid' => $assignment_past->id,
            'userid' => $student1->id,
            'finalgrade' => 22,
            'information' => 'This is a Gradebook assessed final grade',
            'feedback' => 'You have attained the required level according to the Gradebook formula.'
        ]);

        // $quiz_past = $this->getDataGenerator()->create_module('quiz', ['course' => $course_past->id]);
        // $survey_past = $this->getDataGenerator()->create_module('survey', ['course' => $course_past->id]);
        // $wiki_past = $this->getDataGenerator()->create_module('wiki', ['course' => $course_past->id]);
        // $workshop_past = $this->getDataGenerator()->create_module('workshop', ['course' => $course_past->id]);
        // $forum_past = $this->getDataGenerator()->create_module('forum', ['course' => $course_past->id, 'grade_forum' => 100]);

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
        $this->gcat_summativecategory = $gcat_summativecategory;
        $this->gcat_summative_subcategory = $gcat_summative_subcategory;
        $this->gcat_formativecategory = $gcat_formativecategory;
        $this->assignment1 = $assignment1;
        $this->assignment2 = $assignment2;
        $this->assignment3 = $assignment3;

        $this->mygradescourse = $mygradescourse;
        $this->mygrades_summativecategory = $mygrades_summativecategory;
        $this->mygrades_summative_subcategory = $mygrades_summative_subcategory;
        $this->mygrades_summative_subcategory2 = $mygrades_summative_subcategory2;
        $this->mygrades_formativecategory = $mygrades_formativecategory;
        $this->assignment4 = $assignment4;
        $this->assignment5 = $assignment5;
        $this->assignment6 = $assignment6;

        $this->gradebookcourse = $gradebookcourse;
        $this->gradebookcategory = $gradebookcategory;
        $this->assignment7 = $assignment7;

        $this->course_past = $course_past;
        $this->summativecategory_past = $summativecategory_past;
        $this->assignment_past = $assignment_past;
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
}
