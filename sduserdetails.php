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
 * View the Users details by Staff Dashboard.
 *
 * @package   block_newgu_spdetails
 * @copyright Moodle Dev
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$heading = get_string('staffdashboard', 'block_newgu_spdetails');
$url = new \moodle_url('/blocks/newgu_spdetails/sduserdetails.php');

require_login();
//require_once($CFG->dirroot.'/lib/formslib.php');
require_once('locallib.php');
//require_once('locallib.php');

$context = \context_system::instance();

global $CFG, $USER, $DB;

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_heading($heading);
$PAGE->requires->jquery();

$cntstaff = 0;

//$courseid = required_param('courseid', PARAM_INT);
$courseid = optional_param('selectcourse', '0', PARAM_INT);
//$courseid = 400;
$returnurl = optional_param('returnurl', '', PARAM_URL);

$cntstaff = block_newgu_spdetails_external::checkrole($USER->id, $courseid);

if (!is_siteadmin()) {
  if ($cntstaff==0) {
    redirect($CFG->wwwroot);
  }
}

$selectgroup = optional_param('selectgroup', '', PARAM_TEXT);
$selectstudent = optional_param('selectstudent', '', PARAM_TEXT);
$selectcourse = optional_param('selectcourse', '0', PARAM_TEXT);


if ($selectstudent==0) {
    redirect("$CFG->wwwroot/blocks/newgu_spdetails/sduserdetails.php");
}


//$PAGE->requires->js('/blocks/staff_dashboard/main.js?t=' . time(), true);
echo $OUTPUT->header();

$staffenrolledcourses = block_newgu_spdetails_external::return_enrolledcourses($USER->id, "all", "staff");

$staffcourseoptions = [];
$staffcourseoptions['select'] = "Select";

foreach ($staffenrolledcourses as $key_staffenrolledcourses) {
  //print_r($key_staffenrolledcourses);
  $staffcourseoptions[$key_staffenrolledcourses["courseid"]] = $key_staffenrolledcourses["coursename"];
}

$html = '';

$html .= html_writer::start_tag('form', array('action' => '', 'method' => 'post', 'id' => 'sd_userdetails'));

$html .= html_writer::start_tag('div', array('id' => 'coursecont', 'class' => 'row m-4'));
$html .= html_writer::tag('label', 'Course: ', array('class' => 'col-md-2'));
$html .= html_writer::select($staffcourseoptions, 'selectcourse', $selectcourse, '', array('id' => 'selectcourse', 'class' => 'col-md-3 ml-4'));
$html .= html_writer::end_tag('div');

$html .= html_writer::start_tag('div', array('id' => 'cont', 'class' => 'row m-4'));
$html .= html_writer::tag('label', 'Group: ', array('class' => 'col-md-2'));

$get_groups_sql = "SELECT * FROM {groups} WHERE courseid=" . $courseid;
$groups = $DB->get_records_sql($get_groups_sql);

$groupoptions = array();
$arr_groupids = array();

$groupoptions[-1] = 'Select';
$groupoptions['0'] = 'No Group';


foreach ($groups as $group) {
    $groupid = $group->id;
    $groupname = $group->name;
    $groupoptions[$groupid] = $groupname;
    $arr_groupids[] = $group->id;
}
$str_groupids = implode(",",$arr_groupids);

$studentoptions = [];
$studentoptions[0] = "Select";


$html .= html_writer::select($groupoptions, 'selectgroup', $selectgroup, '', array('id' => 'selectgroup', 'class' => 'col-md-3 ml-4'));
$html .= html_writer::end_tag('div');

$html .= html_writer::start_tag('div', array('class' => 'row m-4'));

$html .= html_writer::tag('label', 'Student: ', array('class' => 'col-md-2'));

if ($selectgroup!="0" && $selectgroup!="") {
    $sql_groupstudents = 'SELECT DISTINCT gm.userid as userid,u.firstname,u.lastname FROM {groups_members} gm, {user} u WHERE gm.groupid='. $selectgroup .' AND gm.userid=u.id ORDER BY u.firstname, u.lastname';
    $student_ids = $DB->get_records_sql($sql_groupstudents);

} else {
       $sql_enrolledstudents = block_newgu_spdetails_external::nogroupusers($courseid);
       $student_ids = $DB->get_records_sql($sql_enrolledstudents);
}

if (!empty($student_ids)) {
foreach ($student_ids as $student_id) {
    $studentid = $student_id->userid;
    $studentname = $student_id->firstname . " " . $student_id->lastname;
    $studentoptions[$studentid] = $studentname;
}
}

$html .= html_writer::select($studentoptions, 'selectstudent', $selectstudent, '', array('id' => 'selectstudent', 'class' => 'col-md-3 ml-4'));

$html .= html_writer::end_tag('div');

$html .= html_writer::start_tag('div', array('class' => 'row'));

$html .= html_writer::tag('button', 'Submit', array('class' => 'btn btn-primary ml-4 col-md-1'));

