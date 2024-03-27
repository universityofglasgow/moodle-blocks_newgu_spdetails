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
 * Provides generic activity related methods.
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails;

define('ITEM_URL', $CFG->wwwroot . '/');
define('ITEM_SCRIPT', '/view.php?id=');

/**
 * This class processes activities for MyGrades, GCAT and Gradebook course types.
 *
 * It provides a factory method for instantiating the relevant activity which can
 * then be used to provide further functionality.
 */
class activity {

    /**
     * Main method called from the API.
     *
     * @param int $subcategory
     * @param int $userid
     * @param string $activetab
     * @param string $sortby
     * @param string $sortorder
     * @return array
     */
    public static function get_activityitems(int $subcategory, int $userid, string $activetab, string $sortby,
    string $sortorder): array {
        $activitydata = [];
        $coursedata = [];

        // What's my parent?
        // I need the parent of the parent in order to be able to always
        // step 'up' a level. \local_gugrades\grades::get_activitytree only
        // gives me the parent id, which breaks our mechanism.
        $subcat = \grade_category::fetch(['id' => $subcategory]);
        $parent = \grade_category::fetch(['id' => $subcat->parent]);
        if ($parent->parent == null) {
            $parentid = 0;
        } else {
            $parentid = $parent->id;
        }
        $activitydata['parent'] = $parentid;

        $courseid = $subcat->courseid;

        $course = get_course($courseid);
        $coursedata['coursename'] = $course->shortname;
        $coursedata['subcatfullname'] = ($subcat->fullname != '?' ? $subcat->fullname : '');

        // The assessment type is derived from the parent - which works only
        // as long as the parent name contains 'Formative' or 'Summative'.
        if (!$item = \grade_item::fetch(['courseid' => $course->id, 'iteminstance' => $subcategory, 'itemtype' => 'category'])) {
            $item = \grade_item::fetch(['courseid' => $course->id, 'iteminstance' => $subcategory, 'itemtype' => 'course']);
        }
        $assessmenttype = \block_newgu_spdetails\course::return_assessmenttype($subcat->fullname, $item->aggregationcoef);

        // The weight for this grade (sub)category is derived from the aggregation
        // coefficient value of the grade item, only if it's been set in the gradebook however.
        $weight = \block_newgu_spdetails\course::return_weight($item->aggregationcoef);
        $coursedata['weight'] = $weight;

        // We don't need the status column for past courses.
        $coursedata['hidestatuscol'] = (($activetab == 'past') ? true : false);

        // We'll need to merge these next two arrays at some point, to allow the sorting to
        // to work on all items, rather than just by category/activity item as it currently does.
        $activities = \local_gugrades\api::get_activities($course->id, $subcategory);
        $activitiesdata = self::process_get_activities($activities, $course->id, $subcategory, $userid, $activetab,
        $assessmenttype, $sortby, $sortorder);
        $coursedata['subcategories'] = ((array_key_exists('subcategories', $activitiesdata)) ?
        $activitiesdata['subcategories'] : '');
        $coursedata['assessmentitems'] = ((array_key_exists('assessmentitems', $activitiesdata)) ?
        $activitiesdata['assessmentitems'] : '');
        $activitydata['coursedata'] = $coursedata;

        return $activitydata;
    }

