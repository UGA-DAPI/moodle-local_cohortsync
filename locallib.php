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
 * LDAP enrolment plugin implementation.
 *
 * This plugin synchronises enrolment and roles with a LDAP server.
 *
 * @author     Iñaki Arenaza - based on code by Martin Dougiamas, Martin Langhoff and others
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class local_cohortsync_plugin {
    protected $errorlogtag = '[LOCAL COHORTSYNC] ';

    protected $userfields = ['username' => 'uid', 'idnumber' => 'uid',
                            'firstname' => 'givenName',
                            'lastname'  => 'sn', 'email' => 'mail', ];
    protected $cohortfields = ['name' => 'cn', 'idnumber' => 'cn', 'description' => 'description'];
    protected $usersyncfield;
    private $_cohorts_added    = 0;
    private $_cohorts_existing = 0;
    private $_cohorts_disabled = 0;
    private $_users_added      = 0;
    private $_users_removed    = 0;
    private $_users_existing   = 0;
    private $_user_attribute;
    // Avoid infinite loop with nested groups in 'funny' directories.
    private $_antirecursionarray;

    /**
     * cohorts that will get synchronize.
     *
     * @var array
     */
    private $_cohorts = [];

    /**
     * Constructor for the plugin. In addition to calling the parent
     * constructor, we define and 'fix' some settings depending on the
     * real settings the admin defined.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->libdir.'/ldaplib.php');

        if (is_enabled_auth('cas')) {
            $this->authtype = 'cas';
            $this->roleauth = 'auth_cas';
        } else {
            if (is_enabled_auth('ldap')) {
                $this->authtype = 'ldap';
                $this->roleauth = 'auth_ldap';
            } else {
                if (is_enabled_auth('multicas')) {
                    $this->authtype = 'multicas';
                    $this->roleauth = 'auth_multicas';
                } else {
                    if (is_enabled_auth('shibboleth')) {
                        $this->authtype        = 'shibboleth';
                        $this->roleauth        = 'auth_shibboleth';
                        $this->_user_attribute = get_config('auth_shibboleth', 'user_attribute');
                    } else {
                        return false;
                    }
                }
            }
        }

        // Do our own stuff to fix the config (it's easier to do it
        // here than using the admin settings infrastructure). We
        // don't call $this->set_config() for any of the 'fixups'
        // (except the objectclass, as it's critical) because the user
        // didn't specify any values and relied on the default values
        // defined for the user type she chose.
        $this->auth = get_auth_plugin($this->authtype);
        $this->load_config();

        // Make sure we get sane defaults for critical values.
        $this->config->ldapencoding = $this->get_config('ldapencoding', 'utf-8');
        $this->config->user_type    = $this->get_config('user_type', 'default');

        $ldapusertypes                = ldap_supported_usertypes();
        $this->config->user_type_name = $ldapusertypes[$this->config->user_type];
        unset($ldapusertypes);

        $default = ldap_getdefaults();
        // Remove the objectclass default, as the values specified there are for
        // users, and we are dealing with groups here.
        unset($default['objectclass']);

        // Use defaults if values not given. Dont use this->get_config()
        // here to be able to check for 0 and false values too.
        foreach ($default as $key => $value) {
            // Watch out - 0, false are correct values too, so we can't use $this->get_config().
            if (!isset($this->config->{$key}) or '' === $this->config->{$key}) {
                $this->config->{$key} = $value[$this->config->user_type];
            }
        }

        foreach ($this->userfields as $key => $field) {
            $this->userfields[$key] = $this->config->{'user_'.$key};
        }

        foreach ($this->cohortfields as $key => $field) {
            $this->cohortfields[$key] = $this->config->{'cohort_'.$key};
        }

        $objectclass = ['group_objectclass', 'user_objectclass'];

        foreach ($objectclass as $object) {
            // Normalise the objectclass used for groups.
            if (empty($this->config->{$object})) {
                // No objectclass set yet - set a default class.
                $this->config->{$object} = ldap_normalise_objectclass(null, '*');
                $this->set_config($object, $this->config->{$object});
            } else {
                $objectclass = ldap_normalise_objectclass($this->config->{$object});
                if ($objectclass !== $this->config->{$object}) {
                    // The objectclass was changed during normalisation.
                    // Save it in config, and update the local copy of config.
                    $this->set_config($object, $objectclass);
                    $this->config->{$object} = $objectclass;
                }
            }
        }

        if ($this->config->memberattribute_isdn) { // Member attribute of ldap group.
            $field = array_search('dn', $this->userfields, true);
        } else {
            $field = false;
        }
        $this->usersyncfield = $field ? $field : 'username';
    }

    /**
     * Forces synchronisation of user enrolments with LDAP server.
     * It creates courses if the plugin is configured to do so.
     *
     * @param object $user user record
     */
    public function sync_user_enrolments($user) {
        if (($this->config->login_sync) && (('cas' === $user->auth) || ('ldap' === $user->auth) || ('shibboleth' === $user->auth))) {
            // Do not try to print anything to the output because this method is called during interactive login.
            $trace = new error_log_progress_trace($this->errorlogtag);
            if (!$this->ldap_connect($trace)) {
                $trace->finished();

                return;
            }
            global $CFG, $DB;
            require_once("{$CFG->dirroot}/cohort/lib.php");
            $ldapconnection = $this->ldapconnection;
            if (!is_object($user) or !property_exists($user, 'id')) {
                throw new coding_exception('Invalid $user parameter in sync_user_enrolments()');
            }

            // We may need a lot of memory here.
            core_php_time_limit::raise();
            raise_memory_limit(MEMORY_HUGE);

            $field = array_search('dn', $this->userfields, true);
            $field = $field ? $field : 'username';
            if (($this->_user_attribute === 'eppn') && ('shibboleth' === $user->auth)) {
                $memberofgroups = $this->ldap_find_user(
                    strtok($user->{$field}, '@'),
                    $this->config->memberofattribute,
                    'uid'
                );
            } else {
                $memberofgroups = $this->ldap_find_user(
                    $user->{$field},
                    $this->config->memberofattribute,
                    $this->userfields[$field]
                );
            }

            $memberofgroups = array_change_key_case($memberofgroups, CASE_LOWER);
            $memberofgroups = $memberofgroups[$this->config->memberofattribute];

            if (count($memberofgroups)) {
                foreach ($memberofgroups as $memberof) {
                    $pos = strpos($memberof, '=');
                    if (false !== $pos) {
                        $memberof = explode('=', $memberof);
                        $memberof = $memberof[1];
                    }
                    $moodlecohort = $DB->get_record('cohort', [$this->config->cohort_syncing_field => $memberof]);
                    if (empty($moodlecohort)) {
                        if ($this->config->autocreate_cohorts) {
                            if (false !== ($cohortid = $this->create_cohort($memberof))) {
                                $moodlecohort = $DB->get_record('cohort', ['id' => $cohortid]);
                                $trace->output(get_string('cohort_created', 'local_cohortsync', $moodlecohort->name));
                                ++$this->_cohorts_added;
                            }
                        } else {
                            continue;
                        }
                    } else {
                        ++$this->_cohorts_existing;
                        $trace->output(get_string('cohort_existing', 'local_cohortsync', $moodlecohort->name));
                    }

                    if (empty($moodlecohort->id)) {
                        if ($this->config->debug_mode) {
                            $trace->output(get_string('err_create_cohort', 'local_cohortsync', $memberof));
                        }

                        continue;
                    }
                    if (!$moodlecohort) {
                        if ($this->config->debug_mode) {
                            $trace->output(get_string('err_create_cohort', 'local_cohortsync', $memberof));
                        }

                        continue;
                    }

                    try {
                        cohort_add_member($moodlecohort->id, $user->id);
                    } catch (Exception $e) {
                        if ($this->config->debug_mode) {
                            $trace->output("\t".get_string(
                                'err_user_exists_in_cohort',
                                                     'local_cohortsync',
                                                     ['cohort' => $moodlecohort->name, 'user' => $ldapuser['uid'][0]]
                            ));
                        }
                    }
                    $this->stamp_cohort($moodlecohort);
                }
            }
            $this->ldap_close();
            $trace->finished();
        }
    }

    /**
     * Forces synchronisation of all enrolments with LDAP server.
     * It creates courses if the plugin is configured to do so.
     *
     * inspired by sync_enrolments <- moodle/enrol/ldap
     *
     * @param progress_trace $trace
     * @param null|bool      $forceunsubscribe force remove member
     */
    public function sync_cohorts(progress_trace $trace, $forceunsubscribe = false) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/cohort/lib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $excludes = explode("\n", $this->config->cohorts_exclude);
        $where    = [];
        foreach ($excludes as $exclude) {
            $where[] = 'idnumber LIKE %'.$exclude.'%';
        }
        $where          = implode(' AND ', $where);
        $cohortsexclude = $DB->get_records_select('cohort', $where, null, 'name ASC, idnumber ASC');
        foreach ($cohortsexclude as $cohort) {
            $this->stamp_cohort($cohort, 'Stop', null, true);
            ++$this->_cohorts_disabled;
        }

        if ($this->config->autocreate_cohorts > 0) {
            $this->create_cohorts($trace);
        }
        $this->sync_cohorts_members($trace, $forceunsubscribe);

        $trace->output(get_string('synchronized_cohorts', 'local_cohortsync', $this->_cohorts_added + $this->_cohorts_disabled));
    }

    /*
    * Forces synchronisation of all enrolments with LDAP server.
    * It creates courses if the plugin is configured to do so.
    *
    * inspired by sync_enrolments <- moodle/enrol/ldap
    *
    * @param progress_trace $trace
    */
    public function create_cohorts(progress_trace $trace) {
        if (!empty($this->config->filter)) {
            $filter = '(&('.$this->config->filter;
        }
        $filter .= '(|';
        if ($this->config->autocreate_cohorts > 1) {
            $includes = explode("\n", $this->config->cohorts_include);
            foreach ($cohortsinclude as $cohort) {
                $filter .= '('.$this->config->{'cohort_'.$this->config->cohort_syncing_field}.'='.$cohort.')';
            }
        }
        if (!empty($this->config->filter)) {
            $filter = ')';
        }
        $flatresults = $this->ldap_get_grouplist($filter);
        if (count($flatresults)) {
            foreach ($flatresults as $ldapgroup) {
                $ldapgroup = array_change_key_case($ldapgroup, CASE_LOWER);
                if ($this->config->autocreate_cohorts === 2) {
                    $ldapmembers = get_ldapmembers_by_type($ldapgroup[$this->config->memberattribute], 'group');
                    foreach ($ldapmembers as $ldapmember) {
                        $this->create_cohort($ldapmember);
                    }
                } else {
                    if (count($ldapgroup[$this->config->memberattribute])) {
                        $this->create_cohort($ldapgroup[$this->config->{'cohort_'.$this->config->cohort_syncing_field}][0]);
                    }
                }
            }
        }
    }

    /**
     * Forces synchronisation of all enrolments with LDAP server.
     * It creates courses if the plugin is configured to do so.
     *
     * inspired by sync_enrolments <- moodle/enrol/ldap
     *
     * @param progress_trace $trace
     * @param null|bool      $forceunsubscribe force remove member
     */
    public function sync_cohorts_members(progress_trace $trace, $forceunsubscribe = false) {
        global $DB;

        // We may need a lot of memory here.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $trace->output(get_string('connectingldap', 'local_cohortsync'));
        $trace->output(get_string('synchronizing_cohorts', 'local_cohortsync'));

        $filter      = '|';
        $where       = ' idnumber <> "" ';
        $listcohorts = $DB->get_records_select('cohort', $where, null, 'name ASC, idnumber ASC');
        unset($listcohorts['count']); // Remove oddity.
        foreach ($listcohorts as $cohort) {
            if ($cohort->{$this->config->cohort_syncing_field}) {
                $filter .= '('.$this->config->{'cohort_'.$this->config->cohort_syncing_field}.
                        '='.$cohort->{$this->config->cohort_syncing_field}.')';
            }
        }
        $flatresults = $this->ldap_get_grouplist($filter);

        if (count($flatresults)) {
            foreach ($flatresults as $ldapgroup) {
                $ldapgroup     = array_change_key_case($ldapgroup, CASE_LOWER);
                $ldapgroupname = $ldapgroup[$this->config->{'cohort_'.$this->config->cohort_syncing_field}][0];
                if (empty($ldapgroupname)) {
                    if ($this->config->debug_mode) {
                        $trace->output(get_string(
                            'err_invalid_cohort_name',
                            'local_cohortsync',
                                     $this->config->{'cohort_'.$this->config->cohort_syncing_field}
                        ));
                    }

                    continue;
                }

                $ldapmembers = get_ldapmembers_by_type($ldapgroup[$this->config->memberattribute], 'user');

                $moodlecohort = $DB->get_record('cohort', [$this->config->cohort_syncing_field => $ldapgroupname]);

                // Enrol & unenrol.

                // Pull the ldap membership into a nice array.
                // This is an odd array -- mix of hash and array.
                $cohortmembers = $this->get_cohort_members($moodlecohort->id, $this->usersyncfield);
                if (($this->authtype === 'shibboleth') && ($this->_user_attribute === 'eppn')) {
                    $split = function ($str) {
                        return strtok($str, '@');
                    };

                    $cohortmembers = array_combine(
                        array_map($split, array_keys($cohortmembers)),
                            array_values($cohortmembers)
                    );
                }

                if (count($ldapmembers)) {
                    $addmembers    = array_diff_key($ldapmembers, $cohortmembers);
                    $removemembers = array_diff_key($cohortmembers, $ldapmembers);
                    $count         = 0;

                    if (count($addmembers)) {
                        unset($addmembers['count']);
                        // Deal with the case where the member attribute holds distinguished names.
                        // But only if the user attribute is not a distinguished name itself.
                        foreach ($addmembers as $ldapmember => $i) {
                            $moodleuser = $DB->get_record('user', [$this->usersyncfield => $ldapmember], 'id,username');
                            if (empty($moodleuser)) {
                                if ($this->config->autocreate_users) {
                                    $ldapuser = $this->ldap_find_user(
                                        $ldapmember,
                                        array_values($this->userfields),
                                        $this->userfields[$this->usersyncfield]
                                    );
                                    if (isset($ldapuser)) {
                                        if (false !== ($userid = $this->create_user($ldapuser))) {
                                            $moodleuser = $DB->get_record('user', ['id' => $userid]);
                                            ++$this->_users_added;
                                        }
                                        unset($ldapuser);
                                    }
                                } else {
                                    continue;
                                }
                            }

                            if (empty($moodleuser->id)) {
                                if ($this->config->debug_mode) {
                                    $trace->output("\t".get_string('err_create_user', 'local_cohortsync', $ldapmember));
                                }

                                continue;
                            }

                            try {
                                cohort_add_member($moodlecohort->id, $moodleuser->id);
                            } catch (Exception $e) {
                                if ($this->config->debug_mode) {
                                    $trace->output("\t".get_string(
                                        'err_user_exists_in_cohort',
                                        'local_cohortsync',
                                        ['cohort' => $moodlecohort->name, 'user' => $ldapuser['uid'][0]]
                                    ));
                                }
                            }
                            ++$count;
                            $this->stamp_cohort($moodlecohort, $ldapgroup[$this->config->cohort_name][0]);
                        }
                    }
                    $discount = 0;
                    if (count($removemembers)) {
                        foreach ($removemembers as $user => $userid) {
                            if ($this->config->unsubscribe_users || $forceunsubscribe) {
                                cohort_remove_member($moodlecohort->id, $userid);
                                ++$discount;
                                $this->stamp_cohort($moodlecohort, $ldapgroup[$this->config->cohort_name][0]);
                            }
                            ++$this->_users_removed;
                        }
                    }
                }

                $trace->output(get_string(
                    'user_synchronized',
                    'local_cohortsync',
                    ['count' => $count, 'discount' => $discount, 'cohort' => $moodlecohort->name]
                ));
            }
        }

        @$this->ldap_close();
        $trace->finished();
    }

    public function update_users(progress_trace $trace) {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/user/lib.php');
        $trace->output(get_string('connectingldap', 'auth_ldap'));
        $ldapconnection = $this->ldap_connect();

        // User Updates - time-consuming (optional).

        // Narrow down what fields we need to update.
        $attrmaps   = $this->auth->ldap_attributes();
        $updatekeys = array_keys($attrmaps);

        if (!empty($updatekeys)) { // Run updates only if relevant.
            $users = $DB->get_records_sql(
                'SELECT u.username, u.id,'.implode(',', $updatekeys).'
                                             FROM {user} u
                                            WHERE u.deleted = 0 AND u.auth = ? AND u.mnethostid = ?',
                                          [$this->authtype, $CFG->mnet_localhost_id]
            );
            if (!empty($users)) {
                $trace->output(get_string('userentriestoupdate', 'auth_ldap', count($users)));
                $sitecontext = context_system::instance();
                foreach ($users as $user) {
                    // Protect the userid from being overwritten.
                    $this->auth->sync_roles($user);
                    $userid  = $user->id;
                    $newinfo = $this->ldap_find_user($user->username, array_values($attrmaps), $this->auth->config->user_attribute);
                    if (false !== $newinfo) {
                        $newinfo        = array_change_key_case($newinfo, CASE_LOWER);
                        $updateuser     = new stdClass();
                        $updateuser->id = $userid;
                        $update         = false;
                        foreach ($attrmaps as $key => $values) {
                            if (isset($newinfo[$values])) {
                                if (is_array($newinfo[$values])) {
                                    $newval = coretext::convert($newinfo[$values][0], $this->config->ldapencoding, 'utf-8');
                                } else {
                                    $newval = coretext::convert($newinfo[$values], $this->config->ldapencoding, 'utf-8');
                                }
                                if ($user->{$key} !== $newval) {
                                    $updateuser->{$key} = $newval;
                                    $update             = true;
                                }
                            }
                        }
                        if ($update) {
                            user_update_user($updateuser);
                            $trace->output(get_string(
                                    'auth_dbupdatinguser',
                                    'auth_db',
                                        ['name' => $user->username, 'id' => $user->id]
                                ));
                        }
                    } else {
                        if (2 === $this->auth->config->auth_removeuser) { // AUTH_REMOVEUSER_FULLDELETE.
                            if (delete_user($user)) {
                                $trace->output(get_string(
                                    'auth_dbdeleteuser',
                                    'auth_db',
                                        ['name' => $user->username, 'id' => $user->id]
                                ));
                            } else {
                                $trace->output(get_string('auth_dbdeleteusererror', 'auth_db', $user->username));
                            }
                        } else {
                            if (1 === $this->auth->config->auth_removeuser) { // AUTH_REMOVEUSER_SUSPEND.
                                $updateuser       = new stdClass();
                                $updateuser->id   = $user->id;
                                $updateuser->auth = 'nologin';
                                user_update_user($updateuser);
                                $trace->output(get_string(
                                'auth_dbsuspenduser',
                                'auth_db',
                                    ['name' => $user->username, 'id' => $user->id]
                                ));
                            }
                        }
                    }
                }
                unset($users); // Free mem.
            }
        } else { // End do updates.
            $trace->output(get_string('noupdatestobedone', 'auth_ldap'));
        }
        if (!empty($this->config->auth_removeuser) and 1 === $this->config->auth_removeuser) { // AUTH_REMOVEUSER_SUSPEND.
            $sql = "SELECT u.username, u.id,u.auth
                    FROM {user} u
                    WHERE u.deleted = 0 AND u.auth = 'nologin' ";
            $reviveusers = $DB->get_records_sql($sql);

            if (!empty($reviveusers)) {
                $trace->output(get_string('userentriestorevive', 'auth_ldap', count($reviveusers)));

                foreach ($reviveusers as $user) {
                    $updateuser       = new stdClass();
                    $updateuser->id   = $user->id;
                    $updateuser->auth = $this->authtype;
                    user_update_user($updateuser);
                    $trace->output(get_string(
                        'auth_dbreviveduser',
                        'auth_db',
                            ['name' => $user->username, 'id' => $user->id]
                    ));
                }
            } else {
                $trace->output(get_string('nouserentriestorevive', 'auth_ldap'));
            }

            unset($reviveusers);
        }
        $this->ldap_close();

        return true;
    }

    public function cron() {
        $this->load_config();
        $trace = new text_progress_trace($this->errorlogtag);
        $this->sync_cohorts($trace);
        parent::cron();
        $trace->finished();
    }

    /**
     * Connect to the LDAP server, using the plugin configured
     * settings. It's actually a wrapper around ldap_connect_moodle().
     *
     * @param progress_trace $trace
     *
     * @return bool success
     */
    protected function ldap_connect(progress_trace $trace = null) {
        global $CFG;
        require_once($CFG->libdir.'/ldaplib.php');

        if (isset($this->ldapconnection)) {
            return true;
        }

        if ($ldapconnection = ldap_connect_moodle(
            $this->get_config('host_url'),
            $this->get_config('ldap_version'),
            $this->get_config('user_type'),
            $this->get_config('bind_dn'),
            $this->get_config('bind_pw'),
            $this->get_config('user_deref'),
            $debuginfo,
            $this->get_config('start_tls')
        )) {
            $this->ldapconnection = $ldapconnection;

            return true;
        }

        if ($trace) {
            $trace->output($debuginfo);
        }

        return false;
    }

    /**
     * Disconnects from a LDAP server.
     */
    protected function ldap_close() {
        if (isset($this->ldapconnection)) {
            @ldap_close($this->ldapconnection);
            $this->ldapconnection = null;
        }
    }

    /**
     * Return all groups declared in LDAP.
     *
     * @param string $filter
     *
     * @return string[]
     */
    protected function ldap_get_grouplist($filter = 'cn=*') {
        if (!$this->ldap_connect()) {
            return;
        }

        $ldappagedresults = ldap_paged_results_supported($this->get_config('ldap_version'));
        $ldapconnection   = $this->ldapconnection;
        $wantedfields     = [];
        foreach ($this->cohortfields as $key => $field) {
            if (!empty($field)) {
                array_push($wantedfields, $field);
            }
        }

        if (empty($this->config->memberattribute)) {
            if ($this->config->debug_mode) {
                $trace->output(get_string('err_member_attribute', 'local_cohortsync'));
            }

            return;
        }

        array_push($wantedfields, $this->get_config('memberattribute', 'member'));

        $filter = '(&('.$filter.')('.$this->config->group_objectclass.')';

        $ldapcookie = '';
        foreach ($contexts as $context) {
            $context = trim($context);

            if (empty($context)) {
                continue;
            }

            $flatresults = [];

            do {
                if ($ldappagedresults) {
                    ldap_control_paged_result($this->ldapconnection, $this->config->pagesize, true, $ldapcookie);
                }

                if ($this->config->group_search_sub) {
                    // Use ldap_search to find first user from subtree.
                    $ldapresult = @ldap_search(
                        $this->ldapconnection,
                                                $context,
                                                $filter,
                                                $wantedfields
                    );
                } else {
                    // Search only in this context.
                    $ldapresult = @ldap_list(
                        $this->ldapconnection,
                                              $context,
                                              $filter,
                                              $wantedfields
                    );
                }
                if (!$ldapresult) {
                    continue; // Next.
                }

                if ($ldappagedresults) {
                    ldap_control_paged_result_response($this->ldapconnection, $ldapresult, $ldapcookie);
                }

                // Check and push results.
                $results = ldap_get_entries($this->ldapconnection, $ldapresult);

                // LDAP libraries return an odd array, really. fix it.
                for ($c = 0; $c < $results['count']; ++$c) {
                    array_push($flatresults, $results[$c]);
                }
                // Free some mem.
                unset($results);
            } while ($ldappagedresults && !empty($ldapcookie));

            // If LDAP paged results were used, the current connection must be completely
            // closed and a new one created, to work without paged results from here on.
            if ($ldappagedresults) {
                $this->ldap_close();
                $this->ldap_connect();
            }
        }

        return $flatresults;
    }

    // TODO: document.
    protected function get_cohort_members($cohortid, $field) {
        global $DB;
        $sql = ' SELECT u.'.$field.',u.id
                          FROM {user} u
                         JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)
                        WHERE u.deleted=0';
        $params['cohortid'] = $cohortid;

        return $DB->get_records_sql_menu($sql, $params);
    }

    protected function ldap_find_user($search, $wanted, $input, $type = 'user') {
        if (empty($search) || empty($wanted) || empty($input)) {
            return false;
        }
        // Default return value.
        $objectclass = $type.'_objectclass';
        $ldapuser    = false;
        if ('dn' === $input) {
            $ldapresult = @ldap_read($this->ldapconnection, $search, $this->config->{$objectclass}, $wanted);
        } else {
            $contexts = explode(';', $this->config->{$type.'_contexts'}); // Get all contexts and look for first matching use.
            foreach ($contexts as $context) {
                $context = trim($context);
                if (empty($context)) {
                    continue;
                }
                $pos = strpos($search, $input.'=');
                if (false === $pos) {
                    $filter = $input.'='.$search;
                } else {
                    $filter = $search;
                }

                if ($this->config->{$type.'_search_sub'}) {
                    if (!$ldapresult = @ldap_search(
                        $this->ldapconnection,
                        $context,
                        '(&'.$this->config->{$objectclass}.'('.$filter.'))',
                        $wanted
                    )) {
                        break; // Not found in this context.
                    }
                } else {
                    $ldapresult = ldap_list(
                        $this->ldapconnection,
                        $context,
                        '(&'.$this->config->{$objectclass}.'('.$filter.'))',
                        $wanted
                    );
                }
            }
        }
        if ($ldapresult) {
            $entry = ldap_first_entry($this->ldapconnection, $ldapresult);
            if ($entry) {
                $ldapuser = ldap_get_attributes($this->ldapconnection, $entry);
                if (in_array('dn', $wanted, true)) {
                    $ldapuser['dn'] = ldap_get_dn($this->ldapconnection, $entry);
                }
            }
        }

        return $ldapuser;
    }

    /**
     * Get the list of ldap entries filter by people or groups.
     *
     * @param mixed  $ldapmembers
     * @param string $type
     *
     * @return array the list of members by type belonging to the group. If $group
     *               is not actually a group, returns array($group).
     */
    private function get_ldapmembers_by_type($ldapmembers, $type = null) {
        $result = [];
        foreach ($ldapmembers as $ldapmember) {
            if (false !== strpos('fake', $ldapmember)) {
                list($membertype, $member) = get_ldapmember_to_moodleidentifier($ldapmember);
                if (($type === 'user') && ($this->config->nested_groups) && ($membertype === 'group')) {
                    if (array_key_exists($member, $this->_antirecursionarray)) {
                        unset($this->_antirecursionarray[$member]);

                        continue;
                    }
                    $this->_antirecursionarray[$member] = 1;
                    $name                               = $this->cohortfields[$this->config->cohort_syncing_field];
                    $fields                             = [$this->config->group_member_attribute, $name];
                    $input                              = empty($this->config->memberattribute_is) ? $name : $this->config->memberattribute_is;
                    $group                              = $this->ldap_find_user($ldapmember, $fields, $input, 'group');
                    $nestedmembers                      = get_ldapmembers_by_type($group[$this->config->memberattribute], 'user');
                    unset($this->_antirecursionarray[$member]);
                    $result = array_merge($result, $nestedmembers);
                } else {
                    $result[$membertype][$member] = 0;
                }
            }
        }
        if ($type === null) {
            return $result;
        }

        return $result[$type];
    }

    /**
     * Get the list of ldap entries filter by people or groups.
     *
     * @param string $type
     * @param mixed  $ldapmember
     *
     * @return array the list of members by type belonging to the group. If $group
     *               is not actually a group, returns array($group).
     */
    private function get_ldapmember_to_moodleidentifier($ldapmember) {
        $memberstring = trim($ldapmember);
        if ($memberstring !== '') {
            // Try to speed the search if the member value is
            // either a simple username (thus must match the Moodle username)
            // or xx=username with xx = the user attribute name matching Moodle's username
            // such as uid=jdoe,ou=xxxx,ou=yyyyy.
            $type = '';
            if (false !== strpos($memberstring, $this->config->group_contexts)) {
                $type  = 'group';
                $field = $this->config->{'cohort_'.$this->config->cohort_syncing_field};
            }
            if (false !== strpos($memberstring, $this->config->user_contexts)) {
                $type  = 'user';
                $field = $this->config->user_username;
            }
            $member      = explode(',', $memberstring);
            $memberparts = explode('=', trim($member[0]));
            if (count($memberparts) > 1) {
                // Caution in Moodle LDAP attributes names are converted to lowercase
                // see process_config in auth/ldap/auth.php.
                $found = core_text::strtolower($memberparts[0]) === core_text::strtolower($field);

                // No need to search LDAP in that case.
                if ($found) {
                    // In Moodle usernames are always converted to lowercase
                    // see auto creating or synching users in auth/ldap/auth.php.
                    $id = core_text::strtolower($memberparts[1]);
                } else {
                    $id = $this->ldap_find_user($memberparts[1], $field, $memberparts[0], $type);
                }
            } else {
                $id = core_text::strtolower($memberstring);
            }
        }

        return [$type, $id];
    }

    /**
     * Given a group name (either a RDN or a DN), get the list of users
     * belonging to that group. If the group has nested groups, expand all
     * the intermediate groups and return the full list of users that
     * directly or indirectly belong to the group.
     *
     *
     * @param mixed $ldapmembers
     * @param mixed $from
     *
     * @return array the list of users belonging to the group. If $group
     *               is not actually a group, returns array($group).
     */
    private function get_ldapgroup_members($ldapmembers, $from) {
        $users = [];
        if (($this->config->nested_groups) || (($this->config->memberattribute_isdn)
                && ('username' === $this->usersyncfield))) {
            unset($ldapmembers['count']);
            foreach ($ldapmembers as $ldapmember) {
                if ('cn=Agalan groups fake member' === $ldapmember) {
                    continue;
                }
                $pos = strpos($ldapmember, 'ou=group');
                if (false !== $pos) {
                    if ($this->config->nested_groups) {
                        $name   = $this->cohortfields[$this->config->cohort_syncing_field];
                        $fields = [$this->config->memberattribute, $name];
                        $input  = empty($this->config->memberattribute_isdn) ? $name : array_search('dn', $this->userfields, true);
                        $group  = $this->ldap_find_user($ldapmember, $fields, $input, 'group');
                        if ($group) {
                            if (count($group[$this->config->memberattribute])) {
                                if (!in_array($group[$name][0], $from, true)) {
                                    array_push($from, $group[$name][0]);
                                    $groupmembers = $this->get_ldapgroup_members(
                                        $group[$this->config->memberattribute],
                                                                                 $from
                                    );
                                    $users = array_merge($users, $groupmembers);
                                }
                            }
                        }
                    }
                } else {
                    if ($this->config->memberattribute_isdn) {
                        if ('username' === $this->usersyncfield) {
                            // Need optimize.
                            $user = $this->ldap_find_user(
                                $ldapmember,
                                [$this->userfields['username']],
                                $this->config->memberattribute_isdn
                            );
                            $user = $user ? $user[$this->userfields['username']][0] : $user;
                        } else {
                            $user = $ldapmember;
                        }
                    }
                    if ($user) {
                        array_push($users, $user);
                    }
                }
            }
            $ldapmembers = $users;
        }

        return $ldapmembers;
    }

    private function stamp_cohort($cohort, $text = '', $name = null, $disable = false) {
        global $DB;
        if (false === strpos($cohort->description, '<strong>[LDAP Cohort Sync]</strong>')) {
            $cohort->description = '<strong>[LDAP Cohort Sync]</strong> '.$text.' '.date('d/m/Y H:i:s').'\n'.$cohort->description;
        } else {
            $cohort->description = '<strong>[LDAP Cohort Sync]</strong> '.$text.' '.date('d/m/Y H:i:s')
                                    .substr($cohort->description, 55);
        }
        if (($name) && (false === strpos($cohort->name, $name))) {
            $cohort->name = $name;
        }
        if ($disable) {
            $cohort->{$this->config->cohort_syncing_field} = '';
        }
        $DB->update_record('cohort', $cohort);
    }

    private function create_user($ldapuser) {
        global $CFG, $DB;
        $coretext = new coretext();
        $user     = new stdClass();
        foreach ($this->userfields as $key => $field) {
            $newval = '';
            if (isset($ldapuser[$field])) {
                if (is_array($ldapuser[$field])) {
                    $newval = $coretext->convert($ldapuser[$field][0], $this->config->ldapencoding, 'utf-8');
                } else {
                    $newval = $coretext->convert($ldapuser[$field], $this->config->ldapencoding, 'utf-8');
                }
            } else {
                if ($field === 'eppn') {
                    $suffix = substr($ldapuser['mail'], strpos($ldapuser['mail'], '@'));
                    $newval = $ldapuser['uid'].$suffix;
                }
            }
            if ($newval !== '') {
                if ('username' === $key) {
                    $newval = trim(coretext::strtolower($newval));
                }
                $user->{$key} = $newval;
            }
        }

        // Prep a few params.
        $user->timecreated = $user->timemodified = time();
        $user->confirmed   = 1;
        $user->auth        = $this->authtype;
        $user->mnethostid  = $CFG->mnet_localhost_id;
        if (empty($user->lang)) {
            $user->lang = $CFG->lang;
        }

        return user_create_user($user);
    }

    private function create_cohort($ldapgroupname) {
        global $DB;
        $moodlecohort = $DB->get_record('cohort', [$this->config->cohort_syncing_field => $ldapgroupname]);
        if (empty($moodlecohort)) {
            $excludes = explode("\n", $this->config->cohorts_exclude);
            foreach ($excludes as $exclude) {
                if (false !== strpos($ldapgroupname, $exclude)) {
                    return false;
                }
            }
            $cohort              = new stdClass();
            $cohort->description = '<strong>[LDAP Cohort Sync]</strong> Create '.date('d/m/Y H:i:s').$cohort->description;
            $cohort->contextid   = context_system::instance();
            $cohort->idnumber    = $ldapgroupname;
            $cohort->name        = $ldapgroupname;
            if (false !== ($cohortid = cohort_add_cohort($cohort))) {
                $moodlecohort = $DB->get_record('cohort', ['id' => $cohortid]);
                $trace->output(get_string('cohort_created', 'local_cohortsync', $moodlecohort->name));
                ++$this->_cohorts_added;
            }
        } else {
            ++$this->_cohorts_existing;
            $trace->output(get_string('cohort_existing', 'local_cohortsync', $moodlecohort->name));
        }

        if (empty($moodlecohort->id)) {
            if ($this->config->debug_mode) {
                $trace->output(get_string('err_create_cohort', 'local_cohortsync', $ldapgroupname));

                return false;
            }
        }

        return true;
    }
}