$html .= html_writer::end_tag('div');

$html .= html_writer::end_tag('form');

echo $html;
echo "<p></p>";

if ($selectstudent!="") {

  require("sduserdetails_table.php");

  $currentcourses = block_newgu_spdetails_external::return_enrolledcourses($selectstudent, "current", "student");
  $str_currentcourses = implode(",", $currentcourses);

  $pastcourses = block_newgu_spdetails_external::return_enrolledcourses($selectstudent, "past", "student");
  $str_pastcourses = implode(",", $pastcourses);

  // FETCH LTI IDs TO BE INCLUDED
  $str_ltiinstancenottoinclude = get_ltiinstancenottoinclude();
  //echo $str_ltiinstancenottoinclude;

  $ts = optional_param('ts', "", PARAM_ALPHA);
  $tdr = optional_param('tdr', 1, PARAM_INT);

  $courseselected = "";
  if ($ts=="coursename") {
      $courseselected = "selected";
  }
  $startdateselected = "";
  if ($ts=="startdate") {
      $startdateselected = "selected";
  }
  $enddateselected = "";
  if ($ts=="enddate") {
      $enddateselected = "selected";
  }
  $duedateselected = "";
  if ($ts=="duedate") {
      $duedateselected = "selected";
  }
  $assessmenttypeselected = "";
  if ($ts=="assessmenttype") {
      $assessmenttypeselected = "selected";
  }

  $addsort = "";
  if ($ts=="coursename") {
      $addsort = " ORDER BY c.shortname";

      if ($tdr==4) {
          if ($addsort!="") {
            $addsort .= " DESC";
          }
      }
      if ($tdr==3) {
          if ($addsort!="") {
            $addsort .= " ASC";
          }
      }

  }

  $assessmenttypeorder = "";
  if ($ts=="assessmenttype") {
  $assessmenttypeorder = get_assessmenttypeorder("current",$tdr,$selectstudent);
      if ($assessmenttypeorder!="") {
        $addsort = " ORDER BY FIELD(gi.id, $assessmenttypeorder)";
      }
  }

  $duedateorder = "";
  if ($ts=="duedate") {
      $duedateorder = get_duedateorder($tdr,$selectstudent);

      if ($duedateorder!="") {
        $addsort = " ORDER BY FIELD(gi.id, $duedateorder)";
      }
  }

  $tab = optional_param('t', 1, PARAM_INT);
  $tabs = [];
  $tab1_title = get_string('currentlyenrolledin', 'block_newgu_spdetails');
  $tab2_title = get_string('pastcourses', 'block_newgu_spdetails');
  $tabs[] = new tabobject(1, new moodle_url($url, ['t'=>1, 'selectcourse'=>$selectcourse, 'selectgroup'=>$selectgroup, 'selectstudent'=>$selectstudent]), $tab1_title);
  $tabs[] = new tabobject(2, new moodle_url($url, ['t'=>2, 'selectcourse'=>$selectcourse, 'selectgroup'=>$selectgroup, 'selectstudent'=>$selectstudent]), $tab2_title);
  echo $OUTPUT->tabtree($tabs, $tab);

  if ($tab == 1) {

  $table = new sduserdetailscurrent_table('tab1');

  $str_itemsnotvisibletouser = block_newgu_spdetails_external::fetch_itemsnotvisibletouser($selectstudent, $str_currentcourses);

if ($str_currentcourses=="") {
  $str_currentcourses = "0";
}

if ($str_itemsnotvisibletouser!="") {
  $table->set_sql('gi.*, c.shortname as coursename,' . $selectstudent . ' as userid', "{grade_items} gi, {course} c", "gi.courseid in (".$str_currentcourses.") && gi.courseid=" . $selectcourse . " && gi.courseid=".$courseid." && ((gi.iteminstance IN ($str_ltiinstancenottoinclude) && gi.itemmodule='lti') OR gi.itemmodule!='lti') && gi.itemtype='mod' && gi.id not in (".$str_itemsnotvisibletouser.") && gi.courseid=c.id $addsort");
} else {
  $table->set_sql('gi.*, c.shortname as coursename,' . $selectstudent . ' as userid', "{grade_items} gi, {course} c", "gi.courseid in (".$str_currentcourses.") && gi.courseid=" . $selectcourse . " && ((gi.iteminstance IN ($str_ltiinstancenottoinclude) && gi.itemmodule='lti') OR gi.itemmodule!='lti') && gi.itemtype='mod' && gi.courseid=c.id $addsort");
}

  $table->no_sorting('assessment');
  $table->no_sorting('assessmenttype');
  $table->no_sorting('duedate');
  $table->no_sorting('status');
  $table->no_sorting('includedingcat');
  $table->no_sorting('yourgrade');
  $table->no_sorting('feedback');

  $table->define_baseurl("$CFG->wwwroot/blocks/newgu_spdetails/sduserdetails.php?t=1" . "&selectgroup=" . $selectgroup . "&selectstudent=" . $selectstudent . "&selectcourse=". $courseid);
  $table->out(20, true);
}

if ($tab == 2) {

  $table = new sduserdetailspast_table('tab2');

  $str_itemsnotvisibletouser = block_newgu_spdetails_external::fetch_itemsnotvisibletouser($selectstudent, $str_pastcourses);

if ($str_pastcourses=="") {
    $str_pastcourses = 0;
}

if ($str_itemsnotvisibletouser!="") {
//  $table->set_sql('gi.*, c.fullname as coursename,' . $selectstudent . ' as userid', "{grade_items} gi, {course} c", "gi.courseid in (".$str_currentcourses.") && gi.courseid=".$courseid." && ((gi.iteminstance IN ($str_ltiinstancenottoinclude) && gi.itemmodule='lti') OR gi.itemmodule!='lti') && gi.itemtype='mod' && gi.id not in (".$str_itemsnotvisibletouser.") && gi.courseid=c.id $addsort");
  $table->set_sql('gi.*, c.shortname as coursename,' . $selectstudent . ' as userid', "{grade_items} gi, {course} c", "gi.courseid in (".$str_pastcourses.") && gi.courseid=" . $selectcourse . " && ((gi.iteminstance IN ($str_ltiinstancenottoinclude) && gi.itemmodule='lti') OR gi.itemmodule!='lti') && gi.itemtype='mod' && gi.id not in (".$str_itemsnotvisibletouser.") && gi.courseid=c.id $addsort");

} else {
//    $table->set_sql('gi.*, c.fullname as coursename,' . $selectstudent . ' as userid', "{grade_items} gi, {course} c", "gi.courseid in (".$str_currentcourses.") && gi.courseid=".$courseid." && ((gi.iteminstance IN ($str_ltiinstancenottoinclude) && gi.itemmodule='lti') OR gi.itemmodule!='lti') && gi.itemtype='mod' && gi.courseid=c.id $addsort");
     $table->set_sql('gi.*, c.shortname as coursename,' . $selectstudent . ' as userid', "{grade_items} gi, {course} c", "gi.courseid in (".$str_pastcourses.") && gi.courseid=" . $selectcourse . " && ((gi.iteminstance IN ($str_ltiinstancenottoinclude) && gi.itemmodule='lti') OR gi.itemmodule!='lti') && gi.itemtype='mod' && gi.courseid=c.id $addsort");
}

$table->no_sorting('coursename');
$table->no_sorting('assessment');
$table->no_sorting('assessmenttype');
$table->no_sorting('weight');
$table->no_sorting('startdate');
$table->no_sorting('enddate');
$table->no_sorting('viewsubmission');
$table->no_sorting('yourgrade');
$table->no_sorting('feedback');

$table->define_baseurl("$CFG->wwwroot/blocks/newgu_spdetails/sduserdetails.php?t=2" . "&selectgroup=" . $selectgroup . "&selectstudent=" . $selectstudent . "&selectcourse=". $courseid);
$table->out(20, true);


}

}


