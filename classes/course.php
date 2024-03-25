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
 * Class to describe the structure of a course.
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
     * @return array
     */
    public static function get_course_structure(array $courses, bool $active): array {
        $coursedata = [];
        $data = [
            'parent' => 0,
        ];

        if (!$courses) {
            return $data;
        }

        foreach ($courses as $course) {
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
                foreach ($course->firstlevel as $subcategory) {
                    $subcatid = 0;
                    $subcatname = '';
                    $subcatid = $subcategory['id'];
                    $subcatname = $subcategory['fullname'];
                    $item = \grade_item::fetch(['courseid' => $course->id, 'iteminstance' => $subcatid, 'itemtype' => 'category']);
                    $assessmenttype = self::return_assessmenttype($subcatname, $item->aggregationcoef);
                    $subcatweight = self::return_weight($item->aggregationcoef);
                    $subcatdata[] = [
                        'id' => $subcatid,
                        'name' => $subcatname,
                        'assessmenttype' => $assessmenttype,
                        'subcatweight' => $subcatweight,
                    ];
                }
            } else {
                // Our course appears to contain no sub categories :-( ...
                $gradecat = \grade_category::fetch_all(['courseid' => $course->id]);
                if ($gradecat) {
                    $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
                    $assessmenttype = self::return_assessmenttype($course->fullname, $item->aggregationcoef);
                    $subcatweight = self::return_weight($item->aggregationcoef);
                    if (count($gradecat) > 0) {
                        foreach ($gradecat as $gradecategory) {
                            $subcatdata[] = [
                                'id' => $gradecategory->id,
                                'name' => $course->fullname,
                                'assessmenttype' => $assessmenttype,
                                'subcatweight' => $subcatweight,
                            ];
                        }
                    }
                }
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
     * Process and prepare for display MyGrades specific sub categories.
     *
     * @param int $courseid
     * @param array $mygradecategories
     * @param string $assessmenttype
     * @param string $sortorder
     * @return array
     */
    public static function process_mygrades_subcategories(int $courseid, array $mygradecategories, string $assessmenttype,
    string $sortorder): array {
        $mygradessubcatdata = [];
        $tmp = [];
        foreach ($mygradecategories as $obj) {
            $item = \grade_item::fetch(['courseid' => $courseid, 'iteminstance' => $obj->category->id, 'itemtype' => 'category']);
            $subcatweight = self::return_weight($item->aggregationcoef);
            // We need to work out the grade aggregate for any graded items w/in this sub category...
            // Is there an API call for this?
            $subcat = new \stdClass();
            $subcat->id = $obj->category->id;
            $subcat->name = $obj->category->fullname;
            $subcat->assessment_type = $assessmenttype;
            $subcat->subcatweight = $subcatweight;

            $tmp[] = $subcat;
        }

        // This needs redone. $mygradecategories comes in as an array of
        // objects, whose category property is also an object - making
        // sorting a tad awkward. The items property that comes in also,
        // is an array of objects containing the necessary property/key
        // which ^can^ get sorted and returned in the correct order needed
        // by the mustache engine. @todo!
        $tmp2 = self::sort_items($tmp, $sortorder);
        foreach ($tmp2 as $sortedarray) {
            $mygradessubcatdata[] = $sortedarray;
        }

        return $mygradessubcatdata;
    }

    /**
     * Process and prepare for display GCAT specific sub categories.
     *
     * There doesn't appear to be anything API wise we can use, so we're
     * having to do some manual legwork to get what we need here.
     * @param int $courseid
     * @param array $gcatcategories
     * @param string $assessmenttype
     * @param string $sortorder
     * @return array
     */
    public static function process_gcat_subcategories(int $courseid, array $gcatcategories, string $assessmenttype,
    string $sortorder): array {
        global $CFG, $USER;
        $gcatsubcatdata = [];
        $tmp = [];
        require_once($CFG->dirroot. '/blocks/gu_spdetails/lib.php');

        foreach ($gcatcategories as $gcatcategory) {
            $item = \grade_item::fetch([
                'courseid' => $courseid,
                'iteminstance' => $gcatcategory->category->id,
                'itemtype' => 'category',
            ]);
            $subcatweight = self::return_weight($item->aggregationcoef);
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
            // if ($gcatcategory->items) {}.

            $tmp[] = $subcat;
        }

        // This needs redone. $gcatcategories comes in as an array of
        // objects, whose category property is also an object - making
        // sorting a tad awkward. The items property that comes in also,
        // is an array of objects containing the necessary property/key
        // which ^can^ get sorted and returned in the correct order needed
        // by the mustache engine. @todo!
        $tmp2 = self::sort_items($tmp, $sortorder);
        foreach ($tmp2 as $sortedarray) {
            $gcatsubcatdata[] = $sortedarray;
        }

        return $gcatsubcatdata;
    }

    /**
     * Process and prepare for display MyGrades specific sub categories.
     *
     * @param int $courseid
     * @param array $subcategories
     * @param string $assessmenttype
     * @param string $sortorder
     * @return array
     */
    public static function process_default_subcategories(int $courseid, array $subcategories, string $assessmenttype,
    string $sortorder): array {
        $defaultsubcatdata = [];
        $tmp = [];

        foreach ($subcategories as $obj) {
            $item = \grade_item::fetch(['courseid' => $courseid, 'iteminstance' => $obj->category->id, 'itemtype' => 'category']);
            $subcatweight = self::return_weight($item->aggregationcoef);
            $subcat = new \stdClass();
            $subcat->id = $obj->category->id;
            $subcat->name = $obj->category->fullname;
            $subcat->assessment_type = $assessmenttype;
            $subcat->subcatweight = $subcatweight;

            $tmp[] = $subcat;
        }

        // This needs redone. $subcategories comes in as an array of
        // objects, whose category property is also an object - making
        // sorting a tad awkward. The items property that comes in also,
        // is an array of objects containing the necessary property/key
        // which ^can^ get sorted and returned in the correct order needed
        // by the mustache engine. @todo!
        $tmp2 = self::sort_items($tmp, $sortorder);
        foreach ($tmp2 as $sortedarray) {
            $defaultsubcatdata[] = $sortedarray;
        }

        return $defaultsubcatdata;
    }

    /**
     * Utility function for sorting - as we're not using any fancy libraries
     * that will do this for us, we need to manually implement this feature.
     *
     * @param array $itemstosort
     * @param string $sortorder
     * @return array
     */
    public static function sort_items(array $itemstosort, string $sortorder): array {
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
     * Reusing the code from local_gugrades/api::get_dashboard_get_courses.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_type_mygrades(int $courseid): bool {
        global $DB;

        $mygradesenabled = false;
        $sqlname = $DB->sql_compare_text('name');
        $sql = "SELECT * FROM {local_gugrades_config}
            WHERE courseid = :courseid
            AND $sqlname = :name
            AND value = :value";
        $params = [
            'courseid' => $courseid,
            'name' => 'enabledashboard',
            'value' => 1,
        ];
        if ($DB->record_exists_sql($sql, $params)) {
            $mygradesenabled = true;
        }

        return $mygradesenabled;
    }

    /**
     * Reusing the code from local_gugrades/api::get_dashboard_get_courses.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_type_gcat(int $courseid): bool {
        global $DB;

        $gcatenabled = false;
        $sqlshortname = $DB->sql_compare_text('shortname');
        $sql = "SELECT * FROM {customfield_data} cd
            JOIN {customfield_field} cf ON cf.id = cd.fieldid
            WHERE cd.instanceid = :courseid
            AND cd.intvalue = 1
            AND $sqlshortname = 'show_on_studentdashboard'";
        $params = [
            'courseid' => $courseid,
        ];
        if ($DB->record_exists_sql($sql, $params)) {
            $gcatenabled = true;
        }

        return $gcatenabled;
    }

    /**
     * Returns the 'weight' in percentage
     * According to the spec, weighting is now derived only from the weight in the Gradebook set up.
     * @see https://gla.sharepoint.com/:w:/s/GCATUpgradeProjectTeam/EVDsT68UetZMn8Ug5ISb394BfYLW_MwcyMI7RF0JAC38PQ?e=BOofAS
     *
     * @param float $aggregationcoef
     * @return string Weight (in percentage), or '—' if empty
     */
    public static function return_weight(float $aggregationcoef): string {
        $weight = (($aggregationcoef > 1) ? $aggregationcoef : $aggregationcoef * 100);
        $finalweight = ($weight > 0) ? round($weight, 2) . '%' : get_string('emptyvalue', 'block_newgu_spdetails');

        return $finalweight;
    }

    /**
     * Returns the 'assessment type' for an assessment. Achieved through using the
     * assessments aggregation coefficient and category name. If the item only has
     * a weighting value - then we consider it to be a summative assessment.
     *
     * @param string $gradecategoryname
     * @param int $aggregationcoef
     * @return string 'Formative', 'Summative', or '—'
     */
    public static function return_assessmenttype(string $gradecategoryname, float $aggregationcoef): string {
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
     * @return int
     */
    public static function get_cmid(string $cmodule, int $courseid, int $instance): int {
        // ...$cmodule is module name e.g. quiz, forums etc.
        global $DB;

        $arrmodule = $DB->get_record('modules', ['name' => $cmodule]);
        $moduleid = $arrmodule->id;

        $arrcoursemodule = $DB->get_record('course_modules', [
            'course' => $courseid,
            'module' => $moduleid,
            'instance' => $instance,
        ]);

        $cmid = $arrcoursemodule->id;

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
    public static function return_enrolledcourses(int $userid, string $coursetype, string $usertype = "student"): array {

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

    /**
     * Return the assessments that are due in the next 24 hours, week and month.
     *
     * @return array
     */
    public static function get_assessmentsduesoon() {
        global $USER;

        $sortstring = 'shortname asc';
        $courses = \local_gugrades\api::dashboard_get_courses($USER->id, true, false, $sortstring);

        $stats = [
            '24hours' => 0,
            'week' => 0,
            'month' => 0,
        ];

        if (!$courses) {
            return $stats;
        }

        $assignmentdata = [];
        $ltiinstancestoexclude = \block_newgu_spdetails\api::get_ltiinstancenottoinclude();
        foreach ($courses as $course) {

            if ($course->firstlevel) {
                foreach ($course->firstlevel as $subcategory) {
                    $subcategoryid = $subcategory['id'];
                    $activities = \local_gugrades\api::get_activities($course->id, $subcategoryid);

                    if ($activities) {
                        $categoryitems = \block_newgu_spdetails\grade::recurse_categorytree($subcategoryid, $activities->items, [],
                        $activities->categories);

                        // This is now a flat list of all items associated with this course...
                        if ($categoryitems) {
                            foreach ($categoryitems->items as $item) {
                                $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $item->courseid,
                                false, MUST_EXIST);
                                $modinfo = get_fast_modinfo($item->courseid);
                                $cm = $modinfo->get_cm($cm->id);
                                if ($cm->uservisible) {
                                    if ($item->itemmodule == 'lti') {
                                        if (is_array($ltiinstancestoexclude) && in_array($item->courseid, $ltiinstancestoexclude)
                                        || $item->courseid == $ltiinstancestoexclude) {
                                            continue;
                                        }
                                    }

                                    // Get the activity based on its type...
                                    $activity = \block_newgu_spdetails\activity::activity_factory($item->id, $item->courseid, 0);
                                    if ($records = $activity->get_assessmentsdue()) {
                                        $assignmentdata[] = $records[0];
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // Our course structure doesn't have any categories.
                $activities = \local_gugrades\api::get_activities($course->id, 1);
                if ($activities) {
                    $categoryitems = \block_newgu_spdetails\grade::recurse_categorytree(1,
                    $activities->items, [], $activities->categories);

                    // This is now a flat list of all items associated with this course...
                    if ($categoryitems) {
                        foreach ($categoryitems->items as $item) {
                            $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance,
                            $item->courseid, false, MUST_EXIST);
                            $modinfo = get_fast_modinfo($item->courseid);
                            $cm = $modinfo->get_cm($cm->id);
                            if ($cm->uservisible) {
                                if ($item->itemmodule == 'lti') {
                                    if (is_array($ltiinstancestoexclude) && in_array($item->courseid,
                                    $ltiinstancestoexclude) || $item->courseid == $ltiinstancestoexclude) {
                                        continue;
                                    }
                                }

                                // Get the activity based on its type...
                                $activity = \block_newgu_spdetails\activity::activity_factory($item->id,
                                    $item->courseid, 0
                                );
                                if ($records = $activity->get_assessmentsdue()) {
                                    $assignmentdata[] = $records[0];
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!$assignmentdata) {
            return $stats;
        }

        $now = mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"));
        $next24hours = mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 1, date("Y"));
        $next7days = mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 7, date("Y"));
        $nextmonth = mktime(date("H"), date("i"), date("s"), date("m") + 1, date("d"), date("Y"));

        $duein24hours = 0;
        $duein7days = 0;
        $dueinnextmonth = 0;

        foreach ($assignmentdata as $assignment) {
            if (($assignment->duedate > $now) && ($assignment->duedate < $next24hours)) {
                $duein24hours++;
            }

            if (($assignment->duedate > $now) && ($assignment->duedate < $next7days)) {
                $duein7days++;
            }

            if (($assignment->duedate > $now) && ($assignment->duedate < $nextmonth)) {
                $dueinnextmonth++;
            }
        }

        $stats = [
            '24hours' => $duein24hours,
            'week' => $duein7days,
            'month' => $dueinnextmonth,
        ];

        return $stats;
    }

    /**
     * Return assessments that are due - filtered by type: 24hrs, 7days etc.
     *
     * @param int $charttype
     * @return array
     */
    public static function get_assessmentsduebytype(int $charttype): array {
        global $USER, $PAGE;

        $PAGE->set_context(\context_system::instance());

        $sortstring = 'shortname asc';
        $courses = \local_gugrades\api::dashboard_get_courses($USER->id, true, false, $sortstring);

        $assessmentsdue = [];

        if (!$courses) {
            return $assessmentsdue;
        }

        $option = '';
        switch($charttype) {
            case 0:
                $when = mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 1, date("Y"));
                $option = get_string('chart_24hrs', 'block_newgu_spdetails');
                break;
            case 1:
                $when = mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 7, date("Y"));
                $option = get_string('chart_7days', 'block_newgu_spdetails');
                break;
            case 2:
                $when = mktime(date("H"), date("i"), date("s"), date("m") + 1, date("d"), date("Y"));
                $option = get_string('chart_1mth', 'block_newgu_spdetails');
                break;
        }

        $assessmentsdueheader = get_string('header_assessmentsdue', 'block_newgu_spdetails', $option);

        $assessmentdata = [];
        $ltiinstancestoexclude = \block_newgu_spdetails\api::get_ltiinstancenottoinclude();
        foreach ($courses as $course) {
            $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
            // We're expecting our course object to contain a firstlevel array...
            if ($course->firstlevel) {
                foreach ($course->firstlevel as $subcategory) {
                    $subcategoryid = $subcategory['id'];
                    $activities = \local_gugrades\api::get_activities($course->id, $subcategoryid);

                    if ($activities) {
                        $categoryitems = \block_newgu_spdetails\grade::recurse_categorytree($subcategoryid, $activities->items, [],
                        $activities->categories);

                        // This is now a flat list of all items associated with this course...
                        if ($categoryitems) {
                            foreach ($categoryitems->items as $item) {
                                $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $item->courseid,
                                false, MUST_EXIST);
                                $modinfo = get_fast_modinfo($item->courseid);
                                $cm = $modinfo->get_cm($cm->id);
                                if ($cm->uservisible) {
                                    if ($item->itemmodule == 'lti') {
                                        if (is_array($ltiinstancestoexclude) && in_array($item->courseid, $ltiinstancestoexclude)
                                        || $item->courseid == $ltiinstancestoexclude) {
                                            continue;
                                        }
                                    }

                                    // Get the activity based on its type...
                                    $activityitem = \block_newgu_spdetails\activity::activity_factory($item->id,
                                    $item->courseid, 0);
                                    if ($assessments = $activityitem->get_assessmentsdue()) {
                                        $assessment = $assessments[0];
                                        if (($assessment->duedate != 0) && $assessment->duedate < $when) {
                                            $itemicon = '';
                                            $iconalt = '';
                                            if ($iconurl = $cm->get_icon_url()->out(false)) {
                                                $itemicon = $iconurl;
                                                $iconalt = $cm->get_module_type_name();
                                            }
                                            $assessmentweight = self::return_weight($item->aggregationcoef);
                                            $assessmenttype = self::return_assessmenttype($subcategory['fullname'],
                                            $item->aggregationcoef);
                                            $status = $activityitem->get_status($USER->id);
                                            $duedate = '';
                                            if ($assessment->duedate != 0) {
                                                $duedate = $activityitem->get_formattedduedate($assessment->duedate);
                                            }
                                            $tmp = [
                                                'id' => $assessment->id,
                                                'courseurl' => $courseurl->out(),
                                                'coursename' => $course->shortname,
                                                'assessment_url' => $activityitem->get_assessmenturl(),
                                                'item_icon' => $itemicon,
                                                'icon_alt' => $iconalt,
                                                'item_name' => $assessment->name,
                                                'assessment_type' => $assessmenttype,
                                                'assessment_weight' => $assessmentweight,
                                                'due_date' => $duedate,
                                                'grade_status' => $status->grade_status,
                                                'status_link' => $status->status_link,
                                                'status_class' => $status->status_class,
                                                'status_text' => $status->status_text,
                                                'gradebookenabled' => '',
                                            ];

                                            $assessmentdata[] = $tmp;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // Our course has no sub categories...
                $gradecat = \grade_category::fetch_all(['courseid' => $course->id]);
                if ($gradecat) {
                    if (count($gradecat) > 0) {
                        foreach ($gradecat as $gradecategory) {
                            $activities = \local_gugrades\api::get_activities($course->id, $gradecategory->id);
                            if ($activities) {
                                $categoryitems = \block_newgu_spdetails\grade::recurse_categorytree($gradecategory->id,
                                $activities->items, [], $activities->categories);

                                // This is now a flat list of all items associated with this course...
                                if ($categoryitems) {
                                    foreach ($categoryitems->items as $item) {
                                        $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance,
                                        $item->courseid, false, MUST_EXIST);
                                        $modinfo = get_fast_modinfo($item->courseid);
                                        $cm = $modinfo->get_cm($cm->id);
                                        if ($cm->uservisible) {
                                            if ($item->itemmodule == 'lti') {
                                                if (is_array($ltiinstancestoexclude) && in_array($item->courseid,
                                                $ltiinstancestoexclude) || $item->courseid == $ltiinstancestoexclude) {
                                                    continue;
                                                }
                                            }

                                            // Get the activity based on its type...
                                            $activityitem = \block_newgu_spdetails\activity::activity_factory($item->id,
                                            $item->courseid, 0);
                                            if ($assessments = $activityitem->get_assessmentsdue()) {
                                                $assessment = $assessments[0];
                                                if (($assessment->duedate != 0) && $assessment->duedate < $when) {
                                                    $itemicon = '';
                                                    $iconalt = '';
                                                    if ($iconurl = $cm->get_icon_url()->out(false)) {
                                                        $itemicon = $iconurl;
                                                        $iconalt = $cm->get_module_type_name();
                                                    }
                                                    $assessmentweight = self::return_weight($item->aggregationcoef);
                                                    $assessmenttype = self::return_assessmenttype($subcategory['fullname'],
                                                    $item->aggregationcoef);
                                                    $status = $activityitem->get_status($USER->id);
                                                    $duedate = '';
                                                    if ($assessment->duedate != 0) {
                                                        $duedate = $activityitem->get_formattedduedate($assessment->duedate);
                                                    }
                                                    $tmp = [
                                                        'id' => $assessment->id,
                                                        'courseurl' => $courseurl->out(),
                                                        'coursename' => $course->shortname,
                                                        'assessment_url' => $activityitem->get_assessmenturl(),
                                                        'item_icon' => $itemicon,
                                                        'icon_alt' => $iconalt,
                                                        'item_name' => $assessment->name,
                                                        'assessment_type' => $assessmenttype,
                                                        'assessment_weight' => $assessmentweight,
                                                        'due_date' => $duedate,
                                                        'grade_status' => $status->grade_status,
                                                        'status_link' => $status->status_link,
                                                        'status_class' => $status->status_class,
                                                        'status_text' => $status->status_text,
                                                        'gradebookenabled' => '',
                                                    ];

                                                    $assessmentdata[] = $tmp;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $assessmentsdue['chart_header'] = $assessmentsdueheader;

        if (!$assessmentdata) {
            return $assessmentsdue;
        }

        $assessmentsdue['assessmentitems'] = $assessmentdata;

        return $assessmentsdue;
    }

    /**
     * Return the summary of assessments that have been marked, submitted, are
     * outstanding or are overdue.
     *
     * @return array
     */
    public static function get_assessmentsummary(): array {

        global $DB, $USER;

        $marked = 0;
        $totaloverdue = 0;
        $totalsubmissions = 0;
        $totaltosubmit = 0;

        $currentcourses = self::return_enrolledcourses($USER->id, "current");

        $stats = [
            'total_submissions' => 0,
            'total_tosubmit' => 0,
            'total_overdue' => 0,
            'marked' => 0,
        ];

        if (!$currentcourses) {
            return $stats;
        }

        $strcurrentcourses = implode(",", $currentcourses);
        $stritemsnotvisibletouser = \block_newgu_spdetails\api::fetch_itemsnotvisibletouser($USER->id, $strcurrentcourses);

        $query = "SELECT id, courseid, itemmodule, iteminstance, gradetype, scaleid, grademax
        FROM {grade_items}
        WHERE courseid IN (" . $strcurrentcourses . ")
        AND id NOT IN (" . $stritemsnotvisibletouser . ") AND courseid > 1 AND itemtype='mod'";
        $records = $DB->get_recordset_sql($query);

        if ($records->valid()) {
            foreach ($records as $keygi) {

                $modulename = $keygi->itemmodule;
                $iteminstance = $keygi->iteminstance;
                $courseid = $keygi->courseid;
                $itemid = $keygi->id;

                // Is the item hidden from this user...
                $cm = get_coursemodule_from_instance($modulename, $iteminstance, $courseid, false, MUST_EXIST);
                $modinfo = get_fast_modinfo($courseid);
                $cm = $modinfo->get_cm($cm->id);
                if ($cm->uservisible) {

                    $gradestatus = \block_newgu_spdetails\grade::get_grade_status_and_feedback($courseid,
                        $itemid,
                        $modulename,
                        $iteminstance,
                        $USER->id,
                        $keygi->gradetype,
                        $keygi->scaleid,
                        $keygi->grademax,
                        ''
                    );

                    $status = $gradestatus->grade_status;
                    if ($status == get_string('status_submitted', 'block_newgu_spdetails')) {
                        $totalsubmissions++;
                    }

                    if ($status == get_string('status_submit', 'block_newgu_spdetails')) {
                        $totaltosubmit++;
                    }

                    if ($status == get_string('status_overdue', 'block_newgu_spdetails')) {
                        $totaloverdue++;
                    }

                    if ($status == get_string('status_graded', 'block_newgu_spdetails')) {
                        if (($gradestatus->grade_to_display != null) && ($gradestatus->grade_to_display !=
                        get_string('status_text_tobeconfirmed', 'block_newgu_spdetails'))) {
                            $marked++;
                        }
                    }
                }
            }
        }

        $records->close();

        $stats = [
            'total_submissions' => $totalsubmissions,
            'total_tosubmit' => $totaltosubmit,
            'total_overdue' => $totaloverdue,
            'marked' => $marked,
        ];

        return $stats;
    }

    /**
     * Return only the assessments that have been:
     * Submitted
     * Are still to be submitted
     * Overdue
     * Marked/Graded
     *
     * @param int $charttype
     * @return array
     */
    public static function get_assessmentsummarybytype(int $charttype): array {
        global $DB, $USER, $PAGE;

        $PAGE->set_context(\context_system::instance());

        $sortstring = 'shortname asc';
        $courses = \local_gugrades\api::dashboard_get_courses($USER->id, true, false, $sortstring);

        $assessmentsdue = [];

        if (!$courses) {
            return $assessmentsdue;
        }

        $dateheader = '';
        $option = '';
        $whichstatus = '';
        switch ($charttype) {
            case 0:
                $option = get_string('status_text_submitted', 'block_newgu_spdetails');
                $dateheader = get_string('header_datesubmitted', 'block_newgu_spdetails');
                $whichstatus = get_string('status_submitted', 'block_newgu_spdetails');
                break;
            case 1:
                $option = get_string('status_text_tobesubmitted', 'block_newgu_spdetails');
                $dateheader = get_string('header_duedate', 'block_newgu_spdetails');
                $whichstatus = get_string('status_submit', 'block_newgu_spdetails');
                break;
            case 2:
                $option = get_string('status_text_overdue', 'block_newgu_spdetails');
                $dateheader = get_string('header_duedate', 'block_newgu_spdetails');
                $whichstatus = get_string('status_overdue', 'block_newgu_spdetails');
                break;
            case 3:
                $option = get_string('status_text_marked', 'block_newgu_spdetails');
                $dateheader = get_string('header_dategraded', 'block_newgu_spdetails');
                $whichstatus = get_string('status_graded', 'block_newgu_spdetails');
                break;
        }

        $assessmentsummaryheader = get_string('header_assessmentsummary', 'block_newgu_spdetails', $option);

        $assessmentdata = [];
        $ltiinstancestoexclude = \block_newgu_spdetails\api::get_ltiinstancenottoinclude();
        foreach ($courses as $course) {
            $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
            // We're expecting our course object to contain a firstlevel array...
            if ($course->firstlevel) {
                foreach ($course->firstlevel as $subcategory) {
                    $subcategoryid = $subcategory['id'];
                    $activities = \local_gugrades\api::get_activities($course->id, $subcategoryid);

                    if ($activities) {
                        $categoryitems = \block_newgu_spdetails\grade::recurse_categorytree($subcategoryid, $activities->items, [],
                        $activities->categories);

                        // This is now a flat list of all items associated with this course...
                        if ($categoryitems) {
                            foreach ($categoryitems->items as $item) {
                                $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $item->courseid,
                                false, MUST_EXIST);
                                $modinfo = get_fast_modinfo($item->courseid);
                                $cm = $modinfo->get_cm($cm->id);
                                if ($cm->uservisible) {
                                    if ($item->itemmodule == 'lti') {
                                        if (is_array($ltiinstancestoexclude) && in_array($item->courseid, $ltiinstancestoexclude)
                                        || $item->courseid == $ltiinstancestoexclude) {
                                            continue;
                                        }
                                    }

                                    // Get the activity based on its type...
                                    $gradestatus = \block_newgu_spdetails\grade::get_grade_status_and_feedback($item->courseid,
                                        $item->id,
                                        $item->itemmodule,
                                        $item->iteminstance,
                                        $USER->id,
                                        $item->gradetype,
                                        $item->scaleid,
                                        $item->grademax,
                                        '',
                                    );

                                    $status = $gradestatus->grade_status;
                                    $date = '';

                                    if ($status == $whichstatus) {
                                        $itemicon = '';
                                        $iconalt = '';
                                        if ($iconurl = $cm->get_icon_url()->out(false)) {
                                            $itemicon = $iconurl;
                                            $iconalt = $cm->get_module_type_name();
                                        }

                                        switch($charttype) {
                                            case 3:
                                                $dateobj = \DateTime::createFromFormat('U', $gradestatus->grade_date);
                                                $date = $dateobj->format('jS F Y');
                                                break;
                                            default:
                                                $date = $gradestatus->due_date;
                                                break;
                                        }

                                        $assessmenttype = self::return_assessmenttype($subcategory['fullname'],
                                        $item->aggregationcoef);
                                        $assessmentweight = self::return_weight($item->aggregationcoef);
                                        $tmp = [
                                            'id' => $item->id,
                                            'courseurl' => $courseurl->out(),
                                            'coursename' => $course->shortname,
                                            'assessment_url' => $gradestatus->assessment_url,
                                            'item_icon' => $itemicon,
                                            'icon_alt' => $iconalt,
                                            'item_name' => $item->itemname,
                                            'assessment_type' => $assessmenttype,
                                            'assessment_weight' => $assessmentweight,
                                            'due_date' => $date,
                                            'grade_status' => $gradestatus->grade_status,
                                            'status_link' => $gradestatus->status_link,
                                            'status_class' => $gradestatus->status_class,
                                            'status_text' => $gradestatus->status_text,
                                            'gradebookenabled' => '',
                                        ];

                                        $assessmentdata[] = $tmp;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // Our course structure doesn't have any categories per se - but we need to pass in a categoryid.
                $category = $DB->get_record('grade_categories', ['courseid' => $course->id], 'id');
                if ($category) {
                    $activities = \local_gugrades\api::get_activities($course->id, $category->id);
                    if ($activities) {
                        $categoryitems = \block_newgu_spdetails\grade::recurse_categorytree($category->id,
                        $activities->items, [], $activities->categories);

                        // This is now a flat list of all items associated with this course...
                        if ($categoryitems) {
                            foreach ($categoryitems->items as $item) {
                                $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance,
                                $item->courseid, false, MUST_EXIST);
                                $modinfo = get_fast_modinfo($item->courseid);
                                $cm = $modinfo->get_cm($cm->id);
                                if ($cm->uservisible) {
                                    if ($item->itemmodule == 'lti') {
                                        if (is_array($ltiinstancestoexclude) && in_array($item->courseid,
                                        $ltiinstancestoexclude) || $item->courseid == $ltiinstancestoexclude) {
                                            continue;
                                        }
                                    }

                                    // Get the activity based on its type...
                                    $gradestatus = \block_newgu_spdetails\grade::get_grade_status_and_feedback(
                                        $item->courseid,
                                        $item->id,
                                        $item->itemmodule,
                                        $item->iteminstance,
                                        $USER->id,
                                        $item->gradetype,
                                        $item->scaleid,
                                        $item->grademax,
                                        '',
                                    );

                                    $status = $gradestatus->grade_status;
                                    $date = '';

                                    if ($status == $whichstatus) {
                                        $itemicon = '';
                                        $iconalt = '';
                                        if ($iconurl = $cm->get_icon_url()->out(false)) {
                                            $itemicon = $iconurl;
                                            $iconalt = $cm->get_module_type_name();
                                        }

                                        switch ($charttype) {
                                            case 3:
                                                $date = 'tbc';
                                                break;
                                            default:
                                                $date = $gradestatus->due_date;
                                                break;
                                        }

                                        $assessmenttype = self::return_assessmenttype($course->fullname,
                                        $item->aggregationcoef);
                                        $assessmentweight = self::return_weight($item->aggregationcoef);
                                        $tmp = [
                                            'id' => $item->id,
                                            'courseurl' => $courseurl->out(),
                                            'coursename' => $course->shortname,
                                            'assessment_url' => $gradestatus->assessment_url,
                                            'item_icon' => $itemicon,
                                            'icon_alt' => $iconalt,
                                            'item_name' => $item->itemname,
                                            'assessment_type' => $assessmenttype,
                                            'assessment_weight' => $assessmentweight,
                                            'due_date' => $date,
                                            'grade_status' => $gradestatus->grade_status,
                                            'status_link' => $gradestatus->status_link,
                                            'status_class' => $gradestatus->status_class,
                                            'status_text' => $gradestatus->status_text,
                                            'gradebookenabled' => '',
                                        ];

                                        $assessmentdata[] = $tmp;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $assessmentsdue['chart_header'] = $assessmentsummaryheader;

        if (!$assessmentdata) {
            return $assessmentsdue;
        }

        $assessmentsdue['date_header'] = $dateheader;
        $assessmentsdue['assessmentitems'] = $assessmentdata;

        return $assessmentsdue;
    }

}
