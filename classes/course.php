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

 class course {

    /**
     * Given an array of 1 or more courses, return pertinent information.
     * 
     * @param array $courses - an array of courses the user is enrolled in
     * @param bool $active - indicate if this is a current or past course
     * @param return array $data
     */
    public static function get_course_structure(array $courses, bool $active) {
        $coursedata = [];
        $data = [
            'parent' => 0
        ];

        if (!$courses) {
            return $data;
        }

        foreach($courses as $course) {
            
            // Fetch the categories and subcategories...
            $coursedata['coursename'] = $course->shortname;
            $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
            $coursedata['courseurl'] = $courseurl->out();
            
            if (!$active) {
                $startdate = \DateTime::createFromFormat('U', $course->startdate);
                $enddate = \DateTime::createFromFormat('U', $course->enddate);
                $coursedata['startdate'] = $startdate->format('jS F Y');
                $coursedata['enddate'] = $enddate->format('jS F Y');
            }
            
            $subcatdata = [];
            if (isset($course->firstlevel) && count($course->firstlevel) > 0) {
                foreach($course->firstlevel as $subcategory) {
                    $subcatid = 0;
                    $subcatname = '';
                    $subcatid = $subcategory['id'];
                    $subcatname = $subcategory['fullname'];
                    $item = \grade_item::fetch(['courseid' => $course->id,'iteminstance' => $subcatid, 'itemtype' => 'category']);
                    $assessmenttype = self::return_assessmenttype($subcatname, $item->aggregationcoef);
                    $subcatweight = self::return_weight($item->aggregationcoef);
                    $subcatdata[] = [
                        'id' => $subcatid,
                        'name' => $subcatname,
                        'assessmenttype' => $assessmenttype,
                        'subcatweight' => $subcatweight
                    ];
                }
            } else {
                // Our course appears to contain no sub categories :-( ...
                $gradecat = \grade_category::fetch(['courseid' => $course->id]);
                $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
                $assessmenttype = self::return_assessmenttype($course->fullname, $item->aggregationcoef);
                $subcatweight = self::return_weight($item->aggregationcoef);
                $subcatdata[] = [
                    'id' => $gradecat->id,
                    'name' => $course->fullname,
                    'assessmenttype' => $assessmenttype,
                    'subcatweight' => $subcatweight
                ];
            }

            $coursedata['subcategories'] = $subcatdata;
            $data['coursedata'][] = $coursedata;
        }

        // This is needed by the template for 'past' courses.
        if (!$active) {
            $data['hasstartdate'] = true;
            $data['hasenddate'] = true;
        }

        return $data;
    }

    /**
     * Return the sub categories belonging to the parent
     * 
     * @param int $subcategory
     * @param int $courseid
     * @param string $sortorder
     * @param return array $subcatdata
     */
    public static function get_course_sub_categories(int $gradecategory, int $courseid, string $assessmenttype, string $sortorder = null) {
    
        
        $gugradesenabled = \block_newgu_spdetails\course::is_mygrades_type($courseid);
        $gcatenabled = \block_newgu_spdetails\course::is_gcat_type($courseid);

        $subcatdata = [];
        
        // Allow the relevant course type to call its API
        if ($gugradesenabled) {
            $subcatdata = self::process_mygrades_subcategories($courseid, $gradecategory, $assessmenttype, $sortorder);
        }

        if ($gcatenabled) {
            $subcatdata = self::process_gcat_subcategories($courseid, $gradecategory, $assessmenttype, $sortorder);
        }

        if (!$gugradesenabled && !$gcatenabled) {
            $subcatdata = self::process_default_subcategories($courseid, $gradecategory, $assessmenttype, $sortorder);
        }
        
        return $subcatdata;
    }

    /**
     * Process and prepare for display MyGrades specific sub categories
     * 
     * @param int $courseid
     * @param int $gradecategoryid
     * @param string $assessmenttype
     * @param string $sortorder
     * return array
     */
    public static function process_mygrades_subcategories($courseid, $mygradecategories, $assessmenttype, $sortorder) {
        
        $mygrades_subcatdata = [];
        $tmp = [];
        
        foreach($mygradecategories as $obj) {
            $item = \grade_item::fetch(['courseid' => $courseid,'iteminstance' => $obj->category->id, 'itemtype' => 'category']);
            $subcatweight = \block_newgu_spdetails\course::return_weight($item->aggregationcoef);
            // We need to work out the grade aggregate for any graded items w/in this sub category...
            // Is there an API call for this?
            $subcat = new \stdClass();
            $subcat->id = $obj->category->id;
            $subcat->name = $obj->category->fullname;
            $subcat->assessment_type = $assessmenttype;
            $subcat->subcatweight = $subcatweight;

            $tmp[] = $subcat;
        }

        // @todo - This needs redone. $mygradecategories comes in as an array of 
        // objects, whose category property is also an object - making 
        // sorting a tad awkward. The items property that comes in also, 
        // is an array of objects containing the necessary property/key 
        // which ^can^ get sorted and returned in the correct order needed 
        // by the mustache engine.
        $tmp2 = self::sort_items($tmp, $sortorder);
        foreach($tmp2 as $sortedarray) {
            $mygrades_subcatdata[] = $sortedarray;
        }

        return $mygrades_subcatdata;
    }

    /**
     * Process and prepare for display GCAT specific sub categories.
     * 
     * There doesn't appear to be anything API wise we can use, so we're
     * having to do some manual legwork to get what we need here.
     * @param int $courseid
     * @param int $gcatcategories
     * @param string $assessmenttype
     * @param string $sortorder
     * return array 
     */
    public static function process_gcat_subcategories($courseid, $gcatcategories, $assessmenttype, $sortorder) {
        global $CFG, $USER;
        $gcat_subcatdata = [];
        $tmp = [];
        require_once($CFG->dirroot. '/blocks/gu_spdetails/lib.php');

        foreach($gcatcategories as $gcatcategory) {
            $item = \grade_item::fetch(['courseid' => $courseid,'iteminstance' => $gcatcategory->category->id, 'itemtype' => 'category']);
            $subcatweight = \block_newgu_spdetails\course::return_weight($item->aggregationcoef);
            // We need to work out the grade aggregate for any graded items w/in this sub category...
            // Is there an API call for this?
            $subcat = new \stdClass();
            $subcat->id = $gcatcategory->category->id;
            $subcat->name = $gcatcategory->category->fullname;
            $subcat->assessment_type = $assessmenttype;
            $subcat->subcatweight = $subcatweight;

            // We have an array of 'items' at this point - which we can use to work out the overall grade
            // for each (sub)category - which should then give us an overall grade for all sub categories 
            // of our parent - I think...
            if ($gcatcategory->items) {

            }

            $tmp[] = $subcat;
        }

        // @todo - This needs redone. $gcatcategories comes in as an array of 
        // objects, whose category property is also an object - making 
        // sorting a tad awkward. The items property that comes in also, 
        // is an array of objects containing the necessary property/key 
        // which ^can^ get sorted and returned in the correct order needed 
        // by the mustache engine.
        $tmp2 = self::sort_items($tmp, $sortorder);
        foreach($tmp2 as $sortedarray) {
            $gcat_subcatdata[] = $sortedarray;
        }

        return $gcat_subcatdata;
    }

    /**
     * Process and prepare for display MyGrades specific sub categories
     * 
     * @param int $courseid
     * @param int $gradecategoryid
     * @param string $assessmenttype
     * @param string $sortorder
     * return array
     */
    public static function process_default_subcategories($courseid, $subcategories, $assessmenttype, $sortorder) {
        $default_subcatdata = [];
        $tmp = [];
        
        foreach($subcategories as $obj) {
            $item = \grade_item::fetch(['courseid' => $courseid,'iteminstance' => $obj->category->id, 'itemtype' => 'category']);
            $subcatweight = self::return_weight($item->aggregationcoef);
            $subcat = new \stdClass();
            $subcat->id = $obj->category->id;
            $subcat->name = $obj->category->fullname;
            $subcat->assessment_type = $assessmenttype;
            $subcat->subcatweight = $subcatweight;

            $tmp[] = $subcat;
        }

        // @todo - This needs redone. $subcategories comes in as an array of 
        // objects, whose category property is also an object - making 
        // sorting a tad awkward. The items property that comes in also, 
        // is an array of objects containing the necessary property/key 
        // which ^can^ get sorted and returned in the correct order needed 
        // by the mustache engine.
        $tmp2 = self::sort_items($tmp, $sortorder);
        foreach($tmp2 as $sortedarray) {
            $default_subcatdata[] = $sortedarray;
        }

        return $default_subcatdata;
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
                    return strcmp($a->name, $b->name);
                });
                break;

            case "desc":
                uasort($itemstosort, function($a, $b) {
                    return strcmp($b->name, $a->name);
                });
                break;
        }

        return $itemstosort;
    }

    /**
     * Reusing the code from local_gugrades/api::get_dashboard_get_courses
     * @param int $courseid
     * @return bool $gugradesenabled
     */
    public static function is_type_mygrades(int $courseid) {
        global $DB;
        
        $gugradesenabled = false;
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

        return $gugradesenabled;
    }

    /**
     * Reusing the code from local_gugrades/api::get_dashboard_get_courses
     * @param int $courseid
     * @return bool $gcatenabled
     */
    public static function is_type_gcat($courseid) {
        global $DB;

        $gcatenabled = false;
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

        return $gcatenabled;
    }

    /**
     * Returns the 'weight' in percentage
     * 
     * @param float $aggregationcoef
     * 
     * According to the spec, weighting is now derived only from the weight in the Gradebook set up.
     * @see https://gla.sharepoint.com/:w:/s/GCATUpgradeProjectTeam/EVDsT68UetZMn8Ug5ISb394BfYLW_MwcyMI7RF0JAC38PQ?e=BOofAS
     * 
     * @return string Weight (in percentage), or '—' if empty
     */
    public static function return_weight(float $aggregationcoef) {
        $weight = (($aggregationcoef > 1) ? $aggregationcoef : $aggregationcoef * 100);
        $finalweight = ($weight > 0) ? round($weight, 2) . '%' : get_string('emptyvalue', 'block_newgu_spdetails');

        return $finalweight;
    }

    /**
     * Returns the 'assessment type' for an assessment, using its weighting as a
     * 
     * @param string $gradecategoryname
     * @param int $aggregationcoef
     * @return string 'Formative', 'Summative', or '—'
     */
    public static function return_assessmenttype(string $gradecategoryname, float $aggregationcoef) {
        $type = strtolower($gradecategoryname);
        $hasweight = !empty((float)$aggregationcoef);

        if ($hasweight || (!$hasweight && strpos($type, 'summative') !== false)) {
            $assessmenttype = get_string('summative', 'block_newgu_spdetails');
        } else if (!$hasweight && strpos($type, 'formative') !== false) {
            $assessmenttype = get_string('formative', 'block_newgu_spdetails');
        } else if (!$hasweight && strpos($type, 'summative') === false && strpos($type, 'formative') === false) {
            $assessmenttype = get_string('emptyvalue', 'block_newgu_spdetails');
        }

        return $assessmenttype;
    }

    /**
     * @param string $cmdodule
     * @param int $courseid
     * @param int $instance
     * @param return int $cmid
     */
    public static function get_cmid(string $cmodule, int $courseid, int $instance)
    {
        // cmodule is module name e.g. quiz, forums etc.
        global $DB;

        $arr_module = $DB->get_record('modules', array('name' => $cmodule));
        $moduleid = $arr_module->id;

        $arr_coursemodule = $DB->get_record('course_modules', array('course' => $courseid, 'module' => $moduleid, 'instance' => $instance));

        $cmid = $arr_coursemodule->id;

        return $cmid;

    }

    /**
     * This method returns all courses a user is currently enrolled in.
     * Courses can be filtered by course type and user type.
     *
     * @param int $userid
     * @param string $coursetype
     * @param string $usertype
     * @return array|void
     * @throws dml_exception
     */
    public static function return_enrolledcourses(int $userid, string $coursetype, string $usertype = "student")
    {

        $currentdate = time();
        $coursetypewhere = "";

        global $DB;

        $fields = "c.id, c.fullname as coursename";
        $fieldwhere = "c.visible = 1 AND c.visibleold = 1";

        if ($coursetype == "past") {
            $coursetypewhere = " AND ( c.enddate + (86400 * 30) <=" . $currentdate . " AND c.enddate!=0 )";
        }

        if ($coursetype == "current") {
            $coursetypewhere = " AND ( c.enddate + (86400 * 30) >" . $currentdate . " OR c.enddate=0 )";
        }

        if ($coursetype == "all") {
            $coursetypewhere = "";
        }

        $enrolmentselect = "SELECT DISTINCT e.courseid FROM {enrol} e
                            JOIN {user_enrolments} ue
                            ON (ue.enrolid = e.id AND ue.userid = ?)";

        $enrolmentjoin = "JOIN ($enrolmentselect) en ON (en.courseid = c.id)";

        $sql = "SELECT $fields FROM {course} c $enrolmentjoin
                WHERE $fieldwhere $coursetypewhere";

        $param = [$userid];

        $results = $DB->get_records_sql($sql, $param);

        if ($results) {
            $studentcourses = [];
            $staffcourses = [];
            foreach ($results as $courseid => $courseobject) {

                $coursename = $courseobject->coursename;

                if (\block_newgu_spdetails\api::return_isstudent($courseid, $userid)) {
                    array_push($studentcourses, $courseid);

                } else {
                    $cntstaff = \block_newgu_spdetails\api::checkrole($userid, $courseid);
                    if ($cntstaff != 0) {
                        array_push($staffcourses, ["courseid" => $courseid, "coursename" => $coursename]);
                    }
                }
            }

            if ($usertype == "student") {
                return $studentcourses;
            }

            if ($usertype == "staff") {
                return $staffcourses;
            }

        } else {
            return [];
        }
    }

}
