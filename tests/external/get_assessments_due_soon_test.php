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
 * Test of the assessments due soon feature.
 *
 * @package    block_newgu_spdetails
 * @copyright  2024
 * @author     Greg Pedder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\tests\external;

defined('MOODLE_INTERNAL') || die();
 
global $CFG;

require_once($CFG->dirroot . '/blocks/newgu_spdetails/tests/external/newgu_spdetails_advanced_testcase.php');

class get_assessments_due_soon_test extends \blocks_newgu_spdetails\external\newgu_spdetails_advanced_testcase {

    /**
     * Test that the returned assessments due date falls into
     * one of the following categories:
     * 24 hours
     * 7 days
     * 1 month
     */
    // Public function test_get_assessments_due_soon.
}
