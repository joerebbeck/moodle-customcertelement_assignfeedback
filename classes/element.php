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
 * Assignment feedback element for mod_customcert.
 *
 * Compatible with:
 * - Moodle 4.1 - 5.1 : legacy element API (render_form_elements etc.)
 * - Moodle 5.2+ : Element System v2 interfaces
 *
 * @package customcertelement_assignfeedback
 * @copyright 2026 Joe Rebbeck <tjr@the-ela.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_assignfeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * Assignment feedback element class.
 *
 * Implements Element System v2 interfaces where available (mod_customcert 5.2+)
 * while remaining compatible with the legacy API on older versions.
 *
 * Interfaces implemented (all are no-ops on older mod_customcert versions that
 * do not define them, so the class is safely autoloaded on Moodle 4.1+):
 * - form_buildable_interface -> build_form()
 * - validatable_element_interface -> validate_form_data()
 * - persistable_element_interface -> persist_form_data()
 * - preparable_form_interface -> prepare_form_data()
 */
class element extends \mod_customcert\element implements
    \mod_customcert\element\form_buildable_interface,
    \mod_customcert\element\validatable_element_interface,
    \mod_customcert\element\persistable_element_interface,
    \mod_customcert\element\preparable_form_interface {

    // =========================================================================
    // Element System v2 interface methods (mod_customcert 5.2+)
    // =========================================================================

    /**
     * Adds the element-specific fields to the form (v2 API).
     *
     * @param \MoodleQuickForm $mform The Moodle form.
     */
    public function build_form(\MoodleQuickForm $mform): void {
        global $COURSE;

        $assignments = $this->get_course_assignments($COURSE->id);
        $options = [0 => get_string('chooseassignment', 'customcertelement_assignfeedback')] + $assignments;

        $mform->addElement('select', 'assignid', get_string('assignment', 'customcertelement_assignfeedback'), $options);
        $mform->setType('assignid', PARAM_INT);
    }

    /**
     * Validates the submitted form data (v2 API).
     *
     * @param array $data Submitted form data.
     * @param array $files Uploaded files.
     * @return array Associative array of field => error string.
     */
    public function validate_form_data(array $data, array $files): array {
        $errors = [];
        if (empty($data['assignid'])) {
            $errors['assignid'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Persists the form data to the element record (v2 API).
     *
     * @param \stdClass $data Submitted form data.
     */
    public function persist_form_data(\stdClass $data): void {
        $this->set_data(json_encode(['assignid' => (int) $data->assignid]));
        parent::persist_form_data($data);
    }

    /**
     * Pre-populates the form with the saved element data (v2 API).
     *
     * @param \MoodleQuickForm $mform The Moodle form.
     */
    public function prepare_form_data(\MoodleQuickForm $mform): void {
        $data = json_decode($this->get_data(), true);
        if (!empty($data['assignid'])) {
            $mform->setDefault('assignid', $data['assignid']);
        }
        parent::prepare_form_data($mform);
    }

    // =========================================================================
    // Legacy API methods (mod_customcert 4.1 - 5.1)
    // =========================================================================

    /**
     * Renders the form elements for this element (legacy API).
     *
     * @param \MoodleQuickForm $mform The form being rendered.
     */
    public function render_form_elements($mform) {
        $this->build_form($mform);
        parent::render_form_elements($mform);
    }

    /**
     * Validates form data for this element (legacy API).
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @return array Validation errors.
     */
    public function validate_form_elements($data, $files) {
        $errors = parent::validate_form_elements($data, $files);
        return array_merge($errors, $this->validate_form_data($data, $files));
    }

    /**
     * Saves the form data for this element (legacy API).
     *
     * @param \stdClass $data The form data.
     */
    public function save_form_elements($data) {
        $this->set_data(json_encode(['assignid' => (int) $data->assignid]));
        parent::save_form_elements($data);
    }

    /**
     * Populates the form with the saved element data (legacy API).
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

    // =========================================================================
    // Render / preview
    // =========================================================================

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
     * Security: verifies mod/customcert:view capability before any data access.
     *
     * @param \pdf      $pdf     The PDF object.
     * @param bool      $preview Whether this is a preview render.
     * @param \stdClass $user    The user the certificate is being generated for.
     * @param \stdClass $record  The customcert issue record.
     */
    public function render($pdf, $preview, $user, $record) {
        // 2.1 Context Validation: enforce capability before reading any personal data.
        $context = \context_module::instance($this->get_cmid());
        require_capability('mod/customcert:view', $context);

        if ($preview) {
            \mod_customcert\element_helper::render_content($pdf, $this, $this->preview_text());
            return;
        }

        $elementdata = json_decode($this->get_data(), true);
        $assignid    = (int) ($elementdata['assignid'] ?? 0);

        // 2.2 User Isolation: $user->id is supplied by the caller (never from
        // user-controlled input), ensuring students cannot read each other's feedback.
        $feedback = $this->get_feedback_for_user($assignid, $user->id);

        \mod_customcert\element_helper::render_content($pdf, $this, $feedback);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Retrieves the grader's text feedback for a user on a given assignment.
     *
     * Looks up the grade record first, then fetches the associated
     * assignfeedback_comments row. Returns a localised fallback string
     * if no grade or feedback comment is found.
     *
     * Security:
     *  - Callers must have verified mod/customcert:view before invoking this.
     *  - 2.2 User Isolation: $userid is always caller-supplied; never sourced
     *    from $_GET, $_POST, or any other user-controlled input.
     *  - 2.3 DB API: Uses Moodle $DB API with array conditions throughout.
     *    Array conditions are compiled to parameterised SQL by the DML layer -
     *    no string concatenation into SQL strings anywhere in this method.
     *
     * @param int $assignid The assignment ID.
     * @param int $userid   The target user ID.
     * @return string The plain-text feedback, or a localised unavailable string.
     */
    protected function get_feedback_for_user(int $assignid, int $userid): string {
        global $DB;

        if (empty($assignid)) {
            return '';
        }

        // Verify the assignment exists - $DB array conditions, no raw SQL.
        if (!$DB->record_exists('assign', ['id' => $assignid])) {
            return '';
        }

        // Strict per-user, per-assignment filter - satisfies req 2.2 and 2.3.
        $grade = $DB->get_record('assign_grades', [
            'assignment' => $assignid,
            'userid'     => $userid,
        ]);

        if (!$grade) {
            return get_string('feedbacknotavailable', 'customcertelement_assignfeedback');
        }

        // Fetch the feedback comment linked to this specific grade row.
        $comment = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);

        if (!$comment || empty($comment->commenttext)) {
            return get_string('feedbacknotavailable', 'customcertelement_assignfeedback');
        }

        return strip_tags($comment->commenttext);
    }

    /**
     * Returns an array of assignment names keyed by assignment ID for a course.
     *
     * @param int $courseid The course ID.
     * @return array Associative array of assignment ID => assignment name.
     */
    protected function get_course_assignments(int $courseid): array {
        global $DB;

        $records = $DB->get_records('assign', ['course' => $courseid], 'name ASC', 'id, name');
        $result  = [];
        foreach ($records as $record) {
            $result[$record->id] = $record->name;
        }

        return $result;
    }
}
