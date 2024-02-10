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
 * Concrete implementation for manual grades
 * @package    block_newgu_spdetails
 * @copyright  2024
 * @author     Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails\activities;

/**
 * Manual grade items
 */
class manual extends base {

    /**
     * Get item type
     * @return string
     */
    public function get_itemtype() {
        return  'manual';
    }

    /**
     * Return the Moodle URL to the item
     * @return string
     */
    public function get_assessmenturl(): string {
        return 'THIS NEEDS A URL';
    }

    /**
     * @param int $userid
     * @return object
     */
    public function get_status($userid): object {
        
        return 'THIS NEEDS A STATUS';

    }

    /**
     * Is this a Proxy or Adapter method/pattern??
     * Seeing as get_first_grade is specific to Assignments,
     * what is the better way to describe this.
     */
    public function get_grade(int $userid): object|bool {
        return 'THIS NEEDS FINISHED';
    }

    /**
     * @param object $gradestatusobj
     */
    public function get_feedback($gradestatusobj): object {
        return 'THIS NEEDS FEEDBACK';
    }

}
