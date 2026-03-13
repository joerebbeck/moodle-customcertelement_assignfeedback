<?php
// This file is part of the customcert module for Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings for customcertelement_assignfeedback.
 *
 * @package customcertelement_assignfeedback
 * @copyright 2026 Joe Rebbeck <tjr@the-ela.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Core element strings.
$string['pluginname']        = 'Assignment feedback';
$string['assignment']        = 'Assignment';
$string['chooseassignment']  = 'Choose an assignment...';

// Feedback placeholder strings.
// Shown on the certificate when feedback cannot be rendered.

// Displayed when mod_assign is uninstalled, the assignment record no longer
// exists, or the user has never been graded on the assignment.
$string['feedbacknotavailable'] = 'Feedback not available';

// Displayed when the user has been graded but the grader has not yet written
// any feedback comment (commenttext is empty).
$string['nofeedbackprovided'] = 'No feedback provided';

// Privacy API strings.
$string['privacy:metadata:assign_grades']
    = 'Feedback data retrieved from the Moodle assignment activity to display on the certificate.';
$string['privacy:metadata:assign_grades:userid']
    = 'The ID of the user whose feedback is displayed on the certificate.';
$string['privacy:metadata:assign_grades:assignment']
    = 'The assignment this grade record belongs to.';
$string['privacy:metadata:assign_grades:grade']
    = 'The grade value associated with the feedback.';
$string['privacy:metadata:assignfeedback_comments']
    = 'The feedback comment text rendered on the certificate.';
$string['privacy:metadata:assignfeedback_comments:grade']
    = 'The grade record this comment is linked to.';
$string['privacy:metadata:assignfeedback_comments:commenttext']
    = 'The textual feedback provided by the grader.';
