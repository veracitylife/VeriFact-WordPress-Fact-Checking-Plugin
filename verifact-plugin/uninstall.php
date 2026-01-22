<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
delete_option('verifact_api_base');
delete_option('verifact_api_key');
delete_option('verifact_rate_limit');
delete_option('verifact_cache_duration');
delete_option('verifact_user_permissions');
delete_option('verifact_enable_archiveorg');
delete_option('verifact_enable_grokopedia');
delete_option('verifact_remote_cache_enabled');
delete_option('verifact_remote_cache_url');
delete_option('verifact_role_rate_limits');
delete_option('verifact_schedules');
