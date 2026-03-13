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
 * MUC (Moodle Universal Cache) definitions for customcertelement_assignfeedback.
 *
 * Defines the 'feedbackcache' request-scoped cache used by element::get_feedback_for_user()
 * to avoid redundant DB round-trips when the same feedback is rendered by multiple
 * certificate elements during a single certificate generation request.
 *
 * Cache behaviour:
 * - mode: request  — lives only for the lifetime of the current PHP process.
 *                    No stale data risk; no cross-user contamination possible.
 * - No TTL required — the request store is automatically purged at process end.
 * - On high-traffic sites, this prevents duplicate JOIN queries when the same
 *   assignment feedback element appears more than once on a certificate template.
 *
 * @package customcertelement_assignfeedback
 * @copyright 2026 Joe Rebbeck <tjr@the-ela.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Request-scoped cache for assignment feedback text.
    // Key format: "feedback_{assignid}_{userid}"
    'feedbackcache' => [
        'mode'                   => cache_store::MODE_REQUEST,
        'simplekeys'             => true,   // keys contain only alphanumeric + underscore
        'simpledata'             => true,   // values are plain strings
        'staticacceleration'     => true,   // keep a local PHP array copy for zero-overhead repeat reads
        'staticaccelerationsize' => 10,     // cap the in-memory array at 10 entries per request
    ],
];
