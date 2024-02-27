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
 * Contains the DB query methods for UofG Assessments Details block.
 *
 * @package    block_newgu_spdetails
 * @copyright
 * @author
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Return an array of graded items
 * 
 * @param string $modulename
 * @param int $iteminstance
 * @param int $courseid
 * @param int $itemid
 * @param int $userid
 * @param float $grademax
 * @param int $gradetype
 * @return array
 */
function get_gradefeedback(string $modulename, int $iteminstance, int $courseid, int $itemid, int $userid, float $grademax, int $gradetype) {
    global $CFG, $DB, $USER;

    $link = "";
    $gradetodisplay = "";

    $gradestatus = \block_newgu_spdetails\grade::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $userid);

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

    $cmid = \block_newgu_spdetails\course::get_cmid($modulename, $courseid, $iteminstance);

    if ($finalgrade!=Null) {
        if ($gradetype==1) {
            $gradetodisplay = '<span class="graded">' . number_format((float)$finalgrade) . " / " . number_format((float)$grademax) . '</span>' . ' (Provisional)';
        }
        if ($gradetype==2) {
            $gradetodisplay = '<span class="graded">' . $converted_22grademaxpoint . '</span>' . ' (Provisional)';
        }
        $link = $CFG->wwwroot . '/mod/'.$modulename.'/view.php?id=' . $cmid . '#page-footer';
    }

    if ($finalgrade==Null  && $duedate<time()) {
        if ($status=="notopen" || $status=="notsubmitted") {
            $gradetodisplay = 'To be confirmed';
            $link = "";
        }
        if ($status=="overdue") {
            $gradetodisplay = 'Overdue';
            $link = "";
        }
        if ($status=="notsubmitted") {
            $gradetodisplay = 'Not submitted';
            if ($gradingduedate>time()) {
                $gradetodisplay = "Due " . date("d/m/Y",$gradingduedate);
            }
        }
    }

    if ($status=="tosubmit") {
        $gradetodisplay = 'To be confirmed';
        $link = "";
    }

    return [
      "gradetodisplay"=>$gradetodisplay, 
      "link"=>$link, 
      "provisional_22grademaxpoint"=>$provisional_22grademaxpoint, 
      "converted_22grademaxpoint"=>$converted_22grademaxpoint, 
      "finalgrade"=>$finalgrade, 
      "rawgrade"=>$rawgrade
    ];
}


function get_weight($courseid,$categoryid,$aggregationcoef,$aggregationcoef2) {
  global $DB;

  $arr_gradecategory = $DB->get_record('grade_categories',array('courseid'=>$courseid, 'id'=>$categoryid));
  if (!empty($arr_gradecategory)) {
    $gradecategoryname = $arr_gradecategory->fullname;
    $aggregation = $arr_gradecategory->aggregation;
  }

  $finalweight = "—";

  $assessmenttype = \block_newgu_spdetails\course::return_assessmenttype($gradecategoryname, $aggregationcoef);

  $summative = get_string('summative', 'block_newgu_spdetails');

  $weight = ($aggregation == '10') ?
              (($aggregationcoef > 1) ? $aggregationcoef : $aggregationcoef * 100) :
              (($assessmenttype === $summative) ?
                  $aggregationcoef2 * 100 : 0);

  $finalweight = ($weight > 0) ? round($weight, 2).'%' : get_string('emptyvalue', 'block_newgu_spdetails');


return $finalweight;
}


function get_assessmenttypeorder($coursetype,$tdr,$userid) {

global $DB, $CFG;

$courses = \block_newgu_spdetails\course::return_enrolledcourses($userid, $coursetype);
$str_courses = implode(",", $courses);


$str_itemsnotvisibletouser = \block_newgu_spdetails\api::fetch_itemsnotvisibletouser($userid, $str_courses);

$sql_cc = 'SELECT gi.*, c.fullname as coursename FROM {grade_items} gi, {course} c WHERE gi.courseid in ('.$str_courses.') && gi.courseid>1 && gi.itemtype="mod" && gi.id not in ('.$str_itemsnotvisibletouser.') && gi.courseid=c.id';

$arr_cc = $DB->get_records_sql($sql_cc);

$arr_order = array();

foreach ($arr_cc as $key_cc) {
  $cmid = $key_cc->id;
  $modulename = $key_cc->itemmodule;
  $iteminstance = $key_cc->iteminstance;
  $courseid = $key_cc->courseid;
  $itemid = $key_cc->id;
  $categoryid = $key_cc->categoryid;

  // DUE DATE
  $assessmenttype = "";
  $str_assessmenttype = "—";

  // READ individual TABLE OF ACTIVITY (MODULE)
  if ($modulename!="") {

    $arr_gradecategory = $DB->get_record('grade_categories',array('courseid'=>$courseid, 'id'=>$categoryid));
    if (!empty($arr_gradecategory)) {
      $gradecategoryname = $arr_gradecategory->fullname;
    }

    $aggregationcoef = $key_cc->aggregationcoef;

    $assessmenttype = \block_newgu_spdetails\course::return_assessmenttype($gradecategoryname, $aggregationcoef);

  }

  $arr_order[$itemid] = $assessmenttype;
//    $arr_order2[$duedate] = $itemid;
}

if ($tdr==3) {
  asort($arr_order);
}
if ($tdr==4) {
  arsort($arr_order);
}

// echo "<pre>";
// print_r($arr_order);
// echo "</pre>";

$str_order = "";
foreach ($arr_order as $key_order=>$value) {
$str_order .= $key_order . ",";
}
$str_order = rtrim($str_order,",");
return $str_order;
}


