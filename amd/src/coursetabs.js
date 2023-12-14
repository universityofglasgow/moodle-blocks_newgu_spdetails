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
 * @module     block_newgu_spdetails/coursetabs
 * @author     Greg Pedder <greg.pedder@glasgow.ac.uk>
 * @copyright  2023 University of Glasgow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import * as Log from 'core/log';
import * as Ajax from "../../../../lib/amd/src/ajax";
import {exception as displayException} from 'core/notification';
import Templates from 'core/templates';

const initCourseTabs = () => {

    let activetab = 'current';
    let page = 0;
    let sortby = 'coursetitle';
    let sortorder = 'asc';
    let isPageClicked = false;

    // Load the assessments for the "current" tab to begin with...
    loadAssessments(activetab, page, sortby, sortorder, isPageClicked);

    const triggerTabList = document.querySelectorAll('#courses-Tab button');

    // Bind our event listeners.
    triggerTabList.forEach(triggerEl => {
        triggerEl.addEventListener('click', handleTabChange);
        triggerEl.addEventListener('keyup', function(event) {
            let element = document.activeElement;
            if (event.keyCode === 13 && element.hasAttribute('tabindex')) {
                event.preventDefault();
                element.click();
            }
        });
    });
};

const loadAssessments = function(activetab, page, sortby, sortorder, isPageClicked, subcategory = null, parent = null) {
    let containerBlock = document.querySelector('#course_contents_container');

    let whichTemplate = subcategory === null ? 'coursecategory' : 'coursesubcategory';

    if (containerBlock.children.length > 0) {
        containerBlock.innerHTML = '';
    }

    containerBlock.insertAdjacentHTML("afterbegin","<div class='loader d-flex justify-content-center'>\n" +
    "<div class='spinner-border' role='status'><span class='hidden'>Loading...</span></div></div>");

    let promise = Ajax.call([{
        methodname: 'block_newgu_spdetails_get_assessments',
        args: {
            activetab: activetab,
            page: page,
            sortby: sortby,
            sortorder: sortorder,
            subcategory: subcategory,
        }
    }]);
    promise[0].done(function(response) {
        document.querySelector('.loader').remove();
        let coursedata = JSON.parse(response.result);
        Log.debug('coursedata:' + response.result);
        Templates.renderForPromise('block_newgu_spdetails/' + whichTemplate, {coursedata:coursedata})
        .then(({html, js}) => {
            Templates.appendNodeContents(containerBlock, html, js);
            if (isPageClicked == true) {
                containerBlock.scrollIntoView({ behavior: "smooth"});
            }
            let subCategories = document.querySelectorAll('.subcategory-row');
            let sortColumns = document.querySelectorAll('.th-sortable');
            subCategoryEventHandler(subCategories);
            subCategoryReturnHandler(parent);
            sortingEventHandler(sortColumns);
            sortingStatus(sortby, sortorder);
        }).catch((error) => displayException(error));
    }).fail(function(response) {
        if(response) {
            var errorContainer = document.createElement('div');
            errorContainer.classList.add('alert', 'alert-danger');

            if(response.hasOwnProperty('message')) {
                var errorMsg = document.createElement('p');

                errorMsg.innerHTML = response.message;
                errorContainer.appendChild(errorMsg);
                errorMsg.classList.add('errormessage');
            }

            if(response.hasOwnProperty('moreinfourl')) {
                var errorLinkContainer = document.createElement('p');
                var errorLink = document.createElement('a');

                errorLink.setAttribute('href', response.moreinfourl);
                errorLink.setAttribute('target', '_blank');
                errorLink.innerHTML = 'More information about this error';
                errorContainer.appendChild(errorLinkContainer);
                errorLinkContainer.appendChild(errorLink);
                errorLinkContainer.classList.add('errorcode');
            }

            containerBlock.prepend(errorContainer);
        }
    });
};

const subCategoryEventHandler = (rows) => {
    if (rows.length > 0) {
        rows.forEach((element) => {
            element.addEventListener('click', () => showSubcategoryDetails(element));
        });
    }
};

const showSubcategoryDetails = (object) => {
    let id = object.parentElement.getAttribute('data-id');
    let parent = object.parentElement.getAttribute('data-parent');
    if (id !== null) {
        document.querySelector('#courseNav-container').classList.add('hidden-container');
        loadAssessments('current', 0, 'duedate', 'asc', true, id, parent);
    }
};

const subCategoryReturnHandler = (id) => {
    // We want this to work in 2 ways.
    // When descending levels, we want to know which level to return to, hence setting the data-parent.
    // When ascending, we don't want to get stuck at a sub level, hence, not worrying if we pass null
    // - this gets picked up as 'not a sub category' and will therefore display everything from the top
    // level, i.e. all courses.
    Log.debug('subCategoryReturnHandler called with id:' + id);
    if (id !== null) {
        document.querySelector('#subcategory-return-assessment').setAttribute('data-parent', id);
    }

    // The 'return to...' element won't exist on the page at the top most level.
    if (document.querySelector('#subcategory-return-assessment')) {
        document.querySelector('#subcategory-return-assessment').addEventListener('click', () => {
            // We now want to reload the previous level, using the previous id...
            // In order to display all courses, we pass null back to loadAssessments.
            Log.debug('calling loadAssessments with id:' + id);
            if (id == 0 || id === null) {
                id = null;
                document.querySelector('#courseNav-container').classList.remove('hidden-container');
            }
            loadAssessments('current', 0, 'coursetitle', 'asc', true, id);
        });
    }
};

