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
 * Class cohort_sync_task
 * @package   local_cohortsync
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortsync\task;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/local/cohortsync/locallib.php');

class update_users extends \core\task\scheduled_task {
    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('update_users', 'local_cohortsync');
    }

    /**
     * Run task for synchronising users.
     */
    public function execute() {
        $trace = new \text_progress_trace();
        if ($plugin = new \local_cohortsync()) {
            $plugin->update_users($trace);
        }
    }
}