function get_duedateorder($tdr,$userid) {

global $DB, $CFG;

$currentcourses = block_newgu_spdetails_external::return_enrolledcourses($userid, "current");
$str_currentcourses = implode(",", $currentcourses);

$currentxl = array();

$str_itemsnotvisibletouser = block_newgu_spdetails_external::fetch_itemsnotvisibletouser($userid, $str_currentcourses);

$sql_cc = 'SELECT gi.*, c.fullname as coursename FROM {grade_items} gi, {course} c WHERE gi.courseid in ('.$str_currentcourses.') && gi.courseid>1 && gi.itemtype="mod" && gi.id not in ('.$str_itemsnotvisibletouser.') && gi.courseid=c.id';

$arr_cc = $DB->get_records_sql($sql_cc);

$arr_order = array();

foreach ($arr_cc as $key_cc) {
  $cmid = $key_cc->id;
  $modulename = $key_cc->itemmodule;
  $iteminstance = $key_cc->iteminstance;
  $courseid = $key_cc->courseid;
  $itemid = $key_cc->id;

  // DUE DATE
  $duedate = 0;
  $extspan = "";
  $extensionduedate = 0;
  $str_duedate = "—";

  // READ individual TABLE OF ACTIVITY (MODULE)
  if ($modulename!="") {
    $arr_duedate = $DB->get_record($modulename,array('course'=>$courseid, 'id'=>$iteminstance));

  if (!empty($arr_duedate)) {
    if ($modulename=="assign") {
      $duedate = $arr_duedate->duedate;

      $arr_userflags = $DB->get_record('assign_user_flags', array('userid'=>$userid, 'assignment'=>$iteminstance));

      if ($arr_userflags) {
      $extensionduedate = $arr_userflags->extensionduedate;
      if ($extensionduedate>0) {
        $extspan = '<a href="javascript:void(0)" title="' . get_string('extended', 'block_newgu_spdetails') . '" class="extended">*</a>';
      }
      }

    }
    if ($modulename=="forum") {
      $duedate = $arr_duedate->duedate;
    }
    if ($modulename=="quiz") {
      $duedate = $arr_duedate->timeclose;
    }
    if ($modulename=="workshop") {
      $duedate = $arr_duedate->submissionend;
    }
  }
}

  if ($duedate!=0) {
    $str_duedate = date("d/m/Y", $duedate) . $extspan;
  }

  $arr_order[$itemid] = $duedate;
//    $arr_order2[$duedate] = $itemid;
}

if ($tdr==3) {
  asort($arr_order);
}
if ($tdr==4) {
  arsort($arr_order);
}

$str_order = "";
foreach ($arr_order as $key_order=>$value) {
$str_order .= $key_order . ",";
}
$str_order = rtrim($str_order,",");

return $str_order;
}


