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

define('ITEM_URL', $CFG->wwwroot . '/mod/');
define('ITEM_SCRIPT', '/view.php?id=');
class activity {
    
    /**
     * Main method called from the API
     */
    public static function get_activityitems(int $subcategory, int $userid, string $activetab, string $sortby, string $sortorder) {

        /**
         * Return Structure:
         * $data = [
         *     'parent' => '',
         *     'coursename' => 'GCAT 2023 TW - Existing GCAT',
         *     'subcatfullname' => 'Summative - Various 22 Point Scale Aggregations - course weighting 75%',
         *     'weight' => '75%',
         *     'assessmentitems' => [
         *         [
         *           "id" => 27,
         *           "name" => "Average of assignments - Sub components - Simple Weighted Mean"
         *           "assessmenttype" => "Average",
         *           "weight" => ""
         *         ]
         *     ],
         *     'subcategories' => [
         *         [
         *           "id" => 27,
         *           "name" => "Average of assignments - Sub components - Simple Weighted Mean"
         *           "assessmenttype" => "Average",
         *           "weight" => ""
         *         ],
         *     ]
         */
            
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
        $item = \grade_item::fetch(['courseid' => $course->id,'iteminstance' => $subcategory, 'itemtype' => 'category']);
        $assessmenttype = \block_newgu_spdetails\course::return_assessmenttype($subcat->fullname, $item->aggregationcoef);

        // The assessment weight is derived from the aggregation coefficient 
        // value of the grade item, only if it's been set in the gradebook however.
        $weight = \block_newgu_spdetails\course::return_weight($item->aggregationcoef);
        $coursedata['weight'] = $weight;
        
        // We'll need to merge these next two arrays at some point, to allow the sorting to
        // to work on all items, rather than just by category/activity item as it currently does.
        // @todo - The subcategories call could be moved out of here as it's not really suited to this method...
        // This should really be a 2d array - an array of subcategories - which contain any items and or further
        // sub categories
        // $subcats['sub cat name'][0]['further sub cat']['items']
        // $subcats['sub cat name'][1]['assessment item']
        $tmp = \local_gugrades\api::get_activities($course->id, $subcategory);
        $tmpdata = self::process_get_activities($tmp, $course->id, $assessmenttype, $sortorder);
        $coursedata['subcategories'] = $tmpdata['subcategories'];
        $coursedata['assessmentitems'] = $tmpdata['assessmentitems'];
        //$coursedata['subcategories'] = \block_newgu_spdetails\course::get_course_sub_categories($subcategory, $course->id, $assessmenttype, $sortorder);
        //$coursedata['assessmentitems'] = self::get_assessment_items($subcategory, $userid, $assessmenttype, $courseid, $activetab, $sortby, $sortorder);

        $activitydata['coursedata'] = $coursedata;

        return $activitydata;
    }

    public static function process_get_activities($courseobj, $courseid, $assessmenttype, $sortorder) {
        $data = [];
        $gradecategories = [];
        $activityitems = [];

        // We've lost all knowledge at this point of the course type - fetch it again.
        $gugradesenabled = \block_newgu_spdetails\course::is_mygrades_type($courseid);
        $gcatenabled = \block_newgu_spdetails\course::is_gcat_type($courseid);

        if ($courseobj->categories) {
            $categorydata = [];
            if ($gugradesenabled) {
                $categorydata = \block_newgu_spdetails\course::process_mygrades_subcategories($courseid, $courseobj->categories, $assessmenttype, $sortorder);
            }

            if ($gcatenabled) {
                $categorydata = \block_newgu_spdetails\course::process_gcat_subcategories($courseid, $courseobj->categories, $assessmenttype, $sortorder);
            }

            if (!$gugradesenabled && !$gcatenabled) {
                $categorydata = \block_newgu_spdetails\course::process_default_subcategories($courseid, $courseobj->categories);
            }

            $data['subcategories'] = $categorydata;
        }

        if ($courseobj->items) {
            $activitydata = [];
            if ($gugradesenabled) {
                $activitydata = \block_newgu_spdetails\activity::process_mygrades_items($courseobj->items);
            }

            if ($gcatenabled) {
                $activitydata = \block_newgu_spdetails\activity::process_gcat_items($courseobj->items);
            }

            if (!$gugradesenabled && !$gcatenabled) {
                $activitydata = \block_newgu_spdetails\activity::process_default_items($courseobj->items);
            }

            $activityitems['assessmentitems'] = $activitydata;
            $data[] = $activityitems;
        }

        return $data;
    }

