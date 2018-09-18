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
 * LDAPgroup enrolment plugin settings and presets.
 *
 * @author     Fabrice Menard
 * @copyright  2014 Fabrice Menard <fabrice.menard@upmf-grenoble.fr> - 2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_cohortsync', get_string('pluginname', 'local_cohortsync'));

    // Heading.
    $settings->add(new admin_setting_heading('local_cohortsync_settings', '', get_string('pluginname_desc', 'local_cohortsync')));

    if (!function_exists('ldap_connect')) {
        $settings->add(new admin_setting_heading(
            'local_cohortsync_phpldap_noextension',
            '',
            get_string('phpldap_noextension', 'local_cohortsync')
        ));
    } else {
        // We use a couple of custom admin settings since we need to massage the data before it is inserted into the DB.
        require_once($CFG->dirroot.'/enrol/ldap/settingslib.php');
        require_once($CFG->libdir.'/ldaplib.php');

        $yesno = [get_string('no'), get_string('yes')];
        // General settings.
        $settings->add(
            new admin_setting_heading(
                'local_cohortsync_general_settings',
                get_string('general_settings', 'local_cohortsync'),
                ''
            )
        );

        $settings->add(
            new admin_setting_configselect(
                'local_cohortsync/login_sync',
                get_string('login_sync_key', 'local_cohortsync'),
                get_string('login_sync', 'local_cohortsync'),
                1,
                $yesno
            )
        );

        $settings->add(
            new admin_setting_configcheckbox(
                'local_cohortsync/debug_mode',
                get_string('debug_mode_key', 'local_cohortsync'),
                get_string('debug_mode', 'local_cohortsync'),
                false
            )
        );
        $options = [get_string('none', 'local_cohortsync'),
                    get_string('all', 'local_cohortsync'),
                    get_string('groups', 'local_cohortsync'),
                    get_string('list', 'local_cohortsync')];
        $settings->add(new admin_setting_configselect(
            'local_cohortsync/autocreate_cohorts',
            get_string('autocreate_cohorts_key', 'local_cohortsync'),
            get_string('autocreate_cohorts', 'local_cohortsync'),
            0,
            $options
        ));
        $settings->add(new admin_setting_configtextarea(
            'local_cohortsync/cohorts_include',
            new lang_string('cohorts_include_key', 'local_cohortsync'),
            new lang_string('cohorts_include', 'local_cohortsync'),
            '',
            PARAM_RAW,
            '50',
            '10'
        ));
        $settings->add(new admin_setting_configtextarea(
            'local_cohortsync/cohorts_exclude',
            new lang_string('cohorts_exclude_key', 'local_cohortsync'),
            new lang_string('cohorts_exclude', 'local_cohortsync'),
            '',
            PARAM_RAW,
            '50',
            '10'
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_cohortsync/autocreate_users',
            get_string('autocreate_users_key', 'local_cohortsync'),
            get_string('autocreate_users', 'local_cohortsync'),
            false
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_cohortsync/unsubscribe_users',
            get_string('unsubscribe_users_key', 'local_cohortsync'),
            get_string('unsubscribe_users', 'local_cohortsync'),
            false
        ));

        // Connection settings.
        $settings->add(
            new admin_setting_heading(
                'local_cohortsync_server_settings',
                get_string('server_settings', 'local_cohortsync'),
                ''
            )
        );

        $settings->add(
            new admin_setting_configtext_trim_lower(
                'local_cohortsync/host_url',
                get_string('host_url_key', 'local_cohortsync'),
                get_string('host_url', 'local_cohortsync'),
                ''
            )
        );

        $settings->add(
            new admin_setting_configselect(
                'local_cohortsync/start_tls',
                get_string('start_tls_key', 'auth_ldap'),
                get_string('start_tls', 'auth_ldap'),
                0,
                $yesno
            )
        );

        // Set LDAPv3 as the default. Nowadays all the servers support it and it gives us some real benefits.
        $options = [3 => '3', 2 => '2'];
        $settings->add(
            new admin_setting_configselect(
                'local_cohortsync/ldap_version',
                get_string('version_key', 'local_cohortsync'),
                get_string('version', 'local_cohortsync'),
                3,
                $options
            )
        );

        $settings->add(
            new admin_setting_configtext_trim_lower(
                'local_cohortsync/ldapencoding',
                get_string('ldap_encoding_key', 'local_cohortsync'),
                get_string('ldap_encoding', 'local_cohortsync'),
                'utf-8'
            )
        );

        $settings->add(
            new admin_setting_configtext_trim_lower(
                'local_cohortsync/pagesize',
                get_string('pagesize_key', 'auth_ldap'),
                get_string('pagesize', 'auth_ldap'),
                LDAP_DEFAULT_PAGESIZE,
                true
            )
        );

        // Binding settings.
        $settings->add(
            new admin_setting_heading(
                'local_cohortsync_bind_settings',
                get_string('bind_settings', 'local_cohortsync'),
                ''
            )
        );

        $settings->add(
            new admin_setting_configtext_trim_lower(
                'local_cohortsync/bind_dn',
                get_string('bind_dn_key', 'local_cohortsync'),
                get_string('bind_dn', 'local_cohortsync'),
                ''
            )
        );

        $settings->add(
            new admin_setting_configpasswordunmask(
                'local_cohortsync/bind_pw',
                get_string('bind_pw_key', 'local_cohortsync'),
                get_string('bind_pw', 'local_cohortsync'),
                ''
            )
        );

        // User lookup settings.
        $settings->add(new admin_setting_heading(
            'local_cohortsync_user_settings',
            get_string('user_settings', 'local_cohortsync'),
            ''
        ));

        $usertypes = ldap_supported_usertypes();

        $settings->add(new admin_setting_configselect(
            'local_cohortsync/user_type',
            get_string('user_type_key', 'local_cohortsync'),
            get_string('user_type', 'local_cohortsync'),
            end($usertypes),
            $usertypes
        ));

        $settings->add(new admin_setting_configtext(
            'local_cohortsync/user_contexts',
            get_string('user_contexts_key', 'local_cohortsync'),
            get_string('user_contexts', 'local_cohortsync'),
            ''
        ));

        $settings->add(new admin_setting_configselect(
            'local_cohortsync/user_search_sub',
            get_string('search_sub_key', 'local_cohortsync'),
            get_string('search_sub', 'local_cohortsync'),
            key($yesno),
            $yesno
        ));

        $optderef = [];
        $optderef[LDAP_DEREF_NEVER] = get_string('no');
        $optderef[LDAP_DEREF_ALWAYS] = get_string('yes');

        $settings->add(new admin_setting_configselect(
            'local_cohortsync/opt_deref',
            get_string('opt_deref_key', 'local_cohortsync'),
            get_string('opt_deref', 'local_cohortsync'),
            key($optderef),
            $optderef
        ));

        $settings->add(new admin_setting_configtext_trim_lower(
            'local_cohortsync/memberofattribute',
            get_string('memberofattribute_key', 'local_cohortsync'),
            get_string('memberofattribute', 'local_cohortsync'),
            'memberUid',
            false
        ));

        $settings->add(new admin_setting_configtext(
            'local_cohortsync/user_objectclass',
            get_string('objectclass_key', 'local_cohortsync'),
            get_string('user_objectclass', 'local_cohortsync'),
            ''
        ));

        // Remove external user.
        $deleteopt = [
            get_string('auth_remove_keep', 'auth'),
            get_string('auth_remove_suspend', 'auth'),
            get_string('auth_remove_delete', 'auth')
        ];

        $settings->add(new admin_setting_configselect(
            'local_cohortsync/auth_removeuser',
            new lang_string('auth_remove_user_key', 'auth'),
            new lang_string('auth_remove_user', 'auth'),
            0,
            $deleteopt
        ));
        $userfields = ['username' => 'uid', 'idnumber' => 'uid', 'firstname' => 'givenName', 'lastname' => 'sn', 'email' => 'mail'];
        foreach ($userfields as $key => $field) {
            $settings->add(new admin_setting_configtext_trim_lower(
                'local_cohortsync/user_'.$key,
                get_string('user_'.$key.'_key', 'local_cohortsync'),
                get_string('user_'.$key, 'local_cohortsync'),
                $field,
                true
            ));
        }

        // Group lookup settings.
        $settings->add(
            new admin_setting_heading(
                'local_cohortsync_group_settings',
                get_string('group_settings', 'local_cohortsync'),
                ''
            )
        );

        $settings->add(
            new admin_setting_configtext_trim_lower(
                'local_cohortsync/group_objectclass',
                get_string('objectclass_key', 'local_cohortsync'),
                get_string('objectclass', 'local_cohortsync'),
                'posixGroup'
            )
        );

        $settings->add(
            new admin_setting_configtext_trim_lower(
                'local_cohortsync/group_filter',
                get_string('filter_key', 'local_cohortsync'),
                get_string('filter', 'local_cohortsync'),
                '(cn=*)'
            )
        );

        // GROUP SETTINGS.
        $settings->add(new admin_setting_configtext_trim_lower(
            'local_cohortsync/group_contexts',
            get_string('group_contexts_key', 'local_cohortsync'),
            get_string('group_contexts', 'local_cohortsync'),
            ''
        ));
        $settings->add(new admin_setting_configselect(
            'local_cohortsync/group_search_sub',
            get_string('search_sub_key', 'local_cohortsync'),
            get_string('group_search_sub', 'local_cohortsync'),
            key($yesno),
            $yesno
        ));
        $settings->add(new admin_setting_configtext_trim_lower(
            'local_cohortsync/memberattribute',
            get_string('memberattribute_key', 'local_cohortsync'),
            get_string('memberattribute', 'local_cohortsync'),
            'member',
            false
        ));
        $settings->add(new admin_setting_configselect(
            'local_cohortsync/memberattribute_isdn',
            get_string('memberattribute_isdn_key', 'local_cohortsync'),
            get_string('memberattribute_isdn', 'local_cohortsync'),
            0,
            $yesno
        ));

        $settings->add(new admin_setting_configtext_trim_lower(
            'local_cohortsync/cohort_syncing_field',
            get_string('cohort_syncing_field_key', 'local_cohortsync'),
            get_string('cohort_syncing_field', 'local_cohortsync'),
            'idnumber',
            true
        ));

        $cohortfields = ['name' => 'cn', 'idnumber' => 'cn', 'description' => 'description'];

        foreach ($cohortfields as $key => $field) {
            $settings->add(new admin_setting_configtext_trim_lower(
                'local_cohortsync/cohort_'.$key,
                get_string('cohort_'.$key.'_key', 'local_cohortsync'),
                get_string('cohort_'.$key, 'local_cohortsync'),
                $field,
                true
            ));
        }

        // Nested groups settings.
        $settings->add(new admin_setting_heading(
            'local_cohortsync_nested_groups_settings',
            get_string('nested_groups_settings', 'local_cohortsync'),
            ''
        ));

        $settings->add(new admin_setting_configselect(
            'local_cohortsync/nested_groups',
            get_string('nested_groups_key', 'local_cohortsync'),
            get_string('nested_groups', 'local_cohortsync'),
            0,
            $yesno
        ));

        $ADMIN->add('localplugins', $settings);
    }
}
