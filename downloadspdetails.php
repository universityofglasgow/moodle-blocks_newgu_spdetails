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
 * @copyright  2023 NEW GU
 * @author
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(__FILE__)) . '../../config.php');
require_once("$CFG->libdir/excellib.class.php");
require_once('locallib.php');

defined('MOODLE_INTERNAL') || die();

global $PAGE, $CFG, $DB, $OUTPUT, $USER;
$PAGE->set_context(context_system::instance());
require_login();
$usercontext = context_user::instance($USER->id);
// FETCH LTI IDs TO BE INCLUDED.
$strltiinstancenottoinclude = get_ltiinstancenottoinclude();
$spdetailstype = required_param('spdetailstype', PARAM_TEXT);
$coursestype = required_param('coursestype', PARAM_TEXT);
$strcoursestype = "";
$myfirstlastname = $USER->firstname . " " . $USER->lastname;

$currentcourses = \block_newgu_spdetails\course::return_enrolledcourses($USER->id, "current");
$strcurrentcourses = implode(",", $currentcourses);

$pastcourses = \block_newgu_spdetails\course::return_enrolledcourses($USER->id, "past");
$strpastcourses = implode(",", $pastcourses);

$thhd = 'border="1px" height="15" style="text-align:center;background-color: #ccc; border: 3px solid black;"';
$tdstl = 'border="1px" cellpadding="10" valign="middle" height="22" style="margin-left:10px;"';
$tdstc = 'border="1px" cellpadding="10" valign="middle" height="22" style="text-align:center;"';
$spdetailspdf = "No Courses found.";

