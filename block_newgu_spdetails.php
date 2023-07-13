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
 * Block visits reports base.
 *
 * @package   block_newgu_spdetails
 * @copyright
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('No Direct Access');

class block_newgu_spdetails extends block_base {

    /**
     * Initialize block instance.
     *
     * @throws coding_exception
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_newgu_spdetails');
    }

    /**
     * This block supports configuration fields.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * List of links to access the reports displayed on the blocks.
     *
     * @return object $content
     */
    public function get_content() {
        global $PAGE, $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $context = context_system::instance();

        $this->content = new \stdClass();

        $viewurl = new moodle_url('/blocks/newgu_spdetails/view.php');
        if (!is_siteadmin()) {
          $cntstaff = block_newgu_spdetails_external::checkrole($USER->id, 0);
          if ($cntstaff>0) {
            $staffurl = new moodle_url('/blocks/newgu_spdetails/sduserdetails.php');
            $this->content->text = $OUTPUT->render_from_template('block_newgu_spdetails/block', [
                    'link' => $viewurl,
                    'stafflink' => $staffurl
                    ]);
          } else {
            $this->content->text = $OUTPUT->render_from_template('block_newgu_spdetails/block', [
                    'link' => $viewurl
                    ]);
          }
        }






$this->page->requires->js_amd_inline("require(['core/first', 'jquery', 'jqueryui', 'core/ajax'], function(core, $, bootstrap, ajax) {

// -----------------------------
$(document).ready(function() {
  // get current value then call ajax to get new data

  ajax.call([{
    methodname: 'block_newgu_spdetails_get_statistics',
    args: {
    },
  }])[0].done(function(response) {
console.log(response[0].stathtml);
    $('#spdetails').html(response[0].stathtml);
    return;
  }).fail(function(err) {
    console.log(err);
    //notification.exception(new Error('Failed to load data'));
    return;
  });

  });
  });
");

        return $this->content;


                                    }

    /**
     * Dashes are suitable on all page types.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my' => true);
    }
}
