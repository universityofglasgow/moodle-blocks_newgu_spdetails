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
 * Class to describe the structure of a course
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails;

define('ITEM_URL', $CFG->wwwroot . '/');
define('ITEM_SCRIPT', '/view.php?id=');
class activity {
    
    /**
     * Main method called from the API.
     * 
     * @param int $subcategory
     * @param int $userid
     * @param string $activetab
     * @param string $sortby
     * @param string $sortorder
     * @return array $activitydata
     */
    public static function get_activityitems(int $subcategory, int $userid, string $activetab, string $sortby, string $sortorder): array {    
        $activitydata = [];
        $coursedata = [];
        
        // What's my parent?
        // I need the parent of the parent in order to be able to always
        // step 'up' a level. \local_gugrades\grades::get_activitytree only
        // gives me the parent id, which breaks our mechanism.
        $subcat = \grade_category::fetch(['id' => $subcategory]);
        $parent = \grade_category::fetch(['id' => $subcat->parent]);
        if ($parent->parent == null) {
            $parentId = 0;
        } else {
            $parentId = $parent->id;
        }
        $activitydata['parent'] = $parentId;

        $courseid = $subcat->courseid;
        
        $course = get_course($courseid);
        $coursedata['coursename'] = $course->shortname;
        $coursedata['subcatfullname'] = $subcat->fullname;
        
        // The assessment type is derived from the parent - which works only 
        // as long as the parent name contains 'Formative' or 'Summative'...
        if (!$item = \grade_item::fetch(['courseid' => $course->id,'iteminstance' => $subcategory, 'itemtype' => 'category'])) {
            $item = \grade_item::fetch(['courseid' => $course->id,'iteminstance' => $subcategory, 'itemtype' => 'course']);    
        }
        $assessmenttype = \block_newgu_spdetails\course::return_assessmenttype($subcat->fullname, $item->aggregationcoef);

        // The weight for this grade (sub)category is derived from the aggregation 
        // coefficient value of the grade item, only if it's been set in the gradebook however.
        $weight = \block_newgu_spdetails\course::return_weight($item->aggregationcoef);
        $coursedata['weight'] = $weight;
        
        // We'll need to merge these next two arrays at some point, to allow the sorting to
        // to work on all items, rather than just by category/activity item as it currently does.
        $activities = \local_gugrades\api::get_activities($course->id, $subcategory);
        $activitiesdata = self::process_get_activities($activities, $course->id, $subcategory, $userid, $activetab, $assessmenttype, $sortby, $sortorder);
        $coursedata['subcategories'] = $activitiesdata['subcategories'];
        $coursedata['assessmentitems'] = $activitiesdata['assessmentitems'];
        $activitydata['coursedata'] = $coursedata;

        return $activitydata;
    }

