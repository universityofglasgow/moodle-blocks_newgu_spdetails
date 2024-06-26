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
 * Concrete implementation for mod_forum.
 *
 * @package    block_newgu_spdetails
 * @copyright  2024 University of Glasgow
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

/**
 * Implementation for a forum activity.
 */
class forum_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $forum
     */
    private $forum;

    /**
     * @var constant CACHE_KEY
     */
    const CACHE_KEY = 'studentid_forumduesoon:';

    /**
     * Constructor, set grade itemid.
     *
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        parent::__construct($gradeitemid, $courseid, $groupid);

        // Get the forum object.
        $this->cm = \local_gugrades\users::get_cm_from_grade_item($gradeitemid, $courseid);
        $this->forum = $this->get_forum($this->cm);
    }

    /**
     * Get forum object.
     *
     * @param object $cm course module
     * @return object
     */
    public function get_forum(object $cm): object {
        global $DB;

        $coursemodulecontext = \context_module::instance($cm->id);
        $forum = $DB->get_record('forum', ['id' => $this->gradeitem->iteminstance], '*', MUST_EXIST);
        $forum->coursemodulecontext = $coursemodulecontext;

        return $forum;
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
     * Return the 'Remind me to grade by' date if one exists.
     *
     * @return string
     */
    public function get_grading_duedate(): string {
        $gradingduedate = '';

        return $gradingduedate;
    }

    /**
     * Return the due date as the unix timestamp.
     *
     * @return int
     */
    public function get_rawduedate(): int {
        $dateinstance = $this->forum;
        $rawdate = $dateinstance->duedate;

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
        $statusobj->grade_status = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        $statusobj->due_date = $this->get_formattedduedate($this->forum->duedate);
        $statusobj->raw_due_date = $this->get_rawduedate();
        $statusobj->grade_date = '';
        $statusobj->grade_class = false;

        $forumsubmissions = $DB->count_records('forum_discussion_subs', ['forum' => $this->cm->instance, 'userid' => $userid]);
        if ($forumsubmissions > 0) {
            $statusobj->status_class = get_string('status_class_submitted', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_submitted', 'block_newgu_spdetails');
            $statusobj->status_link = '';
        } else {
            $statusobj->grade_status = get_string('status_submit', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_submit', 'block_newgu_spdetails');
            $statusobj->status_class = get_string('status_class_submit', 'block_newgu_spdetails');
            $statusobj->status_link = $statusobj->assessment_url;
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        }

        return $statusobj;
    }

    /**
     * Return the due date of the forum activity if it hasn't been submitted.
     * @return array $assignmentdata
     */
    public function get_assessmentsdue(): array {
        $assignmentdata = [];
        return $assignmentdata;

    }

}
