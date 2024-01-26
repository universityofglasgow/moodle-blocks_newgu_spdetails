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
    public static function get_course_sub_categories(int $subcategory, int $courseid, string $assessmenttype, string $sortorder = null) {
        $subcategories = \grade_category::fetch_all(['parent' => $subcategory, 'hidden' => 0]);
        $subcatdata = [];
        if ($subcategories && count($subcategories) > 0) {
            
            // Owing to the fact that we can't sort using the grade_category::fetch_all method....
            switch($sortorder) {
                case "asc":
                    uasort($subcategories, function($a, $b) {
                        return strcmp($a->fullname, $b->fullname);
                    });
                    break;

                case "desc":
                    uasort($subcategories, function($a, $b) {
                        return strcmp($b->fullname, $a->fullname);
                    });
                    break;
            }
            
            foreach($subcategories as $subcategory) {
                $item = \grade_item::fetch(['courseid' => $courseid,'iteminstance' => $subcategory->id, 'itemtype' => 'category']);
                $subcatweight = self::return_weight($item->aggregationcoef);
                $subcatdata[] = [
                    'id' => $subcategory->id,
                    'name' => $subcategory->fullname,
                    'assessmenttype' => $assessmenttype,
                    'subcatweight' => $subcatweight
                ];
            }
        }
        
        return $subcatdata;
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
 }