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
    /**
     * Tests that clean_for_pdf() strips dangerous tags that break TCPDF while
     * preserving safe inline formatting tags that TCPDF's writeHTMLCell() supports.
     *
     * Contract:
     *  - Tags that MUST be stripped entirely (with content): img, iframe, video,
     *    audio, object, embed, script, style.
     *  - Tags that MUST be preserved: p, strong, em, b, i, u, br, ul, ol, li.
     *  - Plain text content MUST be present in the output.
     *
     * Background: element_helper::render_content() passes the string to TCPDF's
     * writeHTMLCell(), which fully supports safe inline HTML. Stripping all tags
     * would degrade formatted feedback (bold, paragraphs, lists) unnecessarily.
     */
    public function test_clean_for_pdf_strips_dangerous_keeps_safe_tags(): void {
        $element = $this->get_test_element();

        // --- Dangerous tags must be removed entirely (including their content) ---

        $imghtml = '<p>See diagram: <img src="data:image/png;base64,abc123" alt="chart" /></p>';
        $result  = $this->call_clean_for_pdf($element, $imghtml);
        $this->assertStringNotContainsString('<img',    $result, 'img tag must be stripped');
        $this->assertStringNotContainsString('base64',  $result, 'base64 payload must be stripped');
        $this->assertStringContainsString(   'See diagram:', $result, 'surrounding text must survive');

        $iframehtml = '<p>Watch: <iframe src="https://evil.example/x"></iframe></p>';
        $result     = $this->call_clean_for_pdf($element, $iframehtml);
        $this->assertStringNotContainsString('<iframe', $result, 'iframe tag must be stripped');

        $scripthtml = '<p>Hello</p><script>alert(1)</script>';
        $result     = $this->call_clean_for_pdf($element, $scripthtml);
        $this->assertStringNotContainsString('<script', $result, 'script tag must be stripped');
        $this->assertStringNotContainsString('alert',   $result, 'script content must be stripped');

        $stylehtml = '<style>.x{color:red}</style><p>Styled</p>';
        $result    = $this->call_clean_for_pdf($element, $stylehtml);
        $this->assertStringNotContainsString('<style',   $result, 'style tag must be stripped');
        $this->assertStringNotContainsString('color:red', $result, 'style content must be stripped');

        // --- Safe formatting tags must be preserved for TCPDF rendering ---

        $safehtml = '<p>Great <strong>work</strong>!</p>';
        $result   = $this->call_clean_for_pdf($element, $safehtml);
        $this->assertStringContainsString('Great',      $result, 'plain text must survive');
        $this->assertStringContainsString('work',       $result, 'plain text must survive');
        // p and strong are in TCPDF\'s supported tag set — they must not be stripped.
        $this->assertStringContainsString('<strong>',   $result, 'strong tag must be preserved for TCPDF');
        $this->assertStringContainsString('<p>',        $result, 'p tag must be preserved for TCPDF');

        $listhtml = '<ul><li>Point one</li><li>Point two</li></ul>';
        $result   = $this->call_clean_for_pdf($element, $listhtml);
        $this->assertStringContainsString('<ul>',       $result, 'ul tag must be preserved for TCPDF');
        $this->assertStringContainsString('<li>',       $result, 'li tag must be preserved for TCPDF');
        $this->assertStringContainsString('Point one',  $result, 'list text must survive');
    }

    /**
     * Helper: invoke the protected clean_for_pdf() method via reflection.
     *
     * @param \customcertelement_assignfeedback\element $element
     * @param string $html
     * @return string
     */
    private function call_clean_for_pdf(
        \customcertelement_assignfeedback\element $element,
        string $html
    ): string {
        $ref    = new \ReflectionMethod($element, 'clean_for_pdf');
        $ref->setAccessible(true);
        return $ref->invoke($element, $html);
    }
}