function get_startenddateorder($tdr) {

      global $USER, $DB, $CFG;

      $pastcourses = block_newgu_spdetails_external::return_enrolledcourses($USER->id, "past");
      $str_pastcourses = implode(",", $pastcourses);

      $pastxl = array();

      if ($str_pastcourses!="") {

      $str_itemsnotvisibletouser = block_newgu_spdetails_external::fetch_itemsnotvisibletouser($USER->id, $str_pastcourses);

      $sql_cc = 'SELECT gi.*, c.fullname as coursename FROM {grade_items} gi, {course} c WHERE gi.courseid in ('.$str_pastcourses.') && gi.courseid>1 && gi.itemtype="mod" && gi.id not in ('.$str_itemsnotvisibletouser.') && gi.courseid=c.id';

      $arr_cc = $DB->get_records_sql($sql_cc);


      $arr_sdorder = array();
      $arr_edorder = array();

      foreach ($arr_cc as $key_cc) {
          $cmid = $key_cc->id;
          $modulename = $key_cc->itemmodule;
          $iteminstance = $key_cc->iteminstance;
          $courseid = $key_cc->courseid;
          $categoryid = $key_cc->categoryid;
          $itemid = $key_cc->id;
          $aggregationcoef = $key_cc->aggregationcoef;
          $aggregationcoef2 = $key_cc->aggregationcoef2;

          // FETCH ASSESSMENT TYPE
          $arr_gradecategory = $DB->get_record('grade_categories',array('courseid'=>$courseid, 'id'=>$categoryid));
          if (!empty($arr_gradecategory)) {
            $gradecategoryname = $arr_gradecategory->fullname;
          }

          $assessmenttype = block_newgu_spdetails_external::return_assessmenttype($gradecategoryname, $aggregationcoef);


          // START DATE
          $submissionstartdate = 0;
          $startdate = "";
          $duedate = 0;
          $enddate = "";

          // READ individual TABLE OF ACTIVITY (MODULE)
          if ($modulename!="") {
            $arr_submissionstartdate = $DB->get_record($modulename,array('course'=>$courseid, 'id'=>$iteminstance));

          if (!empty($arr_submissionstartdate)) {
            if ($modulename=="assign") {
              $submissionstartdate = $arr_submissionstartdate->allowsubmissionsfromdate;
              $duedate = $arr_submissionstartdate->duedate;
            }
            if ($modulename=="forum") {
              $submissionstartdate = $arr_submissionstartdate->assesstimestart;
              $duedate = $arr_submissionstartdate->duedate;
            }
            if ($modulename=="quiz") {
              $submissionstartdate = $arr_submissionstartdate->timeopen;
              $duedate = $arr_submissionstartdate->timeclose;
            }
            if ($modulename=="workshop") {
              $submissionstartdate = $arr_submissionstartdate->submissionstart;
              $duedate = $arr_submissionstartdate->submissionend;
            }
          }
        }


            $startdate = date("d/m/Y", $submissionstartdate);
            $arr_sdorder[$itemid] = $submissionstartdate;
            $enddate = date("d/m/Y", $duedate);
            $arr_edorder[$itemid] = $duedate;


      }

    }

  if ($tdr==3) {
    asort($arr_sdorder);
  }
  if ($tdr==4) {
    arsort($arr_sdorder);
  }
  $str_sdorder = "";
  foreach ($arr_sdorder as $key_order=>$value) {
    $str_sdorder .= $key_order . ",";
  }
  $str_sdorder = rtrim($str_sdorder,",");

  if ($tdr==3) {
    asort($arr_edorder);
  }
  if ($tdr==4) {
    arsort($arr_edorder);
  }
  $str_edorder = "";
  foreach ($arr_edorder as $key_order=>$value) {
    $str_edorder .= $key_order . ",";
  }
  $str_edorder = rtrim($str_edorder,",");

  $array_order = array("startdateorder"=>$str_sdorder, "enddateorder"=>$str_edorder);

  return $array_order;

}

function get_ltiinstancenottoinclude() {
    // FETCH LTI IDs TO BE INCLUDED
    global $DB;

    $str_ltitoinclude = "99999";
    $str_ltinottoinclude = "99999";
    $sql_ltitoinclude = "SELECT * FROM {config} WHERE name like '%block_newgu_spdetails_include_%' AND value=1";
    $arr_ltitoinclude = $DB->get_records_sql($sql_ltitoinclude);
    $array_ltitoinclude = array();
    foreach ($arr_ltitoinclude as $key_ltitoinclude) {
        $name = $key_ltitoinclude->name;
        $name_pieces = explode("block_newgu_spdetails_include_",$name);
        $ltitype = $name_pieces[1];
        $array_ltitoinclude[] = $ltitype;
    }
    $str_ltitoinclude = implode(",", $array_ltitoinclude);

    if ($str_ltitoinclude=="") {
      $str_ltitoinclude = "99999";
    }

    $sql_ltitypenottoinclude = "SELECT id FROM {lti_types} WHERE id not in (".$str_ltitoinclude.")";
    $arr_ltitypenottoinclude = $DB->get_records_sql($sql_ltitypenottoinclude);

    $array_ltitypenottoinclude = array();
    $array_ltitypenottoinclude[] = 0;
    foreach ($arr_ltitypenottoinclude as $key_ltitypenottoinclude) {
        $array_ltitypenottoinclude[] = $key_ltitypenottoinclude->id;
    }
    $str_ltitypenottoinclude = implode(",", $array_ltitypenottoinclude);

    $sql_ltiinstancenottoinclude = "SELECT * FROM {lti} WHERE typeid NOT IN (".$str_ltitypenottoinclude.")";
    $arr_ltiinstancenottoinclude = $DB->get_records_sql($sql_ltiinstancenottoinclude);

    $array_ltiinstancenottoinclude = array();
    foreach ($arr_ltiinstancenottoinclude as $key_ltiinstancenottoinclude) {
        $array_ltiinstancenottoinclude[] = $key_ltiinstancenottoinclude->id;
    }
    $str_ltiinstancenottoinclude = implode(",", $array_ltiinstancenottoinclude);

    if ($str_ltiinstancenottoinclude=="") {
        $str_ltiinstancenottoinclude = 99999;
    }
    return $str_ltiinstancenottoinclude;
}