const sortingEventHandler = (rows) => {
    if (rows.length > 0) {
        rows.forEach((element) => {
            element.addEventListener('click', () => sortingHeaders(element));
        });
    }
};

const sortingHeaders = (object) => {
    let sortby = object.getAttribute('data-sortby');
    let sortorder = object.getAttribute('data-value');
    if (sortorder === null) {
        sortorder = 'asc';
    }

    if (sortorder !== null) {
        // reverse the sort order in order for it to function correctly
        if (sortorder == 'asc') {
            sortorder = 'desc';
        } else {
            sortorder = 'asc';
        }
    }

    loadAssessments('current', 0, sortby, sortorder, true);
};

const sortingStatus = function(sortby, sortorder) {
    Log.debug('sortingStatus called with sortby:' + sortby + ' sortorder:' + sortorder);
    let sortByCourse = document.querySelector('#sortby_course');
    let sortByCategoryorComponent = document.querySelector('#sortby_categoryorcomponent');
    let sortByAssessmentType = document.querySelector('#sortby_assessmenttype');
    let sortByWeight = document.querySelector('#sortby_weight');
    let sortByDueDate = document.querySelector('#sortby_duedate');
    //let sortByStatus = document.querySelector('#sortby_status');
    //let sortByGrade = document.querySelector('#sortby_grade');

    switch(sortby) {
        case 'coursetitle':
            if(sortByCourse) {
                if (sortorder == 'asc') {
                    sortByCourse.classList.add('th-sort-asc');
                    sortByCourse.classList.remove('th-sort-desc');
                    sortByCourse.setAttribute('data-value', 'asc');
                } else {
                    sortByCourse.classList.add('th-sort-desc');
                    sortByCourse.classList.remove('th-sort-asc');
                    sortByCourse.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'categoryorcomponent':
            if(sortByCategoryorComponent) {
                if (sortorder == 'asc') {
                    sortByCategoryorComponent.classList.add('th-sort-asc');
                    sortByCategoryorComponent.classList.remove('th-sort-desc');
                    sortByCategoryorComponent.setAttribute('data-value', 'asc');
                } else {
                    sortByCategoryorComponent.classList.add('th-sort-desc');
                    sortByCategoryorComponent.classList.remove('th-sort-asc');
                    sortByCategoryorComponent.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'assessmenttype':
            if(sortByAssessmentType) {
                if (sortorder == 'asc') {
                    sortByAssessmentType.classList.add('th-sort-asc');
                    sortByAssessmentType.classList.remove('th-sort-desc');
                    sortByAssessmentType.setAttribute('data-value', 'asc');
                } else {
                    sortByAssessmentType.classList.add('th-sort-desc');
                    sortByAssessmentType.classList.remove('th-sort-asc');
                    sortByAssessmentType.setAttribute('data-value', 'desc');
                }
            }
        break;
        case 'weight':
            if(sortByWeight) {
                if (sortorder == 'asc') {
                    sortByWeight.classList.add('th-sort-asc');
                    sortByWeight.classList.remove('th-sort-desc');
                    sortByWeight.setAttribute('data-value', 'asc');
                } else {
                    sortByWeight.classList.add('th-sort-desc');
                    sortByWeight.classList.remove('th-sort-asc');
                    sortByWeight.setAttribute('data-value', 'desc');
                }
            }
        break;
        case 'duedate':
            if(sortByDueDate) {
                if(sortorder == 'asc') {
                    sortByDueDate.classList.add('th-sort-asc');
                    sortByDueDate.classList.remove('th-sort-desc');
                    sortByDueDate.setAttribute('data-value', 'asc');
                }else{
                    sortByDueDate.classList.add('th-sort-desc');
                    sortByDueDate.classList.remove('th-sort-asc');
                    sortByDueDate.setAttribute('data-value', 'desc');
                }
            }
            break;
        default:
            break;
    }
};

/**
 * Function to bind events to tabs.
 *
 * @param {object} event
 */
const handleTabChange = function(event) {
    event.preventDefault();

    let currentTab = document.querySelector('#current_tab');
    let pastTab = document.querySelector('#past_tab');
    let isPageClicked = false;

    switch(event.target) {
        case currentTab:
            var activetab = 'current';
            var page = 0;
            var sortby = 'coursetitle';
            var sortorder = 'asc';

            currentTab.classList.add('active');
            pastTab.classList.remove('active');

            loadAssessments(activetab, page, sortby, sortorder, isPageClicked);
            break;
        case pastTab:
            var activetab = 'past';
            var page = 0;
            var sortby = 'coursetitle';
            var sortorder = 'ASC';

            currentTab.classList.remove('active');
            pastTab.classList.add('active');

            loadAssessments(activetab, page, sortby, sortorder, isPageClicked);
            break;
        default:
            break;
    }
};


/**
 * @constructor
 */
export const init = () => {
    initCourseTabs();
};