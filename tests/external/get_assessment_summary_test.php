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
 * Test of the language string settings.
 * 
 * @package    blocks_newgu_spdetails
 * @copyright  2024
 * @author     Greg Pedder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/newgu_spdetails/tests/external/newgu_spdetails_advanced_testcase.php');

class get_assessment_summary_test extends \blocks_newgu_spdetails\external\newgu_spdetails_advanced_testcase {
    public function test_get_assessment_summary() {
        global $DB;

        // We're the test student.
        $this->setUser($this->student1->id);

        // Check that our stats values are returned as expected
        $stats = get_assessmentsummary::execute();
        $stats = \external_api::clean_returnvalue(
            get_assessmentsummary::execute_returns(),
            $stats
        );
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('sub_assess', $stats[0]);
        $this->assertArrayHasKey('tobe_sub', $stats[0]);
        $this->assertArrayHasKey('overdue', $stats[0]);
        $this->assertArrayHasKey('assess_marked', $stats[0]);
    }
}