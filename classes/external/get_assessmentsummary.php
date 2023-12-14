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
 * Web Service to return assessment statistics
 *
 * More indepth description.
 *
 * @package    block/newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use cache;

class get_assessmentsummary extends external_api {

    const CACHE_KEY = 'studentid:';

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            // No params needed at this time.
        ]);
    }

    /**
     * Return the assessment summary statistics
     * @return array of assessment summary statistics
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function execute(): array
    {
        global $DB, $USER;

        $cache = cache::make('block_newgu_spdetails', 'studentdashboarddata');
        $stats = [];
        $sub_assess = 0;
        $tobe_sub = 0;
        $overdue = 0;
        $assess_marked = 0;
        $total_overdue = 0;
        $total_submissions = 0;
        $total_tosubmit = 0;
        $marked = 0;
        $currenttime = time();
        $twohours = $currenttime - 7200;
        $cachekey = self::CACHE_KEY . $USER->id;

        if (!$cache->get([$cachekey]) || $cache->get([$cachekey[0]['timeupdated']]) < $twohours) {

            $currentcourses = \block_newgu_spdetails_external::return_enrolledcourses($USER->id, "current");

            if (!empty($currentcourses)) {
                $str_currentcourses = implode(",", $currentcourses);

                $str_itemsnotvisibletouser = \block_newgu_spdetails_external::fetch_itemsnotvisibletouser($USER->id, $str_currentcourses);

                $records = $DB->get_recordset_sql("SELECT id, courseid, itemmodule, iteminstance FROM {grade_items} WHERE courseid IN (" . $str_currentcourses . ") AND id NOT IN (" . $str_itemsnotvisibletouser . ") AND courseid > 1 AND itemtype='mod'");

                if ($records->valid()) {
                    foreach ($records as $key_gi) {

                        $modulename = $key_gi->itemmodule;
                        $iteminstance = $key_gi->iteminstance;
                        $courseid = $key_gi->courseid;
                        $itemid = $key_gi->id;

                        // security checks first off...
                        $context = \context_course::instance($courseid);
                        self::validate_context($context);
                        require_capability('mod/assign:viewownsubmissionsummary', $context, $USER->id);

                        $gradestatus = \block_newgu_spdetails_external::return_gradestatus($modulename, $iteminstance, $courseid, $itemid, $USER->id);
                        $status = $gradestatus["status"];
                        $finalgrade = $gradestatus["finalgrade"];

                        if ($status == get_string("status_tosubmit", "block_newgu_spdetails")) {
                            $total_tosubmit++;
                        }
                        if ($status == get_string("status_notsubmitted", "block_newgu_spdetails")) {
                            $total_tosubmit++;
                        }
                        if ($status == get_string("status_submitted", "block_newgu_spdetails")) {
                            $total_submissions++;
                            if ($finalgrade != Null) {
                                $marked++;
                            }
                        }
                        if ($status == get_string("status_overdue", "block_newgu_spdetails")) {
                            $total_overdue++;
                        }
                    }
                }
                $records->close();

                $sub_assess = $total_submissions;
                $tobe_sub = $total_tosubmit;
                $overdue = $total_overdue;
                $assess_marked = $marked;

                $statscount = [
                    "timeupdated" => time(),
                    "sub_assess" => $total_submissions,
                    "tobe_sub" => $total_tosubmit,
                    "overdue" => $total_overdue,
                    "assess_marked" => $marked
                ];

                $cachedata = [
                    $cachekey => [
                        $statscount
                    ]
                ];
                $cache->set_many($cachedata);
            }
        } else {
            $cachedata = $cache->get_many([$cachekey]);
            $sub_assess = $cachedata["sub_assess"];
            $tobe_sub = $cachedata["tobe_sub"];
            $overdue = $cachedata["overdue"];
            $assess_marked = $cachedata["assess_marked"];
        }

        $stats[] = [
            'sub_assess' => $sub_assess,
            'tobe_sub' => $tobe_sub,
            'overdue' => $overdue,
            'assess_marked' => $assess_marked
        ];

        return $stats;
    }

    /**
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure
    {
        return new external_multiple_structure(
            new external_single_structure([
                'sub_assess' => new external_value(PARAM_INT, 'total submissions'),
                'tobe_sub' => new external_value(PARAM_INT, 'assignments to be submitted'),
                'overdue' => new external_value(PARAM_INT, 'assignments overdue'),
                'assess_marked' => new external_value(PARAM_INT, 'assessments marked'),
            ])
        );
    }
}