    /**
     * Method to determine which course type API needs to be used in
     * order to process the returned grade category and course items.
     *
     * @param object $activityitems
     * @param int $courseid
     * @param int $subcategory
     * @param int $userid
     * @param string $activetab
     * @param string $assessmenttype
     * @param string $sortby
     * @param string $sortorder
     * @return array
     */
    public static function process_get_activities(object $activityitems, int $courseid, int $subcategory, int $userid,
    string $activetab, string $assessmenttype, string $sortby, string $sortorder): array {
        $data = [];

        // We've lost all knowledge at this point of the course type - fetch it again.
        $mygradesenabled = \block_newgu_spdetails\course::is_type_mygrades($courseid);
        $gcatenabled = \block_newgu_spdetails\course::is_type_gcat($courseid);

        if ($activityitems->categories) {
            $categorydata = [];
            if ($mygradesenabled) {
                $categorydata = \block_newgu_spdetails\course::process_mygrades_subcategories($courseid,
                $activityitems->categories,
                $assessmenttype, $sortorder);
            }

            if ($gcatenabled) {
                $categorydata = \block_newgu_spdetails\course::process_gcat_subcategories($courseid, $activityitems->categories,
                $assessmenttype, $sortorder);
            }

            if (!$mygradesenabled && !$gcatenabled) {
                $categorydata = \block_newgu_spdetails\course::process_default_subcategories($courseid, $activityitems->categories,
                $assessmenttype, $sortorder);
            }

            $data['subcategories'] = $categorydata;
        }

        if ($activityitems->items) {
            // Temp fix for working out which LTI activities to exclude...
            $ltiinstancestoexclude = \block_newgu_spdetails\api::get_ltiinstancenottoinclude();

            $activitydata = [];
            if ($mygradesenabled) {
                $activitydata = self::process_mygrades_items($activityitems->items, $activetab, $ltiinstancestoexclude,
                $assessmenttype, $sortby, $sortorder);
            }

            if ($gcatenabled) {
                // We need to disregard the items we have and use the GCAT API instead...
                $activitydata = self::process_gcat_items($subcategory, $ltiinstancestoexclude, $userid, $activetab,
                $assessmenttype, $sortby, $sortorder);
            }

            if (!$mygradesenabled && !$gcatenabled) {
                $activitydata = self::process_default_items($activityitems->items, $activetab, $ltiinstancestoexclude,
                $assessmenttype, $sortby, $sortorder);
            }

            $data['assessmentitems'] = $activitydata;
        }

        return $data;
    }

