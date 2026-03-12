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
 * Assignment feedback element for mod_customcert.
 *
 * @package   customcertelement_assignfeedback
 * @copyright 2026 Joe Rebbeck <tjr@the-ela.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_assignfeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * Assignment feedback element class.
 */
class element extends \mod_customcert\element {

    /**
     * Renders the form elements for this element.
     *
     * @param \MoodleQuickForm $mform The form being rendered.
     */
    public function render_form_elements($mform) {
        global $COURSE;

        $assignments = $this->get_course_assignments($COURSE->id);
        $options = [0 => get_string('chooseassignment', 'customcertelement_assignfeedback')] + $assignments;

        $mform->addElement('select', 'assignid', get_string('assignment', 'customcertelement_assignfeedback'), $options);
        $mform->setType('assignid', PARAM_INT);

        parent::render_form_elements($mform);
    }

    /**
     * Validates form data for this element.
     *
     * @param array $data  The form data.
     * @param array $files The uploaded files.
     * @return array Validation errors.
     */
    public function validate_form_elements($data, $files) {
        $errors = parent::validate_form_elements($data, $files);

        if (empty($data['assignid'])) {
            $errors['assignid'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Saves the form data for this element.
     *
     * @param \stdClass $data The form data.
     */
    public function save_form_elements($data) {
        $this->set_data(json_encode(['assignid' => (int) $data->assignid]));
        parent::save_form_elements($data);
    }

    /**
     * Populates the form with the saved element data.
     *
     * @param \MoodleQuickForm $mform The form being rendered.
     */
    public function set_form_elements_data($mform) {
        $data = json_decode($this->get_data(), true);
        if (!empty($data['assignid'])) {
            $mform->setDefault('assignid', $data['assignid']);
        }
        parent::set_form_elements_data($mform);
    }

    /**
     * Returns a sample string for the PDF preview.
     *
     * @return string
     */
    public function preview_text() {
        return get_string('pluginname', 'customcertelement_assignfeedback');
    }

    /**
     * Renders this element on the PDF certificate.
     *
     * @param \pdf         $pdf  The PDF object.
     * @param bool         $preview Whether this is a preview render.
     * @param \stdClass    $user The user the certificate is being generated for.
     * @param \stdClass    $record The customcert issue record.
     */
    public function render($pdf, $preview, $user, $record) {
        if ($preview) {
            \mod_customcert\element_helper::render_content($pdf, $this, $this->preview_text());
            return;
        }

        $elementdata = json_decode($this->get_data(), true);
        $assignid = (int) ($elementdata['assignid'] ?? 0);
        $feedback = $this->get_feedback_for_user($assignid, $user->id);

        \mod_customcert\element_helper::render_content($pdf, $this, $feedback);
    }

    /**
     * Retrieves the grader's text feedback for a user on a given assignment.
     *
     * Looks up the grade record first, then fetches the associated
     * assignfeedback_comments row. Returns a localised fallback string
     * if no grade or feedback comment is found.
     *
     * @param int $assignid The assignment ID.
     * @param int $userid   The user ID.
     * @return string The plain-text feedback, or a localised unavailable string.
     */
    protected function get_feedback_for_user(int $assignid, int $userid): string {
        global $DB;

        if (empty($assignid)) {
            return '';
        }

        // Verify the assignment exists.
        if (!$DB->record_exists('assign', ['id' => $assignid])) {
            return '';
        }

        // Look up the grade record.
        $grade = $DB->get_record('assign_grades', [
            'assignment' => $assignid,
            'userid'     => $userid,
        ]);

        if (!$grade) {
            return get_string('feedbacknotavailable', 'customcertelement_assignfeedback');
        }

        // Look up the feedback comment for this grade.
        $comment = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);

        if (!$comment || empty($comment->commenttext)) {
            return get_string('feedbacknotavailable', 'customcertelement_assignfeedback');
        }

        return strip_tags($comment->commenttext);
    }

    /**
     * Returns an array of assignment names keyed by assignment ID for a given course.
     *
     * @param int $courseid The course ID.
     * @return array Associative array of assignment ID => assignment name.
     */
    protected function get_course_assignments(int $courseid): array {
        global $DB;

        $records = $DB->get_records('assign', ['course' => $courseid], 'name ASC', 'id, name');
        $result = [];
        foreach ($records as $record) {
            $result[$record->id] = $record->name;
        }

        return $result;
    }
}
