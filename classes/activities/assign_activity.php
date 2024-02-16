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
     * Is this a Proxy or Adapter method/pattern??
     * Seeing as get_first_grade is specific to Assignments,
     * what is the better way to describe this.
     */
    public function get_grade(int $userid): object|bool {
        return $this->get_first_grade($userid);
    }

    /**
     * Implement get_first_grade
     * @param int $userid
     * @return object|bool
     */
    public function get_first_grade(int $userid): object|bool {
        global $DB;

        $activitygrade = new \stdClass();
        $activitygrade->finalgrade = null;
        $activitygrade->rawgrade = null;

        // If the grade is overridden in the Gradebook then we can
        // revert to the base - i.e., get the grade from the Gradebook.
        if ($grade = $DB->get_record('grade_grades', ['itemid' => $this->gradeitemid, 'userid' => $userid])) {
            if ($grade->overridden) {
                return parent::get_first_grade($userid);
            }

            if ($grade->finalgrade != null && $grade->finalgrade > 0) {
                $activitygrade->finalgrade = $grade->finalgrade;
                return $activitygrade;
            }

            // We want access to other properties, hence the return...
            if ($grade->rawgrade != null && $grade->rawgrade > 0) {
                $activitygrade->rawgrade = $grade->rawgrade;
                return $activitygrade;
            }
        }

        // This just pulls the grade from assign. Not sure it's that simple
        // False, means do not create grade if it does not exist
        // This is the grade object from mdl_assign_grades (check negative values).
        $assigngrade = $this->assign->get_user_grade($userid, false);

        if ($assigngrade !== false) {
            $activitygrade->grade = $assigngrade->grade;
            return $activitygrade;
        }

        return false;
    }

    /**
     * Get item type
     * @return string
     */
    public function get_itemtype(): string {
        return $this->itemtype;
    }

    /**
     * Get item module
     * @return string
     */
    public function get_itemmodule(): string {
        return $this->itemmodule;
    }

    /**
     * Return the Moodle URL to the item
     * @return string
     */
    public function get_assessmenturl(): string {
        return $this->itemurl . $this->get_itemtype() . $this->get_itemmodule() . $this->itemscript . $this->cm->id;
    }

    /**
     * Return a formatted date
     * @param int $unformatteddate
     * @return string
     */
    public function get_formattedduedate(int $unformatteddate = null): string {
        $dateinstance = $this->assign->get_instance();
        $rawdate = $dateinstance->duedate;
        if ($unformatteddate) {
            $rawdate = $unformatteddate;
        }

        $dateobj = \DateTime::createFromFormat('U', $rawdate);
        $due_date = $dateobj->format('jS F Y');
        
        return $due_date;
    }

    /**
     * @param int $userid
     * @return object $statusobj
     */
    public function get_status($userid): object {
        
        global $DB;

        $statusobj = new \stdClass();
        $statusobj->assessment_url = $this->get_assessmenturl();
        $assigninstance = $this->assign->get_instance();
        $allowsubmissionsfromdate = $assigninstance->allowsubmissionsfromdate;
        $statusobj->grade_status = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        $statusobj->due_date = $assigninstance->duedate;

        // Check if any overrides have been set up first of all...
        $overrides = $DB->get_record('assign_overrides', ['assignid' => $assigninstance->id, 'userid' => $userid]);
        if (!empty($overrides)) {
            $allowsubmissionsfromdate = $overrides->allowsubmissionsfromdate;
            $statusobj->due_date = $overrides->duedate;
        }

        $userflags = $DB->get_record('assign_user_flags', ['assignment' => $assigninstance->id, 'userid' => $userid]);
        if (!empty($userflags)) {
            if ($userflags->extensionduedate > 0) {
                $statusobj->due_date = $userflags->extensionduedate;
            }
        }

        // "Allow submissions from" date is in the future...
        if ($allowsubmissionsfromdate > time()) {
            $statusobj->grade_status = get_string('status_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        }

        if ($statusobj->grade_status == '') {
            $assignsubmission = $DB->get_record('assign_submission', ['assignment' => $assigninstance->id, 'userid' => $userid]);
            
            if (!empty($assignsubmission)) {
                $statusobj->grade_status = $assignsubmission->status;

                // There is a bug in class assign->get_user_grade() where get_user_submission() is called 
                // and an assignment entry is created regardless -i.e. "true" is passed instead of an arg.
                // This will always result in an assign_submission entry with a status of "new".
                if ($statusobj->grade_status == 'new') {
                    $statusobj->grade_status = get_string('status_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->status_class = get_string('status_class_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

                    if ($statusobj->due_date != 0 && $statusobj->due_date > time()) {
                        $statusobj->grade_status = get_string('status_submit', 'block_newgu_spdetails');
                        $statusobj->status_text = get_string('status_text_submit', 'block_newgu_spdetails');
                        $statusobj->status_class = get_string('status_class_submit', 'block_newgu_spdetails');
                        $statusobj->status_link = $statusobj->assessment_url;
                        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    }
                    
                    if (time() > $statusobj->due_date + (86400 * 30) && $statusobj->due_date != 0) {
                        $statusobj->grade_status = get_string('status_overdue', 'block_newgu_spdetails');
                        $statusobj->status_class = get_string('status_class_overdue', 'block_newgu_spdetails');
                        $statusobj->status_text = get_string('status_text_overdue', 'block_newgu_spdetails');
                        $statusobj->status_link = $statusobj->assessment_url;
                        $statusobj->grade_to_display = get_string('status_text_overdue', 'block_newgu_spdetails');
                    }
                }

                if ($statusobj->grade_status == get_string('status_submitted', 'block_newgu_spdetails')) {
                    $statusobj->status_class = get_string('status_class_submitted', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_submitted', 'block_newgu_spdetails');
                    $statusobj->status_link = '';
                }

            } else {
                $statusobj->grade_status = get_string('status_submit', 'block_newgu_spdetails');
                $statusobj->status_text = get_string('status_text_submit', 'block_newgu_spdetails');
                $statusobj->status_link = $statusobj->assessment_url;
                $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

                if (time() > $statusobj->due_date && $statusobj->due_date != 0) {
                    $statusobj->grade_status = get_string('status_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->status_link = '';
                    $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    if ($statusobj->due_date > time()) {
                        $statusobj->grade_to_display = get_string('status_text_dueby', 'block_newgu_spdetails', date("d/m/Y", $gradestatus->due_date));
                    }
                }

                if (time() > $statusobj->due_date + (86400 * 30) && $statusobj->due_date != 0) {
                    $statusobj->grade_status = get_string('status_overdue', 'block_newgu_spdetails');
                    $statusobj->status_class = get_string('status_class_overdue', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_overdue', 'block_newgu_spdetails');
                    $statusobj->status_link = $statusobj->assessment_url;
                }
            }
        }

        // Formatting this here as the integer format for the date is no longer needed for testing against.
        if ($statusobj->due_date != 0) {
            $statusobj->due_date = $this->get_formattedduedate($statusobj->due_date);
        } else {
            $statusobj->due_date = '';
        }

        return $statusobj;
    }

    /**
     * @param object $gradestatusobj
     * @return object
     */
    public function get_feedback(object $gradestatusobj): object {
        return parent::get_feedback($gradestatusobj);
    }

}