    /**
     * Process and prepare for display MyGrades specific gradable items.
     *
     * Agreement between HM/TW/GP that we're only displaying items that
     * are visible - so if an assessment has been graded and then the item
     * hidden - this will not display. No further checks for hidden grades
     * are being done - based on how Moodle currenly does things.
     *
     * @param array $mygradesitems
     * @param string $activetab
     * @param array|string $ltiinstancestoexclude
     * @param string $assessmenttype
     * @param string $sortby
     * @param string $sortorder
     * @return array
     */
    public static function process_mygrades_items(array $mygradesitems, string $activetab, array|string $ltiinstancestoexclude,
    string $assessmenttype, string $sortby, string $sortorder): array {

        global $DB, $USER;
        $mygradesdata = [];

        if ($mygradesitems && count($mygradesitems) > 0) {

            $tmp = self::sort_items($mygradesitems, $sortby, $sortorder);

            foreach ($tmp as $mygradesitem) {

                $cm = get_coursemodule_from_instance($mygradesitem->itemmodule, $mygradesitem->iteminstance,
                $mygradesitem->courseid, false, MUST_EXIST);
                $modinfo = get_fast_modinfo($mygradesitem->courseid);
                $cm = $modinfo->get_cm($cm->id);

                // MGU-631 - Honour hidden grades and hidden activities. Having discussed with HM, if the activity is hidden, don't
                // show it full stop. This code may not be correct -if- it should only hide the grade if either condition is true.
                if ($cm->uservisible) {

                    if ($mygradesitem->itemmodule == 'lti') {
                        if (is_array($ltiinstancestoexclude) && in_array($mygradesitem->courseid, $ltiinstancestoexclude) ||
                        $mygradesitem->courseid == $ltiinstancestoexclude) {
                            continue;
                        }
                    }

                    $assessmenturl = $cm->url->out();
                    $itemicon = '';
                    $iconalt = '';
                    if ($iconurl = $cm->get_icon_url()->out(false)) {
                        $itemicon = $iconurl;
                        $iconalt = $cm->get_module_type_name();
                    }
                    $assessmentweight = \block_newgu_spdetails\course::return_weight($mygradesitem->aggregationcoef);
                    $duedate = '';
                    $gradestatus = get_string('status_tobeconfirmed', 'block_newgu_spdetails');
                    $statuslink = '';
                    $statusclass = get_string('status_class_notsubmitted', 'block_newgu_spdetails');
                    $statustext = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    $grade = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    $gradeclass = false;
                    $gradeprovisional = false;
                    $gradefeedback = get_string('status_text_tobeconfirmed', 'block_newgu_spdetails');
                    $gradefeedbacklink = '';

                    $params = [
                        'courseid' => $mygradesitem->courseid,
                        'gradeitemid' => $mygradesitem->id,
                        'userid' => $USER->id,
                        'iscurrent' => 1,
                    ];
                    if ($usergrades = $DB->get_records('local_gugrades_grade', $params)) {
                        // @todo - swap all of this for the relevant mygrades API calls - if/when one exists.
                        foreach ($usergrades as $usergrade) {
                            switch ($usergrade->gradetype) {
                                case 'RELEASED':
                                    $dateobj = \DateTime::createFromFormat('U', $cm->customdata['duedate']);
                                    $duedate = $dateobj->format('jS F Y');
                                    $statusclass = get_string('status_class_graded', 'block_newgu_spdetails');
                                    $statustext = get_string('status_text_graded', 'block_newgu_spdetails');
                                    // MGU-631 - Honour hidden grades and hidden activities.
                                    $gradeishidden = \local_gugrades\api::is_grade_hidden($mygradesitem->id, $USER->id);
                                    $grade = (($gradeishidden) ? get_string('status_text_tobeconfirmed', 'block_newgu_spdetails') :
                                    $usergrade->displaygrade);
                                    $gradeclass = true;
                                    $gradestatus = get_string('status_graded', 'block_newgu_spdetails');
                                    $gradefeedback = get_string('status_text_viewfeedback', 'block_newgu_spdetails');
                                    $gradefeedbacklink = $assessmenturl . '#page-footer';
                                    break;

                                case 'PROVISIONAL':
                                    $gradeprovisional = true;
                                    break;

                                default:
                                    $activity = self::activity_factory($mygradesitem->id, $courseid, 0);
                                    $grade = $activity->get_grading_duedate();
                                break;
                            }
                        }
                    } else {
                        // MyGrades data hasn't been imported OR released yet, revert to getting the data from Gradebook.
                        // By default, items that have been graded will appear - however, if Marking Workflow has been
                        // enabled - we need to consider the grade display options as dictated by those settings.
                        $gradestatobj = \block_newgu_spdetails\grade::get_grade_status_and_feedback($mygradesitem->courseid,
                            $mygradesitem->id,
                            $mygradesitem->itemmodule,
                            $mygradesitem->iteminstance,
                            $USER->id,
                            $mygradesitem->gradetype,
                            $mygradesitem->scaleid,
                            $mygradesitem->grademax,
                            'mygradesenabled'
                        );

                        $duedate = $gradestatobj->due_date;
                        $gradestatus = $gradestatobj->grade_status;
                        $statuslink = $gradestatobj->status_link;
                        $statusclass = $gradestatobj->status_class;
                        $statustext = $gradestatobj->status_text;
                        // MGU-631 - Honour hidden grades and hidden activities.
                        $grade = (($mygradesitem->hidden) ? get_string('status_text_tobeconfirmed', 'block_newgu_spdetails') :
                        $gradestatobj->grade_to_display);
                        $gradeclass = $gradestatobj->grade_class;
                        $gradeprovisional = $gradestatobj->grade_provisional;
                        $gradefeedback = $gradestatobj->grade_feedback;
                        $gradefeedbacklink = $gradestatobj->grade_feedback_link;
                    }

                    $tmp = [
                        'id' => $mygradesitem->id,
                        'assessment_url' => $assessmenturl,
                        'item_icon' => $itemicon,
                        'icon_alt' => $iconalt,
                        'item_name' => $mygradesitem->itemname,
                        'assessment_type' => $assessmenttype,
                        'assessment_weight' => $assessmentweight,
                        'due_date' => $duedate,
                        'grade_status' => $gradestatus,
                        'status_link' => $statuslink,
                        'status_class' => $statusclass,
                        'status_text' => $statustext,
                        'grade' => $grade,
                        'grade_class' => $gradeclass,
                        'grade_provisional' => $gradeprovisional,
                        'grade_feedback' => $gradefeedback,
                        'grade_feedback_link' => $gradefeedbacklink,
                        'mygradesenabled' => 'true',
                    ];

                    if ($activetab == 'past') {
                        unset($tmp['grade_status']);
                    }

                    $mygradesdata[] = $tmp;
                }
            }
        }

        return $mygradesdata;
    }

