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
 *
 * @package    block_newgu_spdetails
 * @author     Shubhendra Diophode <shubhendra.doiphode@gmail.com>
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

define('NUM_ASSESSMENTS_PER_PAGE', 12);

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->libdir . '/gradelib.php');

class block_newgu_spdetails_external extends external_api
{

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_groupusers_parameters()
    {
        return new external_function_parameters(
            array(
                'selected_group' => new external_value(PARAM_INT, 'selected_group', VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'courseid', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Get List of users in selected group
     */
    public static function get_groupusers(int $selected_group = 0, int $courseid = 0)
    {
        global $USER, $DB;


        if ($selected_group == 0) {
            $sql_enrolledstudents = block_newgu_spdetails_external::nogroupusers($courseid);
            $student_ids = $DB->get_records_sql($sql_enrolledstudents);

        } else {
            $sql_groupstudents = 'SELECT DISTINCT gm.userid as userid,u.firstname,u.lastname FROM {groups_members} gm, {user} u WHERE gm.groupid=' . $selected_group . ' AND gm.userid=u.id ORDER BY u.firstname, u.lastname';
            $student_ids = $DB->get_records_sql($sql_groupstudents);
        }

        $result[] = array('id' => 0, 'name' => "Select");

        foreach ($student_ids as $student_id) {

            $studentid = $student_id->userid;
            $studentname = $student_id->firstname . " " . $student_id->lastname;

            $result[] = array('id' => $studentid, 'name' => $student_id->firstname . " " . $student_id->lastname);
        }

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
     */

    public static function get_groupusers_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'id'),
                    'name' => new external_value(PARAM_TEXT, 'name')
                ]
            )
        );
    }

    //========= GROUPS IN COURSE

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_coursegroups_parameters()
    {
        return new external_function_parameters(
            array(
                'selected_course' => new external_value(PARAM_INT, 'selected_course', VALUE_DEFAULT, 0),
                'groupid' => new external_value(PARAM_INT, 'groupid', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Get List of groups in selected course
     */
    public static function get_coursegroups(int $selected_course = 0, int $groupid = 0)
    {
        global $USER, $DB;


        if ($selected_course == 0) {
            // $sql_enrolledstudents = block_newgu_spdetails_external::nogroupusers($courseid);
            // $student_ids = $DB->get_records_sql($sql_enrolledstudents);
            $arr_coursegroups = array();
        } else {
            $sql_coursegroups = 'SELECT id, name FROM {groups} WHERE courseid=' . $selected_course . ' ORDER BY name';
            $arr_coursegroups = $DB->get_records_sql($sql_coursegroups);
        }

        $result[] = array('id' => -1, 'name' => "Select");
        $result[] = array('id' => 0, 'name' => "No Group");

        if (!empty($arr_coursegroups)) {
            foreach ($arr_coursegroups as $key_coursegroups) {

                $groupid = $key_coursegroups->id;
                $groupname = $key_coursegroups->name;

                $result[] = array('id' => $groupid, 'name' => $groupname);
            }
        }
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
     */

    public static function get_coursegroups_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'id'),
                    'name' => new external_value(PARAM_TEXT, 'name')
                ]
            )
        );
    }

    /**
     * Get Statistics Count for Dashboard
     */
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_statistics_parameters()
    {
        return new external_function_parameters(
            array(
                'stathtml' => new external_value(PARAM_RAW, 'stathtml', VALUE_DEFAULT, '')
            )
        );
    }

    /**
     * @param string $activetab
     * @param int $page
     * @param string $sortby
     * @param string $sortorder
     * @param int $subcategory
     * @param string $coursetype
     * @return array
     */
    public static function retrieve_assessments(string $activetab, int $page, string $sortby, string $sortorder, int $subcategory = null, string $coursetype = null) {
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
            'subcategory' => $subcategory,
            'coursetype' => $coursetype
        ];
        $url = new moodle_url('/index.php', $params);
        $totalassessments = 0;
        $data = [];

