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

use cache;

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
     * @var contstant CACHE_KEY
     */
    const CACHE_KEY = 'studentid_workshopduesoon:';
    
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
        $workshoprecord = $DB->get_record('workshop', ['course' => $this->courseid], '*', MUST_EXIST);
        $coursemodulecontext = \context_module::instance($cm->id);
        $workshop = new \workshop($workshoprecord, $cm, $course, $coursemodulecontext);

        return $workshop;
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
     * Workshop creates 2 entries in Gradebook - one for an assessment and one for
     * a submission. Not entirely clear which one we should be using at the moment...
     * @param int $userid
     * @return object
     */
    public function get_status($userid): object {
        global $DB;

        $statusobj = new \stdClass();
        $statusobj->assessment_url = $this->get_assessmenturl();
        $allowsubmissionsfromdate = $this->workshop->submissionstart;
        $statusobj->due_date = $this->workshop->submissionend;
        $statusobj->grade_status = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

        if ($allowsubmissionsfromdate > time()) {
            $statusobj->grade_status = get_string('status_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        }

        if ($statusobj->grade_status == '') {
            $workshopsubmission = $DB->get_record('workshop_submissions', ['workshopid' => $this->workshop->id, 'authorid' => $userid]);

            $statusobj->grade_status = get_string('status_notsubmitted', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
            $statusobj->status_class = get_string('status_class_notsubmitted', 'block_newgu_spdetails');
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

            if (!empty($workshopsubmission)) {
                $statusobj->grade_status = $workshopsubmission->gradeoverby;

                if ($statusobj->grade_status == null) {
                    $statusobj->status_class = get_string('status_class_submitted', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_submitted', 'block_newgu_spdetails');
                    $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    $statusobj->status_link = '';
                }

                if (time() > $statusobj->due_date + (86400 * 30) && $statusobj->due_date != 0) {
                    $statusobj->grade_status = get_string('status_overdue', 'block_newgu_spdetails');
                    $statusobj->status_class = get_string('status_class_overdue', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_overdue', 'block_newgu_spdetails');
                    $statusobj->status_link = $statusobj->assessment_url;
                    $statusobj->grade_to_display = get_string('status_text_overdue', 'block_newgu_spdetails');
                }

            } else {
                $statusobj->grade_status = get_string('status_submit', 'block_newgu_spdetails');
                $statusobj->status_text = get_string('status_text_submit', 'block_newgu_spdetails');
                $statusobj->status_class = get_string('status_class_submit', 'block_newgu_spdetails');
                $statusobj->status_link = $statusobj->assessment_url;
                $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

                if (time() > $statusobj->due_date && $statusobj->due_date != 0) {
                    $statusobj->grade_status = get_string('status_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                    $statusobj->status_class = '';
                    $statusobj->status_link = '';
                    $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    if ($statusobj->due_date > time()) {
                        $statusobj->grade_to_display = get_string('status_text_dueby', 'block_newgu_spdetails', date("d/m/Y", $gradestatus->due_date));
                    }
                }

                if (time() > $statusobj->due_date + (86400 * 30) && $statusobj->due_date != 0) {
                    $statusobj->grade_status = get_string('status_overdue', 'block_newgu_spdetails');
                    $statusobj->status_class = get_string('status_class_overdue', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_overdue', 'block_newgu_spdetails');
                }
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
     * Return the due date of the workshop assignment if it hasn't been submitted.
     * @return array $assignmentdata
     */
    public function get_assessmentsdue(): array {
        global $USER;
        
        // Cache this query as it's going to get called for each assessment in the course otherwise.
        $cache = cache::make('block_newgu_spdetails', 'workshopduequery');
        $now = mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"));
        $currenttime = time();
        $fiveminutes = $currenttime - 300;
        $cachekey = self::CACHE_KEY . $USER->id;
        $cachedata = $cache->get_many([$cachekey]);
        $workshopdata = [];

        if (!$cachedata[$cachekey] || $cachedata[$cachekey][0]['updated'] < $fiveminutes) {
            if ($workshopsubmissions = $this->workshop->get_submission_by_author($USER->id)) {
            
                if (!in_array($workshop->id, $workshopsubmissions)) {
                    
                    // We're checking for both items here as the spec has stated that
                    // both items should appear on the dashboard.
                    if ($workshop->submissionstart != 0 && $workshop->submissionstart < $now) {
                        if ($workshop->submissionend != 0 && $workshop->submissionend > $now) {
                            $obj = new \stdClass();
                            $obj->duedate = $workshop->submissionend;
                            $workshopdata[] = $obj;
                        }
                    }

                    if ($workshop->assessmentstart != 0 && $workshop->assessmentstart < $now) {
                        if ($workshop->assessmentend != 0 && $workshop->assessmentend > $now) {
                            $obj = new \stdClass();
                            $obj->duedate = $workshop->assessmentend;
                            $workshopdata[] = $obj;
                        }
                    }
                }
            }

            $submissionsdata = [
                "updated" => time(),
                "workshopsubmissions" => $workshopdata
            ];

            $cachedata = [
                $cachekey => [
                    $submissionsdata
                ]
            ];
            $cache->set_many($cachedata);
        } else {
            $cachedata = $cache->get_many([$cachekey]);
            $workshopdata = $cachedata[$cachekey][0]["workshopsubmissions"];
        }

        return $workshopdata;
    }

}