$PAGE->requires->js_amd_inline("
require(['core/first', 'jquery', 'jqueryui', 'core/ajax'], function(core, $, bootstrap, ajax) {

  // -----------------------------
  $(document).ready(function() {

    //  toggle event
    $('#selectgroup').change(function() {
      // get current value then call ajax to get new data
      var selected_group = this.value;
      var courseid = $('#selectcourse').val();

      ajax.call([{
        methodname: 'block_newgu_spdetails_get_groupusers',
        args: {
          'selected_group': selected_group,
          'courseid': courseid
        },
      }])[0].done(function(response) {
console.log(response);
        $('#selectstudent').empty();
        $.each(response, function (i, item) {
        $('#selectstudent').append($('<option>', {
          value: item.id,
          text : item.name
          }));
        });
        return;
      }).fail(function(err) {
        console.log(err);
        //notification.exception(new Error('Failed to load data'));
        return;
      });

    });


    //  toggle event
    $('#selectcourse').change(function() {
      // get current value then call ajax to get new data
      var selected_course = this.value;
        $('#selectgroup').empty();
        $('#selectstudent').empty();

        if (selected_course=='select') {
        $('#selectgroup').append($('<option>', {
            value: -1,
            text : 'Select'
          }));
        $('#selectstudent').append($('<option>', {
            value: 0,
            text : 'Select'
          }));
        } else {
          $('#selectstudent').append($('<option>', {
              value: 0,
              text : 'Select'
            }));
        }



      ajax.call([{
        methodname: 'block_newgu_spdetails_get_coursegroups',
        args: {
          'selected_course': selected_course
        },
      }])[0].done(function(response) {
console.log(response);

        $.each(response, function (i, item) {
        $('#selectgroup').append($('<option>', {
          value: item.id,
          text : item.name
          }));
        });
        return;
      }).fail(function(err) {
        console.log(err);
        //notification.exception(new Error('Failed to load data'));
        return;
      });

    });

  });
});

");


echo $OUTPUT->footer();