        $items = self::retrieve_gradable_activities($activetab, $userid, $sortby, $sortorder, $subcategory, $coursetype);

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
     * @param string $coursetype
     * 
     * @return array $items
     * @throws dml_exception
     */
    public static function retrieve_gradable_activities(string $activetab = null, int $userid, string $sortby = null, string $sortorder, int $subcategory = null, string $coursetype = null) {
        global $DB, $USER;
        $items = [];
        $subcatdata = [];

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
            $items = self::return_course_components($courses, $currentcourses);
        } else {
            /**
             * Return Structure:
             * $data = [
             *     'parent' => '',
             *     'coursename' => 'GCAT 2023 TW - Existing GCAT',
             *     'subcatfullname' => 'Summative - Various 22 Point Scale Aggregations - course weighting 75%',
             *     'weight' => '75%',
             *     'coursetype' => 'gcatenabled',
             *     'assessmentitems' => [
             *         [
             *           "id" => 27,
             *           "name" => "Average of assignments - Sub components - Simple Weighted Mean"
             *           "assessmenttype" => "Average",
             *           "weight" => ""
             *         ]
             *     ],
             *     'subcategories' => [
             *         [
             *           "id" => 27,
             *           "name" => "Average of assignments - Sub components - Simple Weighted Mean"
             *           "assessmenttype" => "Average",
             *           "weight" => ""
             *         ],
             *     ]
             */
            
            $data = [];
            $coursedata = [];
            $subcat = grade_category::fetch(['id' => $subcategory]);
            
            // What's my parent?
            $parent = grade_category::fetch(['id' => $subcat->parent]);
            if ($parent->parent == null) {
                $parentId = 0;
            } else {
                $parentId = $parent->id;
            }
            $data['parent'] = $parentId;
   
            $courseid = $subcat->courseid;
            $course = get_course($courseid);
            $coursedata['coursename'] = $course->shortname;
            $coursedata['subcatfullname'] = $subcat->fullname;
            $item = grade_item::fetch(['courseid' => $course->id,'iteminstance' => $subcategory, 'itemtype' => 'category']);
            
            // The assessment type is derived from the parent - which works only 
            // as long as the parent name contains 'Formative' or 'Summative'...
            $assessmenttype = self::return_assessmenttype($subcat->fullname, $item->aggregationcoef);
            $weight = self::return_weight($assessmenttype, $subcat->aggregation, $item->aggregationcoef, $item->aggregationcoef2, $subcat->fullname);
            $coursedata['weight'] = $weight;
            $coursedata['coursetype'] = $coursetype;
            $assessmentdata = [];
            
            // Go and retrieve all the items/further sub categories for this category.
            //
            // We'll need to merge these arrays at some point, to allow the sorting to
            // to work on all items, rather than by category/activity item
            $assessmentitems = grade_item::fetch_all(['categoryid' => $subcategory]);
            if ($assessmentitems && count($assessmentitems) > 0) {
                
                // Owing to the fact that we can't sort using the grade_item method....
                switch($sortorder) {
                    case "asc":
                        asort($assessmentitems, SORT_REGULAR);
                        break;

                    case "desc":
                        arsort($assessmentitems, SORT_REGULAR);
                        break;
                }

                foreach($assessmentitems as $assessmentitem) {
                    $assessmentweight = self::return_weight($assessmenttype, $subcat->aggregation, $assessmentitem->aggregationcoef, $assessmentitem->aggregationcoef2, $assessmentitem->itemname);
                    $gradestatus = self::return_gradestatus($assessmentitem->itemmodule, $assessmentitem->iteminstance, $assessmentitem->courseid, $assessmentitem->id, $USER->id);
                    $feedback = self::get_gradefeedback($assessmentitem->itemmodule, $assessmentitem->iteminstance, $assessmentitem->courseid, $assessmentitem->id, $USER->id, $assessmentitem->grademax, $assessmentitem->gradetype);
                    $duedate = DateTime::createFromFormat('U', $gradestatus['duedate']);
                    $assessmentdata[] = [
                        'id' => $assessmentitem->id,
                        'assessmenturl' => $gradestatus['assessmenturl'],
                        'itemname' => $assessmentitem->itemname,
                        'assessmenttype' => $assessmenttype,
                        'assessmentweight' => $assessmentweight,
                        'duedate' => $duedate->format('jS F Y'),
                        'status' => $gradestatus['status'],
                        'link' => $gradestatus['link'],
                        'status_class' => $gradestatus['status_class'],
                        'status_text' => $gradestatus['status_text'],
                        'grade' => (($gradestatus['finalgrade'] > 0) ? $gradestatus['finalgrade'] : get_string("status_text_tobeconfirmed", "block_newgu_spdetails")),
                        'feedback' => $feedback['gradetodisplay']
                    ];
                }

                $coursedata['assessmentitems'] = $assessmentdata;
            }

            $subcategories = grade_category::fetch_all(['parent' => $subcategory, 'hidden' => 0]);

            if ($subcategories && count($subcategories) > 0) {
                
                // Owing to the fact that we can't sort using the grade_category method....
                switch($sortorder) {
                    case "asc":
                        asort($subcategories, SORT_REGULAR);
                        break;

                    case "desc":
                        arsort($subcategories, SORT_REGULAR);
                        break;
                }
                
                foreach($subcategories as $subcategory) {
                    $item = grade_item::fetch(['courseid' => $course->id,'iteminstance' => $subcategory->id, 'itemtype' => 'category']);
                    $subcatweight = self::return_weight($assessmenttype, $subcategory->aggregation, $item->aggregationcoef, $item->aggregationcoef2, $subcategory->fullname);
                    $subcatdata[] = [
                        'id' => $subcategory->id,
                        'name' => $subcategory->fullname,
                        'assessmenttype' => $assessmenttype,
                        'subcatweight' => $subcatweight,
                        'coursetype' => $coursetype
                    ];
                }
                $coursedata['subcategories'] = $subcatdata;
            }

            $data['coursedata'] = $coursedata;
            
            $items = $data;
        }

