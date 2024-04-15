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
 * Concrete implementation for mod_quiz.
 *
 * @package    block_newgu_spdetails
 * @copyright  2024 University of Glasgow
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

use cache;

/**
 * Implementation for a quiz activity.
 */
class quiz_activity extends base {

    /**
     * @var object $cm
     */
    private $cm;

    /**
     * @var object $quiz
     */
    private $quiz;

    /**
     * @var constant CACHE_KEY
     */
    const CACHE_KEY = 'studentid_quizduesoon:';

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
        $this->quiz = $this->get_quiz($gradeitemid, $this->cm);
    }

    /**
     * Local get quiz object method.
     * There is a get_quiz method on a quiz object also.
     *
     * @param int $gradeitemid
     * @param object $cm course module
     * @return object
     */
    private function get_quiz(int $gradeitemid, object $cm) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        $course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
        $coursemodulecontext = \context_module::instance($cm->id);
        $gradeitem = $DB->get_record('grade_items', ['id' => $gradeitemid], '*', MUST_EXIST);
        $quizrecord = $DB->get_record('quiz', ['id' => $gradeitem->iteminstance], '*', MUST_EXIST);
        $quiz = new \quiz($quizrecord, $cm, $course, $coursemodulecontext);

        return $quiz;
    }

    /**
     * Return the grade directly from Gradebook.
     *
     * @param int $userid
     * @return mixed object|bool
     */
    public function get_grade(int $userid): object|bool {
        global $DB, $USER;

        $activitygrade = new \stdClass();
        $activitygrade->finalgrade = null;
        $activitygrade->rawgrade = null;
        $activitygrade->gradedate = null;
        $activitygrade->gradecolumn = false;
        $activitygrade->feedbackcolumn = false;

        // Quiz setup has a feature which controls the visibility of grades.
        // We need to check this first.
        $attempts = quiz_get_user_attempts($this->quiz->get_quiz()->id, $USER->id, 'finished', true);

        if ($attempts) {
            // Work out if we can display the grade, taking account what data is available in each attempt.
            list($someoptions, $alloptions) = quiz_get_combined_reviewoptions($this->quiz->get_quiz(), $attempts);
            $activitygrade->gradecolumn = $someoptions->marks >= \question_display_options::MARK_AND_MAX &&
            quiz_has_grades($this->quiz->get_quiz());
            $activitygrade->feedbackcolumn = quiz_has_feedback($this->quiz->get_quiz()) && $alloptions->overallfeedback;

            // If the user is able to view the grade...
            if ($activitygrade->gradecolumn) {
                // If the grade is overridden in the Gradebook then we can
                // revert to the base - i.e., get the grade from the Gradebook.
                if ($grade = $DB->get_record('grade_grades', ['itemid' => $this->gradeitemid, 'hidden' => 0, 'userid' => $userid]))
                {
                    if ($grade->overridden) {
                        return parent::get_first_grade($userid);
                    }

                    // We want access to other properties, hence the returns...
                    if ($grade->finalgrade != null && $grade->finalgrade >= 0) {
                        $activitygrade->finalgrade = $grade->finalgrade;
                        $activitygrade->gradedate = $grade->timemodified;
                        return $activitygrade;
                    }

                    if ($grade->rawgrade != null && $grade->rawgrade > 0) {
                        $activitygrade->rawgrade = $grade->rawgrade;
                        return $activitygrade;
                    }
                }
            }

            return $activitygrade;
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
        $quizinstance = $this->quiz->get_quiz();
        $allowsubmissionsfromdate = $quizinstance->timeopen;
        $statusobj->grade_status = '';
        $statusobj->status_text = '';
        $statusobj->status_class = '';
        $statusobj->status_link = '';
        $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        $statusobj->due_date = $this->get_formattedduedate($quizinstance->timeclose);
        $statusobj->gradecolumn = false;
        $statusobj->feedbackcolumn = false;

        // Check if any overrides have been set up first of all...
        $overrides = $DB->get_record('quiz_overrides', ['quiz' => $quizinstance->id, 'userid' => $userid]);
        if (!empty($overrides)) {
            $allowsubmissionsfromdate = $overrides->timeopen;
            $statusobj->due_date = $this->get_formattedduedate($overrides->timeclose);
        }

        if ($allowsubmissionsfromdate > time()) {
            $statusobj->grade_status = get_string('status_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->status_text = get_string('status_text_submissionnotopen', 'block_newgu_spdetails');
            $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
        }

        if ($statusobj->grade_status == '') {
            // Quiz setup has a feature which controls the visibility of grades.
            // We need to check this here also.
            $attempts = quiz_get_user_attempts($quizinstance->id, $userid, 'finished', true);

            if ($attempts) {
                // Work out if we can display the grade, taking account what data is available in each attempt.
                list($someoptions, $alloptions) = quiz_get_combined_reviewoptions($quizinstance, $attempts);
                $statusobj->gradecolumn = $someoptions->marks >= \question_display_options::MARK_AND_MAX &&
                quiz_has_grades($quizinstance->id);
                $statusobj->feedbackcolumn = quiz_has_feedback($quizinstance) && $alloptions->overallfeedback;
    
                $statusobj->grade_status = get_string('status_submitted', 'block_newgu_spdetails');
                $statusobj->status_text = get_string('status_text_submitted', 'block_newgu_spdetails');
                $statusobj->status_class = get_string('status_class_submitted', 'block_newgu_spdetails');

                // This ^should^ be just one record.
                if ($quizgrade = $DB->get_record('quiz_grades', ['quiz' => $quizinstance->id, 'userid' => $userid], '*', MUST_EXIST)) {
                    $statusobj->grade_status = get_string('status_graded', 'block_newgu_spdetails');
                    $statusobj->status_text = get_string('status_text_graded', 'block_newgu_spdetails');
                    $statusobj->status_class = get_string('status_class_graded', 'block_newgu_spdetails');
                    // If the user is able to view the grade...
                    if ($statusobj->gradecolumn) {
                        $statusobj->grade_to_display = $quizgrade->grade;
                    }

                    if ($statusobj->feedbackcolumn) {
                        $statusobj->feedbackcolumn = true;
                    }
                }

            } else {
                $statusobj->grade_status = get_string('status_submit', 'block_newgu_spdetails');
                $statusobj->status_text = get_string('status_text_submit', 'block_newgu_spdetails');
                $statusobj->status_class = get_string('status_class_submit', 'block_newgu_spdetails');
                $statusobj->status_link = $statusobj->assessment_url;
                $statusobj->grade_to_display = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');

                if ($quizinstance->timeclose != 0) {
                    if (time() > $quizinstance->timeclose) {
                        $statusobj->grade_status = get_string('status_notsubmitted', 'block_newgu_spdetails');
                        $statusobj->status_text = get_string('status_text_notsubmitted', 'block_newgu_spdetails');
                        $statusobj->status_class = '';
                        $statusobj->status_link = '';
                    }
                }
            }
        }

        return $statusobj;
    }

    /**
     * Return the due date of the quiz if it hasn't been started.
     *
     * @return array
     */
    public function get_assessmentsdue(): array {
        global $USER, $DB;

        // Cache this query as it's going to get called for each assessment in the course otherwise.
        $cache = cache::make('block_newgu_spdetails', 'quizduequery');
        $now = mktime(date('H'), date('i'), date('s'), date('m'), date('d'), date('Y'));
        $currenttime = time();
        $fiveminutes = $currenttime - 300;
        $cachekey = self::CACHE_KEY . $USER->id;
        $cachedata = $cache->get_many([$cachekey]);
        $quizdata = [];

        if (!$cachedata[$cachekey] || $cachedata[$cachekey][0]['updated'] < $fiveminutes) {
            $lastmonth = mktime(date('H'), date('i'), date('s'), date('m') - 1, date('d'), date('Y'));
            $select = 'userid = :userid AND timestart BETWEEN :lastmonth AND :now AND state != :finished';
            $params = ['userid' => $USER->id, 'lastmonth' => $lastmonth, 'now' => $now, 'finished' => 'finished'];
            $quizattempts = $DB->get_fieldset_select('quiz_attempts', 'id', $select, $params);

            $submissionsdata = [
                'updated' => time(),
                'quizattempts' => $quizattempts,
            ];

            $cachedata = [
                $cachekey => [
                    $submissionsdata,
                ],
            ];
            $cache->set_many($cachedata);
        } else {
            $cachedata = $cache->get_many([$cachekey]);
            $quizattempts = $cachedata[$cachekey][0]['quizattempts'];
        }

        // We are calling the quiz object's get_quiz method here, not our local method.
        $quizobj = $this->quiz->get_quiz();

        if (!in_array($quizobj->id, $quizattempts)) {
            if ($quizobj->timeopen != 0 && $quizobj->timeopen < $now) {
                if ($quizobj->timeclose > $now) {
                    $obj = new \stdClass();
                    $obj->name = $quizobj->name;
                    $obj->duedate = $quizobj->timeclose;
                    $quizdata[] = $obj;
                }
            }
        }

        return $quizdata;
    }

}
