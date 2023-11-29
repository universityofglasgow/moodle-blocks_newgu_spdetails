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
 * This script renders the Student Dashboard view effectively.
 *
 * @package    block_newgu_spdetails
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

require_login();
$PAGE->set_context(context_system::instance());
$url = new moodle_url('/blocks/newgu_spdetails/index.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('title', 'block_newgu_spdetails'));
$PAGE->set_heading(get_string('pluginname', 'block_newgu_spdetails'));
$PAGE->set_pagelayout('report');

$PAGE->navbar->add(get_string('blocktitle', 'block_newgu_spdetails'), new moodle_url('/blocks/newgu_spdetails/index.php'));

$templatecontext = (array)[
    'tab_current'       => get_string('tab_current', 'block_newgu_spdetails'),
    'tab_past'          => get_string('tab_past', 'block_newgu_spdetails'),
    'showdetails' => true,
];

$content = $OUTPUT->render_from_template('block_newgu_spdetails/coursetabs', $templatecontext);

$PAGE->requires->js_call_amd('block_newgu_spdetails/main', 'init');

echo $OUTPUT->header();
echo $content;
echo $OUTPUT->footer();
