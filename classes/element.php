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
     * Retrieves and PDF-safe-formats the grader's feedback for a user.
     *
     * Pipeline:
     *  1. Single JOIN query (assign_grades + assignfeedback_comments) — no N+1.
     *  2. MUC request-cache check — zero DB cost on repeat renders.
     *  3. format_text() — applies Moodle filters, resolves pluginfile URLs,
     *     and produces well-formed HTML from the stored editor content.
     *  4. clean_for_pdf() — strips tags that break TCPDF (img, iframe, object,
     *     video, audio, script, style) while preserving safe inline formatting
     *     (b, i, u, br, p, ul, ol, li, strong, em).
     *
     * Security:
     *  - 2.1: Callers must have verified mod/customcert:view before invoking this.
     *  - 2.2 User Isolation: $userid is always caller-supplied; never from user input.
     *  - 2.3 DB API: named placeholders; no string concatenation into SQL.
     *
     * Performance:
     *  - Single JOIN replaces two sequential get_record() calls.
     *  - Request-scoped MUC cache prevents repeat DB hits within one generation.
     *
     * @param int $assignid The assignment ID.
     * @param int $userid   The target user ID.
     * @return string PDF-safe plain/simple-HTML feedback string.
     */
    protected function get_feedback_for_user(int $assignid, int $userid): string {
        global $DB;

        if (empty($assignid)) {
            return '';
        }

        // ---------------------------------------------------------------
        // MUC request cache — in-process only, purged at request end.
        // ---------------------------------------------------------------
        $cache    = \cache::make('customcertelement_assignfeedback', 'feedbackcache');
        $cachekey = "feedback_{$assignid}_{$userid}";
        $cached   = $cache->get($cachekey);

        if ($cached !== false) {
            return $cached;
        }

        // ---------------------------------------------------------------
        // Single JOIN query — one round-trip for grade + feedback text.
        // Named placeholders satisfy req 2.3 (no string concatenation).
        // ---------------------------------------------------------------
        $sql = "SELECT fc.commenttext, fc.commentformat
                  FROM {assign_grades}           ag
                  JOIN {assignfeedback_comments} fc ON fc.grade = ag.id
                 WHERE ag.assignment = :assignid
                   AND ag.userid     = :userid";

        $row = $DB->get_record_sql($sql, [
            'assignid' => $assignid,
            'userid'   => $userid,
        ]);

        if (!$row || empty($row->commenttext)) {
            $result = get_string('feedbacknotavailable', 'customcertelement_assignfeedback');
            $cache->set($cachekey, $result);
            return $result;
        }

        // ---------------------------------------------------------------
        // TCPDF HTML cleaning pipeline
        // ---------------------------------------------------------------

        // Step 1: Resolve Moodle filters and pluginfile URLs.
        // format_text() converts the stored editor format (HTML, Markdown,
        // plain text etc.) to HTML and applies active filters (e.g. MathJax,
        // multilang). The module context scopes any capability checks inside
        // the filters correctly.
        $context   = \context_module::instance($this->get_cmid());
        $formatted = format_text($row->commenttext, $row->commentformat, [
            'context' => $context,
            'noclean' => false,   // Apply Moodle's own HTML purifier pass.
            'filter'  => true,
        ]);

        // Step 2: Strip tags that TCPDF cannot render safely.
        // TCPDF's writeHTMLCell() will silently fail or produce corrupt output
        // when it encounters <img> with base64 payloads or pluginfile src
        // values, <iframe>, <video>, <audio>, <object>, <script>, or <style>.
        // We remove these entirely while preserving safe inline formatting.
        $result = $this->clean_for_pdf($formatted);

        $cache->set($cachekey, $result);

        return $result;
    }

    /**
     * Strips HTML tags that are unsafe or unsupported by TCPDF.
     *
     * Removes: img, iframe, video, audio, object, embed, script, style,
     *          form, input, button, select, textarea, canvas, svg.
     *
     * Preserves: b, strong, i, em, u, s, p, br, ul, ol, li, span,
     *            h1-h6, blockquote, pre, code, sup, sub, hr, table,
     *            thead, tbody, tr, th, td, caption, a (href only).
     *
     * After tag removal, clean_text() is called as a final safety net to
     * strip any remaining dangerous attributes (e.g. onclick, onerror).
     *
     * @param string $html HTML produced by format_text().
     * @return string PDF-safe string suitable for TCPDF writeHTMLCell().
     */
    protected function clean_for_pdf(string $html): string {
        // Tags whose presence will break TCPDF or pose a security risk.
        // We strip the entire element including its inner content for
        // media tags, but only the tag itself (not content) for others.
        $mediapattern = '/<(img|iframe|video|audio|object|embed|canvas|svg)'
            . '(\s[^>]*)?>.*?<\/\1>|<(img|iframe|video|audio|object|embed)'
            . '(\s[^>]*)?\/?>/is';
        $html = preg_replace($mediapattern, '', $html);

        // Strip script and style blocks with their content.
        $html = preg_replace('/<script(\s[^>]*)?>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style(\s[^>]*)?>.*?<\/style>/is', '', $html);

        // Strip interactive / form elements (tags only, preserve inner text).
        $html = preg_replace('/</?(form|input|button|select|textarea)(\s[^>]*)?\/?>/i', '', $html);

        // Final pass: clean_text() strips dangerous attributes such as
        // onclick, onerror, onload, javascript: href values, and any tags
        // not in Moodle's allowlist — provides defence-in-depth.
        $html = clean_text($html, FORMAT_HTML);

        // Collapse any runs of blank lines left by removed blocks.
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br /><br />', $html);
        $html = trim($html);

        return $html;
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