    /**
     * Return the assessment items for this grade category (also known as subcategory)
     * 
     * @param int $subcategory
     * @param int $userid
     * @param string $assessmenttype
     * @param string $sortorder
     * @param int $courseid
     * @return array $assessmentdata
     */
    public static function get_assessment_items(int $subcategory, int $userid, string $assessmenttype, int $courseid, string $activetab, string $sortby, string $sortorder = "asc") {
        global $DB, $CFG, $USER;

        // Do we need to carry out a context check here?
        // Other than Admin or a teacher, is there anyway someone 
        // other than the student can view their dashboard?
        $context = \context_course::instance($courseid);
        if ($USER->id != $userid) {
            $hascap = has_capability('block/newgu_spdetails:readotherdashboard', $context);
        }

        // Data structure for the return
        $assessmentdata = [];

        // We've lost all knowledge at this point of the course type - fetch it again.
        $gugradesenabled = \block_newgu_spdetails\course::is_mygrades_type($courseid);
        $gcatenabled = \block_newgu_spdetails\course::is_gcat_type($courseid);
        $lti_instances_to_exclude = \block_newgu_spdetails\api::get_ltiinstancenottoinclude();

        if ($gugradesenabled) {
            //$assessmentitems = \local_gugrades\grades::get_dashboard_grades($userid, $subcategory);
            // Custom SQL for now - \local_gugrades\grades::get_dashboard_grades() doesn't give us what we need....
            $sql = "SELECT gi.id, gi.courseid, gi.categoryid, gi.itemname, gi.itemmodule, gi.iteminstance, 
            gi.grademax, gi.gradetype, gi.scaleid, gi.aggregationcoef, gg.rawgrade, gg.convertedgrade,
            gg.displaygrade, gg.gradetype AS gg_gradetype
            FROM mdl_grade_items gi 
            LEFT JOIN mdl_local_gugrades_grade gg ON (gg.gradeitemid = gi.id AND gg.userid = :userid)
            WHERE
            gi.categoryid = :gradecategoryid";
            $gugradesitems = $DB->get_records_sql($sql, ['gradecategoryid' => $subcategory, 'userid' => $userid]);
            $assessmentdata = self::process_mygrades_items($gugradesitems, $lti_instances_to_exclude, $sortorder);
        }

        if ($gcatenabled) {
            // Here we are simply deferring to GCAT's API to return assignments and their status and (released?) grade.
            require_once($CFG->dirroot. '/blocks/gu_spdetails/lib.php');
            // course fullname isn't referenced in the query, it's known as coursetitle - find and replace for now...
            $sortby = preg_replace('/(full|short)name/', 'coursetitle, activityname', $sortby);
            $gcatitems = \assessments_details::retrieve_gradable_activities($activetab, $userid, $sortby, $sortorder, $subcategory);
            $assessmentdata = self::process_gcat_items($gcatitems, $lti_instances_to_exclude, $sortorder);
        }
        
        if (!$gugradesenabled && !$gcatenabled) {
            $defaultitems = \grade_item::fetch_all(['courseid' => $courseid, 'categoryid' => $subcategory]);
            $assessmentdata = self::process_default_items($defaultitems, $lti_instances_to_exclude, $userid, $assessmenttype, $sortorder);
        }

        if ($assessmentitems && count($assessmentitems) > 0) {
            
            // Owing to the fact that we can't sort using the grade_item::fetch_all method....
            switch($sortorder) {
                case "asc":
                    uasort($assessmentitems, function($a, $b) {
                        return strcmp($a->itemname, $b->itemname);
                    });
                    break;

                case "desc":
                    uasort($assessmentitems, function($a, $b) {
                        return strcmp($b->itemname, $a->itemname);
                    });
                    break;
            }

            $coursetype = (($gugradesenabled) ? "gugradesenabled" : (($gcatenabled) ? "gcatenabled" : "gradebookenabled"));

            $lti_instances_to_exclude = \block_newgu_spdetails\api::get_ltiinstancenottoinclude();

            foreach($assessmentitems as $assessmentitem) {
                // First off, exlude the LTI instances that are not required to be shown...
                // @TODO - this feature doesn't appear to be working as expected.
                // Check mdl_lti - the typeid column isn't set initially, until an activity has the
                // lti 'typeid' linked to it. However, this is only updated when an lti is assigned, 
                // meaning that the lti query from the function will always find the matching record, 
                // regardless of what has been set in the settings page - i.e. even when exluding an item 
                if ($assessmentitem->itemmodule == 'lti') {
                    if (is_array($lti_instances_to_exclude) && in_array($assessmentitem->courseid, $lti_instances_to_exclude) ||
                    $assessmentitem->courseid == $lti_instances_to_exclude) {
                        continue;
                    }
                }
                
                $assessmentweight = \block_newgu_spdetails\course::return_weight($assessmentitem->aggregationcoef);
                $assessmentweight = $assessmentitem->weight;
                
                // $gradestatus = \block_newgu_spdetails\grade::get_grade_status_and_feedback($assessmentitem->courseid, $assessmentitem->id, $userid, $assessmentitem->gradetype, $assessmentitem->scaleid, $assessmentitem->grademax, $coursetype);
                
                //$gradestatus = \block_newgu_spdetails\grade::return_gradestatus($assessmentitem->itemmodule, $assessmentitem->iteminstance, $assessmentitem->courseid, $assessmentitem->id, $userid);
                $duedate = \DateTime::createFromFormat('U', $assessmentitem->duedate);
                //$gradefeedback = \block_newgu_spdetails\grade::get_gradefeedback($assessmentitem->itemmodule, $assessmentitem->iteminstance, $assessmentitem->courseid, $assessmentitem->id, $userid, $assessmentitem->grademax, $assessmentitem->gradetype);
                //$feedback = (($gradefeedback['link']) ? get_string('readfeedback', 'block_newgu_spdetails') : (($assessmentitem->itemmodule != 'quiz') ? $gradefeedback['gradetodisplay'] : ''));
                // $coursetype is only really needed/used by the unit tests.
                // $assessmentdata[] = [
                //     'id' => $assessmentitem->id,
                //     'assessmenturl' => $gradestatus['assessmenturl'],
                //     'itemname' => $assessmentitem->itemname,
                //     'assessmenttype' => $assessmenttype,
                //     'assessmentweight' => $assessmentweight,
                //     'duedate' => $duedate->format('jS F Y'),
                //     'grade_status' => $gradestatus['status'],
                //     'status_link' => $gradestatus['link'],
                //     'status_class' => $gradestatus['status_class'],
                //     'status_text' => $gradestatus['status_text'],
                //     'grade' => $gradefeedback['gradetodisplay'],
                //     'grade_feedback' => $feedback,
                //     'grade_feedback_link' => $gradefeedback['link'],
                //     $coursetype => 'true'
                // ];
                $blah = (isset($assessmentitem->status->class) ? $assessmentitem->status->statustext : 'unavailable');
                $assessmentdata[] = [
                    'id' => $assessmentitem->id,
                    'assessmenturl' => $assessmentitem->assessmenturl,
                    'itemname' => $assessmentitem->assessmentname,
                    'assessmenttype' => $assessmentitem->assessmenttype,
                    'assessmentweight' => $assessmentweight,
                    'duedate' => $duedate->format('jS F Y'),
                    'grade_status' =>  get_string("status_" . $blah, "block_newgu_spdetails"),
                    'status_link' => $assessmentitem->assessmenturl,
                    'status_class' => $assessmentitem->status->class,
                    'status_text' => $assessmentitem->status->statustext,
                    'grade' => $assessmentitem->grading->gradetext,
                    'grade_feedback' => $assessmentitem->feedback->feedbacktext,
                    $coursetype => 'true'
                ];
            }
        }

        return $assessmentdata;
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
                    return strcmp($a->itemname, $b->itemname);
                });
                break;

            case "desc":
                uasort($itemstosort, function($a, $b) {
                    return strcmp($b->itemname, $a->itemname);
                });
                break;
        }
    }

    /**
     * Process and prepare for display GCAT specific gradable items
     * 
     * @param array $gcatitems
     * @param array $lti_instances_to_exclude
     * @param string $sortorder
     */
    public static function process_gcat_items($gcatitems, $lti_instances_to_exclude, $sortorder) {

        $gcatdata = [];

        if ($gcatitems && count($gcatitems) > 0) {
            
            self::sort_items($gcatitems, $sortorder);
            
            foreach($gcatitems as $assessmentitem) {
                
                if ($assessmentitem->itemmodule == 'lti') {
                    if (is_array($lti_instances_to_exclude) && in_array($assessmentitem->courseid, $lti_instances_to_exclude) ||
                    $assessmentitem->courseid == $lti_instances_to_exclude) {
                        continue;
                    }
                }
                
                $assessmentweight = $assessmentitem->weight;
                $duedate = \DateTime::createFromFormat('U', $assessmentitem->duedate);
                $blah = (isset($assessmentitem->status->class) ? $assessmentitem->status->statustext : 'unavailable');
                $cmid = $assessmentitem->assessmenturl->get_param('id');
                $itemurl = ITEM_URL . ITEM_SCRIPT . $cmid;

                $gcatdata[] = [
                    'id' => $assessmentitem->id,
                    'assessmenturl' => $itemurl,
                    'itemname' => $assessmentitem->assessmentname,
                    'assessmenttype' => $assessmentitem->assessmenttype,
                    'assessmentweight' => $assessmentweight,
                    'duedate' => $duedate->format('jS F Y'),
                    'grade_status' =>  get_string("status_" . $blah, "block_newgu_spdetails"),
                    'status_link' => $assessmentitem->assessmenturl,
                    'status_class' => $assessmentitem->status->class,
                    'status_text' => $assessmentitem->status->statustext,
                    'grade' => $assessmentitem->grading->gradetext,
                    'grade_feedback' => $assessmentitem->feedback->feedbacktext,
                    'gcatenabled' => 'true'
                ];
            }
        }

        return $gcatdata;
    }

    /**
     * Process and prepare for display MyGrades specific gradable items
     * 
     * @param array $mygradesitems
     * @param array $lti_instances_to_exclude
     * @param string $sortorder
     */
    public static function process_mygrades_items($mygradesitems, $lti_instances_to_exclude, $sortorder) {

        $mygradesdata = [];

        if ($mygradesitems && count($mygradesitems) > 0) {
            
            self::sort_items($mygradesitems, $sortorder);
            
            foreach($mygradesitems as $assessmentitem) {
                
                if ($assessmentitem->itemmodule == 'lti') {
                    if (is_array($lti_instances_to_exclude) && in_array($assessmentitem->courseid, $lti_instances_to_exclude) ||
                    $assessmentitem->courseid == $lti_instances_to_exclude) {
                        continue;
                    }
                }
                
                $assessmentweight = $assessmentitem->weight;
                $duedate = \DateTime::createFromFormat('U', $assessmentitem->duedate);
                $blah = (isset($assessmentitem->status->class) ? $assessmentitem->status->statustext : 'unavailable');

                $mygradesdata[] = [
                    'id' => $assessmentitem->id,
                    'assessmenturl' => $assessmentitem->assessmenturl,
                    'itemname' => $assessmentitem->assessmentname,
                    'assessmenttype' => $assessmentitem->assessmenttype,
                    'assessmentweight' => $assessmentweight,
                    'duedate' => $duedate->format('jS F Y'),
                    'grade_status' =>  get_string("status_" . $blah, "block_newgu_spdetails"),
                    'status_link' => $assessmentitem->assessmenturl,
                    'status_class' => $assessmentitem->status->class,
                    'status_text' => $assessmentitem->status->statustext,
                    'grade' => $assessmentitem->grading->gradetext,
                    'grade_feedback' => $assessmentitem->feedback->feedbacktext,
                    'gugradesenabled' => 'true'
                ];
            }
        }

        return $mygradesdata;
    }

    /**
     * Process and prepare for display default gradable items
     * 
     * @param array $gcatitems
     * @param array $lti_instances_to_exclude
     * @param string $sortorder
     */
    public static function process_default_items($defaultitems, $lti_instances_to_exclude, $userid, $assessmenttype, $sortorder) {
        
        $defaultdata = [];

        if ($defaultitems && count($defaultitems) > 0) {
            
            self::sort_items($defaultitems, $sortorder);
            
            foreach($defaultitems as $assessmentitem) {
                
                if ($assessmentitem->itemmodule == 'lti') {
                    if (is_array($lti_instances_to_exclude) && in_array($assessmentitem->courseid, $lti_instances_to_exclude) ||
                    $assessmentitem->courseid == $lti_instances_to_exclude) {
                        continue;
                    }
                }

                $assessmentweight = \block_newgu_spdetails\course::return_weight($assessmentitem->aggregationcoef);
                $assessmentweight = $assessmentitem->weight;
                $duedate = \DateTime::createFromFormat('U', $assessmentitem->duedate);
                $blah = (isset($assessmentitem->status->class) ? $assessmentitem->status->statustext : 'unavailable');
                $gradestatus = \block_newgu_spdetails\grade::get_grade_status_and_feedback($assessmentitem->courseid, $assessmentitem->id, $assessmentitem->itemmodule, $assessmentitem->iteminstance, $userid, $assessmentitem->gradetype, $assessmentitem->scaleid, $assessmentitem->grademax, 'gradebookenabled');

                $defaultdata[] = [
                    'id' => $assessmentitem->id,
                    'assessmenturl' => $assessmentitem->assessmenturl,
                    'itemname' => $assessmentitem->itemname,
                    'assessmenttype' => $assessmenttype,
                    'assessmentweight' => $assessmentweight,
                    //'duedate' => $duedate->format('jS F Y'),
                    'grade_status' =>  get_string("status_" . $blah, "block_newgu_spdetails"),
                    'status_link' => $gradestatus->status->link,
                    'status_class' => $gradestatus->status->class,
                    'status_text' => $gradestatus->status->statustext,
                    'grade' => $gradestatus->grading->gradetext,
                    'grade_feedback' => $gradestatus->feedback->feedbacktext,
                    'gradebookenabled' => 'true'
                ];
            }
        }

        return $defaultdata;
    }

    /**
     * "Borrowed" from local_gugrades...
     * Factory to get correct class for assignment type
     * These are found in blocks_newgu_spdetails/classes/activities
     * Pick manual for manual grades, xxx_activity for activity xxx (if exists) or default_activity
     * for everything else
     * @param int $gradeitemid
     * @param int $courseid
     * @param int $groupid
     * @return object
     */
    public static function activity_factory(int $gradeitemid, int $courseid, int $groupid = 0) {
        global $DB;

        $item = $DB->get_record('grade_items', ['id' => $gradeitemid], '*', MUST_EXIST);
        $module = $item->itemmodule;
        if ($item->itemtype == 'manual') {
            return new \block_newgu_spdetails\activities\manual($gradeitemid, $courseid, $groupid);
        } else {
            $classname = '\\block_newgu_spdetails\\activities\\' . $module . '_activity';
            if (class_exists($classname)) {
                return new $classname($gradeitemid, $courseid, $groupid);
            } else {
                return new \block_newgu_spdetails\activities\default_activity($gradeitemid, $courseid, $groupid);
            }
        }
    }

}
 