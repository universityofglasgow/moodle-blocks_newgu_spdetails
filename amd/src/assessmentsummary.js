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
 * Javascript to initialise the Assessment Summary section
 *
 * @module     block_newgu_spdetails/summary
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import * as Log from 'core/log';
import * as ajax from 'core/ajax';
import {Chart, DoughnutController} from 'core/chartjs';

const Selectors = {
    SUMMARY_BLOCK: '#assessmentSummaryContainer',
};

/**
 * @method fetchAssessmentSummary
 */
const fetchAssessmentSummary = () => {
    Chart.register(DoughnutController);
    let tempPanel = document.querySelector(Selectors.SUMMARY_BLOCK);

    tempPanel.insertAdjacentHTML("afterbegin","<div class='loader d-flex justify-content-center'>\n" +
        "<div class='spinner-border' role='status'><span class='hidden'>Loading...</span></div></div>");

    ajax.call([{
        methodname: 'block_newgu_spdetails_get_assessmentsummary',
        args: {},
    }])[0].done(function(response) {
        document.querySelector('.loader').remove();
        Log.debug('response is:' + response[0]['sub_assess']);
        tempPanel.insertAdjacentHTML("afterbegin", "<canvas id='assessmentSummaryChart'\n" +
            " aria-label='Assessment Summary chart data' role='graphics-object'>\n" +
            "<p>The &lt;canvas&gt; element appears to be unsupported in your browser.</p>\n" +
            "</canvas>");

        const data = [
            {
                labeltitle: `Submitted`,
                value: response[0]['sub_assess']
            },
            {
                labeltitle: `To be submitted`,
                value: response[0]['tobe_sub']
            },
            {
                labeltitle: `Overdue`,
                value: response[0]['overdue']
            },
            {
                labeltitle: `Marked`,
                value: response[0]['assess_marked']
            },
        ];


        new Chart(
            document.getElementById('assessmentSummaryChart'),
            {
                type: 'doughnut',
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'right',
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
                    radius: '100%',
                    maintainAspectRatio: false
                },
                data: {
                    datasets: [{
                        data: data.map(row => row.value),
                        backgroundColor: [
                            '#058',
                            '#CC5500',
                            '#FF0000FF',
                            '#008000FF'
                        ],
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
    fetchAssessmentSummary();
};