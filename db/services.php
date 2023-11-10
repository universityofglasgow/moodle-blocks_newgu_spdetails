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
 * Web Service function calls.
 *
 * @package    block_newgu_spdetails
 * @author     Shubhendra Diophode <shubhendra.doiphode@gmail.com>
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$services = [
        // Define service for NEW GU SPDETAILS
        'New GU Details' => [
            'functions' => [
                'block_staff_dashboard_get_groupusers',
                'block_newgu_spdetails_get_coursegroups',
                'block_newgu_spdetails_get_assessmentsummary',
                'block_newgu_spdetails_get_assessments'
            ],
            'requiredcapability' => '',
            'restrictedusers' => 1,
            'enabled' => 1,
        ],
];

$functions = [
    'block_newgu_spdetails_get_groupusers' => [
        'classpath' => 'block/newgu_spdetails/classes/external.php',
        'classname'   => 'block_newgu_spdetails_external',
        'methodname'  => 'get_groupusers',
        'description' => 'Get group users',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'block_newgu_spdetails_get_coursegroups' => [
        'classpath' => 'block/newgu_spdetails/classes/external.php',
        'classname'   => 'block_newgu_spdetails_external',
        'methodname'  => 'get_coursegroups',
        'description' => 'Get course groups',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'block_newgu_spdetails_get_assessmentsummary' => [
        'classname'   => 'block_newgu_spdetails\external\get_assessmentsummary',
        'description' => 'Get users assessment statistics',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
        'services'    => [
            MOODLE_OFFICIAL_MOBILE_SERVICE
        ],
    ],
    'block_newgu_spdetails_get_assessments' => [
        'classname'   => 'block_newgu_spdetails\external\get_assessments',
        'description' => 'Display current and past assessments on the Student Dashboard',
        'type'        => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'services'    => [
            MOODLE_OFFICIAL_MOBILE_SERVICE
        ],
    ],
];
