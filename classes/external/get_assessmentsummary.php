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

    const CACHE_KEY = 'studentid_summary:';

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
     * 
     * @return array of assessment summary statistics
     */
    public static function execute(): array {
        global $USER;

        $cache = cache::make('block_newgu_spdetails', 'studentdashboarddata');
        $currenttime = time();
        $thirtyminutes = $currenttime - 1800;
        $cachekey = self::CACHE_KEY . $USER->id;
        $cachedata = $cache->get_many([$cachekey]);

        if (!$cachedata[$cachekey] || $cachedata[$cachekey][0]['timeupdated'] < $thirtyminutes) {

            $assessmentsummary = \block_newgu_spdetails_external::get_assessmentsummary();
            $total_submissions = $assessmentsummary['total_submissions'];
            $total_tosubmit = $assessmentsummary['total_tosubmit'];
            $total_overdue = $assessmentsummary['total_overdue'];
            $marked = $assessmentsummary['marked'];

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

        } else {
            $cachedata = $cache->get_many([$cachekey]);
            $sub_assess = $cachedata[$cachekey][0]["sub_assess"];
            $tobe_sub = $cachedata[$cachekey][0]["tobe_sub"];
            $overdue = $cachedata[$cachekey][0]["overdue"];
            $assess_marked = $cachedata[$cachekey][0]["assess_marked"];
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