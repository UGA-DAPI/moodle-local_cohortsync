<?php
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
 * @package   local_ldap
 * @copyright 2016 Lafayette College ITS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortsync\task;

require_once($CFG->dirroot.'/local/cohortsync/locallib.php');

class cohort_sync_task extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('gcohortsynctask', 'local_cohortsync');
    }

    public function execute() {
        $trace = new \text_progress_trace();
        if ($plugin = new \local_cohortsync()) {
            $plugin->sync_cohorts($trace);
        }
    }
}
