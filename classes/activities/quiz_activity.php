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
 * Concrete implementation for mod_quiz
 * @package    block_newgu_spdetails
 * @copyright  2024
 * @author     Greg Pedder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');

/**
 * Specific implementation for a quiz activity
 */
class quiz_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $quiz
     */
    private $quiz;
    
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
        $this->quiz = $this->get_quiz($gradeitemid, $this->cm);
    }

    /**
     * Get quiz object
     * @param object $gradeitemid
     * @param object $cm course module
     * @return object
     */
    private function get_quiz($gradeitemid, $cm) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
        $coursemodulecontext = \context_module::instance($cm->id);
        $gradeitem = $DB->get_record('grade_items', ['id' => $gradeitemid], '*', MUST_EXIST);
        $quizrecord = $DB->get_record('quiz', ['id' => $gradeitem->iteminstance], '*', MUST_EXIST);
        $quiz = new \quiz($quizrecord, $cm, $course, $coursemodulecontext);

        return $quiz;
    }

    /**
     * Return the grade either from Gradebook, or via the quiz submission table.
     * @return object|bool
     */
    public function get_grade(int $userid): object|bool {
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

        // This just pulls the grade from quiz_grades. Not sure it's that simple.
        $quizgrade = quiz_get_user_grades($this->quiz, $userid);

        if (count($quizgrade) > 0) {
            $activitygrade->grade = $quizgrade->rawgrade;
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
        
        $due_date = '';
        if ($unformatteddate > 0) {
            $dateobj = \DateTime::createFromFormat('U', $unformatteddate);
            $due_date = $dateobj->format('jS F Y');
        }
        
        return $due_date;
    }

    /**
     * @param int $userid
     * @return object
     */
    public function get_status($userid): object {
        
        global $DB;

        $statusobj = new \stdClass();
        $statusobj->assessment_url = $this->get_assessmenturl();
        $quizinstance = $this->quiz->get_quiz();
        $allowsubmissionsfromdate = $quizinstance->timeopen;
        $statusobj->due_date = $this->get_formattedduedate($quizinstance->timeclose);
        $statusobj->grade_status = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

        // Check if any overrides have been set up first of all...
        $overrides = $DB->get_record('quiz_overrides', ['quiz' => $quizinstance->id, 'userid' => $userid]);
        if (!empty($overrides)) {
            $allowsubmissionsfromdate = $overrides->timeopen;
            $statusobj->due_date = $this->get_formattedduedate($overrides->timeclose);
        }

        if ($allowsubmissionsfromdate > time()) {
            $statusobj->grade_status = get_string('status_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        }

        if ($statusobj->grade_status == '') {
            $quizattempts = $DB->count_records("quiz_attempts", ["quiz" => $quizinstance->id, "userid" => $userid, "state" => "finished"]);
            if ($quizattempts > 0) {
                $statusobj->grade_status = get_string("status_submitted", "block_newgu_spdetails");
                $statusobj->status_text = get_string("status_text_submitted", "block_newgu_spdetails");
                $statusobj->status_class = get_string("status_class_submitted", "block_newgu_spdetails");
                $statusobj->assessment_url = '';

                if ($quizgrades = $DB->count_records("quiz_grades", ["quiz" => $quizinstance->id, "userid" => $userid])) {
                    $statusobj->grade_status = get_string("status_graded", "block_newgu_spdetails");
                    $statusobj->status_text = get_string("status_text_graded", "block_newgu_spdetails");
                    $statusobj->status_class = get_string("status_class_graded", "block_newgu_spdetails");
                    $statusobj->grade_to_display = $quizgrades->grade;
                    $statusobj->assessment_url = '';
                }

            } else {
                $statusobj->grade_status = get_string("status_submit", "block_newgu_spdetails");
                $statusobj->status_text = get_string("status_text_submit", "block_newgu_spdetails");
                $statusobj->status_class = get_string("status_class_submit", "block_newgu_spdetails");
                $statusobj->status_link = $statusobj->assessment_url;
            }
        }

        return $statusobj;
    }

    /**
     * @param object $gradestatusobj
     */
    public function get_feedback($gradestatusobj): object {
        return parent::get_feedback($gradestatusobj);
    }

}