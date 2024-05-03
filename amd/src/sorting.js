import * as Log from 'core/log';

/**
 * Function to sort a table by column name and which direction.
 *
 * @param {int} n
 * @param {string} sortName
 * @param {string} tableName
 */
function sortTable(n, sortName, tableName) {
    let table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
    table = document.getElementById(tableName);
    switching = true;
    // Sort dates using the rawdate as we can't sort correctly using text.
    let tmp = sortName.includes('duedate');
    let compareFunction = compareString;
    Log.debug('sortTable called with n:' + n + '\nsortName:' + sortName + '\ncompareFunction:' + compareFunction);
    if (tmp) {
        compareFunction = compareNumber;
        Log.debug('compareFunction is now:' + compareFunction);
    }
    //Set the sorting direction to ascending:
    dir = "asc";
    /*Make a loop that will continue until
    no switching has been done:*/
    while (switching) {
        //start by saying: no switching is done:
        switching = false;
        rows = table.rows;
        /*Loop through all table rows (except the
        first, which contains table headers):*/
        for (i = 1; i < (rows.length - 1); i++) {
            //start by saying there should be no switching:
            shouldSwitch = false;
            /*Get the two elements you want to compare,
            one from current row and one from the next:*/
            x = rows[i].getElementsByTagName("TD")[n];
            y = rows[i + 1].getElementsByTagName("TD")[n];
            /*check if the two rows should switch place,
            based on the direction, asc or desc:*/
            if (dir == "asc") {
                if (compareFunction(x, y, 'asc') == true) {
                    //if so, mark as a switch and break the loop:
                    shouldSwitch= true;
                    break;
                }
                // if (x.innerText.toLowerCase() > y.innerText.toLowerCase()) {
                //     //if so, mark as a switch and break the loop:
                //     shouldSwitch= true;
                //     break;
                // }
            } else if (dir == "desc") {
                if (compareFunction(x, y, 'desc') == true) {
                    //if so, mark as a switch and break the loop:
                    shouldSwitch= true;
                    break;
                }
                // if (x.innerText.toLowerCase() < y.innerText.toLowerCase()) {
                //     //if so, mark as a switch and break the loop:
                //     shouldSwitch = true;
                //     break;
                // }
            }
        }
        if (shouldSwitch) {
            /*If a switch has been marked, make the switch
            and mark that a switch has been done:*/
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            //Each time a switch is done, increase this count by 1:
            switchcount ++;
        } else {
            /*If no switching has been done AND the direction is "asc",
            set the direction to "desc" and run the while loop again.*/
            if (switchcount == 0 && dir == "asc") {
                dir = "desc";
                switching = true;
            }
        }
    }
    sortingStatus(sortName, dir);
}

/**
 * Function to compare two strings.
 * The sort order is passed in also.
 *
 * @param {*} x
 * @param {*} y
 * @param {*} direction
 * @returns
 */
let compareString = function (x, y, direction) {
    if (direction == 'asc') {
        if (x.innerText.toLowerCase() > y.innerText.toLowerCase()) {
            return true;
        }
        return false;
    } else if (direction == 'desc') {
        if (x.innerText.toLowerCase() < y.innerText.toLowerCase()) {
            return true;
        }
        return false;
    }
};

/**
 * Function to compare two dates passed in as raw unix timestamps.
 * The sort order is passed in also.
 *
 * @param {*} x
 * @param {*} y
 * @param {*} direction
 * @returns
 */
let compareNumber = function (x, y, direction) {
    if (direction == 'asc') {
        if (x.getAttribute('data-rawduedate') > y.getAttribute('data-rawduedate')) {
            return true;
        }
        return false;
    } else if (direction == 'desc') {
        if (x.getAttribute('data-rawduedate') < y.getAttribute('data-rawduedate')) {
            return true;
        }
        return false;
    }
};

/**
 * Function to make UI changes to show which direction things are being sorted in.
 *
 * @param {string} sortby
 * @param {string} sortorder
 */
