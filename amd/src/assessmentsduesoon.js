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
 * Javascript to initialise the Assessments due soon section
 *
 * @module     block_newgu_spdetails/assessmentsduesoon
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2024 University of Glasgow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import * as Log from 'core/log';
import * as ajax from 'core/ajax';
import {Chart, BarController} from 'core/chartjs';

const Selectors = {
    DUESOON_BLOCK: '#assessmentsDueSoonContainer',
};

/**
 * @method fetchAssessmentsDueSoon
 */
const fetchAssessmentsDueSoon = () => {
    Chart.register(BarController);
    let tempPanel = document.querySelector(Selectors.DUESOON_BLOCK);

    tempPanel.insertAdjacentHTML("afterbegin","<div class='loader d-flex justify-content-center'>\n" +
        "<div class='spinner-border' role='status'><span class='hidden'>Loading...</span></div></div>");

    ajax.call([{
        methodname: 'block_newgu_spdetails_get_assessmentsduesoon',
        args: {},
    }])[0].done(function(response) {
        document.querySelector('.loader').remove();
        Log.debug('response is:' + response[0]['24hours']);
        tempPanel.insertAdjacentHTML("afterbegin", "<canvas id='assessmentsDueSoonChart'\n" +
            " aria-label='Assessments Due Soon chart data' role='graphics-object'>\n" +
            "<p>The &lt;canvas&gt; element appears to be unsupported in your browser.</p>\n" +
            "</canvas>");

        const data = [
            {
                labeltitle: `24 hours:`,
                value: response[0]['24hours']
            },
            {
                labeltitle: `7 days:`,
                value: response[0]['week']
            },
            {
                labeltitle: `month:`,
                value: response[0]['month']
            }
        ];


        new Chart(
            document.getElementById('assessmentsDueSoonChart'),
            {
                type: 'bar',
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                font: {
                                    size: 20
                                },
                                generateLabels: (chart) => {
                                    const datasets = chart.data.datasets;
                                    return datasets[0].data.map((data, i) => ({
                                        text: `${chart.data.labels[i]} ${data}`,
                                        fillStyle: datasets[0].backgroundColor[i],
                                        strokeStyle: datasets[0].backgroundColor[i],
                                        pointStyle: 'rectRounded',
                                        index: i
                                    }));
                                }
                            },
                        }
                    },
                },
                data: {
                    datasets: [{
                        indexAxis: 'y',
                        data: data.map(row => row.value),
                        backgroundColor: [
                            'rgba(255,0,0,0.6)',
                            'rgba(255,153,0,0.6)',
                            'rgba(129,187,255,0.6)'
                        ],
                        borderColor: [
                            'rgba(255,0,0)',
                            'rgba(255,153,0)',
                            'rgba(129,187,255)'
                        ],
                        borderWidth: 1,
                        hoverOffset: 4
                    }],
                    labels: data.map(row => row.labeltitle),
                }
            }
        );

    }).fail(function(err) {
        document.querySelector('.loader').remove();
        tempPanel.insertAdjacentHTML("afterbegin","<div class='d-flex justify-content-center'>\n" +
            err.message + "</div>");
        Log.debug(err);
    });
};

/**
 * @constructor
 */
export const init = () => {
    fetchAssessmentsDueSoon();
};