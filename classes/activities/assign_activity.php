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
 * Concrete implementation for mod_assign
 * @package    block_newgu_spdetails
 * @copyright  2024
 * @author     Howard Miller/Greg Pedder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Specific implementation for assignment
 */
class assign_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $assign
     */
    private $assign;

    /**
     * Constructor, set grade itemid
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        parent::__construct($gradeitemid, $courseid, $groupid);

        // Get the assignment object.
        $this->cm = \local_gugrades\users::get_cm_from_grade_item($gradeitemid, $courseid);
        $this->assign = $this->get_assign($this->cm);
    }

    /**
     * Get assignment object
     * @param object $cm course module
     * @return object
     */
    public function get_assign($cm) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
        $coursemodulecontext = \context_module::instance($cm->id);
        $assign = new \assign($coursemodulecontext, $cm, $course);

        return $assign;
    }

    /**
     * Implement get_first_grade
     * @param int $userid
     */
    public function get_first_grade(int $userid) {
        global $DB;

        // If the grade is overridden in the Gradebook then we can
        // revert to the base - i.e., get the grade from the Gradebook.
        if ($grade = $DB->get_record('grade_grades', ['itemid' => $this->gradeitemid, 'userid' => $userid])) {
            if ($grade->overridden) {
                return parent::get_first_grade($userid);
            }

            if ($grade->finalgrade != null && $grade->finalgrade > 0) {
                return $grade->finalgrade;
            }

            // We want access to other properties, hence the return...
            if ($grade->rawgrade != null && $grade->rawgrade > 0) {
                return $grade;
            }
        }

        // This just pulls the grade from assign. Not sure it's that simple
        // False, means do not create grade if it does not exist
        // This is the grade object from mdl_assign_grades (check negative values).
        $assigngrade = $this->assign->get_user_grade($userid, false);

        if ($assigngrade !== false) {

            return $assigngrade->grade;
        }

        return false;
    }

    /**
     * Get item type
     * @return string
     */
    public function get_itemtype() {
        return 'assign';
    }

    public function get_assessmenturl() {
        return $this->itemurl . $this->get_itemtype() . $this->itemscript . $this->cm->id;
    }

    public function get_status($userid) {
        
        global $DB;

        $statusobj = new \stdClass();
        $statusobj->assessmenturl = $this->get_assessmenturl();
        $assigninstance = $this->assign->get_instance();
        $allowsubmissionsfromdate = $assigninstance->allowsubmissionsfromdate;
        $statusobj->duedate = $assigninstance->duedate;
        $statusobj->cutoffdate = $assigninstance->cutoffdate;
        $statusobj->gradingduedate = $assigninstance->gradingduedate;

        $overrides = $DB->get_record('assign_overrides', ['assignid' => $assigninstance->id, 'userid' => $userid]);
        if (!empty($overrides)) {
            $allowsubmissionsfromdate = $overrides->allowsubmissionsfromdate;
            $statusobj->duedate = $overrides->duedate;
            $statusobj->cutoffdate = $overrides->cutoffdate;
        }

        $userflags = $DB->get_record('assign_user_flags', ['assignment' => $assigninstance->id, 'userid' => $userid]);
        if (!empty($userflags)) {
            if ($userflags->extensionduedate > 0) {
                $statusobj->duedate = $userflags->extensionduedate;
            }
        }

        if ($allowsubmissionsfromdate > time()) {
            $statusobj->status = get_string('status_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->statustext = get_string('status_text_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->gradetodisplay = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        }

        if ($statusobj->status == '') {
            $assignsubmission = $DB->get_record('assign_submission', ['assignment' => $assigninstance->id, 'userid' => $userid]);
            $statusobj->link = $statusobj->assessmenturl;
            
            if (!empty($assignsubmission)) {
                $statusobj->status = $assignsubmission->status;

                if ($statusobj->status == 'new') {
                    $statusobj->status = get_string('status_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->statustext = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->statusclass = get_string('status_class_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->gradetodisplay = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    if ($statusobj->gradingduedate > time()) {
                        $statusobj->gradetodisplay = get_string('status_text_dueby', 'block_newgu_spdetails', date("d/m/Y", $gradestatus->gradingduedate));
                    }
                    
                    if (time() > $statusobj->duedate + (86400 * 30) && $statusobj->duedate != 0) {
                        $statusobj->status = get_string('status_overdue', 'block_newgu_spdetails');
                        $statusobj->statusclass = get_string('status_class_overdue', 'block_newgu_spdetails');
                        $statusobj->statustext = get_string('status_text_overdue', 'block_newgu_spdetails');
                        $statusobj->gradetodisplay = get_string('status_text_overdue', 'block_newgu_spdetails');
                    }
                }

                if ($statusobj->status == get_string('status_submitted', 'block_newgu_spdetails')) {
                    $statusobj->statusclass = get_string('status_class_submitted', 'block_newgu_spdetails');
                    $statusobj->statustext = get_string('status_text_submitted', 'block_newgu_spdetails');
                    $statusobj->link = '';
                }

            } else {
                $statusobj->status = get_string('status_tosubmit', 'block_newgu_spdetails');
                $statusobj->statustext = get_string('status_text_tosubmit', 'block_newgu_spdetails');
                $statusobj->gradetodisplay = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

                if (time() > $statusobj->duedate && $statusobj->duedate != 0) {
                    $statusobj->status = get_string('status_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->statustext = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->gradetodisplay = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    if ($statusobj->gradingduedate > time()) {
                        $statusobj->gradetodisplay = get_string('status_text_dueby', 'block_newgu_spdetails', date("d/m/Y", $gradestatus->gradingduedate));
                    }
                }

                if (time() > $statusobj->duedate + (86400 * 30) && $statusobj->duedate != 0) {
                    $statusobj->status = get_string('status_overdue', 'block_newgu_spdetails');
                    $statusobj->statusclass = get_string('status_class_overdue', 'block_newgu_spdetails');
                    $statusobj->statustext = get_string('status_text_overdue', 'block_newgu_spdetails');
                }
            }
        }

        return $statusobj;
    }

}