    /**
     * Process and prepare for display GCAT specific gradable items.
     *
     * Agreement between HM/TW/GP that we're only displaying items that
     * are visible - so if an assessment has been graded a then the item
     * hidden - this will not display. No further checks for hidden grades
     * are being done - based on how Moodle currenly does things.
     *
     * @param int $subcategory
     * @param array|string $ltiinstancestoexclude
     * @param int $userid
     * @param string $activetab
     * @param string $assessmenttype
     * @param string $sortby
     * @param string $sortorder
     * @return array
     */
    public static function process_gcat_items(int $subcategory, array|string $ltiinstancestoexclude, int $userid,
    string $activetab, string $assessmenttype, string $sortby, string $sortorder): array {
        global $DB, $CFG;

        /**
         * Use the grade category id, get from grade_items where iteminstance=gc.categoryid (check gc.id=167)
         * and courseid=? and itemtype = category get from grade_grades where itemid = 167 and userid = ? - dig
         * out the rest and pass to sanitize_recordss this should give us the overall grade for this category.
         */
        /**
         * $item = $DB->get_record('grade_items', ['iteminstance' => $subcategory, 'itemtype' => 'category'], '*', MUST_EXIST);
         * $gradeitem = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid], '*', MUST_EXIST);
         * $gradeitem->id = $subcategory;
         * $gradeitem->courseid = $item->courseid;
         * $gradeitem->gradetype = $item->gradetype;
         * $gradeitem->grademin = $item->grademin;
         * $gradeitem->grademax = $item->grademax;
         * $gradeitem->gradeinformation = $gradeitem->information;
         * $gradeitem->gradingduedate = 0;
         * $gradeitem->duedate = 0;
         * $gradeitem->cutoffdate = 0;
         * $gradeitem->scale = $item->scaleid;
         * $gradeitem->convertedgradeid = '';
         * $gradeitem->provisionalgrade = '';
         * $gradeitem->status = $item->itemtype;
         * $gradeitem->idnumber = $item->idnumber;
         * $gradeitem->outcomeid = $item->outcomeid;
         */

        // Here we are simply deferring to GCAT's API to return assignments and their status and grade.
        require_once($CFG->dirroot. '/blocks/gu_spdetails/lib.php');
        // Course fullname isn't referenced in the query, it's known as coursetitle - find and replace for now.
        $sortby = preg_replace('/(full|short)name/', 'coursetitle, activityname', $sortby);
        $gcatitems = \assessments_details::retrieve_gradable_activities($activetab, $userid, $sortby, $sortorder, $subcategory);
        // $overallgrade = \assessments_details::sanitize_records([$gradeitem], null, $userid);
        $gcatdata = [];
        // $tmp = grade_aggregation::get_rows($course, $activities, $studentarr);
        if ($gcatitems && count($gcatitems) > 0) {

            $tmp = self::sort_items($gcatitems, $sortby, $sortorder);

            foreach ($tmp as $gcatitem) {

                // MGU-631 - GCAT seems to take care of checking if the activity item and grade is
                // visible to the user in the API call above. The only issue is whether, for grade
                // items that were hidden, should the rest of the activity information be displayed.
                // The above call currently will not return records where gi.hidden = 1.

                // We have no knowledge of the itemmodule here - how do we get that for this check?
                if (property_exists($gcatitem, 'itemmodule') && $gcatitem->itemmodule == 'lti') {
                    if (is_array($ltiinstancestoexclude) && in_array($gcatitem->courseid, $ltiinstancestoexclude) ||
                    $gcatitem->courseid == $ltiinstancestoexclude) {
                        continue;
                    }
                }

                // With no knowledge of the itemmodule, we can't set an icon, yet.
                $itemicon = '';
                $iconalt = '';
                $duedate = \DateTime::createFromFormat('U', $gcatitem->duedate);
                $class = (isset($gcatitem->status->class) ? $gcatitem->status->statustext : 'unavailable');
                $assessmenturl = $gcatitem->assessmenturl->out(true);
                $statuslink = (($gcatitem->status->hasstatusurl) ? $gcatitem->assessmenturl->out(true) : '');
                $grade = $gcatitem->grading->gradetext;
                $gradeclass = false;
                $gradeprovisional = false;
                if ($gcatitem->grading->hasgrade) {
                    $gradeclass = true;
                    if ($gcatitem->grading->isprovisional) {
                        $gradeprovisional = true;
                    }
                }

                $tmp = [
                    'id' => $gcatitem->id,
                    'assessment_url' => $assessmenturl,
                    'item_icon' => $itemicon,
                    'icon_alt' => $iconalt,
                    'item_name' => $gcatitem->assessmentname,
                    'assessment_type' => $gcatitem->assessmenttype,
                    'assessment_weight' => $gcatitem->weight,
                    'due_date' => $duedate->format('jS F Y'),
                    'grade_status' => get_string("status_" . $class, "block_newgu_spdetails"),
                    'status_link' => $statuslink,
                    'status_class' => $gcatitem->status->class,
                    'status_text' => $gcatitem->status->statustext,
                    'grade' => $grade,
                    'grade_class' => $gradeclass,
                    'grade_provisional' => $gradeprovisional,
                    'grade_feedback' => $gcatitem->feedback->feedbacktext,
                    'grade_feedback_link' => (property_exists($gcatitem->feedback, 'feedbackurl') ?
                    $gcatitem->feedback->feedbackurl : ''),
                    'gcatenabled' => 'true',
                ];

                if ($activetab == 'past') {
                    unset($tmp['grade_status']);
                }

                $gcatdata[] = $tmp;
            }
        }

        return $gcatdata;
    }

