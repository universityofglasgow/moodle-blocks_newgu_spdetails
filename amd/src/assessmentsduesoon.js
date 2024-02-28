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
 * JavaScript to initialise the Assessments due soon section.
 * We're using Chart.js v3.8.0 at present.
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
import {exception as displayException} from 'core/notification';
import Templates from 'core/templates';

const Selectors = {
    DUESOON_BLOCK: '#assessmentsDueSoonContainer',
    COURSECONTENTS_BLOCK: '#courseTab-container',
    ASSESSMENTSDUE_BLOCK: '#assessmentsDue-container',
    ASSESSMENTSDUE_CONTENTS: '#assessmentsdue_content'
};

const viewAssessmentsDueByChartType = function(chartItem, legendItem) {
    const chartType = ((legendItem) ? legendItem.datasetIndex : chartItem);
    Log.debug('chartType:' + chartType);

    let containerBlock = document.querySelector(Selectors.COURSECONTENTS_BLOCK);
    if (containerBlock.checkVisibility()) {
        containerBlock.classList.add('hidden-container');
    }

    let assessmentsDueBlock = document.querySelector(Selectors.ASSESSMENTSDUE_BLOCK);
    let assessmentsDueContents = document.querySelector(Selectors.ASSESSMENTSDUE_CONTENTS);
    assessmentsDueBlock.classList.remove('hidden-container');

    if (assessmentsDueBlock.children.length > 0) {
        assessmentsDueContents.innerHTML = '';
    }

    assessmentsDueContents.insertAdjacentHTML("afterbegin","<div class='loader d-flex justify-content-center'>\n" +
        "<div class='spinner-border' role='status'><span class='hidden'>Loading...</span></div></div>");

    ajax.call([{
        methodname: 'block_newgu_spdetails_get_assessmentsduebytype',
        args: {
            charttype: chartType
        },
    }])[0].done(function(response) {
        document.querySelector('.loader').remove();
        let assessmentdata = JSON.parse(response.result);
        Log.debug('data:' + assessmentdata.assessmentitems);
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

const returnToAssessmentsHandler = () => {
    Log.debug('returnToAssessmentsHandler called');
    if (document.querySelector('#assessments-due-return')) {
        document.querySelector('#assessments-due-return').addEventListener('click', () => {
            Log.debug('click event triggered');
            let containerBlock = document.querySelector(Selectors.COURSECONTENTS_BLOCK);
            let assessmentsDueBlock = document.querySelector(Selectors.ASSESSMENTSDUE_BLOCK);
            assessmentsDueBlock.classList.add('hidden-container');
            containerBlock.classList.remove('hidden-container');
        });
    }
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

        const chart = new Chart(
            document.getElementById('assessmentsDueSoonChart'),
            {
                type: 'bar',
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            suggestedMin: 1,
                            suggestedMax: 10
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            onClick: viewAssessmentsDueByChartType,
                            labels: {
                                usePointStyle: true,
                                font: {
                                    size: 20
                                },
                                generateLabels: (chart) => {
                                    const datasets = chart.data.datasets;
                                    return datasets[0].data.map((data, i) => ({
                                        text: `${chart.data.labels[i]} ${data}`,
                                        borderRadius: 0,
                                        datasetIndex: i,
                                        fillStyle: datasets[0].backgroundColor[i],
                                        fontColor: '',
                                        hidden: false,
                                        lineCap: '',
                                        lineDash: [],
                                        lineDashOffset: 0,
                                        lineJoin: '',
                                        lineWidth: 0,
                                        strokeStyle: datasets[0].backgroundColor[i],
                                        pointStyle: 'rectRounded',
                                        rotation: 0,
                                        index: i
                                    }));
                                }
                            },
                        }
                    },
                },
                data: {
                    labels: data.map(row => row.labeltitle),
                    datasets: [{
                        data: data.map(row => row.value),
                        indexAxis: 'y',
                        backgroundColor: [
                            'rgba(255,0,0,0.6)',
                            'rgba(255,153,0,0.6)',
                            'rgba(0,153,0,0.6)'
                        ],
                        borderColor: [
                            'rgba(255,0,0)',
                            'rgba(255,153,0)',
                            'rgba(0,153,0)'
                        ],
                        borderWidth: 1,
                        hoverOffset: 4
                    }]
                }
            }
        );

        const canvas = document.getElementById('assessmentsDueSoonChart');
        canvas.onclick = (evt) => {
            const points = chart.getElementsAtEventForMode(
                evt,
                'nearest',
                { intersect: true },
                true
              );
              if (points.length === 0) {
                return;
              }
              viewAssessmentsDueByChartType(points[0].index);
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
    fetchAssessmentsDueSoon();
};