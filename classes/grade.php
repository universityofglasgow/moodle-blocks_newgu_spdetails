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
 * Class to provde utility methods for grading attributes
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace block_newgu_spdetails;

class grade {
    /**
     * Reimplementation of return_gradestatus as it misses the mark on a
     * number of fundamental levels.
     * 
     * @param string $modulename
     * @param int $iteminstance
     * @param int $courseid
     * @param int $itemid
     * @param int $userid
     * @param int $gradetype
     * @param int $scaleid
     * @return object
     */
    public static function get_grade_and_gradestatus(string $modulename, int $iteminstance, int $courseid, int $itemid, int $userid, int $gradetype, int $scaleid) {
        $gradestatus = new stdClass();
        $gradestatus->status = '';
        $gradestatus->statustext = '';
        $gradestatus->statusclass = '';
        $gradestatus->duedate = 0;
        $gradestatus->cutoffdate = 0;
        $gradestatus->gradingduedate = 0;
        $gradestatus->link = '';
        $gradestatus->assessmenturl = '';
        $gradestatus->gradetodisplay = '';

        $url = $CFG->wwwroot . '/mod/';
        $script = '/view.php?id=';
        $rawgrade = null;
        $finalgrade = null;

        $gradeqry = $DB->get_record_sql(
            "SELECT rawgrade,finalgrade FROM {grade_grades} WHERE itemid = :itemid AND userid = :userid AND hidden = :ishidden",
            [
                'itemid' => $itemid,
                'userid' => $userid,
                'ishidden' => 0
            ]
        );

        if (!empty($gradeqry)) {
            $rawgrade = (!empty($gradeqry->rawgrade) ? floor($gradeqry->rawgrade) : null);
            $finalgrade = (!empty($gradeqry->finalgrade) ? floor($gradeqry->finalgrade) : null);
        }

        $cmid = self::get_cmid($modulename, $courseid, $iteminstance);

        if ($rawgrade > 0 && ($finalgrade == null || $finalgrade == 0)) {
            $provisional_22grademaxpoint = self::return_22grademaxpoint($rawgrade - 1, 1);
        }

        // Do the easy stuff first...
        if ($finalgrade != null && $finalgrade > 0) {
            
            $converted_22grademaxpoint = self::return_22grademaxpoint($finalgrade - 1, 1);
            
            switch($gradetype) {
                // Point Scale
                case GRADE_TYPE_VALUE:
                    $gradestatus->gradetodisplay = floor($finalgrade);
                    break;

                case GRADE_TYPE_SCALE:
                    // Using the scaleid, derive the scale values...
                    $scaleparams = [
                        'scaleid' => $scaleid
                    ];
                    $scale = new \grade_scale($scaleparams, false);
                    $gradestatus->gradetodisplay = $scale->get_nearest_item($finalgrade);
                    break;
                    
                // Grade Type is None
                case GRADE_TYPE_TEXT:
                    break;
            }

            $gradestatus->status = get_string('status_graded', 'block_newgu_spdetails');
            $gradestatus->statustext = get_string('status_text_graded', 'block_newgu_spdetails');
            $gradestatus->statusclass = get_string('status_class_graded', 'block_newgu_spdetails');
            $gradestatus->link = $url . $modulename . $script . $cmid . '#page-footer';

            return $gradestatus;
        }

        switch ($modulename) {
            case 'assign':
                $assignment = $DB->get_record('assign', ['id' => $iteminstance]);
                $gradestatus->assessmenturl = $url . $modulename . $script . $cmid;

                if (!empty($assignment)) {
                    $allowsubmissionsfromdate = $assignment->allowsubmissionsfromdate;
                    $gradestatus->duedate = $assignment->duedate;
                    $gradestatus->cutoffdate = $assignment->cutoffdate;
                    $gradestatus->gradingduedate = $assignment->gradingduedate;
                }

                $overrides = $DB->get_record('assign_overrides', ['assignid' => $iteminstance, 'userid' => $userid]);
                if (!empty($overrides)) {
                    $allowsubmissionsfromdate = $overrides->allowsubmissionsfromdate;
                    $gradestatus->duedate = $overrides->duedate;
                    $gradestatus->cutoffdate = $overrides->cutoffdate;
                }

                $userflags = $DB->get_record('assign_user_flags', ['assignment' => $iteminstance, 'userid' => $userid]);
                if (!empty($userflags)) {
                    if ($userflags->extensionduedate > 0) {
                        $gradestatus->duedate = $userflags->extensionduedate;
                    }
                }

                if ($allowsubmissionsfromdate > time()) {
                    $gradestatus->status = get_string('status_submissionnotopen', 'block_newgu_spdetails');
                    $gradestatus->statustext = get_string('status_text_submissionnotopen', 'block_newgu_spdetails');
                    $gradestatus->gradetodisplay = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                }

                if ($gradestatus->status == '') {
                    $assignsubmission = $DB->get_record('assign_submission', ['assignment' => $iteminstance, 'userid' => $userid]);
                    $gradestatus->link = $url . $modulename . $script . $cmid;
                    
                    if (!empty($assignsubmission)) {
                        $gradestatus->status = $assignsubmission->status;

                        if ($gradestatus->status == 'new') {
                            $gradestatus->status = get_string('status_notsubmitted', 'block_newgu_spdetails');
                            $gradestatus->statustext = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                            $gradestatus->statusclass = get_string('status_class_notsubmitted', 'block_newgu_spdetails');
                            $gradestatus->gradetodisplay = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                            if ($gradestatus->gradingduedate > time()) {
                                $gradestatus->gradetodisplay = get_string('status_text_dueby', 'block_newgu_spdetails', date("d/m/Y", $gradestatus->gradingduedate));
                            }
                            
                            if (time() > $gradestatus->duedate + (86400 * 30) && $gradestatus->duedate != 0) {
                                $gradestatus->status = get_string('status_overdue', 'block_newgu_spdetails');
                                $gradestatus->statusclass = get_string('status_class_overdue', 'block_newgu_spdetails');
                                $gradestatus->statustext = get_string('status_text_overdue', 'block_newgu_spdetails');
                                $gradestatus->gradetodisplay = get_string('status_text_overdue', 'block_newgu_spdetails');
                            }
                        }

                        if ($gradestatus->status == get_string('status_submitted', 'block_newgu_spdetails')) {
                            $gradestatus->statusclass = get_string('status_class_submitted', 'block_newgu_spdetails');
                            $gradestatus->statustext = get_string('status_text_submitted', 'block_newgu_spdetails');
                            $gradestatus->link = '';
                        }

                    } else {
                        $gradestatus->status = get_string('status_tosubmit', 'block_newgu_spdetails');
                        $gradestatus->statustext = get_string('status_text_tosubmit', 'block_newgu_spdetails');
                        $gradestatus->gradetodisplay = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

                        if (time() > $gradestatus->duedate && $gradestatus->duedate != 0) {
                            $gradestatus->status = get_string('status_notsubmitted', 'block_newgu_spdetails');
                            $gradestatus->statustext = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                            $gradestatus->gradetodisplay = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                            if ($gradestatus->gradingduedate > time()) {
                                $gradestatus->gradetodisplay = get_string('status_text_dueby', 'block_newgu_spdetails', date("d/m/Y", $gradestatus->gradingduedate));
                            }
                        }

                        if (time() > $gradestatus->duedate + (86400 * 30) && $gradestatus->duedate != 0) {
                            $gradestatus->status = get_string('status_overdue', 'block_newgu_spdetails');
                            $gradestatus->statusclass = get_string('status_class_overdue', 'block_newgu_spdetails');
                            $gradestatus->statustext = get_string('status_text_overdue', 'block_newgu_spdetails');
                        }
                    }
                }
                break;

            case "forum":
                $forumsubmissions = $DB->count_records("forum_discussion_subs", ["forum" => $iteminstance, "userid" => $userid]);
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
        
        if ($finalgrade == null  && $gradestatus->duedate < time()) {
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

        return $gradestatus;
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
    public static function return_gradestatus(string $modulename, int $iteminstance, int $courseid, int $itemid, int $userid) {
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
        $rawgrade = null;
        $finalgrade = null;

        $arr_grade = $DB->get_record_sql(
            "SELECT rawgrade,finalgrade FROM {grade_grades} WHERE itemid = :itemid AND userid = :userid",
            [
                'itemid' => $itemid,
                'userid' => $userid
            ]
        );

        if (!empty($arr_grade)) {
            $rawgrade = (!empty($arr_grade->rawgrade) ? floor($arr_grade->rawgrade) : null);
            $finalgrade = (!empty($arr_grade->finalgrade) ? floor($arr_grade->finalgrade) : null);
        
            if (is_null($rawgrade) && !is_null($finalgrade)) {
                $provisionalgrade = $finalgrade;
            }
            if (!is_null($rawgrade) && is_null($finalgrade)) {
                $provisionalgrade = $rawgrade;
            }
        }

        $cmid = self::get_cmid($modulename, $courseid, $iteminstance);

        // Refactor this to allow any activity type to be parsed...
        switch ($modulename) {
            case "assign":
                $arr_assign = $DB->get_record("assign", ["id" => $iteminstance]);
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

                            if ($finalgrade != null) {
                                $status = get_string("status_graded", "block_newgu_spdetails");
                                $statusclass = get_string("status_class_graded", "block_newgu_spdetails");
                                $statustext = get_string("status_text_graded", "block_newgu_spdetails");
                            }
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

        if ($rawgrade > 0 && ($finalgrade == null || $finalgrade == 0)) {
            $provisional_22grademaxpoint = self::return_22grademaxpoint($rawgrade - 1, 1);
        }
        
        if ($finalgrade > 0) {
            $converted_22grademaxpoint = self::return_22grademaxpoint($finalgrade - 1, 1);
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
        global $CFG;
        
        $link = "";
        $gradetodisplay = "";
        
        $gradestatus = self::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $userid);
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
        
        $cmid = self::get_cmid($modulename, $courseid, $iteminstance);
        
        if ($finalgrade != null) {
            
            // I think this is meant to have been 'scale' type and not
            // 'grade' type. This code seems to be trying to determine
            // whether to use the 22 point 'scale' from the grade 'type'
            // i.e. scale, point or none.
            // It should really use the grade 'type' to determine if it
            // is scale/point or none. If it's set to 'point', return just
            // the point 'value' of the grade (20, out of 100 for example).
            // If it's been set to scale, use the scaleid to derive the scale
            // values from mdl_scale and *then* map the final grade to the
            // scale value.
            switch($gradetype) {
                case 1:
                    $gradetodisplay = number_format((float)$finalgrade) . " / " . number_format((float)$grademax) . ' (Provisional)';
                    break;

                case 2:
                    $gradetodisplay = $converted_22grademaxpoint . ' (Provisional)';
                    break;
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
            "finalgrade" => $finalgrade, 
            "rawgrade" => $rawgrade
        ];
    }
}