if ($strcurrentcourses != "" && $coursestype == "current") {
    $strcoursestype = "Current Courses";

    $stritemsnotvisibletouser = \block_newgu_spdetails\api::fetch_itemsnotvisibletouser($USER->id, $strcurrentcourses);

    $sqlcc = 'SELECT gi.*, c.fullname as coursename FROM {grade_items} gi, {course} c WHERE gi.courseid in ('. $strcurrentcourses .
    ') && gi.courseid>1 && gi.itemtype="mod" && ((gi.iteminstance IN (' . $strltiinstancenottoinclude .
    ') && gi.itemmodule="lti") OR gi.itemmodule!="lti") && gi.id not in ('.$stritemsnotvisibletouser.') && gi.courseid=c.id';

    $arrcc = $DB->get_records_sql($sqlcc);

    $spdetailspdf = "<table width=100%>";
    $spdetailspdf .= '<tr style="font-weight: bold;">';
    $spdetailspdf .= '<th width="22%"' . $thhd . '>' . get_string('course') . '</th>';
    $spdetailspdf .= '<th width="22%"' . $thhd . '>' . get_string('assessment') . '</th>';
    $spdetailspdf .= '<th width="8%" ' . $thhd . '>' . get_string('assessmenttype', 'block_newgu_spdetails') . "</th>";
    $spdetailspdf .= '<th width="5%" ' . $thhd . '>' . get_string('weight', 'block_newgu_spdetails') . "</th>";
    $spdetailspdf .= '<th width="7%" ' . $thhd . '>' . get_string('duedate','block_newgu_spdetails') . "</th>";
    $spdetailspdf .= '<th width="10%" ' . $thhd . '>' . get_string('status') . "</th>";
    $spdetailspdf .= '<th width="11%" ' . $thhd . '>' . get_string('yourgrade', 'block_newgu_spdetails') . "</th>";
    $spdetailspdf .= '<th width="15%" ' . $thhd . '>' . get_string('feedback') . "</th>";
    $spdetailspdf .= "</tr>";

    $row = 6;

    foreach ($arrcc as $keycc) {
        $coursename = $keycc->coursename;
        $assessment = $keycc->itemname;
        $activitytype = $keycc->itemmodule;

        $cmid = $keycc->id;
        $modulename = $keycc->itemmodule;
        $iteminstance = $keycc->iteminstance;
        $courseid = $keycc->courseid;
        $categoryid = $keycc->categoryid;
        $itemid = $keycc->id;
        $itemname = $keycc->itemname;
        $aggregationcoef = $keycc->aggregationcoef;
        $aggregationcoef2 = $keycc->aggregationcoef2;
        $gradetype = $keycc->gradetype;

        // FETCH ASSESSMENT TYPE.
        $arrgradecategory = $DB->get_record('grade_categories', ['courseid' => $courseid, 'id' => $categoryid]);
        if (!empty($arrgradecategory)) {
            $gradecategoryname = $arrgradecategory->fullname;
        }

        $assessmenttype = \block_newgu_spdetails\course::return_assessmenttype($gradecategoryname, $aggregationcoef);

        // FETCH INCLUDED IN GCAT.
        $cfdvalue = 0;
        $inclgcat = "";
        $arrcustomfield = $DB->get_record('customfield_field', ['shortname'=>'show_on_studentdashboard']);
        $cffid = $arrcustomfield->id;

        $arrcustomfielddata = $DB->get_record('customfield_data', ['fieldid' => $cffid, 'instanceid' => $courseid]);

        if (!empty($arrcustomfielddata)) {
            $cfdvalue = $arrcustomfielddata->value;
        }

        if ($cfdvalue == 1) {
            $inclgcat = "Old";
        }

        // FETCH WEIGHT.
        $finalweight = \block_newgu_spdetails\course::return_weight($aggregationcoef);

        // DUE DATE.
        $duedate = 0;
        $extspan = "";
        $extensionduedate = 0;
        $strduedate = get_string('noduedate', 'block_newgu_spdetails');;

        // READ individual TABLE OF ACTIVITY (MODULE).
        if ($modulename != "") {
            $arrduedate = $DB->get_record($modulename, ['course' => $courseid, 'id' => $iteminstance]);

            if (!empty($arrduedate)) {
                if ($modulename == "assign") {
                    $duedate = $arrduedate->duedate;

                    $arruserflags = $DB->get_record('assign_user_flags', [
                        'userid' => $USER->id,
                        'assignment' => $iteminstance,
                    ]);

                    if ($arruserflags) {
                        $extensionduedate = $arruserflags->extensionduedate;
                        if ($extensionduedate > 0) {
                            $extspan = get_string('extended', 'block_newgu_spdetails') . '" class="extended">*';
                        }
                    }
                }
                if ($modulename == "forum") {
                    $duedate = $arrduedate->duedate;
                }
                if ($modulename == "quiz") {
                    $duedate = $arrduedate->timeclose;
                }
                if ($modulename == "workshop") {
                    $duedate = $arrduedate->submissionend;
                }
            }
        }

        if ($duedate != 0) {
            $strduedate = date("d/m/Y", $duedate) . $extspan;
        }

        // FETCH STATUS.
        $gradestatus = \block_newgu_spdetails\grade::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $USER->id);

        $status = $gradestatus["status"];
        $link = $gradestatus["link"];
        $allowsubmissionsfromdate = $gradestatus["allowsubmissionsfromdate"];
        $duedate = $gradestatus["duedate"];
        $cutoffdate = $gradestatus["cutoffdate"];
        $finalgrade = $gradestatus["finalgrade"];
        $statustodisplay = "";

        if ($status == 'tosubmit') {
            $statustodisplay = get_string('submit');
        }
        if ($status == 'notsubmitted') {
            $statustodisplay = get_string('notsubmitted', 'block_newgu_spdetails');
        }
        if ($status == 'submitted') {
            $statustodisplay = ucwords(trim(get_string('submitted', 'block_newgu_spdetails')));
            if ($finalgrade != null) {
                $statustodisplay = get_string('graded', 'block_newgu_spdetails');
            }
        }
        if ($status == "notopen") {
            $statustodisplay = get_string('submissionnotopen', 'block_newgu_spdetails');
        }
        if ($status == "TO_BE_ASKED") {
            $statustodisplay = get_string('individualcomponents', 'block_newgu_spdetails');
        }
        if ($status == "overdue") {
            $statustodisplay = get_string('overdue', 'block_newgu_spdetails');
        }

        // FETCH YOUR Grade.
        $arrgradetodisplay = get_gradefeedback($modulename, $iteminstance, $courseid, $itemid, $USER->id, $keycc->grademax,
        $gradetype);
        $gradetodisplay = $arrgradetodisplay["gradetodisplay"];

        // FETCH Feedback.
        $link = "";

        $feedback = get_gradefeedback($modulename, $iteminstance, $courseid, $itemid, $USER->id, $keycc->grademax, $gradetype);
        $link = $feedback["link"];
        $gradetodisplay = $feedback["gradetodisplay"];

        if ($link != "") {
            $strgradetodisplay =  get_string('readfeedback', 'block_newgu_spdetails');
        } else {
            if ($modulename != "quiz") {
                $strgradetodisplay = $gradetodisplay;
            }
        }

        $spdetailspdf .= "<tr>";
        $spdetailspdf .= "<td $tdstl>" . $coursename . "</td>";
        $spdetailspdf .= "<td $tdstl>" . $assessment . "</td>";
        $spdetailspdf .= "<td $tdstc>" . $assessmenttype . "</td>";
        $spdetailspdf .= "<td $tdstc>" . $finalweight . "</td>";
        $spdetailspdf .= "<td $tdstc>" . $strduedate . "</td>";
        $spdetailspdf .= "<td $tdstc>" . $statustodisplay . "</td>";
        $spdetailspdf .= "<td $tdstc>" . $gradetodisplay . "</td>";
        $spdetailspdf .= "<td $tdstc>" . $strgradetodisplay . "</td>";
        $spdetailspdf .= "</tr>";

        $row++;
        $col = 0;
        $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $coursename];
        $col++;
        $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $assessment];
        $col++;
        $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $assessmenttype];
        $col++;
        $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $finalweight];
        $col++;
        $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $strduedate];
        $col++;
        $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $statustodisplay];
        $col++;
        $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => strip_tags($gradetodisplay)];
        $col++;
        $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => strip_tags($gradetodisplay)];
        $col++;
    }
    $spdetailspdf .= "</table>";
}