    /**
     * Process and prepare for display default gradable items.
     *
     * Agreement between HM/TW/GP that we're only displaying items that
     * are visible - so if an assessment has been graded a then the item
     * hidden - this will not display. No further checks for hidden grades
     * are being done - based on how Moodle currenly does things.
     *
     * @param array $defaultitems
     * @param string $activetab
     * @param array|string $ltiinstancestoexclude
     * @param string $assessmenttype
     * @param string $sortby
     * @param string $sortorder
     * @return array
     */
    public static function process_default_items(array $defaultitems, string $activetab, array|string $ltiinstancestoexclude,
    string $assessmenttype, string $sortby, string $sortorder): array {

        global $USER;
        $defaultdata = [];

        if ($defaultitems && count($defaultitems) > 0) {

            $tmp = self::sort_items($defaultitems, $sortby, $sortorder);

            foreach ($tmp as $defaultitem) {

                $cm = get_coursemodule_from_instance($defaultitem->itemmodule, $defaultitem->iteminstance, $defaultitem->courseid,
                false, MUST_EXIST);
                $modinfo = get_fast_modinfo($defaultitem->courseid);
                $cm = $modinfo->get_cm($cm->id);

                // MGU-631 - Honour hidden grades and hidden activities.
                // Having discussed with HM, if the activity is hidden,
                // don't show it full stop.
                if ($cm->uservisible) {

                    if ($defaultitem->itemmodule == 'lti') {
                        if (is_array($ltiinstancestoexclude) && in_array($defaultitem->courseid, $ltiinstancestoexclude) ||
                        $defaultitem->courseid == $ltiinstancestoexclude) {
                            continue;
                        }
                    }

                    $itemicon = '';
                    $iconalt = '';
                    if ($iconurl = $cm->get_icon_url()->out(false)) {
                        $itemicon = $iconurl;
                        $iconalt = $cm->get_module_type_name();
                    }
                    $assessmentweight = \block_newgu_spdetails\course::return_weight($defaultitem->aggregationcoef);
                    $grade = '';
                    $gradeclass = false;
                    $gradeprovisional = false;
                    $gradestatus = '';
                    $statusclass = '';
                    $statustext = '';
                    $statuslink = '';
                    $gradefeedback = '';
                    $gradefeedbacklink = '';

                    $gradestatobj = \block_newgu_spdetails\grade::get_grade_status_and_feedback($defaultitem->courseid,
                            $defaultitem->id,
                            $defaultitem->itemmodule,
                            $defaultitem->iteminstance,
                            $USER->id,
                            $defaultitem->gradetype,
                            $defaultitem->scaleid,
                            $defaultitem->grademax,
                            'gradebookenabled',
                        );

                    $assessmenturl = $gradestatobj->assessment_url;
                    $duedate = $gradestatobj->due_date;
                    $gradestatus = $gradestatobj->grade_status;
                    $statuslink = $gradestatobj->status_link;
                    $statusclass = $gradestatobj->status_class;
                    $statustext = $gradestatobj->status_text;
                    // MGU-631 - Honour hidden grades and hidden activities.
                    $grade = ((!$defaultitem->hidden) ? $gradestatobj->grade_to_display :
                    get_string('status_text_tobeconfirmed', 'block_newgu_spdetails'));
                    $gradeclass = $gradestatobj->grade_class;
                    $gradeprovisional = $gradestatobj->grade_provisional;
                    $gradefeedback = $gradestatobj->grade_feedback;
                    $gradefeedbacklink = $gradestatobj->grade_feedback_link;

                    $tmp = [
                        'id' => $defaultitem->id,
                        'assessment_url' => $assessmenturl,
                        'item_icon' => $itemicon,
                        'icon_alt' => $iconalt,
                        'item_name' => $defaultitem->itemname,
                        'assessment_type' => $assessmenttype,
                        'assessment_weight' => $assessmentweight,
                        'due_date' => $duedate,
                        'grade_status' => $gradestatus,
                        'status_link' => $statuslink,
                        'status_class' => $statusclass,
                        'status_text' => $statustext,
                        'grade' => $grade,
                        'grade_class' => $gradeclass,
                        'grade_provisional' => $gradeprovisional,
                        'grade_feedback' => $gradefeedback,
                        'grade_feedback_link' => $gradefeedbacklink,
                        'gradebookenabled' => 'true',
                    ];

                    if ($activetab == 'past') {
                        unset($tmp['grade_status']);
                    }

                    $defaultdata[] = $tmp;
                }
            }
        }

        return $defaultdata;
    }

