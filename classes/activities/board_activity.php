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
 * Concrete implementation for mod_board
 * @package    block_newgu_spdetails
 * @copyright  2024 University of Glasgow
 * @author     Howard Miller/Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

/**
 * Specific implementation for board activity.
 */
class board_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $board
     */
    private $board;

    /**
     * Constructor, set grade itemid.
     *
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        parent::__construct($gradeitemid, $courseid, $groupid);

        // Get the board object.
        $this->cm = \local_gugrades\users::get_cm_from_grade_item($gradeitemid, $courseid);
        $this->board = $this->get_board($this->cm);
    }

    /**
     * Get a board object.
     *
     * @param object $cm course module
     * @return object
     */
    public function get_board($cm): object {
        global $CFG;

        require_once($CFG->dirroot . '/mod/board/lib.php');
        $board = board_get_coursemodule_info($cm);

        return $board;
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
     * Method to return the current status of the board item.
     *
     * @param int $userid
     * @return object
     */
    public function get_status(int $userid): object {

        $statusobj = new \stdClass();
        $statusobj->assessment_url = $this->get_assessmenturl();
        $boardinstance = $this->board;
        $statusobj->grade_status = '';
        $statusobj->status_text = '';
        $statusobj->status_class = '';
        $statusobj->status_link = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        $statusobj->due_date = '';
        $statusobj->raw_due_date = '';
        $statusobj->cutoff_date = '';
        $statusobj->markingworkflow = '';
        $statusobj->grade_date = '';

        return $statusobj;
    }

    /**
     * Board isn't an assessed activity.
     *
     * @return array
     */
    public function get_assessmentsdue(): array {

        $boarddata = [];

        return $boarddata;
    }

}