if ($coursestype == "past") {

    $pastxl = [];
    if ($strpastcourses != "") {

        $strcoursestype = "Past Courses";
        $stritemsnotvisibletouser = \block_newgu_spdetails\api::fetch_itemsnotvisibletouser($USER->id, $strpastcourses);
        $sqlcc = 'SELECT gi.*, c.fullname as coursename FROM {grade_items} gi, {course} c WHERE gi.courseid in ('.$strpastcourses .
        ') && gi.courseid>1 && gi.itemtype="mod" && ((gi.iteminstance IN (' . $strltiinstancenottoinclude .
        ') && gi.itemmodule="lti") OR gi.itemmodule!="lti") && gi.id not in ('.$stritemsnotvisibletouser.') && gi.courseid=c.id';
        $arrcc = $DB->get_records_sql($sqlcc);

        $spdetailspdf = "<table width=100%>";
        $spdetailspdf .= '<tr style="font-weight: bold;">';
        $spdetailspdf .= '<th width="22%"' . $thhd . '>' . get_string('course') . '</th>';
        $spdetailspdf .= '<th width="22%"' . $thhd . '>' . get_string('assessment') . '</th>';
        $spdetailspdf .= '<th width="8%" ' . $thhd . '>' . get_string('assessmenttype', 'block_newgu_spdetails') . "</th>";
        $spdetailspdf .= '<th width="6%" ' . $thhd . '>' . get_string('weight', 'block_newgu_spdetails') . "</th>";
        $spdetailspdf .= '<th width="7%" ' . $thhd . '>' . get_string('startdate','block_newgu_spdetails') . "</th>";
        $spdetailspdf .= '<th width="7%" ' . $thhd . '>' . get_string('enddate','block_newgu_spdetails') . "</th>";
        $spdetailspdf .= '<th width="11%" ' . $thhd . '>' . get_string('yourgrade', 'block_newgu_spdetails') . "</th>";
        $spdetailspdf .= '<th width="14%" ' . $thhd . '>' . get_string('feedback') . "</th>";
        $spdetailspdf .= "</tr>";

        $row = 6;
        foreach ($arrcc as $keycc) {
            $col = 0;
            $coursename = $keycc->coursename;
            $assessment = $keycc->itemname;
            $activitytype = $keycc->itemmodule;
            $cmid = $keycc->id;
            $modulename = $keycc->itemmodule;
            $iteminstance = $keycc->iteminstance;
            $courseid = $keycc->courseid;
            $categoryid = $keycc->categoryid;
            $itemid = $keycc->id;
            $itemname = $keycc->itemname;
            $aggregationcoef = $keycc->aggregationcoef;
            $aggregationcoef2 = $keycc->aggregationcoef2;
            $gradetype = $keycc->gradetype;
    
            // FETCH ASSESSMENT TYPE.
            $arrgradecategory = $DB->get_record('grade_categories', ['courseid' => $courseid, 'id' => $categoryid]);
            if (!empty($arrgradecategory)) {
                $gradecategoryname = $arrgradecategory->fullname;
            }

            $assessmenttype = \block_newgu_spdetails\course::return_assessmenttype($gradecategoryname, $aggregationcoef);

            // FETCH INCLUDED IN GCAT.
            $cfdvalue = 0;
            $inclgcat = "";
            $arrcustomfield = $DB->get_record('customfield_field', ['shortname' => 'show_on_studentdashboard']);
            $cffid = $arrcustomfield->id;

            $arrcustomfielddata = $DB->get_record('customfield_data', ['fieldid' => $cffid, 'instanceid' => $courseid]);

            if (!empty($arrcustomfielddata)) {
                $cfdvalue = $arrcustomfielddata->value;
            }

            if ($cfdvalue == 1) {
                $inclgcat = "Old";
            }

            // FETCH WEIGHT.
            $finalweight = get_weight($courseid, $categoryid, $aggregationcoef, $aggregationcoef2);

            // START DATE.
            $submissionstartdate = 0;
            $startdate = "";

            // READ individual TABLE OF ACTIVITY (MODULE).
            if ($modulename != "") {
                $arrsubmissionstartdate = $DB->get_record($modulename, ['course' => $courseid, 'id' => $iteminstance]);

                if (!empty($arrsubmissionstartdate)) {
                    if ($modulename == "assign") {
                        $submissionstartdate = $arrsubmissionstartdate->allowsubmissionsfromdate;
                    }
                    if ($modulename == "forum") {
                        $submissionstartdate = $arrsubmissionstartdate->assesstimestart;
                    }
                    if ($modulename == "quiz") {
                        $submissionstartdate = $arrsubmissionstartdate->timeopen;
                    }
                    if ($modulename == "workshop") {
                        $submissionstartdate = $arrsubmissionstartdate->submissionstart;
                    }
                }
            }

            if ($submissionstartdate != 0) {
                $startdate = date("d/m/Y", $submissionstartdate);
            }

            // END DATE.
            $duedate = 0;
            $enddate = "";

            // READ individual TABLE OF ACTIVITY (MODULE).
            if ($modulename != "") {
                $arrduedate = $DB->get_record($modulename, ['course' => $courseid, 'id' => $iteminstance]);
                if (!empty($arrduedate)) {
                    if ($modulename == "assign" || $modulename == "forum") {
                        $duedate = $arrduedate->duedate;
                    }
                    if ($modulename == "quiz") {
                        $duedate = $arrduedate->timeclose;
                    }
                    if ($modulename == "workshop") {
                        $duedate = $arrduedate->submissionend;
                    }
                }
            }

            if ($duedate != 0) {
                $enddate = date("d/m/Y", $duedate);
            }

            // VIEW SUBMISSIONS.
            $link = "";
            $status="";
            $cmid = \block_newgu_spdetails\course::get_cmid($modulename, $courseid, $iteminstance);
            $link = $CFG->wwwroot . '/mod/' . $modulename . '/view.php?id=' . $cmid;
            if (!empty($link)) {
                $viewsubmission = get_string('viewsubmission', 'block_newgu_spdetails');
                $viewsubmissionxls = '';
            }

            // FETCH YOUR Grade.
            $arrgradetodisplay = get_gradefeedback($modulename, $iteminstance, $courseid, $itemid, $USER->id, $keycc->grademax,
            $gradetype);
            $gradetodisplay = $arrgradetodisplay["gradetodisplay"];

            // FETCH Feedback.
            $link = "";
            $feedback = get_gradefeedback($modulename, $iteminstance, $courseid, $itemid, $USER->id, $keycc->grademax, $gradetype);
            $link = $feedback["link"];
            $gradetodisplay = $feedback["gradetodisplay"];

            if ($link != "") {
                $strgradetodisplay = get_string('readfeedback', 'block_newgu_spdetails');
            } else {
                if ($modulename != "quiz") {
                    $strgradetodisplay = $gradetodisplay;
                }
            }

            $spdetailspdf .= "<tr>";
            $spdetailspdf .= "<td $tdstl>" . $coursename . "</td>";
            $spdetailspdf .= "<td $tdstl>" . $assessment . "</td>";
            $spdetailspdf .= "<td $tdstc>" . $assessmenttype . "</td>";
            $spdetailspdf .= "<td $tdstc>" . $finalweight . "</td>";
            $spdetailspdf .= "<td $tdstc>" . $startdate . "</td>";
            $spdetailspdf .= "<td $tdstc>" . $enddate . "</td>";
            $spdetailspdf .= "<td $tdstc>" . $gradetodisplay . "</td>";
            $spdetailspdf .= "<td $tdstc>" . $strgradetodisplay . "</td>";
            $spdetailspdf .= "</tr>";

            $row++;
            $col = 0;
            $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $coursename];
            $col++;
            $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $assessment];
            $col++;
            $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $assessmenttype];
            $col++;
            $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $finalweight];
            $col++;
            $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $startdate];
            $col++;
            $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => $enddate];
            $col++;
            $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => strip_tags($gradetodisplay)];
            $col++;
            $pastxl[$row][$col] = ["row" => $row, "col" => $col, "text" => strip_tags($gradetodisplay)];
            $col++;
        }

        $spdetailspdf .= "</table>";
    }
}

