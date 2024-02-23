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
 * Concrete implementation for mod_lesson
 * @package    block_newgu_spdetails
 * @copyright  2024
 * @author     Howard Miller/Greg Pedder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

use cache;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/lesson/lib.php');
require_once($CFG->dirroot . '/mod/lesson/locallib.php');

/**
 * Specific implementation for a lesson activity
 */
class lesson_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $lesson
     */
    private $lesson;

    /**
     * @var contstant CACHE_KEY
     */
    const CACHE_KEY = 'studentid_lessonsduesoon:';

    /**
     * Constructor, set grade itemid
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        parent::__construct($gradeitemid, $courseid, $groupid);

        // Get the lesson object.
        $this->cm = \local_gugrades\users::get_cm_from_grade_item($gradeitemid, $courseid);
        $this->lesson = $this->get_lesson();
    }

    /**
     * Get lesson object
     * @return object
     */
    public function get_lesson() {
        global $DB;
        $lessonid = $this->gradeitem->iteminstance;
        $lessonrecord = $DB->get_record('lesson', ['id' => $lessonid]);
        $lesson = new \lesson($lessonrecord);

        return $lesson;
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
        if ($grade = $DB->get_record('grade_grades', ['itemid' => $this->gradeitemid, 'hidden' => 0, 'userid' => $userid])) {
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
     * Return the Moodle URL to the item
     * @return string
     */
    public function get_assessmenturl(): string {
        return $this->get_itemurl() . $this->cm->id;
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
        $statusobj->due_date = $this->lesson->deadline;
        $statusobj->grade_status = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        $allowsubmissionsfromdate = $this->lesson->available;
        $statusobj->status_link = '';

        // Check if any overrides have been set up first of all...
        $overrides = $DB->get_record('lesson_overrides', ['lessonid' => $this->lesson->id, 'userid' => $userid]);
        if (!empty($overrides)) {
            $allowsubmissionsfromdate = $overrides->available;
            $statusobj->due_date = $overrides->deadline;
        }

        if ($allowsubmissionsfromdate > time()) {
            $statusobj->grade_status = get_string('status_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        }

        if ($statusobj->grade_status == '') {
            $lessonattempts = $DB->count_records("lesson_attempts", ["lessonid" => $this->lesson->id, "userid" => $userid]);
            if ($lessonattempts > 0) {
                $statusobj->grade_status = get_string("status_submitted", "block_newgu_spdetails");
                $statusobj->status_text = get_string("status_text_submitted", "block_newgu_spdetails");
                $statusobj->status_class = get_string("status_class_submitted", "block_newgu_spdetails");

                if ($lessongrades = $DB->count_records("lesson_grades", ["lessonid" => $this->lesson->id, "userid" => $userid, "completed" => 1])) {
                    $statusobj->grade_status = get_string("status_graded", "block_newgu_spdetails");
                    $statusobj->status_text = get_string("status_text_graded", "block_newgu_spdetails");
                    $statusobj->status_class = get_string("status_class_graded", "block_newgu_spdetails");
                    $statusobj->grade_to_display = $lessongrades->grade;
                }

            } else {
                $statusobj->grade_status = get_string("status_submit", "block_newgu_spdetails");
                $statusobj->status_text = get_string("status_text_submit", "block_newgu_spdetails");
                $statusobj->status_class = get_string("status_class_submit", "block_newgu_spdetails");
                $statusobj->status_link = $statusobj->assessment_url;
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
     */
    public function get_feedback($gradestatusobj): object {
        return parent::get_feedback($gradestatusobj);
    }

    /**
     * Return the due date of the lesson if it hasn't been submitted.
     * @return array $assignmentdata
     */
    public function get_assessmentsdue(): array {
        global $USER;
        
        // Cache this query as it's going to get called for each assessment in the course otherwise.
        $cache = cache::make('block_newgu_spdetails', 'lessonsduequery');
        $now = mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"));
        $currenttime = time();
        $fiveminutes = $currenttime - 300;
        $cachekey = self::CACHE_KEY . $USER->id;
        $cachedata = $cache->get_many([$cachekey]);
        $lessondata = [];

        if (!$cachedata[$cachekey] || $cachedata[$cachekey][0]['updated'] < $fiveminutes) {
            $lessondeadlines = lesson_get_user_deadline($this->courseid);
            
            foreach($lessondeadlines as $lessondeadline) {
                if ($this->lesson->id == $lessondeadline->id) {
                    if ($this->lesson->deadline != 0 && $this->lesson->deadline > $now) {                    
                        $obj = new \stdClass();
                        $obj->duedate = $this->lesson->deadline;
                        $lessondata[] = $obj;
                    }
                }
            }

            $submissionsdata = [
                "updated" => time(),
                "lessonsubmissions" => $lessondata
            ];

            $cachedata = [
                $cachekey => [
                    $submissionsdata
                ]
            ];
            $cache->set_many($cachedata);
        } else {
            $cachedata = $cache->get_many([$cachekey]);
            $lessondata = $cachedata[$cachekey][0]["lessonsubmissions"];
        }

        return $lessondata;

    }

}
