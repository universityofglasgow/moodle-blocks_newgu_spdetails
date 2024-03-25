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
 * Concrete implementation for mod_scorm.
 *
 * @package    block_newgu_spdetails
 * @copyright  2024 University of Glasgow
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

use cache;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/scorm/locallib.php');

/**
 * Implementation for a SCORM activity type.
 */
class scorm_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $scorm
     */
    private $scorm;

    /**
     * @var constant CACHE_KEY
     */
    const CACHE_KEY = 'studentid_scormduesoon:';

    /**
     * For this activity, get just the basic course module info.
     *
     * @param int $gradeitemid Grade item id
     * @param int $courseid
     * @param int $groupid
     */
    public function __construct(int $gradeitemid, int $courseid, int $groupid) {
        parent::__construct($gradeitemid, $courseid, $groupid);

        // Get the scorm object.
        $this->cm = \local_gugrades\users::get_cm_from_grade_item($gradeitemid, $courseid);
        $this->scorm = $this->get_scorm();
    }

    /**
     * Get scorm object.
     *
     * @return object
     */
    public function get_scorm(): object {
        global $DB;

        $scorm = $DB->get_record('scorm', ['id' => $this->gradeitem->iteminstance]);

        return $scorm;
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
     * Default implementation for returning the status of
     * an assessment.
     *
     * @param int $userid
     * @return object
     */
    public function get_status(int $userid): object {
        global $DB;

        $statusobj = new \stdClass();
        $statusobj->assessment_url = $this->get_assessmenturl();
        $statusobj->due_date = '';
        $statusobj->grade_status = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        $statusobj->status_text = '';
        $statusobj->status_class = '';
        $statusobj->status_link = '';
        $allowsubmissionsfromdate = $this->scorm->timeopen;

        if ($allowsubmissionsfromdate > time()) {
            $statusobj->grade_status = get_string('status_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        }

        if (time() > $this->scorm->timeclose) {
            $statusobj->grade_status = get_string('status_notsubmitted', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
            $statusobj->status_link = '';
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
            $statusobj->due_date = $this->scorm->timeclose;
        }

        if ($statusobj->grade_status == '') {
            $scormsubmission = scorm_get_last_completed_attempt($this->scorm->id, $userid);

            $statusobj->grade_status = get_string('status_notsubmitted', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
            $statusobj->status_class = get_string('status_class_notsubmitted', 'block_newgu_spdetails');
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
            $statusobj->due_date = $this->scorm->timeclose;

            if (!empty($scormsubmission) && $scormsubmission != '1') {
                $statusobj->status_class = get_string('status_class_submitted', 'block_newgu_spdetails');
                $statusobj->status_text = get_string('status_text_submitted', 'block_newgu_spdetails');
                $statusobj->status_link = '';
            } else {
                $statusobj->grade_status = get_string('status_submit', 'block_newgu_spdetails');
                $statusobj->status_text = get_string('status_text_submit', 'block_newgu_spdetails');
                $statusobj->status_class = get_string('status_class_submit', 'block_newgu_spdetails');
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
     * Method to return any feedback provided by the teacher.
     *
     * @param object $gradestatusobj
     * @return object
     */
    public function get_feedback(object $gradestatusobj): object {
        return parent::get_feedback($gradestatusobj);
    }

    /**
     * Return the due date of the default activity if it hasn't been submitted.
     *
     * @return array
     */
    public function get_assessmentsdue(): array {
        global $USER, $DB;

        // Cache this query as it's going to get called for each assessment in the course otherwise.
        $cache = cache::make('block_newgu_spdetails', 'scormduequery');
        $now = mktime(date('H'), date('i'), date('s'), date('m'), date('d'), date('Y'));
        $currenttime = time();
        $fiveminutes = $currenttime - 300;
        $cachekey = self::CACHE_KEY . $USER->id;
        $cachedata = $cache->get_many([$cachekey]);
        $scormdata = [];

        if (!$cachedata[$cachekey] || $cachedata[$cachekey][0]['updated'] < $fiveminutes) {

            $lastmonth = mktime(date('H'), date('i'), date('s'), date('m') - 1, date('d'), date('Y'));
            $select = 'userid = :userid AND timemodified BETWEEN :lastmonth AND :now';
            $params = ['userid' => $USER->id, 'lastmonth' => $lastmonth, 'now' => $now];
            $scormsubmissions = $DB->get_fieldset_select('scorm_scoes_track', 'scormid', $select, $params);

            $submissionsdata = [
                'updated' => time(),
                'scormsubmissions' => $scormsubmissions,
            ];

            $cachedata = [
                $cachekey => [
                    $submissionsdata,
                ],
            ];
            $cache->set_many($cachedata);
        } else {
            $cachedata = $cache->get_many([$cachekey]);
            $scormsubmissions = $cachedata[$cachekey][0]['scormsubmissions'];
        }

        $scorm = $this->scorm;

        if (!in_array($scorm->id, $scormsubmissions)) {
            if ($scorm->timeclose < $now) {
                if ($scorm->timeopen > $now) {
                    $scormdata[] = $scorm;
                }
            }
        }

        return $scormdata;

    }

}
