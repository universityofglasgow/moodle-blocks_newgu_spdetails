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
  * @category   external
  * @copyright  2023 NEW GU
  * @author
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');

class block_newgu_spdetails_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_groupusers_parameters() {
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
    public static function get_groupusers(int $selected_group = 0,int $courseid = 0) {
        global $USER, $DB;


    if ($selected_group==0) {
      $sql_enrolledstudents = block_newgu_spdetails_external::nogroupusers($courseid);
      $student_ids = $DB->get_records_sql($sql_enrolledstudents);

    } else {
      $sql_groupstudents = 'SELECT DISTINCT gm.userid as userid,u.firstname,u.lastname FROM {groups_members} gm, {user} u WHERE gm.groupid='. $selected_group .' AND gm.userid=u.id ORDER BY u.firstname, u.lastname';
      $student_ids = $DB->get_records_sql($sql_groupstudents);
    }

    $result[] = array('id'=>0, 'name' => "Select");

    foreach ($student_ids as $student_id) {

        $studentid = $student_id->userid;
        $studentname = $student_id->firstname . " " . $student_id->lastname;

        $result[] = array('id'=>$studentid, 'name' => $student_id->firstname . " " . $student_id->lastname);
    }

    return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
     */

    public static function get_groupusers_returns() {
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
    public static function get_coursegroups_parameters() {
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
    public static function get_coursegroups(int $selected_course = 0,int $groupid = 0) {
        global $USER, $DB;


    if ($selected_course==0) {
      // $sql_enrolledstudents = block_newgu_spdetails_external::nogroupusers($courseid);
      // $student_ids = $DB->get_records_sql($sql_enrolledstudents);
      $arr_coursegroups = array();
    } else {
      $sql_coursegroups = 'SELECT id, name FROM {groups} WHERE courseid='. $selected_course . ' ORDER BY name';
      $arr_coursegroups = $DB->get_records_sql($sql_coursegroups);
    }

    $result[] = array('id'=>-1, 'name' => "Select");
    $result[] = array('id'=>0, 'name' => "No Group");

    if (!empty($arr_coursegroups)) {
      foreach ($arr_coursegroups as $key_coursegroups) {

          $groupid = $key_coursegroups->id;
          $groupname = $key_coursegroups->name;

          $result[] = array('id'=>$groupid, 'name' => $groupname);
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

    public static function get_coursegroups_returns() {
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
     public static function get_statistics_parameters() {
         return new external_function_parameters(
           array(
             'stathtml' => new external_value(PARAM_RAW, 'stathtml', VALUE_DEFAULT, '')
           )
         );
     }




     /**
      * Checks if user has capability of a student
      *
      * @param int $courseid
      * @param int $userid
      * @return boolean has_capability
      */
     public static function return_isstudent($courseid, $userid) {
         $context = context_course::instance($courseid);
         return has_capability('moodle/grade:view', $context, $userid, false);
     }



     public static function return_enrolledcourses($userid, $coursetype, $usertype="student") {

             $currentdate = time();
     //        $plusonemonth = strtotime("+1 month", $currentdate);

             $coursetypewhere = "";

             global $DB;

             $fields = "c.id, c.fullname as coursename";
     /*        $customfieldjoin = "JOIN {customfield_field} cff
                                 ON cff.shortname = 'show_on_studentdashboard'
                                 JOIN {customfield_data} cfd
                                 ON (cfd.fieldid = cff.id AND cfd.instanceid = c.id)"; */
     //        $customfieldwhere = "cfd.value = 1 AND c.visible = 1 AND c.visibleold = 1";
             $fieldwhere = "c.visible = 1 AND c.visibleold = 1";

             if ($coursetype=="past") {
               $coursetypewhere = " AND ( c.enddate + (86400 * 30) <=" . $currentdate . " AND c.enddate!=0 )";
             }
             if ($coursetype=="current") {
               $coursetypewhere = " AND ( c.enddate + (86400 * 30) >" . $currentdate . " OR c.enddate=0 )";
             }
             if ($coursetype=="all") {
               $coursetypewhere = "";
             }

             $enrolmentselect = "SELECT DISTINCT e.courseid FROM {enrol} e
                                 JOIN {user_enrolments} ue
                                 ON (ue.enrolid = e.id AND ue.userid = ?)";
             $enrolmentjoin = "JOIN ($enrolmentselect) en ON (en.courseid = c.id)";
             /*
             $sql = "SELECT $fields FROM {course} c $customfieldjoin $enrolmentjoin
                     WHERE $customfieldwhere $coursetypewhere";
             */
             $sql = "SELECT $fields FROM {course} c $enrolmentjoin
                     WHERE $fieldwhere $coursetypewhere";
     //echo $coursetype . " " . $sql . "<br/><br>";

             $param = array($userid);

             $results = $DB->get_records_sql($sql, $param);
     // echo $userid;
     // echo $sql;
     //         print_r($results);


             if ($results) {
                 $studentcourses = array();
                 $staffcourses = array();
                 foreach ($results as $courseid => $courseobject) {

                   $coursename = $courseobject->coursename;
                   $cnt_totalclose = 0;
                   $cnt_quizclose = 0;
                   $cnt_assignclose = 0;
                   $cnt_forumclose = 0;
                   $cnt_workshopclose = 0;

                     if (block_newgu_spdetails_external::return_isstudent($courseid, $userid)) {

                         /* CHECK IF ANY ASSIGNMENT HAS DUEDATE (timeclose) + 1 MONTH IS LESS THAN CURRENTIMESTAMP
                           AND ADD IT IN ARRAY IF CONDITION IS FOR PAST TAB
                         */

                         /*
                         // QUIZ
                         if ($coursetype=="past") {
     //                        $sql_quizclose = 'SELECT COUNT(*) AS cnt_quizclose FROM {quiz} WHERE course=' . $courseid . ' AND timeclose!=0 AND timeclose>=' . $plusonemonth;
                             $sql_quizclose = 'SELECT COUNT(*) AS cnt_quizclose FROM {quiz} WHERE course=' . $courseid . ' AND timeclose!=0 AND UNIX_TIMESTAMP(TIMESTAMPADD(MONTH, 1, from_unixtime(timeclose))) <=' . $currentdate;
     //                        " AND UNIX_TIMESTAMP(TIMESTAMPADD(MONTH, 1, from_unixtime(c.enddate))) <=" . $currentdate;
                             $arr_quizclose = $DB->get_record_sql($sql_quizclose);
                             $cnt_quizclose = $arr_quizclose->cnt_quizclose;

                             $cnt_totalclose += $cnt_quizclose;

                         // ASSIGN
                             $sql_assignclose = 'SELECT COUNT(*) AS cnt_assignclose FROM {assign} WHERE course=' . $courseid . ' AND duedate!=0 AND UNIX_TIMESTAMP(TIMESTAMPADD(MONTH, 1, from_unixtime(duedate))) <=' . $currentdate;
                             $arr_assignclose = $DB->get_record_sql($sql_assignclose);
                             $cnt_assignclose = $arr_assignclose->cnt_assignclose;

                             $cnt_totalclose += $cnt_assignclose;

                         // FORUM
                             $sql_forumclose = 'SELECT COUNT(*) AS cnt_forumclose FROM {forum} WHERE course=' . $courseid . ' AND duedate!=0 AND UNIX_TIMESTAMP(TIMESTAMPADD(MONTH, 1, from_unixtime(duedate))) <=' . $currentdate;
                             $arr_forumclose = $DB->get_record_sql($sql_forumclose);
                             $cnt_forumclose = $arr_forumclose->cnt_forumclose;

                             $cnt_totalclose += $cnt_forumclose;

                         // WORKSHOP
                             $sql_workshopclose = 'SELECT COUNT(*) AS cnt_workshopclose FROM {workshop} WHERE course=' . $courseid . ' AND assessmentend!=0 AND UNIX_TIMESTAMP(TIMESTAMPADD(MONTH, 1, from_unixtime(assessmentend))) <=' . $currentdate;
                             $arr_workshopclose = $DB->get_record_sql($sql_workshopclose);
                             $cnt_workshopclose = $arr_workshopclose->cnt_workshopclose;

                             $cnt_totalclose += $cnt_workshopclose;

                         }
     */
     //echo "<br/>" . $courseid . " / " . $cnt_totalclose . "<br/>";


                         //if ($cnt_totalclose!=0) {
                           array_push($studentcourses, $courseid);
                         //}

                     } else {

                     $cntstaff = block_newgu_spdetails_external::checkrole($userid, $courseid);
                     if ($cntstaff!=0) {
                         array_push($staffcourses, array("courseid"=>$courseid, "coursename"=>$coursename));
                     }
                   }

                 }
                 if ($usertype=="student") {
                   return $studentcourses;
                 }
                 if ($usertype=="staff") {
                   return $staffcourses;
                 }
             } else {
                 return array();
             }

         }

         public static function checkrole($userid, $courseid) {
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
             if ($courseid!=0) {
             $sql_staff .= " AND c.id = " . $courseid;
             }
             $sql_staff .= " AND u.id = " . $userid;

             $arr_cntstaff = $DB->get_record_sql($sql_staff);
             $cntstaff = $arr_cntstaff->cntstaff;

             return $cntstaff;
         }



         public static function get_cmid($cmodule, $courseid, $instance) {
           // cmodule is module name e.g. quiz, forums etc.
           global $DB;

           $arr_module = $DB->get_record('modules', array('name'=>$cmodule));
           $moduleid = $arr_module->id;

           $arr_coursemodule = $DB->get_record('course_modules', array('course'=>$courseid, 'module'=>$moduleid, 'instance'=>$instance));

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
         public static function return_22grademaxpoint($grade, $idnumber) {
             $values = array('H', 'G2', 'G1', 'F3', 'F2', 'F1', 'E3', 'E2', 'E1', 'D3', 'D2', 'D1',
                             'C3', 'C2', 'C1', 'B3', 'B2', 'B1', 'A5', 'A4', 'A3', 'A2', 'A1');
             $value = $values[$grade];
             if ($idnumber == 2) {
                 $stringarray = str_split($value);
                 if ($stringarray[0] != 'H') {
                     $value = $stringarray[0] . '0';
                 }
             }
             return $value;
         }


         /**
          * Returns the 'assessment type'
          *
          * @param string $gradecategoryname
          * @param int $aggregationcoef
          * @return string 'Formative', 'Summative', or '—'
          */
         public static function return_assessmenttype($gradecategoryname, $aggregationcoef) {
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
          * @return string Weight (in percentage), or '—' if empty
          */
         public static function return_weight($assessmenttype, $aggregation, $aggregationcoef,
                                              $aggregationcoef2, $subcategoryparentfullname) {
             $summative = get_string('summative', 'block_newgu_spdetails');

             // If $aggregation == '10', meaning 'Weighted mean of grades' is used.
             $weight = ($aggregation == '10') ?
                         (($aggregationcoef > 1) ? $aggregationcoef : $aggregationcoef * 100) :
                         (($assessmenttype === $summative || $subcategoryparentfullname === $summative) ?
                             $aggregationcoef2 * 100 : 0);

             $finalweight = ($weight > 0) ? round($weight, 2).'%' : get_string('emptyvalue', 'block_newgu_spdetails');

             return $finalweight;
         }



         public static function fetch_itemsnotvisibletouser($userid, $strcourses) {

           global $DB;

           $courses = explode(",", $strcourses);

           $itemsnotvisibletouser = array();

           $itemsnotvisibletouser[] = 0;

           $str_itemsnotvisibletouser = "";

           if ($strcourses!="") {
           foreach ($courses as $courseid) {

             $modinfo = get_fast_modinfo($courseid);
             $cms = $modinfo->get_cms();

             foreach($cms as $cm) {
         // Check if course module is visible to the user.
               $iscmvisible = $cm->uservisible;

                         if (!$iscmvisible) {
                           $sql_modinstance = 'SELECT cm.id, cm.instance, cm.module, m.name FROM {modules} m, {course_modules} cm WHERE cm.id=' . $cm->id . ' AND cm.module=m.id';
                           $arr_modinstance = $DB->get_record_sql($sql_modinstance);
                           $instance = $arr_modinstance->instance;
                           $module = $arr_modinstance->module;
                           $cmid = $arr_modinstance->id;
                           $modname = $arr_modinstance->name;

                           $sql_gradeitemtoexclude = "SELECT id FROM {grade_items} WHERE courseid = " . $courseid . " AND itemmodule='". $modname ."' AND iteminstance=" . $instance;
                           $arr_gradeitemtoexclude = $DB->get_record_sql($sql_gradeitemtoexclude);
                           if (!empty($arr_gradeitemtoexclude)) {
                             $itemsnotvisibletouser[] = $arr_gradeitemtoexclude->id;
                           }
                         }

               }
           }
             $str_itemsnotvisibletouser = implode(",",$itemsnotvisibletouser);
         }

           return $str_itemsnotvisibletouser;

         }


         public static function return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $userid) {

           global $DB, $USER, $CFG;

           $status = "";
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

           $sql_grade = "SELECT rawgrade,finalgrade FROM {grade_grades} where itemid=" . $itemid . " AND userid=" . $userid;
           // . " AND rawgrade IS NOT NULL AND finalgrade IS NULL";
           $arr_grade = $DB->get_record_sql($sql_grade);

           if (!empty($arr_grade)) {
             $rawgrade = $arr_grade->rawgrade;
             $finalgrade = $arr_grade->finalgrade;
           }

           if (!empty($arr_grade)) {
               if (is_null($arr_grade->rawgrade) && !is_null($arr_grade->finalgrade)) {
                   $provisionalgrade = $arr_grade->finalgrade;
               }
               if (!is_null($arr_grade->rawgrade) && is_null($arr_grade->finalgrade)) {
                   $provisionalgrade = $arr_grade->rawgrade;
               }
           }

           if ($modulename=="assign") {
               $arr_assign = $DB->get_record('assign', array('id'=>$iteminstance));

               $cmid = block_newgu_spdetails_external::get_cmid('assign', $courseid, $iteminstance);

               if (!empty($arr_assign)) {
                 $allowsubmissionsfromdate = $arr_assign->allowsubmissionsfromdate;
                 $duedate = $arr_assign->duedate;
                 $cutoffdate = $arr_assign->cutoffdate;
                 $gradingduedate = $arr_assign->gradingduedate;
               }
               if ($allowsubmissionsfromdate>time()) {
                 $status = 'notopen';
               }
               if ($status=="") {
                 $arr_assignsubmission = $DB->get_record('assign_submission', array('assignment'=>$iteminstance, 'userid'=>$userid));

                 if (!empty($arr_assignsubmission)) {
                   $status = $arr_assignsubmission->status;

                   if ($status=="new") {
                     $status = "notsubmitted";
                     if (time()>$duedate + (86400 * 30) && $duedate!=0) {
                       $status = 'overdue';
                     }
                   }

                 } else {
                   $status = 'tosubmit';

                   if (time()>$duedate && $duedate!=0) {
                     $status = 'notsubmitted';
                   }

                   if (time()>$duedate + (86400 * 30) && $duedate!=0) {
                     $status = 'overdue';
                   }

                   $link = $CFG->wwwroot . '/mod/assign/view.php?id=' . $cmid;
                 }
               }
           }

           if ($modulename=="forum") {
                 $forumsubmissions = $DB->count_records('forum_discussion_subs', array('forum'=>$iteminstance, 'userid'=>$userid));

                 $cmid = block_newgu_spdetails_external::get_cmid('forum', $courseid, $iteminstance);

                 if ($forumsubmissions>0) {
                     $status = 'submitted';
                 } else {
                     $status = 'tosubmit';
                     $link = $CFG->wwwroot . '/mod/forum/view.php?id=' . $cmid;
                 }
             }

             if ($modulename=="quiz") {

                   $cmid = block_newgu_spdetails_external::get_cmid('quiz', $courseid, $iteminstance);

                   $quizattempts = $DB->count_records('quiz_attempts', array('quiz'=>$iteminstance, 'userid'=>$userid, 'state'=>'finished'));
                   if ($quizattempts>0) {
                       $status = 'submitted';
                   } else {
                       $status = 'tosubmit';
                       $link = $CFG->wwwroot . '/mod/quiz/view.php?id=' . $cmid;
                   }
             }

             if ($modulename=="workshop") {

                   $arr_workshop = $DB->get_record('workshop', array('id'=>$iteminstance));

                   $cmid = block_newgu_spdetails_external::get_cmid('workshop', $courseid, $iteminstance);

                   $workshopsubmissions = $DB->count_records('workshop_submissions', array('workshopid'=>$iteminstance, 'authorid'=>$userid));
                   if ($workshopsubmissions>0) {
                       $status = 'submitted';
                   } else {
                       $status = 'tosubmit';
                       if ($arr_workshop->submissionstart==0) {
                         $status = 'notopen';
                       }
                       $link = $CFG->wwwroot . '/mod/workshop/view.php?id=' . $cmid;
                   }
             }

             $arr_grades = $DB->get_record('grade_grades',array('itemid'=>$itemid, 'userid'=>$userid));

             if (!empty($arr_grades)) {
                 $finalgrade = $arr_grades->finalgrade;
             }

             if (floor($rawgrade)>0 && floor($finalgrade)==0) {
               $provisional_22grademaxpoint = block_newgu_spdetails_external::return_22grademaxpoint((floor($rawgrade))-1, 1);
             }
             if (floor($finalgrade)>0) {
               $converted_22grademaxpoint = block_newgu_spdetails_external::return_22grademaxpoint((floor($finalgrade))-1, 1);
             }

         //    echo "<br/>XXXX<br/>" . floor($rawgrade) . " / " . floor($finalgrade) . " // " . $converted_22grademaxpoint . "<br/>XXXX<br/>";

             $gradestatus = array( "status"=>$status,
                                   "link"=>$link,
                                   "allowsubmissionsfromdate"=>$allowsubmissionsfromdate,
                                   "duedate"=>$duedate,
                                   "cutoffdate"=>$cutoffdate,
                                   "rawgrade"=>$rawgrade,
                                   "finalgrade"=>$finalgrade,
                                   "gradingduedate"=>$gradingduedate,
                                   "provisionalgrade"=>$provisionalgrade,
                                   "convertedgrade"=>$convertedgrade,
                                   "provisional_22grademaxpoint"=>$provisional_22grademaxpoint,
                                   "converted_22grademaxpoint"=>$converted_22grademaxpoint,
                                 );

             return $gradestatus;

         }





    public static function get_statistics() {

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
  //    $twohours = $currenttime - 2*60*60;
      $twohours = $currenttime - 2*60*60;



      if (!isset($SESSION->statscount) || $SESSION->statscount["timeupdated"]<$twohours) {

      $currentcourses = block_newgu_spdetails_external::return_enrolledcourses($USER->id, "current", $usertype="student");

      if (!empty($currentcourses)) {
      $str_currentcourses = implode(",", $currentcourses);

      $itemmodules = "'assign','forum','quiz','workshop'";

      $courseids = implode(', ', $currentcourses);
      $currentdate = time();

      $str_itemsnotvisibletouser = block_newgu_spdetails_external::fetch_itemsnotvisibletouser($USER->id, $str_currentcourses);

  //    $sql_gi = "SELECT * FROM {grade_items} WHERE courseid in (".$str_currentcourses.") && id not in (".$str_itemsnotvisibletouser.") && courseid>1 && itemtype='mod' && itemmodule in (" . $itemmodules . ")";
      $sql_gi = "SELECT * FROM {grade_items} WHERE courseid in (".$str_currentcourses.") && id not in (".$str_itemsnotvisibletouser.") && courseid>1 && itemtype='mod'";

      $arr_gi = $DB->get_records_sql($sql_gi);

      foreach($arr_gi as $key_gi) {
        $modulename = $key_gi->itemmodule;
        $iteminstance = $key_gi->iteminstance;
        $courseid = $key_gi->courseid;
        $itemid = $key_gi->id;

        $gradestatus = block_newgu_spdetails_external::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $USER->id);

        $status = $gradestatus["status"];
        $link = $gradestatus["link"];
        $allowsubmissionsfromdate = $gradestatus["allowsubmissionsfromdate"];
        $duedate = $gradestatus["duedate"];
        $cutoffdate = $gradestatus["cutoffdate"];

        $finalgrade = $gradestatus["finalgrade"];


        $statustodisplay = "";

        if($status == 'tosubmit'){
          $total_tosubmit++;
        }
        if($status == 'notsubmitted'){
          $total_tosubmit++;
        }
        if($status == 'submitted'){
          $total_submissions++;
          if ($finalgrade!=Null) {
            $marked++;
          }
        }
        if($status == "notopen"){
          $statustodisplay = '<span class="status-item">Submission not open</span> ';
        }
        if($status == "TO_BE_ASKED"){
          $statustodisplay = '<span class="status-item status-graded">Individual components</span> ';
        }
        if($status == "overdue"){
          $total_overdue++;
        }

      }

      $sub_assess = $total_submissions;
      $tobe_sub = $total_tosubmit;
      $overdue = $total_overdue;
      $assess_marked = $marked;

      $statscount = array(
                          "timeupdated"=>time(),
                          "sub_assess"=>$total_submissions,
                          "tobe_sub"=>$total_tosubmit,
                          "overdue"=>$total_overdue,
                          "assess_marked"=>$marked
                        );

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

      $result[] = array('stathtml'=>$html);
      return $result;

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
     */

    public static function get_statistics_returns() {
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


    public static function nogroupusers($courseid) {
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
      $str_groupids = implode(",",$arr_groupids);
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
      WHERE r.id=5 AND c.id = ' . $courseid . ' AND u.id NOT IN ('. $str_enrolledstudents .') ORDER BY u.firstname, u.lastname';


      return $sql_enrolledstudents;
    }
}