if ($spdetailstype == "pdf" && $spdetailspdf != "" && $strcoursestype != "") {

    require_once($CFG->libdir . '/pdflib.php');

    $doc = new pdf();
    $pathsw1b = 'img/uglogo03.png';
    $type = pathinfo($pathsw1b, PATHINFO_EXTENSION);
    $sw1bdata = file_get_contents($pathsw1b);
    $doc->SetFont('helvetica', '', 10);

    // Set default footer data.
    $doc->setFooterData();

    // Set header and footer fonts.
    $doc->setHeaderFont(['helvetica', 'b', 18,]);

    // Set margins.
    $doc->SetMargins(5, 20, 5);
    $doc->SetHeaderMargin(50);
    $doc->setFooterMargin(10);

    // Set auto page breaks.
    $doc->SetAutoPageBreak(TRUE, 15);

    // Set image scale factor.
    $doc->setImageScale(PDF_IMAGE_SCALE_RATIO);

    $doc->AddPage('L', 'A4');
    $doc->SetXY(5, 2);
    $doc->SetFont('helvetica', '', 20);
    $doc->SetXY(215, 15);
    $doc->Cell(25, 10, $myfirstlastname, 0, $ln=0, 'C', 0, '', 0, false, 'B', 'B');
    $doc->SetFont('helvetica', '', 9);
    $doc->SetXY(245, 20);
    $doc->Cell(25, 10, $strcoursestype . " Report Date : " . date("d-m-Y"), 0, $ln=0, 'C', 0, '', 0, false, 'B', 'B');
    $doc->SetMargins(5, 20, 5);
    $doc->SetFont('helvetica', '', 10);
    $doc->SetXY(5, 23);
    $thtml = <<<EOD
$spdetailspdf
EOD;

    $c = $thtml;

    $doc->writeHTML($c, true, false, false, false, '');
    $doc->Output($strcoursestype . " Report - " . $myfirstlastname . '_' . date("d-m-Y") . '.pdf', 'D');

    exit();
}

