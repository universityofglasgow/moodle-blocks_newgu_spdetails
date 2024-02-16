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
 * Concrete implementation for mod_workshop
 * @package    block_newgu_spdetails
 * @copyright  2024
 * @author     Greg Pedder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/workshop/locallib.php');

/**
 * Specific implementation for a workshop activity
 */
class workshop_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $workshop
     */
    private $workshop;
    
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
        $this->workshop = $this->get_workshop($this->cm);
    }

    /**
     * Get workshop object
     * @param object $cm course module
     * @return object
     */
    private function get_workshop($cm) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
        $coursemodulecontext = \context_module::instance($cm->id);
        $workshop = new \workshop($coursemodulecontext, $cm, $course);

        return $quiz;
    }

    /**
     * Return the grade directly from Gradebook
     * @param int $userid
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
     * @param int $userid
     * @return object
     */
    public function get_status($userid): object {
        $obj = new \stdClass();
        $obj->due_date = time();
        return $obj;
    }

    /**
     * @param object $gradestatusobj
     */
    public function get_feedback($gradestatusobj): object {
        return parent::get_feedback($gradestatusobj);
    }

}