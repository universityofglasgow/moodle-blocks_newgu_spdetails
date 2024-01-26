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
 * The API for the Student Dashboard plugin
 *
 * @package    block_newgu_spdetails
 * @author     Shubhendra Diophode <shubhendra.doiphode@gmail.com>
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_newgu_spdetails;

use context_course;
use context_system;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->libdir . '/gradelib.php');

define('NUM_ASSESSMENTS_PER_PAGE', 12);

class api extends \external_api
{

    /**
     * @param string $activetab
     * @param int $page
     * @param string $sortby
     * @param string $sortorder
     * @param int $subcategory
     * @return array
     */
    public static function retrieve_assessments(string $activetab, int $page, string $sortby, string $sortorder, int $subcategory = null) {
        global $USER, $OUTPUT, $PAGE;
        $PAGE->set_context(context_system::instance());

        $userid = $USER->id;
        $limit = NUM_ASSESSMENTS_PER_PAGE;
        $offset = $page * $limit;
        $params = [
            'activetab' => $activetab, 
            'page' => $page,
            'sortby' => $sortby, 
            'sortorder' => $sortorder, 
            'subcategory' => $subcategory
        ];
        $url = new \moodle_url('/index.php', $params);
        $totalassessments = 0;
        $data = [];

        $items = self::retrieve_gradable_activities($activetab, $userid, $sortby, $sortorder, $subcategory);

        if ($items) {
            $totalassessments = count($items);
            $paginatedassessments = array_splice($items, $offset, $limit);
            
            foreach ($paginatedassessments as $k => $v) {
                $data[$k] = $v;
            }

            //$pagination = $OUTPUT->paging_bar($totalassessments, $page, $limit, $url);
            
            //$data['pagination'] = $pagination;
        }

        return $data;
    }

    /**
     * @param string $activetab
     * @param int $userid
     * @param string $sortby
     * @param string $sortorder
     * @param int $subcategory
     * 
     * @return array $items
     * @throws dml_exception
     */
    public static function retrieve_gradable_activities(string $activetab = null, int $userid, string $sortby = null, string $sortorder, int $subcategory = null) {
        $gradableactivities = [];

        // Start with getting the top level categories for all courses.
        if (!$subcategory) {
            switch ($activetab) {
                case 'current':
                    $currentcourses = true;
                    $pastcourses = false;
                break;

                case 'past':
                    $currentcourses = false;
                    $pastcourses = true;
                break;

                default:
                    $currentcourses = false;
                    $pastcourses = false;
                break;
            }

            $courses = \local_gugrades\api::dashboard_get_courses($userid, $currentcourses, $pastcourses, $sortby . " " . $sortorder);                    
            $gradableactivities = \block_newgu_spdetails\course::get_course_structure($courses, $currentcourses);
        } else {
            $gradableactivities = \block_newgu_spdetails\activity::get_activityitems($subcategory, $userid, $sortorder);
        }

        return $gradableactivities;
    }

