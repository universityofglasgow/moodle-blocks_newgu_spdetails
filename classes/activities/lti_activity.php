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
 * Concrete implementation for mod_lti
 * @package    block_newgu_spdetails
 * @copyright  2024
 * @author     Howard Miller/Greg Pedder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * Specific implementation for a lti activity
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
     * Constructor, set grade itemid
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        parent::__construct($gradeitemid, $courseid, $groupid);

        // Get the lti object.
        $this->cm = \local_gugrades\users::get_cm_from_grade_item($gradeitemid, $courseid);
        $this->lti = $this->get_lti($this->cm);
    }

    /**
     * Get lti object
     * @param object $cm course module
     * @return object
     */
    public function get_lti($cm) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
        $coursemodulecontext = \context_module::instance($cm->id);
        $lti = new \lti($coursemodulecontext, $cm, $course);

        return $lti;
    }

    /**
     * Is this a Proxy or Adapter method/pattern??
     * Seeing as get_first_grade is specific to Assignments,
     * what is the better way to describe this.
     */
    public function get_grade(int $userid): object|bool {
        return false;
    }

    /**
     * Get item type
     * @return string
     */
    public function get_itemtype() {
        return 'lti';
    }

    /**
     * Return the Moodle URL to the item
     * @return string
     */
    public function get_assessmenturl(): string {
        return $this->itemurl . $this->get_itemtype() . $this->itemscript . $this->cm->id;
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
