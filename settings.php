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
 * @package    local_cohortsync
 * @author     Fabrice Menard
 * @copyright  2014 Fabrice Menard <fabrice.menard@upmf-grenoble.fr> - 2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    


    //--- heading ---
    $settings->add(new admin_setting_heading('local_cohortsync_settings', '', get_string('pluginname_desc', 'local_cohortsync')));

    if (!function_exists('ldap_connect')) {
        $settings->add(new admin_setting_heading('local_cohortsync_phpldap_noextension', '', get_string('phpldap_noextension', 'local_cohortsync')));
    } else {
        // We use a couple of custom admin settings since we need to massage the data before it is inserted into the DB.
        require_once($CFG->dirroot.'/enrol/ldap/settingslib.php');
        require_once($CFG->libdir.'/ldaplib.php');

        $yesno = array(get_string('no'), get_string('yes'));

        //--- general settings ---
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

        $settings->add(new admin_setting_configcheckbox('local_cohortsync/autocreate_cohorts', get_string('autocreate_cohorts_key', 'local_cohortsync'), get_string('autocreate_cohorts', 'local_cohortsync'), false));
        $settings->add(new admin_setting_configcheckbox('local_cohortsync/autocreate_users', get_string('autocreate_users_key', 'local_cohortsync'), get_string('autocreate_users', 'local_cohortsync'), false));
        $settings->add(new admin_setting_configcheckbox('local_cohortsync/unsubscribe_users', get_string('unsubscribe_users_key', 'local_cohortsync'), get_string('unsubscribe_users', 'local_cohortsync'), false));

        //--- connection settings ---
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
        $options = array(3=>'3', 2=>'2');
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

        //--- binding settings ---
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

       

        //--- user lookup settings
        $settings->add(new admin_setting_heading('local_cohortsync_user_settings', 
        get_string('user_settings', 'local_cohortsync'), ''));

        $usertypes = ldap_supported_usertypes();

        $settings->add(new admin_setting_configselect('local_cohortsync/user_type', get_string('user_type_key', 'local_cohortsync'), get_string('user_type', 'local_cohortsync'), end($usertypes), $usertypes));

        $settings->add(new admin_setting_configtext('local_cohortsync/user_contexts', get_string('user_contexts_key', 'local_cohortsync'), get_string('user_contexts', 'local_cohortsync'), ''));

        $settings->add(new admin_setting_configselect('local_cohortsync/search_sub', get_string('search_sub_key', 'local_cohortsync'), get_string('search_sub', 'local_cohortsync'), key($yesno), $yesno));

        $opt_deref = array();
        $opt_deref[LDAP_DEREF_NEVER] = get_string('no');
        $opt_deref[LDAP_DEREF_ALWAYS] = get_string('yes');
        
        $settings->add(new admin_setting_configselect('local_cohortsync/opt_deref', get_string('opt_deref_key', 'local_cohortsync'), get_string('opt_deref', 'local_cohortsync'), key($opt_deref), $opt_deref));
       
       
        
        $settings->add(new admin_setting_configtext_trim_lower('local_cohortsync/user_attribute', get_string('user_attribute_key', 'local_cohortsync'), get_string('user_attribute', 'local_cohortsync'), '', true, true));


        $settings->add(new admin_setting_configtext_trim_lower('local_cohortsync/memberattribute', get_string('memberattribute_key', 'local_cohortsync'), get_string('memberattribute', 'local_cohortsync'), 'memberUid', false));

        $settings->add(new admin_setting_configselect('local_cohortsync/memberattribute_isdn', get_string('memberattribute_isdn_key', 'local_cohortsync'), get_string('memberattribute_isdn', 'local_cohortsync'), 0, $yesno));


        $settings->add(new admin_setting_configtext('local_cohortsync/user_objectclass', get_string('objectclass_key', 'local_cohortsync'), get_string('user_objectclass', 'local_cohortsync'), ''));


         // Remove external user.
         $settings->add(new admin_setting_configselect('local_cohortsync/removeuser',
                 new lang_string('auth_remove_user_key', 'auth'),
                 new lang_string('auth_remove_user', 'auth'), 0, $yesno));

                 // USEFULL OR NOT ?
 /*
         // NTLM SSO Header.
         $settings->add(new admin_setting_heading('local_cohortsync/ntlm',
                 new lang_string('auth_ntlmsso', 'auth_ldap'), ''));
 
         // Enable NTLM.
         $settings->add(new admin_setting_configselect('local_cohortsync/ntlmsso_enabled',
                 new lang_string('auth_ntlmsso_enabled_key', 'auth_ldap'),
                 new lang_string('auth_ntlmsso_enabled', 'auth_ldap'), 0 , $yesno));
 
         // Subnet.
         $settings->add(new admin_setting_configtext('local_cohortsync/ntlmsso_subnet',
                 get_string('auth_ntlmsso_subnet_key', 'auth_ldap'),
                 get_string('auth_ntlmsso_subnet', 'auth_ldap'), '', PARAM_RAW_TRIMMED));
 
         // NTLM Fast Path.
        $fastpathoptions = array();
        $fastpathoptions[AUTH_NTLM_FASTPATH_YESFORM] = get_string('auth_ntlmsso_ie_fastpath_yesform', 'auth_ldap');
        $fastpathoptions[AUTH_NTLM_FASTPATH_YESATTEMPT] = get_string('auth_ntlmsso_ie_fastpath_yesattempt', 'auth_ldap');
        $fastpathoptions[AUTH_NTLM_FASTPATH_ATTEMPT] = get_string('auth_ntlmsso_ie_fastpath_attempt', 'auth_ldap');
 
        $settings->add(new admin_setting_configselect('local_cohortsync/ntlmsso_ie_fastpath',
                 new lang_string('auth_ntlmsso_ie_fastpath_key', 'auth_ldap'),
                 new lang_string('auth_ntlmsso_ie_fastpath', 'auth_ldap'),
                 AUTH_NTLM_FASTPATH_ATTEMPT, $fastpathoptions));
 
         // Authentication type.
        $types = array();
        $types['ntlm'] = 'NTLM';
        $types['kerberos'] = 'Kerberos';
 
        $settings->add(new admin_setting_configselect('local_cohortsync/ntlmsso_type',
                 new lang_string('auth_ntlmsso_type_key', 'auth_ldap'),
                 new lang_string('auth_ntlmsso_type', 'auth_ldap'), 'ntlm', $types));
 
        // Remote Username format.
        $settings->add(new auth_ldap_admin_setting_special_ntlm_configtext('local_cohortsync/ntlmsso_remoteuserformat',
                 get_string('auth_ntlmsso_remoteuserformat_key', 'auth_ldap'),
                 get_string('auth_ntlmsso_remoteuserformat', 'auth_ldap'), '', PARAM_RAW_TRIMMED));
    }*/

        //--- group lookup settings
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

        $userfields = array('username'=>'uid','idnumber'=>'uid','firstname'=>'givenName','lastname'=>'sn','email'=>'mail' );
        foreach ($userfields as $key =>  $field) {
            $settings->add(new admin_setting_ldapcohort_trim_lower('local_cohortsync/user_'.$key, get_string('user_'.$key.'_key', 'local_cohortsync'), get_string('user_'.$key, 'local_cohortsync'), $field , true));
        }

        // GROUP SETTINGS
        $settings->add(new admin_setting_configtext_trim_lower('local_cohortsync/group_contexts', get_string('group_contexts_key', 'local_cohortsync'), get_string('group_contexts', 'local_cohortsync'), ''));
        $settings->add(new admin_setting_configselect('local_cohortsync/group_search_sub', get_string('search_subcontexts_key', 'local_cohortsync'), get_string('group_search_sub', 'local_cohortsync'), key($yesno), $yesno));
        $settings->add(new admin_setting_configtext_trim_lower('local_cohortsync/memberofattribute', get_string('member_attribute_key', 'local_cohortsync'), get_string('memberofattribute', 'local_cohortsync'), 'member', false));
        $settings->add(new admin_setting_configselect('local_cohortsync/memberofattribute_isdn', get_string('memberattribute_isdn_key', 'local_cohortsync'), get_string('memberofattribute_isdn', 'local_cohortsync'), 0, $yesno));
        
        $settings->add(new admin_setting_ldapcohort_trim_lower('local_cohortsync/cohort_syncing_field', get_string('cohort_syncing_field_key', 'local_cohortsync'), get_string('cohort_syncing_field', 'local_cohortsync'), 'idnumber', true));
        
        $cohortfields = array ('name'=>'cn', 'idnumber'=>'cn', 'description'=>'description');

        foreach ($cohortfields as $key => $field) {
            $settings->add(new admin_setting_ldapcohort_trim_lower('local_cohortsync/cohort_'.$key, get_string('cohort_'.$key.'_key', 'local_cohortsync'), get_string('cohort_'.$key, 'local_cohortsync'), $field , true));
        }

        //--- nested groups settings ---
        $settings->add(new admin_setting_heading('local_cohortsync_nested_groups_settings', get_string('nested_groups_settings', 'local_cohortsync'), ''));

        $options = $yesno;
        $settings->add(new admin_setting_configselect('local_cohortsync/nested_groups', get_string('nested_groups_key', 'local_cohortsync'), get_string('nested_groups', 'local_cohortsync'), 0, $options));
    }
}
