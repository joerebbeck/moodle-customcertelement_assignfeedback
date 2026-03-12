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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * PHPUnit tests for the assignfeedback customcert element.
 *
 * @package   customcertelement_assignfeedback
 * @copyright 2026 Joe Rebbeck <tjr@the-ela.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_assignfeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the assignfeedback element.
 *
 * @covers \customcertelement_assignfeedback\element
 */
class element_test extends \advanced_testcase {

    /**
     * Test that feedback is returned for a graded student.
     */
    public function test_feedback_returned_for_graded_student(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $assign  = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Insert a grade record.
        $gradeid = $DB->insert_record('assign_grades', [
            'assignment' => $assign->id,
            'userid'     => $student->id,
            'timecreated'  => time(),
            'timemodified' => time(),
            'grader'     => 2,
            'grade'      => 80.0,
            'attemptnumber' => 0,
        ]);

        // Insert a feedback comment.
        $DB->insert_record('assignfeedback_comments', [
            'assignment'  => $assign->id,
            'grade'       => $gradeid,
            'commenttext' => 'Well done on your submission.',
            'commentformat' => FORMAT_HTML,
        ]);

        $element = new element(new \stdClass());
        $method  = new \ReflectionMethod($element, 'get_feedback_for_user');
        $method->setAccessible(true);

        $result = $method->invoke($element, $assign->id, $student->id);
        $this->assertEquals('Well done on your submission.', $result);
    }

    /**
     * Test that the fallback string is returned when no feedback comment exists.
     */
    public function test_no_feedback_comments_returns_unavailable_string(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $assign  = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Grade exists but no feedback comment row.
        $DB->insert_record('assign_grades', [
            'assignment' => $assign->id,
            'userid'     => $student->id,
            'timecreated'  => time(),
            'timemodified' => time(),
            'grader'     => 2,
            'grade'      => 70.0,
            'attemptnumber' => 0,
        ]);

        $element = new element(new \stdClass());
        $method  = new \ReflectionMethod($element, 'get_feedback_for_user');
        $method->setAccessible(true);

        $result = $method->invoke($element, $assign->id, $student->id);
        $this->assertEquals(get_string('feedbacknotavailable', 'customcertelement_assignfeedback'), $result);
    }

    /**
     * Test that the fallback string is returned when the student has not been graded.
     */
    public function test_no_grade_record_returns_unavailable_string(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $assign  = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $element = new element(new \stdClass());
        $method  = new \ReflectionMethod($element, 'get_feedback_for_user');
        $method->setAccessible(true);

        $result = $method->invoke($element, $assign->id, $student->id);
        $this->assertEquals(get_string('feedbacknotavailable', 'customcertelement_assignfeedback'), $result);
    }

    /**
     * Test that an empty string is returned when the assignment does not exist.
     */
    public function test_deleted_assignment_returns_empty_string(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();

        $element = new element(new \stdClass());
        $method  = new \ReflectionMethod($element, 'get_feedback_for_user');
        $method->setAccessible(true);

        $result = $method->invoke($element, 99999, $student->id);
        $this->assertSame('', $result);
    }

    /**
     * Test that an empty string is returned when assignid is zero (unconfigured element).
     */
    public function test_zero_assignid_returns_empty_string(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();

        $element = new element(new \stdClass());
        $method  = new \ReflectionMethod($element, 'get_feedback_for_user');
        $method->setAccessible(true);

        $result = $method->invoke($element, 0, $student->id);
        $this->assertSame('', $result);
    }

    /**
     * Test that HTML tags are stripped from feedback comments.
     */
    public function test_html_is_stripped_from_feedback(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $assign  = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $gradeid = $DB->insert_record('assign_grades', [
            'assignment' => $assign->id,
            'userid'     => $student->id,
            'timecreated'  => time(),
            'timemodified' => time(),
            'grader'     => 2,
            'grade'      => 90.0,
            'attemptnumber' => 0,
        ]);

        $DB->insert_record('assignfeedback_comments', [
            'assignment'  => $assign->id,
            'grade'       => $gradeid,
            'commenttext' => '<p>Great <strong>work</strong>!</p>',
            'commentformat' => FORMAT_HTML,
        ]);

        $element = new element(new \stdClass());
        $method  = new \ReflectionMethod($element, 'get_feedback_for_user');
        $method->setAccessible(true);

        $result = $method->invoke($element, $assign->id, $student->id);
        $this->assertEquals('Great work!', $result);
    }
}
