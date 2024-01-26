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
     * 
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

        // If this isn't current user, do they have the rights to look at other users.
        $context = \context_course::instance($courseid);
        if ($USER->id != $userid) {
            require_capability('local/gugrades:readotherdashboard', $context);
        } else {
            require_capability('local/gugrades:readdashboard', $context);
        }

        // We've lost all knowledge at this point of the course type: mygrades, gcat etc.
        // We now need to run the same query again that's in Howard's API in order to 
        // determine the course type - using the course id that's now being passed in.
        $gugradesenabled = false;
        $gcatenabled = false;
    
        // MyGrades check
        $sqlname = $DB->sql_compare_text('name');
        $sql = "SELECT * FROM {local_gugrades_config}
            WHERE courseid = :courseid
            AND $sqlname = :name
            AND value = :value";
        $params = [
            'courseid' => $courseid, 
            'name' => 'enabledashboard', 
            'value' => 1
        ];
        if ($DB->record_exists_sql($sql, $params)) {
            $gugradesenabled = true;
        }

        // GCAT check
        $sqlshortname = $DB->sql_compare_text('shortname');
        $sql = "SELECT * FROM {customfield_data} cd
            JOIN {customfield_field} cf ON cf.id = cd.fieldid
            WHERE cd.instanceid = :courseid
            AND cd.intvalue = 1
            AND $sqlshortname = 'show_on_studentdashboard'";
        $params = [
            'courseid' => $courseid
        ];
        if ($DB->record_exists_sql($sql, $params)) {
            $gcatenabled = true;
        }

        // With the course type now determined, we can use it to derive the "items"
        // from either the local_gugrades_x tables, or the regular grade_x tables.
        $assessmentdata = [];

        // This should be the point where we query either grade_items or local_gugrades_grade
        // Check if our course is MyGrades enabled, if so, run a custom SQL query LEFT JOINing
        // local_gugrades_grade against grade_items and checking for grade entries there, in 
        // order to use/fetch grade status info - otherwise, just fall back to the below query
        // grade_item::fetchall()
        if ($gugradesenabled) {
            $assessmentitems = \local_gugrades\grades::get_dashboard_grades($userid, $subcategory);
        }

        if ($gcatenabled) {

        }
        
        if (!$gugradesenabled && ($gcatenabled || !$gcatenabled)) {
            $assessmentitems = \grade_item::fetch_all(['courseid' => $courseid, 'categoryid' => $subcategory, 'hidden' => 0, 'display' => 0]);
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

            $coursetype = (($gcatenabled) ? "gcatenabled" : "gradebookenabled");

            foreach($assessmentitems as $assessmentitem) {
                $assessmentweight = \block_newgu_spdetails\course::return_weight($assessmentitem->aggregationcoef);
                // What do we actually want back from return_gradestatus:
                // 1 the status as a value
                //   -- grade_status
                //   -- status_link
                //   -- status_text
                //   -- status_class
                //   -- grade
                
                // 2 the grade as a string
                // An object to pass to get_feedback - which doesn't need to repeat return_gradestatus
                // $gradeandgradestatus = self::get_gradeandgradestatus($assessmentitem->itemmodule, $assessmentitem->iteminstance, $assessmentitem->courseid, $assessmentitem->id, $userid, $assessmentitem->gradetype);
                // 'status' => $gradeandgradestatus->status;
                // 'status_class' => $gradeandgradestatus->status_class;
                // etc etc
                // $gradestatus = \block_newgu_spdetails\grade::return_grade_and_gradestatus($assessmentitem->itemmodule, $assessmentitem->iteminstance, $assessmentitem->courseid, $assessmentitem->id, $userid, $assessmentitem->gradetype, $assessmentitem->scaleid);
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
 }