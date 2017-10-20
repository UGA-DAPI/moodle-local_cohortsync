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
 * You may localized strings in your plugin
 *
 * @package    local_cohortsync
 * @copyright  2014 Daniel Neis
 * @license    http://www.gnu.org/copyleft/gpl.html gnu gpl v3 or later
 */

$string['pluginname'] = 'Local cohort sync';

$string['auth_ldap_noconnect_all'] = 'LDAP-module cannot connect to any servers';
$string['auth_method_key'] = 'Authentication method';
$string['auth_method'] = 'Authentication method';

$string['by_groups_key']='Synch with LDAP groups ';
$string['by_groups']='Synch Moodle\'s cohorts with LDAP groups and not with LDAP user attribute';
$string['debug_mode_key']='Verbose mode';
$string['debug_mode']='Turn on or off the verbose mode when running the script';
$string['autocreate_cohorts_key']='Autocreate missing cohorts';
$string['autocreate_cohorts']='if false will not create missing cohorts (admin must create them before) ';
$string['login_sync_key']='Sync at login user';
$string['login_sync']='Enable syncing during interactive login ';

$string['autocreate_users_key']='Autocreate missing users';
$string['autocreate_users']='if false will not create missing users ';
$string['unsubscribe_users_key']='Unsubscribe missing users';
$string['unsubscribe_users']='if true will  unsubscribe missing users ';

$string['bind_dn'] = 'If you want to use a bind user to search users, specify it here. Someting like \'cn=ldapuser,ou=public,o=org\'';
$string['bind_dn_key'] = 'Bind user distinguished name';
$string['bind_pw'] = 'Password for the bind user';
$string['bind_pw_key'] = 'Password';
$string['bind_settings'] = 'Bind settings';

$string['cohort_context'] = 'Default context for newly created cohorts';
$string['cohort_context_key'] = 'Cohort context';
$string['group_contexts'] = 'List of contexts where cohorts are located. Separate different contexts with \';\'. For example: \'ou=users,o=org; ou=others,o=org\'';
$string['group_contexts_key'] = 'Contexts';
$string['cohort_created'] = 'Cohort "{$a}" created';
$string['cohort_description'] = 'LDAP attribute to get the cohort description from';
$string['cohort_description_key'] = 'Cohort description';
$string['cohort_existing'] = 'Cohort "{$a}" already exists, skipping';
$string['cohort_found_users'] = 'Found {$a} users';
$string['cohort_idnumber'] = 'LDAP attribute to get the cohort ID number from. Usually \'cn\' or \'uid\'.';
$string['cohort_idnumber_key'] = 'Cohort ID.';
$string['group_settings'] = 'Group lookup settings';
$string['cohort_name'] = 'LDAP attribute to get the cohort name from';
$string['cohort_name_key'] = 'Cohort name';
$string['cohort_no_users'] = 'no users found';
$string['group_member_attribute'] = 'Group membership attribute in cohort entry. This denotes the attribute of the cohort which should be used to search users. Usual values: \'member\', \'uid\'';
$string['group_member_attribute_key'] = 'Cohort member attribute';
$string['cohort_syncing_field'] = 'Cohort field to synchronzing. Usual values: \'name\', \'idnumber\'';
$string['cohort_syncing_field_key'] = 'Cohort field to synchronzing';
$string['group_search_sub'] = 'Search cohorts from subcontexts';
$string['cohort_sync_users'] = 'Synchronizing users...';
$string['connectingldap'] = 'Connecting ldap...';

$string['err_create_cohort'] = 'Cannot create cohort with name: {$a}';
$string['err_create_user'] = 'Cannot create user with uid: {$a}';
$string['err_member_attribute'] = 'EMPTY MEMBER ATTRIBUTE FOR USER LOOKUP, PLEASE REVIEW SETTINGS';
$string['err_invalid_cohort_name'] = 'Empty LDAP group attribute (cohort name) : {$a}, skipping...';
$string['err_invalid_cohort_name'] = 'Empty LDAP group attribute (cohort id) : {$a}, skipping...';
$string['err_user_empty_uid'] = 'Empty uid in LDAP entry: {$a}';
$string['err_user_exists_in_cohort'] = 'User {$a->user} exists in cohort {$a->cohort}';

$string['general_settings'] = 'General settings';

$string['host_url'] = 'Specify LDAP host in URL-form like \'ldap://ldap.myorg.com/\' or \'ldaps://ldap.myorg.com/\'';
$string['host_url_key'] = 'Host URL';

$string['ldap_encoding'] = 'Specify encoding used by LDAP server. Most probably utf-8, MS AD v2 uses default platform encoding such as cp1252, cp1250, etc.';
$string['ldap_encoding_key'] = 'LDAP encoding';
$string['objectclass'] = 'objectClass used to search cohorts. Usually \'groupOfNames\' or \'posixGroup\'';
$string['objectclass_key'] = 'Object class';