function sortingStatus(sortby, sortorder) {
    Log.debug('sort:' + sortby + ' order:' + sortorder);
    let sortByShortName = document.querySelector('#sortby_shortname');
    let sortByFullName = document.querySelector('#sortby_fullname');
    let sortByType = document.querySelector('#sortby_assessmenttype');
    let sortByWeight = document.querySelector('#sortby_weight');
    let sortByStartDate = document.querySelector('#sortby_startdate');
    let sortByEndDate = document.querySelector('#sortby_enddate');
    let sortByDueDate = document.querySelector('#sortby_duedate');
    let sortByStatus = document.querySelector('#sortby_status');
    let sortByGrade = document.querySelector('#sortby_grade');

    let sortByShortName2 = document.querySelector('#sortby_shortname2');
    let sortByFullName2 = document.querySelector('#sortby_fullname2');
    let sortByType2 = document.querySelector('#sortby_assessmenttype2');
    let sortByWeight2 = document.querySelector('#sortby_weight2');
    let sortByDueDate2 = document.querySelector('#sortby_duedate2');
    let sortByStatus2 = document.querySelector('#sortby_status2');
    let excludeElement = '';

    switch (sortby) {
        case 'shortname':
            if (sortByShortName) {
                excludeElement = sortByShortName;
                if (sortorder == 'asc') {
                    sortByShortName.classList.add('th-sort-asc');
                    sortByShortName.classList.remove('th-sort-desc');
                    sortByShortName.setAttribute('data-value', 'asc');
                } else {
                    sortByShortName.classList.add('th-sort-desc');
                    sortByShortName.classList.remove('th-sort-asc');
                    sortByShortName.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'fullname':
            if (sortByFullName) {
                excludeElement = sortByFullName;
                if (sortorder == 'asc') {
                    sortByFullName.classList.add('th-sort-asc');
                    sortByFullName.classList.remove('th-sort-desc');
                    sortByFullName.setAttribute('data-value', 'asc');
                } else {
                    sortByFullName.classList.add('th-sort-desc');
                    sortByFullName.classList.remove('th-sort-asc');
                    sortByFullName.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'assessmenttype':
            if (sortByType) {
                excludeElement = sortByType;
                if (sortorder == 'asc') {
                    sortByType.classList.add('th-sort-asc');
                    sortByType.classList.remove('th-sort-desc');
                    sortByType.setAttribute('data-value', 'asc');
                } else {
                    sortByType.classList.add('th-sort-desc');
                    sortByType.classList.remove('th-sort-asc');
                    sortByType.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'weight':
            if (sortByWeight) {
                excludeElement = sortByWeight;
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
            if (sortByDueDate) {
                excludeElement = sortByDueDate;
                if (sortorder == 'asc') {
                    sortByDueDate.classList.add('th-sort-asc');
                    sortByDueDate.classList.remove('th-sort-desc');
                    sortByDueDate.setAttribute('data-value', 'asc');
                } else {
                    sortByDueDate.classList.add('th-sort-desc');
                    sortByDueDate.classList.remove('th-sort-asc');
                    sortByDueDate.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'startdate':
            if (sortByStartDate) {
                excludeElement = sortByStartDate;
                if (sortorder == 'asc') {
                    sortByStartDate.classList.add('th-sort-asc');
                    sortByStartDate.classList.remove('th-sort-desc');
                    sortByStartDate.setAttribute('data-value', 'asc');
                } else {
                    sortByStartDate.classList.add('th-sort-desc');
                    sortByStartDate.classList.remove('th-sort-asc');
                    sortByStartDate.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'enddate':
        if (sortByEndDate) {
            excludeElement = sortByEndDate;
            if (sortorder == 'asc') {
                sortByEndDate.classList.add('th-sort-asc');
                sortByEndDate.classList.remove('th-sort-desc');
                sortByEndDate.setAttribute('data-value', 'asc');
            } else {
                sortByEndDate.classList.add('th-sort-desc');
                sortByEndDate.classList.remove('th-sort-asc');
                sortByEndDate.setAttribute('data-value', 'desc');
            }
        }
        break;
        case 'status':
            if (sortByStatus) {
                excludeElement = sortByStatus;
                if (sortorder == 'asc') {
                    sortByStatus.classList.add('th-sort-asc');
                    sortByStatus.classList.remove('th-sort-desc');
                    sortByStatus.setAttribute('data-value', 'asc');
                } else {
                    sortByStatus.classList.add('th-sort-desc');
                    sortByStatus.classList.remove('th-sort-asc');
                    sortByStatus.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'grade':
            if (sortByGrade) {
                excludeElement = sortByGrade;
                if (sortorder == 'asc') {
                    sortByGrade.classList.add('th-sort-asc');
                    sortByGrade.classList.remove('th-sort-desc');
                    sortByGrade.setAttribute('data-value', 'asc');
                } else {
                    sortByGrade.classList.add('th-sort-desc');
                    sortByGrade.classList.remove('th-sort-asc');
                    sortByGrade.setAttribute('data-value', 'desc');
                }
            }
            break;



        case 'shortname2':
            if (sortByShortName2) {
                excludeElement = sortByShortName2;
                if (sortorder == 'asc') {
                    sortByShortName2.classList.add('th-sort-asc');
                    sortByShortName2.classList.remove('th-sort-desc');
                    sortByShortName2.setAttribute('data-value', 'asc');
                } else {
                    sortByShortName2.classList.add('th-sort-desc');
                    sortByShortName2.classList.remove('th-sort-asc');
                    sortByShortName2.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'fullname2':
            if (sortByFullName2) {
                excludeElement = sortByFullName2;
                if (sortorder == 'asc') {
                    sortByFullName2.classList.add('th-sort-asc');
                    sortByFullName2.classList.remove('th-sort-desc');
                    sortByFullName2.setAttribute('data-value', 'asc');
                } else {
                    sortByFullName2.classList.add('th-sort-desc');
                    sortByFullName2.classList.remove('th-sort-asc');
                    sortByFullName2.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'assessmenttype2':
            if (sortByType2) {
                excludeElement = sortByType2;
                if (sortorder == 'asc') {
                    sortByType2.classList.add('th-sort-asc');
                    sortByType2.classList.remove('th-sort-desc');
                    sortByType2.setAttribute('data-value', 'asc');
                } else {
                    sortByType2.classList.add('th-sort-desc');
                    sortByType2.classList.remove('th-sort-asc');
                    sortByType2.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'weight2':
            if (sortByWeight2) {
                excludeElement = sortByWeight2;
                if (sortorder == 'asc') {
                    sortByWeight2.classList.add('th-sort-asc');
                    sortByWeight2.classList.remove('th-sort-desc');
                    sortByWeight2.setAttribute('data-value', 'asc');
                } else {
                    sortByWeight2.classList.add('th-sort-desc');
                    sortByWeight2.classList.remove('th-sort-asc');
                    sortByWeight2.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'duedate2':
            if (sortByDueDate2) {
                excludeElement = sortByDueDate2;
                if (sortorder == 'asc') {
                    sortByDueDate2.classList.add('th-sort-asc');
                    sortByDueDate2.classList.remove('th-sort-desc');
                    sortByDueDate2.setAttribute('data-value', 'asc');
                } else {
                    sortByDueDate2.classList.add('th-sort-desc');
                    sortByDueDate2.classList.remove('th-sort-asc');
                    sortByDueDate2.setAttribute('data-value', 'desc');
                }
            }
            break;
        case 'status2':
            if (sortByStatus2) {
                excludeElement = sortByStatus2;
                if (sortorder == 'asc') {
                    sortByStatus2.classList.add('th-sort-asc');
                    sortByStatus2.classList.remove('th-sort-desc');
                    sortByStatus2.setAttribute('data-value', 'asc');
                } else {
                    sortByStatus2.classList.add('th-sort-desc');
                    sortByStatus2.classList.remove('th-sort-asc');
                    sortByStatus2.setAttribute('data-value', 'desc');
                }
            }
        break;
        default:
            break;
    }

    if (excludeElement != '') {
        let elId = excludeElement.id;
        let els = document.querySelectorAll(".th-sortable:not(#" + elId + ")");
        els.forEach((el) => {
            let classes = el.className;
            let tmp = classes.match(new RegExp(/th-sort-.+/, 'g'));
            el.classList.remove(tmp);
            el.removeAttribute('data-value');
        });
    }
}

export default sortTable;