    /**
     * @param object $activityitems
     * @param int $courseid
     * @param int $subcategory
     * @param int $userid
     * @param string $activetab
     * @param string $assessmenttype
     * @param string $sortby
     * @param string $sortorder
     * @return array $data
     */
    public static function process_get_activities(object $activityitems, int $courseid, int $subcategory, int $userid, string $activetab, string $assessmenttype, string $sortby, string $sortorder): array {
        $data = [];

        // We've lost all knowledge at this point of the course type - fetch it again.
        $gugradesenabled = \block_newgu_spdetails\course::is_type_mygrades($courseid);
        $gcatenabled = \block_newgu_spdetails\course::is_type_gcat($courseid);

        if ($activityitems->categories) {
            $categorydata = [];
            if ($gugradesenabled) {
                $categorydata = \block_newgu_spdetails\course::process_mygrades_subcategories($courseid, $activityitems->categories, $assessmenttype, $sortorder);
            }

            if ($gcatenabled) {
                $categorydata = \block_newgu_spdetails\course::process_gcat_subcategories($courseid, $activityitems->categories, $assessmenttype, $sortorder);
            }

            if (!$gugradesenabled && !$gcatenabled) {
                $categorydata = \block_newgu_spdetails\course::process_default_subcategories($courseid, $activityitems->categories, $assessmenttype, $sortorder);
            }

            $data['subcategories'] = $categorydata;
        }

        if ($activityitems->items) {
            // Temp fix for working out which LTI activities to exclude...
            $lti_instances_to_exclude = \block_newgu_spdetails\api::get_ltiinstancenottoinclude();
            
            $activitydata = [];
            if ($gugradesenabled) {
                $activitydata = \block_newgu_spdetails\activity::process_mygrades_items($activityitems->items, $activetab, $lti_instances_to_exclude, $assessmenttype, $sortorder);
            }

            if ($gcatenabled) {
                // We need to disregard the items we have and use the GCAT API instead...
                $activitydata = \block_newgu_spdetails\activity::process_gcat_items($subcategory, $lti_instances_to_exclude, $userid, $activetab, $assessmenttype, $sortby, $sortorder);
            }

            if (!$gugradesenabled && !$gcatenabled) {
                $activitydata = \block_newgu_spdetails\activity::process_default_items($activityitems->items, $activetab, $lti_instances_to_exclude, $assessmenttype, $sortorder);
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
     * @param array|string $lti_instances_to_exclude
     * @param string $assessmenttype
     * @param string $sortorder
     * @return array $mygradesdata
     */
    public static function process_mygrades_items(array $mygradesitems, string $activetab, array|string $lti_instances_to_exclude, string $assessmenttype, string $sortorder): array {

        global $DB, $USER;
        $mygradesdata = [];

        if ($mygradesitems && count($mygradesitems) > 0) {
            
            $tmp = self::sort_items($mygradesitems, $sortorder);
            
            foreach($tmp as $mygradesitem) {
                
                // Is the item hidden from this user...
                $cm = get_coursemodule_from_instance($mygradesitem->itemmodule, $mygradesitem->iteminstance, $mygradesitem->courseid, false, MUST_EXIST);
                $modinfo = get_fast_modinfo($mygradesitem->courseid);
                $cm = $modinfo->get_cm($cm->id);
                if ($cm->uservisible) {

                    if ($mygradesitem->itemmodule == 'lti') {
                        if (is_array($lti_instances_to_exclude) && in_array($mygradesitem->courseid, $lti_instances_to_exclude) ||
                        $mygradesitem->courseid == $lti_instances_to_exclude) {
                            continue;
                        }
                    }

                    $item_icon = '';
                    $icon_alt = '';
                    if ($iconurl = $cm->get_icon_url()->out(false)) {
                        $item_icon = $iconurl;
                        $icon_alt = $cm->get_module_type_name();
                    }
                    $assessment_weight = \block_newgu_spdetails\course::return_weight($mygradesitem->aggregationcoef);
                    $due_date = '';
                    $grade = '';
                    $grade_status = '';
                    $status_class = '';
                    $status_text = '';
                    $status_link = '';
                    $grade_feedback = '';
                    $grade_feedback_link = '';

                    $params = [
                        'courseid' => $mygradesitem->courseid,
                        'gradeitemid' => $mygradesitem->id,
                        'userid' => $USER->id, 
                        'iscurrent' => 1
                    ];
                    if ($usergrades = $DB->get_records('local_gugrades_grade', $params)) {
                        // @todo - swap all of this for the relevant mygrades API calls - if/when one exists.
                        $assessment_url = $cm->url->out();
                        $dateobj = \DateTime::createFromFormat('U', $cm->customdata['duedate']);
                        $due_date = $dateobj->format('jS F Y');
                        
                        foreach ($usergrades as $usergrade) {
                            switch($usergrade->gradetype) {
                                case 'RELEASED':
                                    $grade = $usergrade->displaygrade;
                                    $grade_status = get_string('status_graded', 'block_newgu_spdetails');
                                    $status_text = get_string('status_text_graded', 'block_newgu_spdetails');
                                    $status_class = get_string('status_class_graded', 'block_newgu_spdetails');
                                    $grade_feedback = get_string('status_text_viewfeedback', 'block_newgu_spdetails');
                                    $grade_feedback_link = $assessment_url . '#page-footer';
                                    break;
                            }
                        }
                    } else {
                        // MyGrades data hasn't been released yet, revert to getting data from Gradebook,
                        // but don't include Grade, or Feedback data - this should remain empty.
                        $gradestatobj = \block_newgu_spdetails\grade::get_grade_status_and_feedback($mygradesitem->courseid, 
                            $mygradesitem->id, 
                            $mygradesitem->itemmodule, 
                            $mygradesitem->iteminstance, 
                            $USER->id, 
                            $mygradesitem->gradetype, 
                            $mygradesitem->scaleid, 
                            $mygradesitem->grademax, 
                            'gugradesenabled'
                        );
                        
                        $assessment_url = $gradestatobj->assessment_url;
                        $due_date = $gradestatobj->due_date;
                        $grade_status = $gradestatobj->grade_status;
                        $status_link = $gradestatobj->status_link;
                        $status_class = $gradestatobj->status_class;
                        $status_text = $gradestatobj->status_text;
                    }

                    $tmp = [
                        'id' => $mygradesitem->id,
                        'assessment_url' => $assessment_url,
                        'item_icon' => $item_icon,
                        'icon_alt' => $item_alt,
                        'item_name' => $mygradesitem->itemname,
                        'assessment_type' => $assessmenttype,
                        'assessment_weight' => $assessment_weight,
                        'due_date' => $due_date,
                        'grade_status' => $grade_status,
                        'status_link' => $status_link,
                        'status_class' => $status_class,
                        'status_text' => $status_text,
                        'grade' => $grade,
                        'grade_feedback' => $grade_feedback,
                        'grade_feedback_link' => $grade_feedback_link,
                        'gugradesenabled' => 'true'
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
     * @param array|string $lti_instances_to_exclude
     * @param string $sortorder
     * @return array $gcatdata
     */
    public static function process_gcat_items(int $subcategory, array|string $lti_instances_to_exclude, int $userid, string $activetab, string $assessmenttype, string $sortby, string $sortorder): array {

        global $CFG;
        // Here we are simply deferring to GCAT's API to return assignments and their status and (released?) grade.
        require_once($CFG->dirroot. '/blocks/gu_spdetails/lib.php');
        // course fullname isn't referenced in the query, it's known as coursetitle - find and replace for now...
        $sortby = preg_replace('/(full|short)name/', 'coursetitle, activityname', $sortby);
        $gcatitems = \assessments_details::retrieve_gradable_activities($activetab, $userid, $sortby, $sortorder, $subcategory);
        $gcatdata = [];

        if ($gcatitems && count($gcatitems) > 0) {
            
            $tmp = self::sort_items($gcatitems, $sortorder);
            
            foreach($tmp as $gcatitem) {
                
                // GCAT seems to take care of checking if the module and item is visible to the user
                // in the API call above. We're assuming that we only have items returned that the
                // user is therefore able to see.

                // We have no knowledge of the itemmodule here - how do we get that for this check?
                if (property_exists($gcatitem, 'itemmodule') && $gcatitem->itemmodule == 'lti') {
                    if (is_array($lti_instances_to_exclude) && in_array($gcatitem->courseid, $lti_instances_to_exclude) ||
                    $gcatitem->courseid == $lti_instances_to_exclude) {
                        continue;
                    }
                }

                $item_icon = '';
                $icon_alt = '';
                // if ($iconurl = $cm->get_icon_url()->out(false)) {
                //     $item_icon = $iconurl;
                //     $icon_alt = $cm->get_module_type_name();
                // }
                $due_date = \DateTime::createFromFormat('U', $gcatitem->duedate);
                $class = (isset($gcatitem->status->class) ? $gcatitem->status->statustext : 'unavailable');
                $assessment_url = $gcatitem->assessmenturl->out(true);
                $status_link = (($gcatitem->status->hasstatusurl) ? $gcatitem->assessmenturl->out(true) : '');

                $tmp = [
                    'id' => $gcatitem->id,
                    'assessment_url' => $assessment_url,
                    'item_icon' => $item_icon,
                    'icon_alt' => $icon_alt,
                    'item_name' => $gcatitem->assessmentname,
                    'assessment_type' => $gcatitem->assessmenttype,
                    'assessment_weight' => $gcatitem->weight,
                    'due_date' => $due_date->format('jS F Y'),
                    'grade_status' =>  get_string("status_" . $class, "block_newgu_spdetails"),
                    'status_link' => $status_link,
                    'status_class' => $gcatitem->status->class,
                    'status_text' => $gcatitem->status->statustext,
                    'grade' => $gcatitem->grading->gradetext,
                    'grade_feedback' => $gcatitem->feedback->feedbacktext,
                    'grade_feedback_link' => (property_exists($gcatitem->feedback, 'feedbackurl') ? $gcatitem->feedback->feedbackurl : ''),
                    'gcatenabled' => 'true'
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
     * @param array|string $lti_instances_to_exclude
     * @param string $assessmenttype
     * @param string $sortorder
     * @return array $defaultdata
     */
    public static function process_default_items(array $defaultitems, string $activetab, array|string $lti_instances_to_exclude, string $assessmenttype, string $sortorder): array {
        
        global $USER;
        $defaultdata = [];

        if ($defaultitems && count($defaultitems) > 0) {
            
            $tmp = self::sort_items($defaultitems, $sortorder);
            
            foreach($tmp as $defaultitem) {
                
                // Is the item hidden from this user...
                $cm = get_coursemodule_from_instance($defaultitem->itemmodule, $defaultitem->iteminstance, $defaultitem->courseid, false, MUST_EXIST);
                $modinfo = get_fast_modinfo($defaultitem->courseid);
                $cm = $modinfo->get_cm($cm->id);
                if ($cm->uservisible) {
                
                    if ($defaultitem->itemmodule == 'lti') {
                        if (is_array($lti_instances_to_exclude) && in_array($defaultitem->courseid, $lti_instances_to_exclude) ||
                        $defaultitem->courseid == $lti_instances_to_exclude) {
                            continue;
                        }
                    }

                    $item_icon = '';
                    $icon_alt = '';
                    if ($iconurl = $cm->get_icon_url()->out(false)) {
                        $item_icon = $iconurl;
                        $icon_alt = $cm->get_module_type_name();
                    }
                    $assessmentweight = \block_newgu_spdetails\course::return_weight($defaultitem->aggregationcoef);
                    $grade = '';
                    $grade_status = '';
                    $status_class = '';
                    $status_text = '';
                    $status_link = '';
                    $grade_feedback = '';
                    $grade_feedback_link = '';

                    $gradestatobj = \block_newgu_spdetails\grade::get_grade_status_and_feedback($defaultitem->courseid, 
                            $defaultitem->id, 
                            $defaultitem->itemmodule, 
                            $defaultitem->iteminstance, 
                            $USER->id, 
                            $defaultitem->gradetype, 
                            $defaultitem->scaleid, 
                            $defaultitem->grademax, 
                            'gradebookenabled'
                        );
                        
                    $assessmenturl = $gradestatobj->assessment_url;
                    $duedate = $gradestatobj->due_date;
                    $grade_status = $gradestatobj->grade_status;
                    $status_link = $gradestatobj->status_link;
                    $status_class = $gradestatobj->status_class;
                    $status_text = $gradestatobj->status_text;
                    $grade = $gradestatobj->grade_to_display;
                    $grade_feedback = $gradestatobj->grade_feedback;
                    $grade_feedback_link = $gradestatobj->grade_feedback_link;

                    $tmp = [
                        'id' => $defaultitem->id,
                        'assessment_url' => $assessmenturl,
                        'item_icon' => $item_icon,
                        'icon_alt' => $icon_alt,
                        'item_name' => $defaultitem->itemname,
                        'assessment_type' => $assessmenttype,
                        'assessment_weight' => $assessmentweight,
                        'due_date' => $duedate,
                        'grade_status' => $grade_status,
                        'status_link' => $status_link,
                        'status_class' => $status_class,
                        'status_text' => $status_text,
                        'grade' => $grade,
                        'grade_feedback' => $grade_feedback,
                        'grade_feedback_link' => $grade_feedback_link,
                        'gradebookenabled' => 'true'
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
     * @param array $itemstosort
     * @param string $sortorder
     */
    public static function sort_items($itemstosort, $sortorder) {
        switch($sortorder) {
            case "asc":
                uasort($itemstosort, function($a, $b) {
                    
                    // Account for GCAT uniqueness :-(
                    if (property_exists($a, 'assessmentname')) {
                        return strcmp($a->assessmentname, $b->assessmentname);
                    }
                    
                    return strcmp($a->itemname, $b->itemname);
                });
                break;

            case "desc":
                uasort($itemstosort, function($a, $b) {
                    
                    // Account for GCAT uniqueness :-(
                    if (property_exists($a, 'assessmentname')) {
                        return strcmp($b->assessmentname, $a->assessmentname);
                    }

                    return strcmp($b->itemname, $a->itemname);
                });
                break;
        }

        return $itemstosort;
    }

}
 