<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
$verifact_cleanup_site=static function(): void {
foreach(['verifact_daily_retention','verifact_process_job','verifact_queue_worker','verifact_queue_action_scheduler'] as $hook){wp_clear_scheduled_hook($hook);}
$options=['verifact_api_base','verifact_api_key','verifact_public_enabled','verifact_require_login','verifact_user_permissions','verifact_rate_limit','verifact_cache_duration','verifact_log_client_metadata','verifact_retention_days','verifact_db_version','verifact_gate_enabled','verifact_gate_post_types','verifact_gate_min_confidence','verifact_connection_profiles','verifact_active_profile','verifact_allowed_api_hosts'];
foreach($options as $option){delete_option($option);}
foreach(['administrator','editor','author'] as $role_name){$role=get_role($role_name);if($role){foreach(['verifact_check_content','verifact_view_reports','verifact_manage'] as $cap){$role->remove_cap($cap);}}}
foreach(['verifact_job','verifact_evidence'] as $type){$ids=get_posts(['post_type'=>$type,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);foreach($ids as $id){wp_delete_post($id,true);}}
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}verifact_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}verifact_jobs");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_verifact_%' OR option_name LIKE '_transient_timeout_verifact_%'");
};
if (is_multisite()) { foreach (get_sites(['fields'=>'ids']) as $site_id) { switch_to_blog($site_id); $verifact_cleanup_site(); restore_current_blog(); } } else { $verifact_cleanup_site(); }
