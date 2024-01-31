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

 class activity {
    
    /**
     * Main method called from the API
     */
    public static function get_activityitems(int $subcategory, int $userid, string $sortorder) {

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
        $coursedata['subcategories'] = \block_newgu_spdetails\course::get_course_sub_categories($subcategory, $course->id, $assessmenttype, $sortorder);
        $coursedata['assessmentitems'] = self::get_assessment_items($subcategory, $userid, $assessmenttype, $courseid, $sortorder);
        $activitydata['coursedata'] = $coursedata;

        return $activitydata;
    }

    /**
     * Return the assessment items for this category
     * 
     * @param int $subcategory
     * @param int $userid
     * @param string $assessmenttype
     * @param string $sortorder
     * @param int $courseid
     * @return array $assessmentdata
     */
    public static function get_assessment_items(int $subcategory, int $userid, string $assessmenttype, int $courseid, string $sortorder = "asc") {
        global $DB, $USER;

        // Do we need to carry out a context check here?
        // Other than Admin or a teacher, is there anyway someone 
        // other than the student can view their dashboard?
        $context = \context_course::instance($courseid);
        if ($USER->id != $userid) {
            $hascap = has_capability('block/newgu_spdetails:readotherdashboard', $context);
        }

        // We've lost all knowledge at this point of the course type - fetch it again.
        $gugradesenabled = \block_newgu_spdetails\course::is_mygrades_type($courseid);
        $gcatenabled = \block_newgu_spdetails\course::is_gcat_type($courseid);

        // This should be the point where we either query grade_items or local_gugrades_grade
        // Check if our course is MyGrades enabled, if so, run a custom SQL query LEFT JOINing
        // local_gugrades_grade against grade_items and checking for grade entries there, in 
        // order to use/fetch grade status info - otherwise, just fall back to the below query
        // grade_item::fetchall()
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
            $assessmentitems = $DB->get_records_sql($sql, ['gradecategoryid' => $subcategory, 'userid' => $userid]);
        }
        
        if (!$gugradesenabled && ($gcatenabled || !$gcatenabled)) {
            $assessmentitems = \grade_item::fetch_all(['courseid' => $courseid, 'categoryid' => $subcategory]);
        }

        // With the course type now determined, we can use it to derive the "items"
        // from either the local_gugrades_x tables, or the regular grade_x tables.
        $assessmentdata = [];

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
                if ($assessmentitem->itemmodule == 'lti') {
                    if (is_array($lti_instances_to_exclude) && in_array($assessmentitem->courseid, $lti_instances_to_exclude) ||
                    $assessmentitem->courseid == $lti_instances_to_exclude) {
                        continue;
                    }
                }
                
                $assessmentweight = \block_newgu_spdetails\course::return_weight($assessmentitem->aggregationcoef);
                
                // if ($gugradesenabled) {
                //     if (in_array($assessmentitem->id, $gradesenabled_assessmentitems) {
                //         // Call the appropriate \local_gugrades\ methods
                //        $gradestatus = $gradesenabled_assessmentitems->$assessmentitem->id->object
                //     } else {
                //         // All other properties for the assessment need to be set if no corresponding mygrades record was found.
                //         $gradestatus = \block_newgu_spdetails\grade::get_grade_status_and_feedback($assessmentitem->courseid, $assessmentitem->id, $userid, $assessmentitem->gradetype, $assessmentitem->scaleid);
                //     }
                // }
                // OR...
                // $gradestatus = \block_newgu_spdetails\grade::get_grade_status_and_feedback($assessmentitem->courseid, $assessmentitem->id, $userid, $assessmentitem->gradetype, $assessmentitem->scaleid, $assessmentitem->grademax, $coursetype);
                
                $gradestatus = \block_newgu_spdetails\grade::return_gradestatus($assessmentitem->itemmodule, $assessmentitem->iteminstance, $assessmentitem->courseid, $assessmentitem->id, $userid);
                $duedate = \DateTime::createFromFormat('U', $gradestatus['duedate']);
                $gradefeedback = \block_newgu_spdetails\grade::get_gradefeedback($assessmentitem->itemmodule, $assessmentitem->iteminstance, $assessmentitem->courseid, $assessmentitem->id, $userid, $assessmentitem->grademax, $assessmentitem->gradetype);
                $feedback = (($gradefeedback['link']) ? get_string('readfeedback', 'block_newgu_spdetails') : (($assessmentitem->itemmodule != 'quiz') ? $gradefeedback['gradetodisplay'] : ''));
                // $coursetype is only really needed/used by the unit tests.
                $assessmentdata[] = [
                    'id' => $assessmentitem->id,
                    'assessmenturl' => $gradestatus['assessmenturl'],
                    'itemname' => $assessmentitem->itemname,
                    'assessmenttype' => $assessmenttype,
                    'assessmentweight' => $assessmentweight,
                    'duedate' => $duedate->format('jS F Y'),
                    'grade_status' => $gradestatus['status'],
                    'status_link' => $gradestatus['link'],
                    'status_class' => $gradestatus['status_class'],
                    'status_text' => $gradestatus['status_text'],
                    'grade' => $gradefeedback['gradetodisplay'],
                    'grade_feedback' => $feedback,
                    'grade_feedback_link' => $gradefeedback['link'],
                    $coursetype => 'true'
                ];
            }
        }

        return $assessmentdata;
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
            return new \blocks_newgu_spdetails\activities\manual($gradeitemid, $courseid, $groupid);
        } else {
            $classname = '\\blocks_newgu_spdetails\\activities\\' . $module . '_activity';
            if (class_exists($classname)) {
                return new $classname($gradeitemid, $courseid, $groupid);
            } else {
                return new \blocks_newgu_spdetails\activities\default_activity($gradeitemid, $courseid, $groupid);
            }
        }
    }

}
 