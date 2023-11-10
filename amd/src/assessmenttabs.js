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
 * Javascript to initialise the Course Tabs section
 *
 * @module     block_newgu_spdetails/assessmenttabs
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import * as Log from 'core/log';
import * as bootstrap from 'core/bootstrap';
import * as ajax from "../../../../lib/amd/src/ajax";

const Selectors = {
    DASHBOARD_BLOCK: '#assessments-Tab'
};

const initAssessmentTabs = () => {
    const triggerTabList = document.querySelectorAll(`#${Selectors.DASHBOARD_BLOCK} button`);

    // Bind our event listeners.
    triggerTabList.forEach(triggerEl => {
        triggerEl.addEventListener('click', handleTabChange);
        triggerEl.addEventListener("keyup", function(event) {
            let element = document.activeElement;
            if (event.keyCode === 13 && element.hasAttribute('tabindex')) {
                event.preventDefault();
                element.click();
            }
        });
    });

    let activetab = 'current';
    let page = 0;
    let sortby = 'coursetitle';
    let sortorder = 'asc';
    let isPageClicked = false;

    // Load the assessments for the "current" tab to begin with...
    loadAssessments(activetab, page, sortby, sortorder, isPageClicked);
};

const loadAssessments = function(activetab, page, sortby, sortorder, isPageClicked, subcategory = null) {
    let assessmentContainer = document.querySelector('#assessments-container');
    let subcategoryContainer = document.querySelector('#subcategory-container');
    let tabContent = subcategory === null ? document.querySelector('.nav-link.active')
        : document.querySelector('#subcategory_details_contents');

    if (subcategory === null) {
        assessmentContainer.classList.remove('hidden-container');
        subcategoryContainer.classList.add('hidden-container');
    } else {
        assessmentContainer.classList.add('hidden-container');
        subcategoryContainer.classList.remove('hidden-container');
    }

    let promise = ajax.call([{
        methodname: 'block_newgu_spdetails_get_assessments',
        args: {
            activetab: activetab,
            page: page,
            sortby: sortby,
            sortorder: sortorder,
            subcategory: subcategory
        }
    }]);
    promise[0].done(function(response) {
        tabContent.innerHTML = response.result;
    });
};

/**
 * Function to bind events to tabs.
 *
 * @param {object} event
 */
function handleTabChange(event) {
    event.preventDefault();
    const tabTrigger = new bootstrap.Tab(event.target);
    Log.debug('found:' + tabTrigger);

    let activetab = event.target.dataset.activetab;
    let page = 0;
    let sortby = 'coursetitle';
    let sortorder = 'asc';
    let isPageClicked = false;

    loadAssessments(activetab, page, sortby, sortorder, isPageClicked);
}


/**
 * @constructor
 */
export const init = () => {
    initAssessmentTabs();
};