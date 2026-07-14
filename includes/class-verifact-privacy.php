<?php
if (!defined('ABSPATH')) { exit; }
final class VeriFact_Privacy {
    private const RETENTION='verifact_retention_days';
    public function __construct() {
        add_action('admin_init',[$this,'settings']);
        add_action('verifact_daily_retention',[$this,'purge_expired']);
        add_action('init',[$this,'schedule']);
        add_filter('wp_privacy_personal_data_exporters',[$this,'exporters']);
        add_filter('wp_privacy_personal_data_erasers',[$this,'erasers']);
        add_action('rest_api_init',[$this,'routes']);
    }
    public function settings(): void { register_setting('verifact_settings',self::RETENTION,['type'=>'integer','sanitize_callback'=>fn($v)=>max(0,min(3650,absint($v))),'default'=>90]); }
    public function schedule(): void { if(!wp_next_scheduled('verifact_daily_retention')){wp_schedule_event(time()+HOUR_IN_SECONDS,'daily','verifact_daily_retention');} }
    public function purge_expired(): int {
        global $wpdb;$days=(int)get_option(self::RETENTION,90);if($days===0){return 0;}
        $cutoff=gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS);
        return (int)$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}verifact_logs WHERE created_at < %s",$cutoff));
    }
    public function exporters(array $items): array { $items['verifact']=['exporter_friendly_name'=>'VeriFact history','callback'=>[$this,'export_user']];return $items; }
    public function erasers(array $items): array { $items['verifact']=['eraser_friendly_name'=>'VeriFact history','callback'=>[$this,'erase_user']];return $items; }
    public function export_user(string $email,int $page=1): array {
        global $wpdb;$user=get_user_by('email',$email);if(!$user){return ['data'=>[],'done'=>true];}
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,claim,stance,confidence,created_at FROM {$wpdb->prefix}verifact_logs WHERE user_id=%d ORDER BY id LIMIT 100 OFFSET %d",$user->ID,max(0,$page-1)*100),ARRAY_A);$data=[];
        foreach($rows as $row){$data[]=['group_id'=>'verifact','group_label'=>'VeriFact history','item_id'=>'verifact-'.$row['id'],'data'=>array_map(fn($k,$v)=>['name'=>ucwords(str_replace('_',' ',$k)),'value'=>(string)$v],array_keys($row),$row)];}
        return ['data'=>$data,'done'=>count($rows)<100];
    }
    public function erase_user(string $email,int $page=1): array {
        global $wpdb;$user=get_user_by('email',$email);$removed=false;
        if($user){$removed=(bool)$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}verifact_logs WHERE user_id=%d LIMIT 500",$user->ID));}
        return ['items_removed'=>$removed,'items_retained'=>false,'messages'=>[],'done'=>true];
    }
    public function routes(): void {
        register_rest_route('verifact/v1','/history/(?P<id>\d+)',['methods'=>WP_REST_Server::DELETABLE,'callback'=>[$this,'delete_log'],'permission_callback'=>fn()=>current_user_can('verifact_manage')]);
    }
    public function delete_log(WP_REST_Request $request) {
        global $wpdb;$deleted=$wpdb->delete($wpdb->prefix.'verifact_logs',['id'=>absint($request['id'])],['%d']);
        return $deleted?['deleted'=>true]:new WP_Error('not_found','History entry not found.',['status'=>404]);
    }
}