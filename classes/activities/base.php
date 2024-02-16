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
 * Default class for grade/activity access classes
 * @package    blocks_newgu_spdetails
 * @copyright  2024
 * @author     Howard Miller/Greg Pedder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

/**
 * Access data in course activities
 *
 */
abstract class base {

    /**
     * @var int $gradeitemid
     */
    protected int $gradeitemid;

    /**
     * @var object $gradeitem
     */
    protected object $gradeitem;

    /**
     * @var int $courseid
     */
    protected int $courseid;

    /**
     * @var int $groupid
     */
    protected int $groupid;

    /**
     * @var string $itemurl
     */
    protected string $itemurl;

    /**
     * @var string $itemtype
     */
    protected string $itemtype;

    /**
     * @var string $itemmodule
     */
    protected string $itemmodule;
    
    /**
     * @var string $itemscript
     */
    protected string $itemscript;

    /**
     * @var object $feedback
     */
    protected object $feedback;

    /**
     * Constructor, set grade itemid
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        global $CFG, $DB;

        $this->gradeitemid = $gradeitemid;
        $this->courseid = $courseid;
        $this->groupid = $groupid;

        // Get grade item.
        $this->gradeitem = $DB->get_record('grade_items', ['id' => $gradeitemid], '*', MUST_EXIST);
        $this->itemtype = $this->gradeitem->itemtype;

        // The URL format seems to be consistent between activities.
        $this->itemurl = $CFG->wwwroot . '/';
        $this->itemmodule = '/' . $this->gradeitem->itemmodule;
        $this->itemscript = '/view.php?id=';
    }

    /**
     * Implement get_first_grade
     * This is currently just the same as a manual grade
     * (this is pulling 'finalgrade' instead of 'rawgrade'. Not sure if this is correct/complete)
     * @param int $userid
     * @return object|bool
     */
    public function get_first_grade(int $userid):object|bool {
        global $DB;
        $gradeobj = new \stdClass();
        $gradeobj->finalgrade = null;
        $gradeobj->rawgrade = null;

        if ($grade = $DB->get_record('grade_grades', ['itemid' => $this->gradeitemid, 'userid' => $userid])) {
            if ($grade->finalgrade) {
                $gradeobj->finalgrade = $grade->finalgrade;
                return $gradeobj;
            }

            if ($grade->rawgrade) {
                $gradeobj->rawgrade = $grade->rawgrade;
                return $gradeobj;
            }
        }

        return false;
    }

    /**
     * Get item type
     * @return string
     */
    public function get_itemtype(): string {
        return $this->gradeitem->itemtype;
    }

    /**
     * Get item module
     * @return string
     */
    public function get_itemmodule(): string {
        return $this->gradeitem->itemmodule;
    }

    /**
     * Get item name
     * @return string
     */
    public function get_itemname(): string {
        return $this->gradeitem->itemname;
    }

    /**
     * @param int $userid
     * @return object
     */
    abstract public function get_status($userid): object;

    /**
     * Return the feedback for a given graded activity
     * 
     * We need to make this part of the object - currently
     * being called as a static method.
     * 
     * @param object $gradestatusobj
     * @return object $feedbackobj
     */
    public function get_feedback(object $gradestatusobj): object {
        $feedbackobj = new \stdClass();
        $feedbackobj->grade_feedback = '';
        $feedbackobj->grade_feedback_link = '';

        switch($gradestatusobj->grade_status) {
            case get_string('status_submit', 'block_newgu_spdetails'):
            case get_string('status_notopen', 'block_newgu_spdetails'):
            case get_string('status_submissionnotopen', 'block_newgu_spdetails'):
            case get_string('status_notsubmitted', 'block_newgu_spdetails') :
                $feedbackobj->grade_feedback = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                break;
                
            case get_string('status_overdue', 'block_newgu_spdetails'):
                $feedbackobj->grade_feedback = get_string('status_text_overdue', 'block_newgu_spdetails');
                break;

            case get_string('status_notsubmitted', 'block_newgu_spdetails'):
                $feedbackobj->grade_feedback = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                if ($gradestatusobj->due_date > time()) {
                    $feedbackobj->grade_feedback = get_string('status_text_dueby', 'block_newgu_spdetails', $gradestatusobj->due_date);
                }
                break;

            case get_string('status_graded', 'block_newgu_spdetails'):
                $feedbackobj->grade_feedback = get_string('status_text_graded', 'block_newgu_spdetails');
                $feedbackobj->grade_feedback_link = $gradestatusobj->assessment_url . '#page-footer';
                break;
        }

        return $feedbackobj;
    }

}