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
 * Concrete implementation for mod_attendance.
 *
 * @package    block_newgu_spdetails
 * @copyright  2024 University of Glasgow
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

use cache;

/**
 * Implementation for an attendance activity.
 */
class attendance_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $attendance
     */
    private $attendance;

    /**
     * @var constant CACHE_KEY
     */
    const CACHE_KEY = 'studentid_attendanceduesoon:';

    /**
     * Constructor, set grade itemid.
     *
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        parent::__construct($gradeitemid, $courseid, $groupid);

        // Get the assignment object.
        $this->cm = \local_gugrades\users::get_cm_from_grade_item($gradeitemid, $courseid);
        $this->attendance = $this->get_attendance($this->cm);
    }

    /**
     * Local get attendance object method.
     *
     * @param object $cm course module
     * @return object
     */
    private function get_attendance($cm): object {
        global $DB;

        $coursemodulecontext = \context_module::instance($cm->id);
        $attendance = $DB->get_record('attendance', ['id' => $this->gradeitem->iteminstance], '*', MUST_EXIST);
        $attendance->coursemodulecontext = $coursemodulecontext;

        return $attendance;

    }

    /**
     * Return the grade directly from Gradebook.
     *
     * @param int $userid
     * @return mixed object|bool
     */
    public function get_grade(int $userid): object|bool {

        $activitygrade = new \stdClass();
        $activitygrade->finalgrade = null;
        $activitygrade->rawgrade = null;
        $activitygrade->gradedate = null;
        $activitygrade->gradecolumn = false;
        $activitygrade->feedbackcolumn = false;

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
     * Return the due date as the unix timestamp.
     *
     * @return int
     */
    public function get_rawduedate(): int {
        $dateinstance = $this->attendance;
        $rawdate = $dateinstance->timeclose;

        return $rawdate;
    }

    /**
     * Return a formatted date.
     *
     * @param int $unformatteddate
     * @return string
     */
    public function get_formattedduedate(int $unformatteddate = null): string {

        $duedate = '';
        if ($unformatteddate > 0) {
            $dateobj = \DateTime::createFromFormat('U', $unformatteddate);
            $duedate = $dateobj->format('jS F Y');
        }

        return $duedate;
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
        $statusobj->grade_status = get_string('status_tobeconfirmed', 'block_newgu_spdetails');
        $statusobj->status_text = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        $statusobj->status_class = get_string('status_class_notsubmitted', 'block_newgu_spdetails');
        $statusobj->status_link = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        $statusobj->due_date = $this->get_formattedduedate($this->attendance->timeclose);
        $statusobj->raw_due_date = $this->attendance->timeclose;
        $statusobj->gradecolumn = false;
        $statusobj->feedbackcolumn = false;
        $statusobj->grade_date = '';

        return $statusobj;

    }

    /**
     * Return the due date of the attendance if it hasn't been started.
     *
     * @return array
     */
    public function get_assessmentsdue(): array {
        global $USER, $DB;

        $attendancedata = [];

        return $attendancedata;

    }

}
