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
 * Concrete implementation for mod_lti.
 * 
 * @package    block_newgu_spdetails
 * @copyright  2024 University of Glasgow
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * Implementation for a LTI activity.
 */
class lti_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $lti
     */
    private $lti;

    /**
     * Constructor, set grade itemid.
     * 
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        parent::__construct($gradeitemid, $courseid, $groupid);

        // Get the lti object.
        $this->cm = \local_gugrades\users::get_cm_from_grade_item($gradeitemid, $courseid);
        //$this->lti = $this->get_lti($this->cm);
    }

    /**
     * Get lti object.
     * 
     * @param object $cm course module
     * @return object
     */
    public function get_lti(object $cm): object {
        global $DB;

        $course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
        $coursemodulecontext = \context_module::instance($cm->id);
        $lti = new \lti($coursemodulecontext, $cm, $course);

        return $lti;
    }

    /**
     * Return the grade directly from Gradebook.
     * 
     * @param int $userid
     * @return mixed object|bool
     */
    public function get_grade(int $userid): object|bool {
        global $DB;

        $activitygrade = new \stdClass();
        $activitygrade->finalgrade = null;
        $activitygrade->rawgrade = null;
        $activitygrade->gradedate = null;

        // If the grade is overridden in the Gradebook then we can
        // revert to the base - i.e., get the grade from the Gradebook.
        if ($grade = $DB->get_record('grade_grades', ['itemid' => $this->gradeitemid, 'hidden' => 0, 'userid' => $userid])) {
            if ($grade->overridden) {
                return parent::get_first_grade($userid);
            }

            // We want access to other properties, hence the returns...
            if ($grade->finalgrade != null && $grade->finalgrade > 0) {
                $activitygrade->finalgrade = $grade->finalgrade;
                $activitygrade->gradedate = $grade->timemodified;
                return $activitygrade;
            }

            if ($grade->rawgrade != null && $grade->rawgrade > 0) {
                $activitygrade->rawgrade = $grade->rawgrade;
                return $activitygrade;
            }
        }

        return false;
    }

    /**
     * Return the Moodle URL to the item.
     * 
     * @return string
     */
    public function get_assessmenturl(): string {
        return $this->get_itemurl() . $this->cm->id;
    }

    /**
     * Return a formatted date.
     * 
     * @param int $unformatteddate
     * @return string
     */
    public function get_formattedduedate(int $unformatteddate = null): string {
        
        $due_date = '';
        if ($unformatteddate > 0) {
            $dateobj = \DateTime::createFromFormat('U', $unformatteddate);
            $due_date = $dateobj->format('jS F Y');
        }
        
        return $due_date;
    }

    /**
     * Method to return the current status of the assessment item.
     * 
     * @param int $userid
     * @return object
     */
    public function get_status(int $userid): object {
        global $DB;

        $statusobj = new \stdClass();
        $statusobj->assessment_url = $this->get_assessmenturl();
        $statusobj->due_date = time();

        
        // Formatting this here as the integer format for the date is no longer needed for testing against.
        if ($statusobj->due_date != 0) {
            $statusobj->due_date = $this->get_formattedduedate($statusobj->due_date);
        } else {
            $statusobj->due_date = '';
        }

        return $statusobj;
    }

    /**
     * Method to return any feedback provided by the teacher.
     * 
     * @param object $gradestatusobj
     * @return object
     */
    public function get_feedback(object $gradestatusobj): object {
        return parent::get_feedback($gradestatusobj);
    }

    /**
     * Return the due date of the LTI activity if it hasn't been submitted.
     * 
     * This requires some extra checks - we have the feature that excludes
     * LTI's from the dashboard as gradable activities - we probably want to
     * carry out that check here also, when checking the submission entries.
     * 
     * @return array
     */
    public function get_assessmentsdue(): array {
        $assignmentdata = [];
        return $assignmentdata;

    }

}