    /**
     * Return the assessments that are due in the next 24 hours, week and month.
     * 
     * @return array
     */
    public static function get_assessmentsduesoon() {
        global $DB, $USER;
        $sortstring = 'shortname asc';
        $courses = \local_gugrades\api::dashboard_get_courses($USER->id, true, false, $sortstring);

        $stats = [
            '24hours' => 0,
            'week' => 0,
            'month' => 0
        ];

        if (!$courses) {
            return $stats;
        }

        // This should probably account for workshop and other submission activities...
        $assignmentsubmissions = $DB->get_fieldset_select('assign_submission', 'id', 'userid = :userid', ['userid' => $USER->id]);
        $assignmentdata = [];
        $now = mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"));
        
        foreach($courses as $course) {
            if ($assignments = $DB->get_records('assign', ['course' => $course->id], 'id', '*',0,0)) {
                foreach($assignments as $assignment) {
                    if (!in_array($assignment->id, $assignmentsubmissions)) {
                        if ($assignment->allowsubmissionsfromdate < $now) {
                            if ($assignment->cuttoffdate == 0 || $assignment->cutoffddate > $now) {
                                $assignmentdata[] = $assignment;
                            }
                        }
                    }
                }
            }
        }

        if (!$assignmentdata) {
            return $stats;
        }

        $next24hours = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+1, date("Y"));
        $next7days = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+7, date("Y"));
        $nextmonth = mktime(date("H"), date("i"), date("s"), date("m")+1, date("d"), date("Y"));

        $duein24hours = 0;
        $duein7days = 0;
        $dueinnextmonth = 0;

        foreach($assignmentdata as $assignment) {
            if (($assignment->duedate > $now) && ($assignment->duedate < $next24hours)) {
                $duein24hours++;
            }

            if (($assignment->duedate > $now) && ($assignment->duedate > $next24hours) && ($assignment->duedate < $next7days)) {
                $duein7days++;
            }

            if (($assignment->duedate > $now) && ($assignment->duedate > $next7days) && ($assignment->duedate < $nextmonth)) {
                $dueinnextmonth++;
            }
        }

        $stats = [
            '24hours' => $duein24hours,
            'week' => $duein7days,
            'month' => $dueinnextmonth
        ];

        return $stats;
    }

    /**
     * Return a summary of current assessments for the student
     * 
     * @TODO - this needs to be refactored to make better use of
     * \local_gugrades\api::dashboard_get_courses instead of
     * self::return_enrolledcourses
     * 
     * @return array
     */
    public static function get_assessmentsummary() {
        global $DB, $USER;

        $marked = 0;
        $total_overdue = 0;
        $total_submissions = 0;
        $total_tosubmit = 0;

        $currentcourses = self::return_enrolledcourses($USER->id, "current");

        $stats = [
            'total_submissions' => 0,
            'total_tosubmit' => 0,
            'total_overdue' => 0,
            'marked' => 0
        ];

        if (!$currentcourses) {
            return $stats;
        }

        
        $str_currentcourses = implode(",", $currentcourses);
        $str_itemsnotvisibletouser = self::fetch_itemsnotvisibletouser($USER->id, $str_currentcourses);

        $records = $DB->get_recordset_sql("SELECT id, courseid, itemmodule, iteminstance FROM {grade_items} WHERE courseid IN (" . $str_currentcourses . ") AND id NOT IN (" . $str_itemsnotvisibletouser . ") AND courseid > 1 AND itemtype='mod'");

        if ($records->valid()) {
            foreach ($records as $key_gi) {

                $modulename = $key_gi->itemmodule;
                $iteminstance = $key_gi->iteminstance;
                $courseid = $key_gi->courseid;
                $itemid = $key_gi->id;

                // security checks first off...
                $context = context_course::instance($courseid);
                self::validate_context($context);
                require_capability('mod/assign:viewownsubmissionsummary', $context, $USER->id);

                $gradestatus = self::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $USER->id);
                $status = $gradestatus["status"];
                $finalgrade = $gradestatus["finalgrade"];

                if ($status == get_string("status_tosubmit", "block_newgu_spdetails")) {
                    $total_tosubmit++;
                }
                if ($status == get_string("status_notsubmitted", "block_newgu_spdetails")) {
                    $total_tosubmit++;
                }
                if ($status == get_string("status_submitted", "block_newgu_spdetails")) {
                    $total_submissions++;
                    if ($finalgrade != Null) {
                        $marked++;
                    }
                }
                if ($status == get_string("status_overdue", "block_newgu_spdetails")) {
                    $total_overdue++;
                }
            }
        }

        $records->close();

        $stats = [
            'total_submissions' => $total_submissions,
            'total_tosubmit' => $total_tosubmit,
            'total_overdue' => $total_overdue,
            'marked' => $marked
        ];

        return $stats;
    }

    /**
     * Retrieves Parent category ids
     *
     * @param string $courseids
     * @return array $ids
     * @throws dml_exception
     * @deprecated - no longer used.
     */
    public static function retrieve_parent_category($courseids) {
        global $DB;

        $courses = implode(', ', $courseids);
        $sql = "SELECT id FROM {grade_categories} WHERE parent IS NULL AND courseid IN ($courses)";
        $uncategorised = $DB->get_records_sql($sql);
        $ids = [];
        foreach ($uncategorised as $key => $value) {
            array_push($ids, $key);
        }
        return $ids;
    }

    /**
     * Checks if user has capability of a student
     *
     * @param int $courseid
     * @param int $userid
     * @return boolean has_capability
     * @throws coding_exception
     */
    public static function return_isstudent($courseid, $userid)
    {
        $context = context_course::instance($courseid);
        return has_capability('moodle/grade:view', $context, $userid, false);
    }

    /**
     * This method returns all courses a user is currently enrolled in.
     * Courses can be filtered by course type and user type.
     *
     * @param int $userid
     * @param string $coursetype
     * @param string $usertype
     * @return array|void
     * @throws dml_exception
     */
    public static function return_enrolledcourses(int $userid, string $coursetype, string $usertype = "student")
    {

        $currentdate = time();
        $coursetypewhere = "";

        global $DB;

        $fields = "c.id, c.fullname as coursename";
        $fieldwhere = "c.visible = 1 AND c.visibleold = 1";

        if ($coursetype == "past") {
            $coursetypewhere = " AND ( c.enddate + (86400 * 30) <=" . $currentdate . " AND c.enddate!=0 )";
        }

        if ($coursetype == "current") {
            $coursetypewhere = " AND ( c.enddate + (86400 * 30) >" . $currentdate . " OR c.enddate=0 )";
        }

        if ($coursetype == "all") {
            $coursetypewhere = "";
        }

        $enrolmentselect = "SELECT DISTINCT e.courseid FROM {enrol} e
                            JOIN {user_enrolments} ue
                            ON (ue.enrolid = e.id AND ue.userid = ?)";

        $enrolmentjoin = "JOIN ($enrolmentselect) en ON (en.courseid = c.id)";

        $sql = "SELECT $fields FROM {course} c $enrolmentjoin
                WHERE $fieldwhere $coursetypewhere";

        $param = [$userid];

        $results = $DB->get_records_sql($sql, $param);

        if ($results) {
            $studentcourses = [];
            $staffcourses = [];
            foreach ($results as $courseid => $courseobject) {

                $coursename = $courseobject->coursename;

                if (self::return_isstudent($courseid, $userid)) {
                    array_push($studentcourses, $courseid);

                } else {
                    $cntstaff = self::checkrole($userid, $courseid);
                    if ($cntstaff != 0) {
                        array_push($staffcourses, ["courseid" => $courseid, "coursename" => $coursename]);
                    }
                }
            }

            if ($usertype == "student") {
                return $studentcourses;
            }

            if ($usertype == "staff") {
                return $staffcourses;
            }

        } else {
            return [];
        }
    }

    /**
     * This function checks that, for a given userid, the user
     * is enrolled on a given course (passed in as courseid).
     *
     * @param $userid
     * @param $courseid
     * @return mixed
     * @throws dml_exception
     */
    public static function checkrole($userid, $courseid)
    {
        global $DB;

        $sql_staff = "SELECT count(*) as cntstaff
             FROM {user} u
             JOIN {user_enrolments} ue ON ue.userid = u.id
             JOIN {enrol} e ON e.id = ue.enrolid
             JOIN {role_assignments} ra ON ra.userid = u.id
             JOIN {context} ct ON ct.id = ra.contextid
             AND ct.contextlevel = 50
             JOIN {course} c ON c.id = ct.instanceid
             AND e.courseid = c.id
             JOIN {role} r ON r.id = ra.roleid
             AND r.shortname in ('staff', 'editingstaff')
             WHERE e.status = 0
             AND u.suspended = 0
             AND u.deleted = 0
             AND ue.status = 0 ";
        if ($courseid != 0) {
            $sql_staff .= " AND c.id = " . $courseid;
        }
        $sql_staff .= " AND u.id = " . $userid;

        $arr_cntstaff = $DB->get_record_sql($sql_staff);
        $cntstaff = $arr_cntstaff->cntstaff;

        return $cntstaff;
    }

    /**
     * @param string $cmdodule
     * @param int $courseid
     * @param int $instance
     * @param return int $cmid
     */
    public static function get_cmid(string $cmodule, int $courseid, int $instance)
    {
        // cmodule is module name e.g. quiz, forums etc.
        global $DB;

        $arr_module = $DB->get_record('modules', array('name' => $cmodule));
        $moduleid = $arr_module->id;

        $arr_coursemodule = $DB->get_record('course_modules', array('course' => $courseid, 'module' => $moduleid, 'instance' => $instance));

        $cmid = $arr_coursemodule->id;

        return $cmid;

    }

    /**
     * Returns a corresponding value for grades with gradetype = "value" and grademax = "22"
     *
     * @param int $grade
     * @param int $idnumber = 1 - Schedule A, 2 - Schedule B
     * @return string 22-grade max point value
     */
    public static function return_22grademaxpoint($grade, $idnumber)
    {
        $values = array('H', 'G2', 'G1', 'F3', 'F2', 'F1', 'E3', 'E2', 'E1', 'D3', 'D2', 'D1',
            'C3', 'C2', 'C1', 'B3', 'B2', 'B1', 'A5', 'A4', 'A3', 'A2', 'A1');
        if ($grade <= 22) {
            $value = $values[$grade];
            if ($idnumber == 2) {
                $stringarray = str_split($value);
                if ($stringarray[0] != 'H') {
                    $value = $stringarray[0] . '0';
                }
            }
            return $value;
        } else {
            return "";
        }
    }

    /**
     * @param $userid
     * @param $strcourses
     * @return string
     */
    public static function fetch_itemsnotvisibletouser($userid, $strcourses)
    {

        global $DB;

        $courses = explode(",", $strcourses);
        $itemsnotvisibletouser = [];
        $itemsnotvisibletouser[] = 0;
        $str_itemsnotvisibletouser = "";

        if ($strcourses != "") {
            foreach ($courses as $courseid) {

                $modinfo = get_fast_modinfo($courseid);
                $cms = $modinfo->get_cms();

                foreach ($cms as $cm) {
                    // Check if course module is visible to the user.
                    $iscmvisible = $cm->uservisible;

                    if (!$iscmvisible) {
                        $sql_modinstance = 'SELECT cm.id, cm.instance, cm.module, m.name FROM {modules} m, {course_modules} cm WHERE cm.id=' . $cm->id . ' AND cm.module=m.id';
                        $arr_modinstance = $DB->get_record_sql($sql_modinstance);
                        $instance = $arr_modinstance->instance;
                        $modname = $arr_modinstance->name;

                        $sql_gradeitemtoexclude = "SELECT id FROM {grade_items} WHERE courseid = " . $courseid . " AND itemmodule='" . $modname . "' AND iteminstance=" . $instance;
                        $arr_gradeitemtoexclude = $DB->get_record_sql($sql_gradeitemtoexclude);
                        if (!empty($arr_gradeitemtoexclude)) {
                            $itemsnotvisibletouser[] = $arr_gradeitemtoexclude->id;
                        }
                    }
                }
            }
            $str_itemsnotvisibletouser = implode(",", $itemsnotvisibletouser);
        }

        return $str_itemsnotvisibletouser;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
     * @deprecated as no longer used - to be removed.
     */

    public static function get_statistics_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    /*
                    'id' => new external_value(PARAM_INT, 'id'),
                    'name' => new external_value(PARAM_TEXT, 'name')
                    */
                    'stathtml' => new external_value(PARAM_RAW, 'stathtml')
                ]
            )
        );
    }

    /**
     * 
     */
    public static function nogroupusers($courseid)
    {
        global $DB;
        $get_groups_sql = "SELECT * FROM {groups} WHERE courseid=" . $courseid;
        $groups = $DB->get_records_sql($get_groups_sql);

        $str_groupids = "0";
        $str_enrolledstudents = "0";

        if (!empty($groups)) {
            $groupoptions = array();
            $arr_groupids = array();
            foreach ($groups as $group) {
                $groupid = $group->id;
                $groupname = $group->name;

                $groupoptions[''] = '--Select--';
                $groupoptions['0'] = 'No Group';
                $groupoptions[$groupid] = $groupname;

                $arr_groupids[] = $group->id;
            }
            $str_groupids = implode(",", $arr_groupids);
        }
        $student_ids = $DB->get_records_sql('SELECT userid FROM {groups_members} WHERE groupid IN (' . $str_groupids . ')');

        if (!empty($student_ids)) {
            $array_enrolledstudents = array();
            foreach ($student_ids as $student_id) {
                $array_enrolledstudents[] = $student_id->userid;
            }

            $str_enrolledstudents = implode(",", $array_enrolledstudents);
        }

        $sql_enrolledstudents = 'SELECT DISTINCT u.id as userid, u.firstname, u.lastname
      FROM {course} c
      JOIN {context} ct ON c.id = ct.instanceid
      JOIN {role_assignments} ra ON ra.contextid = ct.id
      JOIN {user} u ON u.id = ra.userid
      JOIN {role} r ON r.id = ra.roleid
      WHERE r.id=5 AND c.id = ' . $courseid . ' AND u.id NOT IN (' . $str_enrolledstudents . ') ORDER BY u.firstname, u.lastname';


        return $sql_enrolledstudents;
    }

    /**
     * Method to return only LTI's that have "gradable" activities 
     * associated with them - and have been selected to be included.
     * 
     * @throws dml_exception
     * @return mixed array int
     */
    function get_ltiinstancenottoinclude() {
        global $DB;
    
        $str_ltitoinclude = "99999";
        $str_ltinottoinclude = "99999";
        $arr_ltitoinclude = $DB->get_records_sql(
            "SELECT * FROM {config} WHERE name LIKE :configname AND value = :configvalue",
            [
                "configname" => "%block_newgu_spdetails_include_%",
                "configvalue" => 1
            ]
        );
        
        $array_ltitoinclude = [];
        foreach ($arr_ltitoinclude as $key_ltitoinclude) {
            $name = $key_ltitoinclude->name;
            $name_pieces = explode("block_newgu_spdetails_include_",$name);
            $ltitype = $name_pieces[1];
            $array_ltitoinclude[] = $ltitype;
        }

        $str_ltitoinclude = implode(",", $array_ltitoinclude);
    
        if ($str_ltitoinclude == "") {
            $str_ltitoinclude = "99999";
        }
    
        $arr_ltitypenottoinclude = $DB->get_records_sql(
            "SELECT id FROM {lti_types} WHERE id NOT IN (:ltistoinclude)",
            [
                "ltistoinclude" => $str_ltitoinclude
            ]
        );
    
        $array_ltitypenottoinclude = [];
        $array_ltitypenottoinclude[] = 0;

        foreach ($arr_ltitypenottoinclude as $key_ltitypenottoinclude) {
            $array_ltitypenottoinclude[] = $key_ltitypenottoinclude->id;
        }
        
        $str_ltitypenottoinclude = implode(",", $array_ltitypenottoinclude);
    
        $arr_ltiinstancenottoinclude = $DB->get_records_sql(
            "SELECT * FROM {lti} WHERE typeid NOT IN (:ltisnottoinclude)",
            [
                "ltisnottoinclude" => $str_ltitypenottoinclude
            ]
        );
    
        $array_ltiinstancenottoinclude = [];
        
        foreach ($arr_ltiinstancenottoinclude as $key_ltiinstancenottoinclude) {
            $array_ltiinstancenottoinclude[] = $key_ltiinstancenottoinclude->course;
        }
        
        $str_ltiinstancenottoinclude = implode(",", $array_ltiinstancenottoinclude);
    
        if ($str_ltiinstancenottoinclude == "") {
            $str_ltiinstancenottoinclude = 99999;
        }

        return $str_ltiinstancenottoinclude;
    }
}
