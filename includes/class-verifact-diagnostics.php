<?php
if (!defined('ABSPATH')) { exit; }
final class VeriFact_Diagnostics {
    private VeriFact_Plugin $core;
    public function __construct(VeriFact_Plugin $core) { $this->core=$core;add_filter('site_status_tests',[$this,'tests']);add_filter('debug_information',[$this,'debug_info']); }
    public function tests(array $tests): array {
        $tests['direct']['verifact_api']=['label'=>'VeriFact API connection','test'=>[$this,'api_test']];
        $tests['direct']['verifact_database']=['label'=>'VeriFact database','test'=>[$this,'database_test']];
        $tests['direct']['verifact_scheduler']=['label'=>'VeriFact scheduler','test'=>[$this,'scheduler_test']];return $tests;
    }
    private function result(string $label,string $status,string $description): array { return ['label'=>$label,'status'=>$status,'badge'=>['label'=>'VeriFact','color'=>'blue'],'description'=>'<p>'.esc_html($description).'</p>','actions'=>'','test'=>'verifact_'.sanitize_key($label)]; }
    public function api_test(): array { $status=$this->core->connection_report();return $this->result('Authenticated API connection',!empty($status['connected'])?'good':'critical',!empty($status['connected'])?'The API URL, credentials, verify scope, capabilities, and contract version are compatible.':(string)($status['message']??'The authenticated API connection failed.')); }
    public function database_test(): array { global $wpdb;$table=$wpdb->prefix.'verifact_logs';$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table;return $this->result('Database table',$exists?'good':'critical',$exists?'The verification history table exists.':'The verification history table is missing. Reactivate the plugin to repair it.'); }
    public function scheduler_test(): array { $retention=wp_next_scheduled('verifact_daily_retention');$cron=wp_next_scheduled('verifact_queue_worker');$actions=function_exists('as_has_scheduled_action')&&as_has_scheduled_action('verifact_queue_action_scheduler');$ready=$retention&&($cron||$actions);return $this->result('Durable scheduling',$ready?'good':'recommended',$ready?'Retention and a durable queue runner are scheduled.':'Configure Action Scheduler, WP-Cron, or system cron using wp verifact queue run.'); }
    public function debug_info(array $info): array { $status=$this->core->connection_report();$stats=$this->core->dashboard_stats();$info['verifact']=['label'=>'VeriFact','fields'=>['version'=>['label'=>'Version','value'=>VeriFact_Plugin::VERSION],'api'=>['label'=>'API health','value'=>!empty($status['connected'])?'connected':'unavailable'],'checks'=>['label'=>'Total checks','value'=>(string)$stats['total_checks']],'retention'=>['label'=>'Retention days','value'=>(string)get_option('verifact_retention_days',90)]]];return $info; }
}