    /**
     * "Borrowed" from local_gugrades...
     * Factory to get the correct class based on the assignment type.
     * These are found in blocks_newgu_spdetails/classes/activities/
     * Pick xxx_activity for activity xxx (if exists) or default_activity
     * for everything else.
     *
     * @param int $gradeitemid
     * @param int $courseid
     * @param int $groupid
     * @return object
     */
    public static function activity_factory(int $gradeitemid, int $courseid, int $groupid = 0): object {
        global $DB;

        $item = $DB->get_record('grade_items', ['id' => $gradeitemid], '*', MUST_EXIST);
        $module = $item->itemmodule;
        $classname = '\\block_newgu_spdetails\\activities\\' . $module . '_activity';
        if (class_exists($classname)) {
            return new $classname($gradeitemid, $courseid, $groupid);
        } else {
            return new \block_newgu_spdetails\activities\default_activity($gradeitemid, $courseid, $groupid);
        }
    }

    /**
     * Utility function for sorting - as we're not using any fancy libraries
     * that will do this for us, we need to manually implement this feature.
     *
     * @param array $itemstosort
     * @param string $sortby
     * @param string $sortorder
     * @return array
     */
    public static function sort_items(array $itemstosort, string $sortby, string $sortorder): array {
        switch ($sortorder) {
            case "asc":
                uasort($itemstosort, function($a, $b) {

                    // Account for GCAT uniqueness.
                    if (property_exists($a, 'assessmentname')) {
                        return strcasecmp($a->assessmentname, $b->assessmentname);
                    }

                    return strcasecmp($a->itemname, $b->itemname);
                });
                break;

            case "desc":
                uasort($itemstosort, function($a, $b) {

                    // Account for GCAT uniqueness.
                    if (property_exists($a, 'assessmentname')) {
                        return strcasecmp($b->assessmentname, $a->assessmentname);
                    }

                    return strcasecmp($b->itemname, $a->itemname);
                });
                break;
        }

        return $itemstosort;
    }

}
