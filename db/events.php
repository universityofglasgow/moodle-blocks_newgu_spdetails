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
 * An array of observers.
 *
 * The list of events we are wanting to observe as part of the
 * Assessments Overview and Assessments Due soon calls that are
 * made periodically.
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_assign\event\submission_created',
        'callback' => 'block_newgu_spdetails\observer::submission_created'
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => 'block_newgu_spdetails\observer::assessable_submitted'
    ],
    [
        'eventname' => '\mod_peerwork\event\assessable_submitted',
        'callback' => 'block_newgu_spdetails\observer::peerwork_assessable_submitted'
    ],
    [
        'eventname' => '\mod_assign\event\submission_removed',
        'callback' => 'block_newgu_spdetails\observer::submission_removed'
    ],
    [
        'eventname' => '\mod_assign\event\identities_revealed',
        'callback' => 'block_newgu_spdetails\observer::identities_revealed'
    ],
];