if ($spdetailstype == "excel" && $spdetailspdf != "" && $strcoursestype != "") {

    $filename = clean_filename($strcoursestype . " Report - " . $myfirstlastname . "_" . date("d-M-Y") . '.xls');

    // Creating a workbook.
    $workbook = new MoodleExcelWorkbook("-");
    // Send HTTP headers.
    $workbook->send($filename);

    $formatsetcenter = $workbook->add_format();
    $formatsetcenter->set_align('center');
    $formatsetcenter->set_v_align('center');
    $formatsetvcenter = $workbook->add_format();
    $formatsetvcenter->set_v_align('center');

    /// Creating the first worksheet.
    $myxls = $workbook->add_worksheet("Sheet-1");

    $formatuname = $workbook->add_format();
    $formatuname->set_size(18);
    $formatbgcol = $workbook->add_format();
    $formatbgcol->set_align('center');
    $formatbgcol->set_v_align('center');
    $formatbgcol->set_border(0);
    $formatbgcol->set_color('white');
    $formatbgcol->set_bg_color('black');
    $formatbgcol->set_text_wrap();

    $bitmap = 'img/uglogo03.png';
    $myxls->insert_bitmap(0, 0, $bitmap, 2, 2, 1, 1);
    $myxls->merge_cells(0, 0, 3, 0);
    $myxls->write_string(2, 4, $myfirstlastname, $formatuname);
    $myxls->set_column(0, 1, 40);
    $myxls->set_column(2, 2, 15);
    $myxls->set_column(3, 4, 10);
    $myxls->write_string(4, 0, $strcoursestype . ' Report - ' . date("d/m/Y"));

    if ($coursestype == "current") {

      $rowhd = 6;
      $col = 0;
      $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('course')];
      $col++;
      $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string("assessment")];
      $col++;
      $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string("assessmenttype", "block_newgu_spdetails")];
      $col++;
      $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('weight', 'block_newgu_spdetails')];
      $col++;
      $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('duedate','block_newgu_spdetails')];
      $col++;
      $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('status')];
      $col++;
      $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('yourgrade', 'block_newgu_spdetails')];
      $col++;
      $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('feedback')];
      $col++;

      $myxls->set_column(5, 5, 15);
      $myxls->set_column(6, 6, 20);
      $myxls->set_column(7, 8, 25);
    }

    if ($coursestype == "past") {
        $row++;
        $rowhd = 6;
        $col = 0;
        $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('course')];
        $col++;
        $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string("assessment")];
        $col++;
        $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string("assessmenttype", "block_newgu_spdetails")];
        $col++;
        $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('weight', 'block_newgu_spdetails')];
        $col++;
        $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('startdate','block_newgu_spdetails')];
        $col++;
        $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('enddate','block_newgu_spdetails')];
        $col++;
        $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('yourgrade', 'block_newgu_spdetails')];
        $col++;
        $pastxl[$row][$col] = ["row" => $rowhd, "col" => $col, "text" => get_string('feedback')];
        $col++;

        $myxls->set_column(5, 6, 15);
        $myxls->set_column(7, 8, 25);
    }

    $rowheight = 22;

    foreach ($pastxl as $keypastxl) {
        foreach ($keypastxl as $keykeypastxl) {

            if ($keykeypastxl["row"] == 6) {
                $cellformat = $formatbgcol;
            } else {
                if ($keykeypastxl["col"] >= 2 && $keykeypastxl["col"] <= 7) {
                  $cellformat = $formatsetcenter;
                } else {
                  $cellformat = $formatsetvcenter;
                }
            }

            $myxls->set_row($keykeypastxl["row"], $rowheight, null, false);
            $myxls->write_string($keykeypastxl["row"], $keykeypastxl["col"], $keykeypastxl["text"], $cellformat);
        }
    }

    // Close the workbook.
    $workbook->close();
}
