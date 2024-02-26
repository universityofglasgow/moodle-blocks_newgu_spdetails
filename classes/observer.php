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
 * Class to handle assessment events.
 *
 * The cache needs to be cleared when certain assessment events occcur.
 * This is needed by the charts on the dashboard to pull in the correct
 * assessment summaries.
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_newgu_spdetails;

defined('MOODLE_INTERNAL') || die();

class observer {

    /** @var string Our key in the cache. */
    const CACHE_KEY = 'studentid_summary:';

    /**
     * Handle the submission added event.
     *
     * @param \mod_assign\event\submission_created $event
     * @return bool
     */
    public static function submission_created(\mod_assign\event\submission_created $event): bool {

        // Invalidate the cache if an assignment submission is made.
        if ((!empty($event->userid)) && $event->userid != 1) {
            return self::delete_key_from_cache($event->userid);
        }

        return false;
    }

    /**
     * Handle the assessable submitted event.
     *
     * @param \mod_assign\event\assessable_submitted $event
     * @return bool
     */
    public static function assessable_submitted(\mod_assign\event\assessable_submitted $event): bool {

        // Invalidate the cache if an assessable submission is made.
        if (!empty($event->userid)) {
            return self::delete_key_from_cache($event->userid);
        }

        return false;
    }

    /**
     * Handle the peerwork assessable submitted event.
     *
     * @param \mod_peerwork\event\assessable_submitted $event
     * @return bool
     */
    public static function peerwork_assessable_submitted(\mod_peerwork\event\assessable_submitted $event): bool {

        // Invalidate the cache if a peerwork assessable submission is made.
        if (!empty($event->userid)) {
            return self::delete_key_from_cache($event->userid);
        }

        return false;
    }

    /**
     * Handle the submission removed event.
     *
     * @param \mod_assign\event\submission_removed $event
     * @return bool
     */
    public static function submission_removed(\mod_assign\event\submission_removed $event): bool {

        // Invalidate the cache if an assignment removal is made.
        if (!empty($event->userid)) {
            return self::delete_key_from_cache($event->userid);
        }

        return false;
    }

    /**
     * Handle the identities revealed event.
     * I'm taking this to mean where grades have been released to students.
     *
     * @param \mod_assign\event\identities_revealed $event
     * @return bool
     */
    public static function identities_revealed(\mod_assign\event\identities_revealed $event): bool {

        // Invalidate the cache if an assignment submission is made.
        if (!empty($event->userid)) {
            return self::delete_key_from_cache($event->userid);
        }

        return false;
    }

    /**
     * Utility method to save violating DRY rules.
     * @param int $userid
     * @return bool
     */
    public static function delete_key_from_cache($userid): bool {

        $cache = \cache::make('block_newgu_spdetails', 'studentdashboarddata');
        $cachekey = self::CACHE_KEY . $userid;
        $cachedata = $cache->get_many([$cachekey]);

        if ($cachedata[$cachekey] != false) {
            $cache->delete($cachekey);

            return true;
        }

        return false;
    }
}
