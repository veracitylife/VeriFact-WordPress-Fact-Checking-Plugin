<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Enterprise {
    public function __construct() { add_action('admin_init',[$this,'settings']);add_filter('verifact_api_base_url',[$this,'select_profile']); }
    public function settings(): void {
        register_setting('verifact_settings','verifact_connection_profiles',['type'=>'array','sanitize_callback'=>[$this,'sanitize_profiles'],'default'=>[]]);
        register_setting('verifact_settings','verifact_active_profile',['type'=>'string','sanitize_callback'=>'sanitize_key','default'=>'']);
        register_setting('verifact_settings','verifact_allowed_api_hosts',['type'=>'array','sanitize_callback'=>[$this,'sanitize_hosts'],'default'=>[]]);
    }
    public function sanitize_hosts($value): array { $hosts=[];foreach((array)$value as $group){foreach(preg_split('/[\r\n,]+/',(string)$group) as $host){$host=sanitize_text_field(strtolower(trim($host)));if($host&&preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',$host)){$hosts[]=$host;}}}return array_values(array_unique($hosts)); }
    public function sanitize_profiles($profiles): array { $clean=[];foreach((array)$profiles as $id=>$profile){$url=esc_url_raw((string)($profile['url']??''));if($url&&wp_http_validate_url($url)){$clean[sanitize_key($id)]=['name'=>sanitize_text_field((string)($profile['name']??$id)),'url'=>untrailingslashit($url),'environment'=>sanitize_key((string)($profile['environment']??'production'))];}}return $clean; }
    public function select_profile(string $current): string { $profiles=defined('VERIFACT_CONNECTION_PROFILES')&&is_array(VERIFACT_CONNECTION_PROFILES)?VERIFACT_CONNECTION_PROFILES:(array)get_option('verifact_connection_profiles',[]);$active=defined('VERIFACT_ACTIVE_PROFILE')?(string)VERIFACT_ACTIVE_PROFILE:(string)get_option('verifact_active_profile','');$candidate=(string)($profiles[$active]['url']??$current);$host=strtolower((string)wp_parse_url($candidate,PHP_URL_HOST));$allowed=defined('VERIFACT_ALLOWED_API_HOSTS')?(array)VERIFACT_ALLOWED_API_HOSTS:(array)get_option('verifact_allowed_api_hosts',[]);if($allowed&&!in_array($host,array_map('strtolower',$allowed),true)){return '';}return wp_http_validate_url($candidate)?untrailingslashit(esc_url_raw($candidate)):''; }
    public function status(): array { $rotated=getenv('VERIFACT_API_KEY_ROTATED_AT')?:'';$age=$rotated?max(0,(int)floor((time()-strtotime($rotated))/DAY_IN_SECONDS)):null;return ['profile'=>defined('VERIFACT_ACTIVE_PROFILE')?VERIFACT_ACTIVE_PROFILE:get_option('verifact_active_profile','default'),'key_id'=>getenv('VERIFACT_API_KEY_ID')?:'not-disclosed','key_age_days'=>$age,'rotation_status'=>$age===null?'unknown':($age>90?'rotate':'current'),'allowed_hosts_count'=>count((array)get_option('verifact_allowed_api_hosts',[]))]; }
}