        return $items;
    }

    /**
     * Given an array of 1 or more courses, return pertinent information.
     * 
     * @param array $courses - an array of courses the user is enrolled in
     * @param bool $active - boolean to indicate current or past course(s)
     * @param return array $data
     */
    public static function return_course_components(array $courses, bool $active) {
        
        $coursedata = [];
        $data = [
            'parent' => 0
        ];

        if (!$courses) {
            return $data;
        }

        foreach($courses as $course) {
            // Fetch the Summative and Formative categories...
            $parent = grade_category::fetch(['courseid' => $course->id, 'hidden' => 0, 'parent' => NULL]);
            $coursedata['coursename'] = $course->shortname;
            $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            $coursedata['courseurl'] = $courseurl->out();
            
            if (!$active) {
                $startdate = DateTime::createFromFormat('U', $course->startdate);
                $enddate = DateTime::createFromFormat('U', $course->enddate);
                $coursedata['startdate'] = $startdate->format('jS F Y');
                $coursedata['enddate'] = $enddate->format('jS F Y');
            }
            
            $subcatdata = [];
            if (isset($course->firstlevel) && count($course->firstlevel) > 0) {
                foreach($course->firstlevel as $subcategory) {
                    $subcatid = 0;
                    $subcatname = '';
                    $subcatid = $subcategory['id'];
                    $subcatname = $subcategory['fullname'];
                    $item = grade_item::fetch(['courseid' => $course->id,'iteminstance' => $subcatid, 'itemtype' => 'category']);
                    $assessmenttype = self::return_assessmenttype($subcatname, $item->aggregationcoef);
                    $subcatweight = self::return_weight($assessmenttype, $parent->aggregation, $item->aggregationcoef, $item->aggregationcoef2, $subcatname);
                    $coursetype = (($course->gugradesenabled) ? 'gugradesenabled' : (($course->gcatenabled) ? 'gcatenabled' : 'gradebook'));
                    $subcatdata[] = [
                        'id' => $subcatid,
                        'name' => $subcatname,
                        'assessmenttype' => $assessmenttype,
                        'subcatweight' => $subcatweight,
                        'coursetype' => $coursetype
                    ];
                }
            }

            $coursedata['subcategories'] = $subcatdata;
            $data['coursedata'][] = $coursedata;
        }

        if (!$active) {
            $data['hasstartdate'] = true;
            $data['hasenddate'] = true;
        }

        return $data;
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
     * @return array
     */
    public static function get_assessmentsummary() {
        global $DB, $USER;

        $marked = 0;
        $total_overdue = 0;
        $total_submissions = 0;
        $total_tosubmit = 0;

        $currentcourses = \block_newgu_spdetails_external::return_enrolledcourses($USER->id, "current");

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
        $str_itemsnotvisibletouser = \block_newgu_spdetails_external::fetch_itemsnotvisibletouser($USER->id, $str_currentcourses);

        $records = $DB->get_recordset_sql("SELECT id, courseid, itemmodule, iteminstance FROM {grade_items} WHERE courseid IN (" . $str_currentcourses . ") AND id NOT IN (" . $str_itemsnotvisibletouser . ") AND courseid > 1 AND itemtype='mod'");

        if ($records->valid()) {
            foreach ($records as $key_gi) {

                $modulename = $key_gi->itemmodule;
                $iteminstance = $key_gi->iteminstance;
                $courseid = $key_gi->courseid;
                $itemid = $key_gi->id;

                // security checks first off...
                $context = \context_course::instance($courseid);
                self::validate_context($context);
                require_capability('mod/assign:viewownsubmissionsummary', $context, $USER->id);

                $gradestatus = \block_newgu_spdetails_external::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $USER->id);
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

                if (block_newgu_spdetails_external::return_isstudent($courseid, $userid)) {
                    array_push($studentcourses, $courseid);

                } else {
                    $cntstaff = block_newgu_spdetails_external::checkrole($userid, $courseid);
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

    public static function get_cmid($cmodule, $courseid, $instance)
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
     * Returns the 'assessment type'
     *
     * @param string $gradecategoryname
     * @param int $aggregationcoef
     * @return string 'Formative', 'Summative', or '—'
     */
    public static function return_assessmenttype($gradecategoryname, $aggregationcoef)
    {
        $type = strtolower($gradecategoryname);
        $hasweight = !empty((float)$aggregationcoef);

        if (strpos($type, 'summative') !== false || $hasweight) {
            $assessmenttype = get_string('summative', 'block_newgu_spdetails');
        } else if (strpos($type, 'formative') !== false) {
            $assessmenttype = get_string('formative', 'block_newgu_spdetails');
        } else {
            $assessmenttype = get_string('emptyvalue', 'block_newgu_spdetails');
        }

        return $assessmenttype;
    }

    /**
     * Returns the 'weight' in percentage
     *
     * @param string $assessmenttype
     * @param string $aggregation
     * @param string $aggregationcoef
     * @param string $aggregationcoef2
     * @param string $subcategoryparentfullname
     * 
     * According to the spec, weighting is now derived only from the weight in the Gradebook set up.
     * @see https://gla.sharepoint.com/:w:/s/GCATUpgradeProjectTeam/EVDsT68UetZMn8Ug5ISb394BfYLW_MwcyMI7RF0JAC38PQ?e=BOofAS
     * @return string Weight (in percentage), or '—' if empty
     */
    public static function return_weight($assessmenttype, $aggregation, $aggregationcoef,
                                         $aggregationcoef2, $subcategoryparentfullname)
    {
        $weight = (($aggregationcoef > 1) ? $aggregationcoef : $aggregationcoef * 100);
        $finalweight = ($weight > 0) ? round($weight, 2) . '%' : get_string('emptyvalue', 'block_newgu_spdetails');

        return $finalweight;
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
     * For a given userid, return the current grading status for this assessment item.
     * 
     * @param string $modulename
     * @param int $iteminstance
     * @param int $courseid
     * @param int $itemid
     * @param int $userid
     * @return array
     */
    public static function return_gradestatus(string $modulename, int $iteminstance, int $courseid, int $itemid, int $userid)
    {

        global $DB, $CFG;

        $status = "";
        $statusclass = "";
        $statustext = "";
        $assessmenturl = "";
        $link = "";
        $duedate = 0;
        $allowsubmissionsfromdate = 0;
        $cutoffdate = 0;
        $gradingduedate = 0;
        $provisionalgrade = 0;
        $convertedgrade = 0;
        $provisional_22grademaxpoint = 0;
        $converted_22grademaxpoint = 0;
        $rawgrade = 0;
        $finalgrade = 0;

        $arr_grade = $DB->get_record_sql(
            "SELECT rawgrade,finalgrade FROM {grade_grades} WHERE itemid = :itemid AND userid = :userid",
            [
                'itemid' => $itemid,
                'userid' => $userid
            ]
        );

        if (!empty($arr_grade)) {
            $rawgrade = $arr_grade->rawgrade;
            $finalgrade = $arr_grade->finalgrade;
        
            if (is_null($arr_grade->rawgrade) && !is_null($arr_grade->finalgrade)) {
                $provisionalgrade = $arr_grade->finalgrade;
            }
            if (!is_null($arr_grade->rawgrade) && is_null($arr_grade->finalgrade)) {
                $provisionalgrade = $arr_grade->rawgrade;
            }
        }

        switch ($modulename) {
            case "assign":
                $arr_assign = $DB->get_record("assign", ["id" => $iteminstance]);
                $cmid = block_newgu_spdetails_external::get_cmid("assign", $courseid, $iteminstance);
                $assessmenturl = $CFG->wwwroot . "/mod/assign/view.php?id=" . $cmid;

                if (!empty($arr_assign)) {
                    $allowsubmissionsfromdate = $arr_assign->allowsubmissionsfromdate;
                    $duedate = $arr_assign->duedate;
                    $cutoffdate = $arr_assign->cutoffdate;
                    $gradingduedate = $arr_assign->gradingduedate;
                }

                if ($allowsubmissionsfromdate > time()) {
                    $status = get_string("status_submissionnotopen", "block_newgu_spdetails");
                    $statustext = get_string("status_text_submissionnotopen", "block_newgu_spdetails");
                }

                if ($status == "") {
                    $arr_assignsubmission = $DB->get_record("assign_submission", ["assignment" => $iteminstance, "userid" => $userid]);
                    $link = $CFG->wwwroot . "/mod/assign/view.php?id=" . $cmid;
                    
                    if (!empty($arr_assignsubmission)) {
                        $status = $arr_assignsubmission->status;

                        if ($status == "new") {
                            $status = get_string("status_notsubmitted", "block_newgu_spdetails");
                            $statustext = get_string("status_text_notsubmitted", "block_newgu_spdetails");
                            $statusclass = get_string("status_class_notsubmitted", "block_newgu_spdetails");
                            
                            if (time() > $duedate + (86400 * 30) && $duedate != 0) {
                                $status = get_string("status_overdue", "block_newgu_spdetails");
                                $statusclass = get_string("status_class_overdue", "block_newgu_spdetails");
                                $statustext = get_string("status_text_overdue", "block_newgu_spdetails");
                            }
                        }

                        if ($status == get_string("status_submitted", "block_newgu_spdetails")) {
                            $status = get_string("status_submitted", "block_newgu_spdetails");
                            $statusclass = get_string("status_class_submitted", "block_newgu_spdetails");
                            $statustext = get_string("status_text_submitted", "block_newgu_spdetails");
                            $link = '';
                        }

                    } else {
                        $status = get_string("status_tosubmit", "block_newgu_spdetails");
                        $statustext = get_string("status_text_tosubmit", "block_newgu_spdetails");

                        if (time() > $duedate && $duedate != 0) {
                            $status = get_string("status_notsubmitted", "block_newgu_spdetails");
                            $statustext = get_string("status_text_notsubmitted", "block_newgu_spdetails");
                        }

                        if (time() > $duedate + (86400 * 30) && $duedate != 0) {
                            $status = get_string("status_overdue", "block_newgu_spdetails");;
                            $statusclass = get_string("status_class_overdue", "block_newgu_spdetails");
                            $statustext = get_string("status_text_overdue", "block_newgu_spdetails");
                        }
                    }
                }
                break;

            case "forum":
                $forumsubmissions = $DB->count_records("forum_discussion_subs", ["forum" => $iteminstance, "userid" => $userid]);
                $cmid = block_newgu_spdetails_external::get_cmid('forum', $courseid, $iteminstance);
                $assessmenturl = $CFG->wwwroot . "/mod/forum/view.php?id=" . $cmid;

                if ($forumsubmissions > 0) {
                    $status = get_string("status_submitted", "block_newgu_spdetails");;
                    $statusclass = get_string("status_class_submitted", "block_newgu_spdetails");
                    $statustext = get_string("status_text_submitted", "block_newgu_spdetails");
                } else {
                    $status = get_string("status_tosubmit", "block_newgu_spdetails");;
                    $statusclass = get_string("status_class_submit", "block_newgu_spdetails");
                    $statustext = get_string("status_text_submit", "block_newgu_spdetails");
                    $link = $CFG->wwwroot . "/mod/forum/view.php?id=" . $cmid;
                }
                break;

            case "quiz":
                $cmid = block_newgu_spdetails_external::get_cmid("quiz", $courseid, $iteminstance);
                $assessmenturl = $CFG->wwwroot . "/mod/quiz/view.php?id=" . $cmid;

                $quizattempts = $DB->count_records("quiz_attempts", ["quiz" => $iteminstance, "userid" => $userid, "state" => "finished"]);
                if ($quizattempts > 0) {
                    $status = get_string("status_submitted", "block_newgu_spdetails");
                    $statusclass = get_string("status_class_submitted", "block_newgu_spdetails");
                    $statustext = get_string("status_text_submitted", "block_newgu_spdetails");
                } else {
                    $status = get_string("status_tosubmit", "block_newgu_spdetails");
                    $statusclass = get_string("status_class_submit", "block_newgu_spdetails");
                    $statustext = get_string("status_text_submit", "block_newgu_spdetails");
                    $link = $CFG->wwwroot . "/mod/quiz/view.php?id=" . $cmid;
                }
                break;

            case "workshop":
                $arr_workshop = $DB->get_record("workshop", ["id" => $iteminstance]);
                $cmid = block_newgu_spdetails_external::get_cmid("workshop", $courseid, $iteminstance);
                $assessmenturl = $CFG->wwwroot . "/mod/workshop/view.php?id=" . $cmid;

                $workshopsubmissions = $DB->count_records("workshop_submissions", ["workshopid" => $iteminstance, "authorid" => $userid]);
                if ($workshopsubmissions > 0) {
                    $status = get_string("status_submitted", "block_newgu_spdetails");
                    $statusclass = get_string("status_class_submitted", "block_newgu_spdetails");
                    $statustext = get_string("status_text_submitted", "block_newgu_spdetails");
                } else {
                    $status = get_string("status_tosubmit", "block_newgu_spdetails");
                    $statusclass = get_string("status_class_submit", "block_newgu_spdetails");
                    $statustext = get_string("status_text_submit", "block_newgu_spdetails");
                    if ($arr_workshop->submissionstart == 0) {
                        $status = get_string("status_submissionnotopen", "block_newgu_spdetails");
                        $statusclass = "";
                        $statustext = get_string("status_text_submissionnotopen", "block_newgu_spdetails");
                    }
                    $link = $CFG->wwwroot . "/mod/workshop/view.php?id=" . $cmid;
                }
                break;

            default :
            break;
        }

        $arr_grades = $DB->get_record("grade_grades", ["itemid" => $itemid, "userid" => $userid]);

        if (!empty($arr_grades)) {
            $finalgrade = $arr_grades->finalgrade;
        }

        if ((is_int($rawgrade) && floor($rawgrade) > 0) && (is_int($finalgrade) && floor($finalgrade) == 0)) {
            $provisional_22grademaxpoint = block_newgu_spdetails_external::return_22grademaxpoint((floor($rawgrade)) - 1, 1);
        }
        
        if ((is_int($finalgrade) && floor($finalgrade) > 0)) {
            $converted_22grademaxpoint = block_newgu_spdetails_external::return_22grademaxpoint((floor($finalgrade)) - 1, 1);
        }

        $gradestatus = [
            "status" => $status,
            "status_class" => $statusclass,
            "status_text" => $statustext,
            "assessmenturl" => $assessmenturl,
            "link" => $link,
            "allowsubmissionsfromdate" => $allowsubmissionsfromdate,
            "duedate" => $duedate,
            "cutoffdate" => $cutoffdate,
            "rawgrade" => $rawgrade,
            "finalgrade" => $finalgrade,
            "gradingduedate" => $gradingduedate,
            "provisionalgrade" => $provisionalgrade,
            "convertedgrade" => $convertedgrade,
            "provisional_22grademaxpoint" => $provisional_22grademaxpoint,
            "converted_22grademaxpoint" => $converted_22grademaxpoint,
        ];

        return $gradestatus;
    }


    /**
     * This method returns a summary of the gradable items
     * that a student currently has.
     *
     * Note that the main query is only ever executed on the
     * first call of this method, then again after 2 hours.
     * Values are stored in the session - for performance
     * reasons presumably.
     *
     * @return array
     */
    public static function get_statistics()
    {

        global $DB, $USER, $SESSION;

        $sub_assess = 0;
        $tobe_sub = 0;
        $overdue = 0;
        $assess_marked = 0;
        $total_overdue = 0;
        $total_submissions = 0;
        $total_tosubmit = 0;
        $marked = 0;
        $currenttime = time();
        $twohours = $currenttime - 7200;

        if (!isset($SESSION->statscount) || $SESSION->statscount["timeupdated"] < $twohours) {

            $currentcourses = block_newgu_spdetails_external::return_enrolledcourses($USER->id, "current");

            if (!empty($currentcourses)) {
                $str_currentcourses = implode(",", $currentcourses);

                $str_itemsnotvisibletouser = block_newgu_spdetails_external::fetch_itemsnotvisibletouser($USER->id, $str_currentcourses);

                $rs_gi = $DB->get_recordset_sql("SELECT id, courseid, itemmodule, iteminstance FROM {grade_items} WHERE courseid IN (" . $str_currentcourses . ") AND id NOT IN (" . $str_itemsnotvisibletouser . ") AND courseid > 1 AND itemtype='mod'");

                if ($rs_gi->valid()) {
                    foreach ($rs_gi as $key_gi) {
                        $modulename = $key_gi->itemmodule;
                        $iteminstance = $key_gi->iteminstance;
                        $courseid = $key_gi->courseid;
                        $itemid = $key_gi->id;

                        $gradestatus = block_newgu_spdetails_external::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $USER->id);
                        $status = $gradestatus["status"];
                        $finalgrade = $gradestatus["finalgrade"];

                        if ($status == 'tosubmit') {
                            $total_tosubmit++;
                        }
                        if ($status == 'notsubmitted') {
                            $total_tosubmit++;
                        }
                        if ($status == 'submitted') {
                            $total_submissions++;
                            if ($finalgrade != Null) {
                                $marked++;
                            }
                        }
                        if ($status == "overdue") {
                            $total_overdue++;
                        }
                    }
                }
                $rs_gi->close();

                $sub_assess = $total_submissions;
                $tobe_sub = $total_tosubmit;
                $overdue = $total_overdue;
                $assess_marked = $marked;

                $statscount = [
                    "timeupdated" => time(),
                    "sub_assess" => $total_submissions,
                    "tobe_sub" => $total_tosubmit,
                    "overdue" => $total_overdue,
                    "assess_marked" => $marked
                ];

                $SESSION->statscount = $statscount;
            }
        } else {
            $sub_assess = $SESSION->statscount["sub_assess"];
            $tobe_sub = $SESSION->statscount["tobe_sub"];
            $overdue = $SESSION->statscount["overdue"];
            $assess_marked = $SESSION->statscount["assess_marked"];
        }

        $html = '';
        $html .= html_writer::start_tag('div', array('class' => 'assessments-overview-container border rounded my-2 p-2'));
        $html .= html_writer::tag('h4', get_string('headingataglance', 'block_newgu_spdetails'));

        $html .= html_writer::start_tag('div', array('class' => 'row'));
        $html .= html_writer::start_tag('div', array('class' => 'assessments-item assessments-submitted col-md-3 col-sm-6 col-xs-12'));
        $html .= html_writer::tag('h1', $sub_assess, array('class' => 'assessments-item-count h1'));
        $html .= html_writer::tag('p', get_string('assessment', 'block_newgu_spdetails') . ' ' . get_string('submitted', 'block_newgu_spdetails'), array('class' => 'assessments-item-label'));
        $html .= html_writer::end_tag('div');

        $html .= html_writer::start_tag('div', array('class' => 'assessments-item assessments-submitted col-md-3 col-sm-6 col-xs-12'));
        $html .= html_writer::tag('h1', $tobe_sub, array('class' => 'assessments-item-count h1', 'style' => 'color: #CC5500'));
        $html .= html_writer::tag('p', get_string('tobesubmitted', 'block_newgu_spdetails'), array('class' => 'assessments-item-label'));
        $html .= html_writer::end_tag('div');

        $html .= html_writer::start_tag('div', array('class' => 'assessments-item assessments-submitted col-md-3 col-sm-6 col-xs-12'));
        $html .= html_writer::tag('h1', $overdue, array('class' => 'assessments-item-count h1', 'style' => 'color: red'));
        $html .= html_writer::tag('p', get_string('overdue', 'block_newgu_spdetails'), array('class' => 'assessments-item-label'));
        $html .= html_writer::end_tag('div');

        $html .= html_writer::start_tag('div', array('class' => 'assessments-item assessments-submitted col-md-3 col-sm-6 col-xs-12'));
        $html .= html_writer::tag('h1', $assess_marked, array('class' => 'assessments-item-count h1', 'style' => 'color: green'));
        $html .= html_writer::tag('p', get_string('assessments', 'block_newgu_spdetails') . ' ' . get_string('marked', 'block_newgu_spdetails'), array('class' => 'assessments-item-label'));
        $html .= html_writer::end_tag('div');

        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');

        $result[] = ['stathtml' => $html];

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
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
     * Method to return grading feedback.
     * 
     * @param string $modulename
     * @param int $iteminstance
     * @param int $courseid
     * @param int $itemid
     * @param int $userid
     * @param int $grademax
     * @param string $gradetype
     * @param return array
     */
    public static function get_gradefeedback(string $modulename, int $iteminstance, int $courseid, int $itemid, int $userid, int $grademax, string $gradetype) {
        global $CFG, $DB, $USER;
        
        $link = "";
        $gradetodisplay = "";
        
        $gradestatus = block_newgu_spdetails_external::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $userid);
        $status = $gradestatus["status"];
        $link = $gradestatus["link"];
        $allowsubmissionsfromdate = $gradestatus["allowsubmissionsfromdate"];
        $duedate = $gradestatus["duedate"];
        $cutoffdate = $gradestatus["cutoffdate"];
        $gradingduedate = $gradestatus["gradingduedate"];
        $rawgrade = $gradestatus["rawgrade"];
        $finalgrade = $gradestatus["finalgrade"];
        $provisional_22grademaxpoint = $gradestatus["provisional_22grademaxpoint"];
        $converted_22grademaxpoint = $gradestatus["converted_22grademaxpoint"];
        
        $cmid = block_newgu_spdetails_external::get_cmid($modulename, $courseid, $iteminstance);
        
        if ($finalgrade != null) {
            if ($gradetype == 1) {
                $gradetodisplay = '<span class="graded">' . number_format((float)$finalgrade) . " / " . number_format((float)$grademax) . '</span>' . ' (Provisional)';
            }
            
            if ($gradetype == 2) {
                $gradetodisplay = '<span class="graded">' . $converted_22grademaxpoint . '</span>' . ' (Provisional)';
            }

            $link = $CFG->wwwroot . '/mod/'.$modulename.'/view.php?id=' . $cmid . '#page-footer';
        }
        
        if ($finalgrade == null  && $duedate < time()) {
            if ($status == "notopen" || $status == "notsubmitted") {
                $gradetodisplay = get_string("feedback_tobeconfirmed", "block_newgu_spdetails");
                $link = "";
            }
            if ($status == "overdue") {
                $gradetodisplay = get_string("status_text_overdue", "block_newgu_spdetails");
                $link = "";
            }
            if ($status == "notsubmitted") {
                $gradetodisplay = get_string("status_text_notsubmitted", "block_newgu_spdetails");
                if ($gradingduedate > time()) {
                    $gradetodisplay = "Due " . date("d/m/Y",$gradingduedate);
                }
            }
        
        }
        
        if ($status == "tosubmit") {
            $gradetodisplay = get_string("feedback_tobeconfirmed", "block_newgu_spdetails");
            $link = "";
        }
        
        return [
            "gradetodisplay" => $gradetodisplay, 
            "link" => $link, 
            "provisional_22grademaxpoint" => $provisional_22grademaxpoint, 
            "converted_22grademaxpoint" => $converted_22grademaxpoint, 
            "finalgrade" => floor($finalgrade), 
            "rawgrade" => floor($rawgrade)
        ];
    }

    /**
     * Method to return only LTI's that have "gradeable" activities 
     * associated with them - and have been selected to be included.
     * 
     * @throws dml_exception
     * @return mixed array int
     */
    function get_ltiinstancenottoinclude() {
        global $DB;
    
        $str_ltitoinclude = "99999";
        $str_ltinottoinclude = "99999";
        $sql_ltitoinclude = "SELECT * FROM {config} WHERE name like '%block_newgu_spdetails_include_%' AND value=1";
        $arr_ltitoinclude = $DB->get_records_sql($sql_ltitoinclude);
        
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
            $array_ltiinstancenottoinclude[] = $key_ltiinstancenottoinclude->id;
        }
        
        $str_ltiinstancenottoinclude = implode(",", $array_ltiinstancenottoinclude);
    
        if ($str_ltiinstancenottoinclude == "") {
            $str_ltiinstancenottoinclude = 99999;
        }

        return $str_ltiinstancenottoinclude;
    }
}
