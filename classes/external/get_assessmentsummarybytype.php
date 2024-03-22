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
 * Web Service to return the assessment summary by type: submitted, overdue etc.
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class get_assessmentsummarybytype extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'charttype' => new external_value(PARAM_INT, 'The selected type', VALUE_DEFAULT),
        ]);
    }

    /**
     * Return the assessments.
     *
     * @return array of assessments.
     * @throws \invalid_parameter_exception
     */
    public static function execute($charttype): array {
        $params = self::validate_parameters(self::execute_parameters(),
            [
                'charttype' => $charttype,
            ]);
        return [
            'result' => json_encode(\block_newgu_spdetails\api::get_assessmentsummarybytype(
                $params['charttype'])),
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_TEXT, 'The assessment summary, filtered by type - in JSON format'),
        ]);
    }
}
