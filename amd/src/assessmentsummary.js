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
 * @module     block_newgu_spdetails/assessmentsummary
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import * as Log from 'core/log';
import * as ajax from 'core/ajax';
import {Chart, DoughnutController} from 'core/chartjs';
import {exception as displayException} from 'core/notification';
import Templates from 'core/templates';

const Selectors = {
    SUMMARY_BLOCK: '#assessmentSummaryContainer',
    COURSECONTENTS_BLOCK: '#courseTab-container',
    ASSESSMENTSDUE_BLOCK: '#assessmentsDue-container',
    ASSESSMENTSDUE_CONTENTS: '#assessmentsdue_content'
};

const viewAssessmentSummaryByChartType = function(event, legendItem, legend) {
    const chartType = ((legendItem) ? legendItem.index : legend);

    let containerBlock = document.querySelector(Selectors.COURSECONTENTS_BLOCK);
    if (containerBlock) {
        if (containerBlock.checkVisibility()) {
            containerBlock.classList.add('hidden-container');
        }
    }

    let assessmentsDueBlock = document.querySelector(Selectors.ASSESSMENTSDUE_BLOCK);
    let assessmentsDueContents = document.querySelector(Selectors.ASSESSMENTSDUE_CONTENTS);

    if (assessmentsDueBlock.children.length > 0) {
        assessmentsDueContents.innerHTML = '';
    }

    assessmentsDueBlock.classList.remove('hidden-container');

    assessmentsDueContents.insertAdjacentHTML("afterbegin","<div class='loader d-flex justify-content-center'>\n" +
        "<div class='spinner-border' role='status'><span class='hidden'>Loading...</span></div></div>");

    ajax.call([{
        methodname: 'block_newgu_spdetails_get_assessmentsummarybytype',
        args: {
            charttype: chartType
        },
    }])[0].done(function(response) {
        document.querySelector('.loader').remove();
        let assessmentdata = JSON.parse(response.result);
        Templates.renderForPromise('block_newgu_spdetails/assessmentsdue', {data:assessmentdata})
        .then(({html, js}) => {
            Templates.appendNodeContents(assessmentsDueContents, html, js);
            returnToAssessmentsHandler();
        }).catch((error) => displayException(error));
    }).fail(function(response) {
        if(response) {
            document.querySelector('.loader').remove();
            let errorContainer = document.createElement('div');
            errorContainer.classList.add('alert', 'alert-danger');

            if(response.hasOwnProperty('message')) {
                let errorMsg = document.createElement('p');

                errorMsg.innerHTML = response.message;
                errorContainer.appendChild(errorMsg);
                errorMsg.classList.add('errormessage');
            }

            if(response.hasOwnProperty('moreinfourl')) {
                let errorLinkContainer = document.createElement('p');
                let errorLink = document.createElement('a');

                errorLink.setAttribute('href', response.moreinfourl);
                errorLink.setAttribute('target', '_blank');
                errorLink.innerHTML = 'More information about this error';
                errorContainer.appendChild(errorLinkContainer);
                errorLinkContainer.appendChild(errorLink);
                errorLinkContainer.classList.add('errorcode');
            }

            assessmentsDueContents.prepend(errorContainer);
        }
    });
};

/**
 * @method returnToAssessmentsHandler
 */
const returnToAssessmentsHandler = () => {
    if (document.querySelector('#assessments-due-return')) {
        document.querySelector('#assessments-due-return').addEventListener('click', () => {
            let containerBlock = document.querySelector(Selectors.COURSECONTENTS_BLOCK);
            let assessmentsDueBlock = document.querySelector(Selectors.ASSESSMENTSDUE_BLOCK);
            assessmentsDueBlock.classList.add('hidden-container');
            containerBlock.classList.remove('hidden-container');
        });

        document.querySelector('#assessments-due-return').addEventListener('keyup', function(event) {
            let element = document.activeElement;
            if (event.keyCode === 13 && element.hasAttribute('tabindex')) {
                event.preventDefault();
                element.click();
            }
        });
    }
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
        tempPanel.insertAdjacentHTML("afterbegin", "<canvas id='assessmentSummaryChart'\n" +
            " width='400' height='300' aria-label='Assessment Summary chart data' role='graphics-object'>\n" +
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


        const chart = new Chart(
            document.getElementById('assessmentSummaryChart'),
            {
                type: 'doughnut',
                options: {
                    responsive: true,
                    onHover: (event, chartElement) => {
                        event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'right',
                            onClick: (event, legendItem, legend) => {
                                viewAssessmentSummaryByChartType(event, legendItem, legend);
                            },
                            onHover: (event) => {
                                event.native.target.style.cursor = 'pointer';
                            },
                            onLeave: (event) => {
                                event.native.target.style.cursor = 'default';
                            },
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
                            'rgba(0,153,0)',
                            'rgba(255,153,0)',
                            'rgba(255,0,0)',
                            'rgba(129,187,255)'
                        ],
                        hoverOffset: 4
                    }],
                    labels: data.map(row => row.labeltitle),
                }
            }
        );

        const canvas = document.getElementById('assessmentSummaryChart');
        canvas.onclick = (evt) => {
            const points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);

            if (points.length) {
                const firstPoint = points[0];
                viewAssessmentSummaryByChartType(evt,null,firstPoint.index);
            }
          };

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