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
    let sortorder = 'ASC';
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

const loadAssessments = function(activetab, page, sortby, sortorder, isPageClicked, subcategory = null) {
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
        Log.debug('courses:' + response.result);
        Templates.renderForPromise('block_newgu_spdetails/' + whichTemplate, {coursedata:coursedata})
        .then(({html, js}) => {
            Templates.appendNodeContents(containerBlock, html, js);
            let subCategories = document.querySelectorAll('.subcategory-row');
            subCategoryEventHandler(subCategories);
        }).catch((error) => displayException(error));
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
    let id = object.getAttribute('data-id');
    //let subname = object.getAttribute('data-name');
    //let course = object.getAttribute('data-course');
    //let grade = object.getAttribute('data-grade');
    //let weight = object.getAttribute('data-weight');
    Log.debug('showSubcategoryDetails called with id:' + id);

    if (id !== null) {
        loadAssessments('current', 0, 'duedate', 'ASC', false, id);
        //document.querySelector('#subcategory-details-course').innerHTML = course;
        //document.querySelector('#subcategory-details-weight').innerHTML = weight;
        //document.querySelector('#subcategory-details-grade').innerHTML = grade;
        //document.querySelector('#subcategory-details-name').innerHTML = subname;
        //document.querySelector('#subcategory-return-assessment').addEventListener('click', () => {
        //    document.querySelector('#courses-container').classList.remove('hidden-container');
        //    document.querySelector('#subcategory-container').classList.add('hidden-container');
        //    document.querySelector('#subcategory_details_contents').innerHTML = "";
        //});
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