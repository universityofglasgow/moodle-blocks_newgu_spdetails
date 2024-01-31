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

namespace blocks_newgu_spdetails\activities;

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
     * @var string $itemtype
     */
    protected string $itemtype;

    /**
     * Constructor, set grade itemid
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        global $DB;

        $this->gradeitemid = $gradeitemid;
        $this->courseid = $courseid;
        $this->groupid = $groupid;

        // Get grade item.
        $this->gradeitem = $DB->get_record('grade_items', ['id' => $gradeitemid], '*', MUST_EXIST);
        $this->itemtype = $this->gradeitem->itemtype;
    }

    /**
     * Should the student names be hidden to normal users?
     * Probabl mostly applies to Assignment
     * @return boolean
     */
    public function is_names_hidden() {
        return false;
    }

    /**
     * Implement get_first_grade
     * This is currently just the same as a manual grade
     * (this is pulling 'finalgrade' instead of 'rawgrade'. Not sure if this is correct/complete)
     * @param int $userid
     */
    public function get_first_grade(int $userid) {
        global $DB;

        if ($grade = $DB->get_record('grade_grades', ['itemid' => $this->gradeitemid, 'userid' => $userid])) {
            if ($grade->finalgrade) {
                return $grade->finalgrade;
            }

            if ($grade->rawgrade) {
                return $grade->rawgrade;
            }
        }

        return false;
    }

    /**
     * Get item type
     * @return string
     */
    public function get_itemtype() {}

    /**
     * Get item name
     * @return string
     */
    public function get_itemname() {
        return $this->gradeitem->itemname;
    }

}