$string['opt_deref'] = 'Determines how aliases are handled during search. Select one of the following values: "No" (LDAP_DEREF_NEVER) or "Yes" (LDAP_DEREF_ALWAYS)';

$string['pagesize'] = 'Make sure this value is smaller than your LDAP server result set size limit (the maximum number of entries that can be returned in a single query)';
$string['pagesize_key'] = 'Page size';

$string['filter'] = 'LDAP filter used to search cohorts. Usually \'cn=*\' or \'cn=*2013*\'';
$string['filter_key'] = 'Filter';

$string['phpldap_noextension'] = '<em>The PHP LDAP module does not seem to be present. Please ensure it is installed and enabled if you want to use this enrolment plugin.</em>';


$string['pluginname_desc'] = '<p>You can use an LDAP server to automatically create users and put them into Moodle cohorts.</p><p>In the given LDAP path there are groups containing users. Every group will generate a cohort in Moodle.</p><p>The synchronization process will be executed within the moodle cron.</p><p>You can run it manually by clicking {$a}</p>';

$string['memberattribute'] = 'Optional: Overrides user member attribute, when users belongs to a group. Usually \'member\'';

$string['memberofattribute'] = 'Optional: Overrides group member of attribute, when member belongs to a group. Usually \'memberOf\'';


$string['memberattribute_isdn'] = 'If the group membership contains distinguised names, you need to specify it here. If it does, you also need to configure the remaining settings of this section';
$string['memberattribute_is_key'] = 'Member attribute uses dn';
$string['memberofattribute_isdn'] = 'If the user member of contains distinguised names, you need to specify it here. If it does, you also need to configure the remaining settings of this section';
$string['memberofattribute_isdn_key'] = 'Member of attribute uses ';
$string['nested_groups'] = 'Do you want to use nested groups (groups of groups) for enrolment?';
$string['nested_groups_key'] = 'Nested groups';
$string['nested_groups_settings'] = 'Nested groups settings';

$string['search_subcontexts_key'] = 'Search subcontexts';
$string['server_settings'] = 'LDAP server settings';

$string['shibboleth_uid_key'] = 'Key for unique identifier';
$string['shibboleth_uid'] = 'Key for unique identifier';

$string['start_tls'] = 'Use regular LDAP service (port 389) with TLS encryption';
$string['start_tls_key'] = 'Use TLS';
$string['synchronized_cohorts'] = 'Done. Synchornized {$a} cohorts.';
$string['synchronizing_cohorts'] = 'Synchronizing cohorts...';
$string['synctask'] = 'Cohort users sync job';

$string['cohort_synchronized_with_group']='Cohort synchronized with LDAP group {$a}';
$string['cohort_synchronized_with_attribute']='Cohort synchronized with LDAP attribute {$a}';

$string['user_attribute'] = 'Optional: Overrides the attribute used to name/search users. Usually \'cn\'.';

$string['user_idnumber'] = 'Field to map with ID Number for a user entry. (Usually, this is uid or uidNumber)';
$string['user_idnumber_key'] = 'User ID Number';
$string['user_username'] = 'Optional: Overrides the attribute used to name/search users. Usually \'cn\'.';
$string['user_username_key'] = 'User attribute';
$string['user_firstname'] = 'Field to map with firstname for a user entry.';
$string['user_firstname_key'] = 'User firstname';
$string['user_lastname'] = 'Field to map with lastname for a user entry.';
$string['user_lastname_key'] = 'User lastname';
$string['user_email'] = 'Field to map with email for a user entry.';
$string['user_email_key'] = 'User email';
$string['user_contexts'] = 'List of contexts where users are located. Separate different contexts with \';\'. For example: \'ou=users,o=org; ou=others,o=org\'';
$string['user_contexts_key'] = 'Contexts';

$string['user_created'] = 'User "{$a}" created';
$string['user_dbinsert'] = 'Inserted user {$a->name} with id {$a->id}';

$string['memberof_attribute'] = 'Group membership attribute in user entry. This denotes the user group(s) memberhsips. Usually \'member\', or \'memberUid\'';
$string['memberof_attribute_key'] = 'User member attribute';
$string['user_objectclass'] = 'Optional: Overrides objectClass used to name/search users on ldap_user_type. Usually you dont need to change this.';

$string['user_settings'] = 'User settings';

$string['search_sub'] = 'Search users from subcontexts';
$string['user_synchronized'] = 'Synchronized {$a->count} added, {$a->discount} removed users for cohort "{$a->cohort}"';
$string['user_type'] = 'Select how users are stored in LDAP. This setting also specifies how login expiration, grace logins and user creation will work.';
$string['user_type_key'] = 'User type';
$string['version'] = 'The version of the LDAP protocol your server is using';
$string['version_key'] = 'Version';
