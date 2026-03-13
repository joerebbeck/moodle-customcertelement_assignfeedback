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
 * Privacy provider for the assignfeedback certificate element.
 *
 * Implements the Moodle Privacy API so that assignment feedback data
 * rendered on certificates is correctly included in "Export my data"
 * and "Delete my data" GDPR requests.
 *
 * @package customcertelement_assignfeedback
 * @copyright 2026 Joe Rebbeck <tjr@the-ela.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_assignfeedback\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider class.
 *
 * This plugin reads feedback data owned by mod_assign / assignfeedback_comments.
 * We declare those external data locations via get_metadata() and implement the
 * full request API so Moodle's privacy tooling can surface and act on the data
 * displayed by this element.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    // =========================================================================
    // Metadata declaration
    // =========================================================================

    /**
     * Describes the personal data this plugin accesses.
     *
     * We read from assign_grades and assignfeedback_comments, which are owned
     * by mod_assign. Declaring them here ensures they appear in the user's
     * data export when this element is active.
     *
     * @param collection $collection The metadata collection to populate.
     * @return collection The populated collection.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_external_location_link(
            'assign_grades',
            [
                'userid'     => 'privacy:metadata:assign_grades:userid',
                'assignment' => 'privacy:metadata:assign_grades:assignment',
                'grade'      => 'privacy:metadata:assign_grades:grade',
            ],
            'privacy:metadata:assign_grades'
        );

        $collection->add_external_location_link(
            'assignfeedback_comments',
            [
                'grade'       => 'privacy:metadata:assignfeedback_comments:grade',
                'commenttext' => 'privacy:metadata:assignfeedback_comments:commenttext',
            ],
            'privacy:metadata:assignfeedback_comments'
        );

        return $collection;
    }

    // =========================================================================
    // Context discovery
    // =========================================================================

    /**
     * Returns the contexts in which this plugin holds data for the given user.
     *
     * Finds every customcert module context in courses where the user has at
     * least one assign_grades record - i.e. every context where their feedback
     * could be rendered on a certificate.
     *
     * @param int $userid The target user's ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // 2.3 DB API: named placeholders, no string concatenation.
        $sql = "SELECT ctx.id
                  FROM {context}       ctx
                  JOIN {course_modules} cm   ON cm.id            = ctx.instanceid
                                           AND ctx.contextlevel  = :ctxlevel
                  JOIN {modules}        m    ON m.id              = cm.module
                                           AND m.name             = 'customcert'
                  JOIN {assign}         a    ON a.course           = cm.course
                  JOIN {assign_grades}  ag   ON ag.assignment      = a.id
                                           AND ag.userid           = :userid";

        $contextlist->add_from_sql($sql, [
            'ctxlevel' => CONTEXT_MODULE,
            'userid'   => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Returns the users who have data within a given context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        // 2.3 DB API: named placeholder :cmid, no concatenation.
        $sql = "SELECT ag.userid
                  FROM {assign_grades}  ag
                  JOIN {assign}         a   ON a.id     = ag.assignment
                  JOIN {course_modules} cm  ON cm.course = a.course
                                          AND cm.id      = :cmid
                  JOIN {modules}        m   ON m.id      = cm.module
                                          AND m.name     = 'customcert'";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    // =========================================================================
    // Data export
    // =========================================================================

    /**
     * Exports personal data for the user within the approved contexts.
     *
     * For each context, retrieves the user's grade and feedback comment for
     * every assignment in the course and writes them to the privacy export.
     *
     * @param approved_contextlist $contextlist The approved context list.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        // 2.2 User Isolation: userid comes from the approved contextlist,
        // not from any user-controlled input.
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('customcert', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // 2.3 DB API: named placeholders :userid and :courseid.
            $sql = "SELECT a.name  AS assignment_name,
                           ag.grade,
                           fc.commenttext
                      FROM {assign}                 a
                      JOIN {assign_grades}           ag ON ag.assignment = a.id
                                                     AND ag.userid      = :userid
                 LEFT JOIN {assignfeedback_comments} fc ON fc.grade      = ag.id
                     WHERE a.course = :courseid
                  ORDER BY a.name ASC";

            $records = $DB->get_records_sql($sql, [
                'userid'   => $userid,
                'courseid' => $cm->course,
            ]);

            $exportdata = [];
            foreach ($records as $record) {
                $exportdata[] = (object) [
                    'assignment' => $record->assignment_name,
                    'grade'      => $record->grade,
                    'feedback'   => strip_tags($record->commenttext ?? ''),
                ];
            }

            if (!empty($exportdata)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'customcertelement_assignfeedback')],
                    (object) ['feedback_entries' => $exportdata]
                );
            }
        }
    }

    // =========================================================================
    // Data deletion
    // =========================================================================

    /**
     * Deletes all personal data for all users within the given context.
     *
     * This plugin only reads data owned by mod_assign, so we do not delete
     * from assign_grades or assignfeedback_comments here - those are managed
     * by mod_assign's own privacy provider.
     *
     * @param \context $context The context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // Data is owned by mod_assign; deletion is handled by its provider.
    }

    /**
     * Deletes personal data for a specific user across the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // Data is owned by mod_assign; deletion is handled by its provider.
    }

    /**
     * Deletes personal data for a list of users within a given context.
     *
     * @param approved_userlist $userlist The approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        // Data is owned by mod_assign; deletion is handled by its provider.
    }
}
