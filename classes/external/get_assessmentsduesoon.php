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
 * Web Service to return the assessments due soon for a given student
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use cache;

class get_assessmentsduesoon extends external_api {

    const CACHE_KEY = 'studentid_duesoon:';

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            // No params needed at this time.
        ]);
    }

    /**
     * Return the assessments due in the next 24 hours, 1 week and 1 month.
     *
     * We probably want to cache this on something like a 5 minute basis,
     * given that the service gets called each time the user visits the
     * dashboard.
     * 
     * @return array of assessments, grouped by return time.
     * @throws \invalid_parameter_exception
     */
    public static function execute(): array {
        global $USER;

        $cache = cache::make('block_newgu_spdetails', 'studentdashboarddata');
        $currenttime = time();
        $fiveminutes = $currenttime - 300;
        $cachekey = self::CACHE_KEY . $USER->id;
        $cachedata = $cache->get_many([$cachekey]);

        if (!$cachedata[$cachekey] || $cachedata[$cachekey][0]['summaryupdated'] < $fiveminutes) {
            $assessmentsduesoon = \block_newgu_spdetails\api::get_assessmentsduesoon();
            $twentyfourhours = $assessmentsduesoon['24hours'];
            $week = $assessmentsduesoon['week'];
            $month = $assessmentsduesoon['month'];

            $statscount = [
                "summaryupdated" => time(),
                "24hours" => $twentyfourhours,
                "week" => $week,
                "month" => $month,
            ];

            $cachedata = [
                $cachekey => [
                    $statscount,
                ],
            ];
            $cache->set_many($cachedata);
        } else {
            $cachedata = $cache->get_many([$cachekey]);
            $twentyfourhours = $cachedata[$cachekey][0]["24hours"];
            $week = $cachedata[$cachekey][0]["week"];
            $month = $cachedata[$cachekey][0]["month"];
        }
        
        $stats[] = [
            '24hours' => $twentyfourhours,
            'week' => $week,
            'month' => $month,
        ];

        return $stats;
    }

    /**
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                '24hours' => new external_value(PARAM_INT, 'due in 24 hours'),
                'week' => new external_value(PARAM_INT, 'due in the next week'),
                'month' => new external_value(PARAM_INT, 'due by the end of the month'),
            ])
        );
    }
}
