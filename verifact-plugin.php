<?php
/**
 * Plugin Name: VeriFact Checker
 * Description: Evidence-backed fact checking for WordPress through a private VeriFact API.
 * Version: 3.4.0
 * Author: Veracity Integrity
 * License: MIT
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain: verifact
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/includes/class-verifact-editor.php';
require_once __DIR__.'/includes/class-verifact-privacy.php';
require_once __DIR__.'/includes/class-verifact-diagnostics.php';
require_once __DIR__.'/includes/class-verifact-queue.php';
require_once __DIR__.'/includes/class-verifact-compatibility.php';
require_once __DIR__.'/includes/class-verifact-workflow.php';
require_once __DIR__.'/includes/class-verifact-evidence.php';
require_once __DIR__.'/includes/class-verifact-enterprise.php';
require_once __DIR__.'/includes/class-verifact-support.php';
require_once __DIR__.'/includes/class-verifact-bulk.php';
require_once __DIR__.'/includes/class-verifact-platform.php';
require_once __DIR__.'/includes/class-verifact-subscription.php';

final class VeriFact_Plugin {
    public const VERSION='3.4.0';
    private const DB_VERSION='3.4.0';
    private const API_BASE='verifact_api_base';
    private const PUBLIC_ACCESS='verifact_public_enabled';
    private const REQUIRE_LOGIN='verifact_require_login';
    private const ROLES='verifact_user_permissions';
    private const RATE_LIMIT='verifact_rate_limit';
    private const CACHE_SECONDS='verifact_cache_duration';
    private const LOG_METADATA='verifact_log_client_metadata';
    private const ADMIN_NONCE='verifact_admin_nonce';

    public function __construct() {
        add_action('plugins_loaded',[$this,'maybe_upgrade']);
        add_action('wp_initialize_site',[$this,'initialize_site']);
        add_action('admin_menu',[$this,'add_admin_menu']);
        add_action('admin_init',[$this,'register_settings']);
        add_action('admin_enqueue_scripts',[$this,'admin_assets']);
        add_action('wp_enqueue_scripts',[$this,'public_assets']);
        add_action('rest_api_init',[$this,'register_rest']);
        add_shortcode('verifact',[$this,'shortcode']);
        foreach (['get_stats','clear_cache','test_api','get_log'] as $action) {
            add_action('wp_ajax_verifact_'.$action,[$this,'ajax_'.$action]);
        }
    }

    private static function install_site(): void {
        global $wpdb;
        $table=$wpdb->prefix.'verifact_logs';
        $charset=$wpdb->get_charset_collate();
        $sql="CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            claim text NOT NULL,
            stance varchar(32) NOT NULL DEFAULT 'insufficient_evidence',
            confidence decimal(5,4) NOT NULL DEFAULT 0,
            evidence_count int(10) unsigned NOT NULL DEFAULT 0,
            runtime_sec decimal(8,3) NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_address varchar(64) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY created_at (created_at), KEY stance (stance), KEY user_id (user_id)
        ) {$charset};";
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        VeriFact_Queue::activate();
        VeriFact_Editor::activate(false);
        update_option('verifact_db_version',self::DB_VERSION,false);
    }

    public static function activate(bool $network_wide=false): void {
        if (is_multisite() && $network_wide) {
            foreach (get_sites(['fields'=>'ids']) as $site_id) { switch_to_blog($site_id); self::install_site(); restore_current_blog(); }
            return;
        }
        self::install_site();
    }

    public function initialize_site(WP_Site $site): void {
        $network_plugins=(array)get_site_option('active_sitewide_plugins',[]); if (!isset($network_plugins[plugin_basename(__FILE__)])) { return; }
        switch_to_blog((int)$site->blog_id); self::install_site(); restore_current_blog();
    }

    public function maybe_upgrade(): void {
        if (get_option('verifact_db_version')!==self::DB_VERSION) { self::activate(); }
    }

    public function register_settings(): void {
        register_setting('verifact_settings',self::API_BASE,['type'=>'string','sanitize_callback'=>'esc_url_raw','default'=>'']);
        register_setting('verifact_settings',self::PUBLIC_ACCESS,['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>false]);
        register_setting('verifact_settings',self::REQUIRE_LOGIN,['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>true]);
        register_setting('verifact_settings',self::ROLES,['type'=>'array','sanitize_callback'=>[$this,'sanitize_roles'],'default'=>['administrator','editor']]);
        register_setting('verifact_settings',self::RATE_LIMIT,['type'=>'integer','sanitize_callback'=>[$this,'sanitize_limit'],'default'=>60]);
        register_setting('verifact_settings',self::CACHE_SECONDS,['type'=>'integer','sanitize_callback'=>[$this,'sanitize_limit'],'default'=>900]);
        register_setting('verifact_settings',self::LOG_METADATA,['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>false]);
    }

    public function sanitize_limit($value): int { return max(0,min(86400,absint($value))); }
    public function sanitize_roles($roles): array {
        $roles=is_array($roles)?array_map('sanitize_key',$roles):[];
        return array_values(array_intersect($roles,array_keys(wp_roles()->roles)));
    }

    public function add_admin_menu(): void {
        add_menu_page('VeriFact','VeriFact','manage_options','verifact-dashboard',[$this,'dashboard_page'],'dashicons-search',30);
        $pages=['dashboard'=>'Dashboard','checking'=>'Fact Checking','analytics'=>'Analytics','history'=>'History','bulk'=>'Bulk Tools','api'=>'API Management','settings'=>'Settings','users'=>'Access'];
        foreach ($pages as $slug=>$title) {
            add_submenu_page('verifact-dashboard',$title,$title,'manage_options','verifact-'.$slug,[$this,str_replace('-','_',$slug).'_page']);
        }
    }

    public function admin_assets(string $hook): void {
        if (strpos($hook,'verifact')===false) { return; }
        wp_enqueue_style('verifact-admin',plugins_url('assets/verifact-admin.css',__FILE__),[],self::VERSION);
        wp_enqueue_style('verifact-graphics',plugins_url('assets/verifact-graphics.css',__FILE__),['verifact-admin'],self::VERSION);
        wp_enqueue_script('verifact-admin',plugins_url('assets/verifact-admin.js',__FILE__),['jquery'],self::VERSION,true);
        wp_localize_script('verifact-admin','verifactAdmin',['ajaxurl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce(self::ADMIN_NONCE)]);
    }

    public function public_assets(): void {
        wp_register_style('verifact',plugins_url('assets/verifact.css',__FILE__),[],self::VERSION);
        wp_register_script('verifact',plugins_url('assets/verifact.js',__FILE__),[],self::VERSION,true);
        wp_localize_script('verifact','VeriFactCfg',['restUrl'=>esc_url_raw(rest_url('verifact/v1/check')),'nonce'=>wp_create_nonce('wp_rest')]);
    }

    public function shortcode(): string {
        wp_enqueue_style('verifact'); wp_enqueue_script('verifact');
        return '<div class="verifact-root" data-verifact-root></div>';
    }

    public function register_rest(): void {
        register_rest_route('verifact/v1','/check',[
            'methods'=>WP_REST_Server::CREATABLE,'callback'=>[$this,'handle_check'],'permission_callback'=>[$this,'check_permissions'],
            'args'=>[
                'claim'=>['type'=>'string','sanitize_callback'=>'sanitize_textarea_field'],
                'prompt'=>['type'=>'string','sanitize_callback'=>'sanitize_textarea_field'],
                'answer'=>['type'=>'string','sanitize_callback'=>'sanitize_textarea_field'],
            ],
        ]);
    }

    public function check_permissions() {
        if ((bool)get_option(self::PUBLIC_ACCESS,false)) { return true; }
        if ((bool)get_option(self::REQUIRE_LOGIN,true) && !is_user_logged_in()) {
            return new WP_Error('verifact_login_required','Authentication required.',['status'=>401]);
        }
        $allowed=(array)get_option(self::ROLES,['administrator','editor']);
        return array_intersect(wp_get_current_user()->roles,$allowed)?true:new WP_Error('verifact_forbidden','Permission denied.',['status'=>403]);
    }

    public function handle_check(WP_REST_Request $request) {
        $payload=array_filter([
            'claim'=>$request->get_param('claim'),'prompt'=>$request->get_param('prompt'),'answer'=>$request->get_param('answer')
        ],static fn($v)=>is_string($v)&&trim($v)!=='');
        if (!$payload || (!isset($payload['claim']) && !(isset($payload['prompt'],$payload['answer'])))) {
            return new WP_Error('verifact_bad_request','Provide a claim or both prompt and answer.',['status'=>400]);
        }
        foreach ($payload as $value) {
            if (mb_strlen($value)>10000) { return new WP_Error('verifact_too_large','Inputs must be 10,000 characters or fewer.',['status'=>413]); }
        }
        if (!$this->consume_rate_limit()) { return new WP_Error('verifact_rate_limited','Hourly limit reached.',['status'=>429]); }
        $cache_key='verifact_result_'.hash('sha256',wp_json_encode($payload));
        $cached=get_transient($cache_key);
        if (is_array($cached)) { $cached['meta']['cache']='hit'; return rest_ensure_response($cached); }
        $result=$this->api_request('/api/v1/check',$payload);
        if (is_wp_error($result)) { return $result; }
        $ttl=(int)get_option(self::CACHE_SECONDS,900);
        if ($ttl>0) { set_transient($cache_key,$result,$ttl); }
        $this->log_result($payload,$result,$request);
        return rest_ensure_response($result);
    }

    public function verify_content(string $content) {
        $content=sanitize_textarea_field($content);
        if ($content==='' || mb_strlen($content)>100000) {
            return new WP_Error('verifact_invalid_content','Content must be between 1 and 100,000 characters.');
        }
        $cache_key='verifact_result_'.hash('sha256',$content);
        $cached=get_transient($cache_key);
        if (is_array($cached)) { return $cached; }
        $result=$this->api_request('/api/v1/check',['content'=>$content,'max_claims'=>50]);
        if (!is_wp_error($result)) {
            $ttl=(int)get_option(self::CACHE_SECONDS,900);
            if ($ttl>0) { set_transient($cache_key,$result,$ttl); }
        }
        return $result;
    }
    public function verify_claim(string $claim,string $project_id='default') { return $this->api_request('/api/v1/check',['claim'=>sanitize_textarea_field($claim),'project_id'=>sanitize_key($project_id)?:'default']); }
    public function extract_claims(string $content,int $max_claims=50) {
        $result=$this->api_request('/api/v1/claims/extract',['content'=>sanitize_textarea_field($content),'max_claims'=>max(1,min(50,$max_claims))]);
        return is_wp_error($result)?$result:array_values(array_filter(array_map('strval',(array)($result['claims']??[]))));
    }

    public function api_status(): array {
        $result=$this->api_call('GET','/health');
        return is_wp_error($result)?['healthy'=>false,'message'=>$result->get_error_message()]:['healthy'=>($result['status']??'')==='ok','version'=>(string)($result['version']??''),'runtime_ms'=>(float)($result['_client_runtime_ms']??0)];
    }

    public function connection_report(bool $force=false): array {
        if($force){delete_transient('verifact_connection_report');delete_transient('verifact_api_circuit');}
        $cached=get_transient('verifact_connection_report');if(!$force&&is_array($cached)){return $cached;}
        $base=$this->api_base();if(!$base){return ['connected'=>false,'configured'=>false,'message'=>__('API URL is not configured.','verifact')];}
        $capabilities=$this->api_call('GET','/api/v1/capabilities');
        $identity=$this->api_call('GET','/api/v1/auth/whoami');
        $test=$this->api_call('GET','/api/v1/connection/test');
        $errors=[];foreach([$capabilities,$identity,$test] as $item){if(is_wp_error($item)){$errors[]=$item->get_error_message();}}
        $features=is_array($capabilities)?(array)($capabilities['features']??[]):[];$scopes=is_array($identity)?(array)($identity['scopes']??[]):[];
        $compatible=is_array($capabilities)&&($capabilities['api_version']??'')==='v1'&&version_compare(self::VERSION,(string)($capabilities['minimum_client_version']??'0.0.0'),'>=');
        $required_features=['authenticated_handshake','bulk_jobs','provenance_manifest','claim_registry','review_cases','signed_receipts','policy_packs','integration_conformance'];$feature_ready=!array_diff($required_features,$features);
        $authenticated=is_array($identity)&&!empty($identity['authenticated'])&&(in_array('verify',$scopes,true)||in_array('admin',$scopes,true));
        $report=['connected'=>!$errors&&$compatible&&$feature_ready&&$authenticated&&!empty($test['connected']),'configured'=>true,'authenticated'=>$authenticated,'compatible'=>$compatible,'server_version'=>(string)($capabilities['server_version']??''),'api_version'=>(string)($capabilities['api_version']??''),'contract_version'=>(string)($capabilities['contract_version']??''),'auth_type'=>(string)($identity['auth_type']??''),'key_id'=>(string)($identity['key_id']??''),'tenant_id'=>(string)($identity['tenant_id']??''),'scopes'=>array_values(array_map('sanitize_key',$scopes)),'features'=>array_values(array_map('sanitize_key',$features)),'region'=>(string)($test['region']??''),'message'=>$errors?implode(' ',array_unique($errors)):__('Authenticated VeriFact API connection verified.','verifact'),'checked_at'=>current_time('mysql',true)];
        set_transient('verifact_connection_report',$report,5*MINUTE_IN_SECONDS);return $report;
    }

    public function dashboard_stats(): array { return $this->stats(); }
    public function api_request(string $path,array $payload) { return $this->api_call('POST',$path,$payload); }
    public function api_get(string $path) { return $this->api_call('GET',$path); }
    public function api_delete(string $path) { return $this->api_call('DELETE',$path); }
    public function api_public_request(string $path,array $payload) { return $this->api_call('POST',$path,$payload,false); }
    public function api_public_get(string $path) { return $this->api_call('GET',$path,[],false); }
    public function api_base_url(): string { return $this->api_base(); }

    private function api_call(string $method,string $path,array $payload=[],bool $authenticate=true) {
        $base=$this->api_base();if(!$base){return new WP_Error('verifact_not_configured',__('VeriFact API URL is not configured.','verifact'),['status'=>503]);}
        $circuit=(int)get_transient('verifact_api_circuit');if($circuit>time()){return new WP_Error('verifact_circuit_open',__('VeriFact API is temporarily paused after repeated connection failures.','verifact'),['status'=>503,'retry_after'=>$circuit-time()]);}
        $headers=['Content-Type'=>'application/json','Accept'=>'application/json','X-Request-ID'=>wp_generate_uuid4(),'VeriFact-Client-Version'=>self::VERSION];
        if($authenticate){$bearer=(string)apply_filters('verifact_api_bearer_token','');if($bearer!==''){$headers['Authorization']='Bearer '.$bearer;}else{$key=$this->api_key();if($key!==''){$headers['X-API-Key']=$key;}}}
        $url=$base.'/'.ltrim($path,'/');$started=microtime(true);$last_status=0;$last_error='';
        for($attempt=0;$attempt<3;$attempt++){
            $args=['timeout'=>45,'redirection'=>0,'headers'=>$headers];if($method==='POST'){$args['body']=wp_json_encode($payload);$response=wp_safe_remote_post($url,$args);}elseif($method==='DELETE'){$args['method']='DELETE';$response=wp_safe_remote_request($url,$args);}else{$response=wp_safe_remote_get($url,$args);}
            if(is_wp_error($response)){$last_error=$response->get_error_message();$last_status=502;}else{$last_status=(int)wp_remote_retrieve_response_code($response);$data=json_decode(wp_remote_retrieve_body($response),true);if($last_status>=200&&$last_status<300&&is_array($data)){$data['_client_runtime_ms']=round((microtime(true)-$started)*1000,1);delete_transient('verifact_api_circuit');if($path==='/api/v1/check'&&!$this->verify_provenance($data)){return new WP_Error('verifact_provenance_mismatch',__('The API provenance manifest did not match the response.','verifact'),['status'=>502]);}return $data;}$last_error=is_array($data)?sanitize_text_field((string)($data['detail']??$data['error']['message']??'')):'';}
            if(!in_array($last_status,[429,502,503,504],true)){break;}if($attempt<2){usleep((int)(100000*(2**$attempt)));}
        }
        if(in_array($last_status,[429,502,503,504],true)){set_transient('verifact_api_circuit',time()+30,30);}
        $message=$last_status===401?__('API authentication failed. Check the server-side Vault mapping.','verifact'):($last_status===403?__('The API key does not have the required scope.','verifact'):__('VeriFact API request failed.','verifact'));
        return new WP_Error('verifact_upstream_error',$message,['status'=>$last_status?:502,'upstream_message'=>mb_substr($last_error,0,200)]);
    }

    private function verify_provenance(array $response): bool {
        $expected=(string)($response['meta']['provenance_sha256']??'');if(!preg_match('/^[a-f0-9]{64}$/',$expected)){return false;}$manifest=[];
        foreach((array)($response['results']??[]) as $result){$evidence=[];foreach((array)($result['evidence']??[]) as $item){$evidence[]=['url'=>(string)($item['url']??''),'content_hash'=>(string)($item['content_hash']??''),'retrieved_at'=>(string)($item['retrieved_at']??'')];}$manifest[]=['claim'=>(string)($result['claim']??''),'stance'=>(string)($result['stance']??''),'confidence'=>(float)($result['confidence']??0),'evidence'=>$evidence];}
        $sort=static function(&$value)use(&$sort):void{if(!is_array($value)){return;}if($value===[]||array_keys($value)===range(0,count($value)-1)){foreach($value as &$item){$sort($item);}unset($item);return;}ksort($value);foreach($value as &$item){$sort($item);}unset($item);};$sort($manifest);
        $actual=hash('sha256',wp_json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));return hash_equals($expected,$actual);
    }

    private function api_base(): string {
        if (defined('VERIFACT_API_BASE_URL') && VERIFACT_API_BASE_URL) { return untrailingslashit(esc_url_raw(VERIFACT_API_BASE_URL)); }
        $env=getenv('VERIFACT_API_BASE_URL');
        $url=untrailingslashit($env?esc_url_raw($env):(string)get_option(self::API_BASE,''));
        return (string)apply_filters('verifact_api_base_url',$url);
    }

    private function api_key(): string {
        if (defined('VERIFACT_API_KEY') && is_string(VERIFACT_API_KEY)) { return VERIFACT_API_KEY; }
        $value=getenv('VERIFACT_API_KEY'); return is_string($value)?$value:'';
    }

    private function consume_rate_limit(): bool {
        $limit=(int)get_option(self::RATE_LIMIT,60); if ($limit===0) { return true; }
        $identity=get_current_user_id() ?: hash('sha256',$this->client_ip().wp_salt('nonce'));
        $key='verifact_rate_'.hash('sha256',(string)$identity);
        $count=(int)get_transient($key); if ($count>=$limit) { return false; }
        set_transient($key,$count+1,HOUR_IN_SECONDS); return true;
    }

    private function log_result(array $request,array $response,WP_REST_Request $rest): void {
        global $wpdb; $first=$response['results'][0]??[]; $metadata=(bool)get_option(self::LOG_METADATA,false);
        $wpdb->insert($wpdb->prefix.'verifact_logs',[
            'claim'=>mb_substr((string)($request['claim']??$request['answer']??''),0,10000),
            'stance'=>sanitize_key((string)($first['stance']??'insufficient_evidence')),
            'confidence'=>min(1,max(0,(float)($first['confidence']??0))),
            'evidence_count'=>count($first['evidence']??[]),'runtime_sec'=>max(0,(float)($response['meta']['runtime_sec']??0)),
            'user_id'=>get_current_user_id()?:null,
            'ip_address'=>$metadata?hash('sha256',$this->client_ip().wp_salt('auth')):null,
            'user_agent'=>$metadata?mb_substr(sanitize_text_field($rest->get_header('user_agent')),0,255):null,
            'created_at'=>current_time('mysql',true),
        ]);
    }

    private function client_ip(): string { return isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):'unknown'; }
    private function stats(): array {
        global $wpdb; $table=$wpdb->prefix.'verifact_logs';
        return ['total_checks'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'supported'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE stance=%s",'supported')),
            'refuted'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE stance=%s",'refuted')),
            'average_runtime'=>(float)$wpdb->get_var("SELECT AVG(runtime_sec) FROM {$table}")];
    }

    private function require_admin_ajax(): void {
        check_ajax_referer(self::ADMIN_NONCE,'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'Forbidden'],403); }
    }
    public function ajax_get_stats(): void { $this->require_admin_ajax(); wp_send_json_success($this->stats()); }
    public function ajax_clear_cache(): void {
        $this->require_admin_ajax(); global $wpdb;
        $one=$wpdb->esc_like('_transient_verifact_result_').'%'; $two=$wpdb->esc_like('_transient_timeout_verifact_result_').'%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",$one,$two));
        wp_send_json_success(['message'=>'Cache cleared.']);
    }
    public function ajax_test_api(): void {
        $this->require_admin_ajax();$report=$this->connection_report(true);
        $report['connected']?wp_send_json_success($report):wp_send_json_error($report,502);
    }
    public function ajax_get_log(): void {
        $this->require_admin_ajax(); global $wpdb; $id=absint($_POST['id']??0);
        $row=$wpdb->get_row($wpdb->prepare("SELECT id,claim,stance,confidence,evidence_count,runtime_sec,user_id,created_at FROM {$wpdb->prefix}verifact_logs WHERE id=%d",$id),ARRAY_A);
        $row?wp_send_json_success($row):wp_send_json_error(['message'=>'Not found.'],404);
    }

    private function start(string $title): void { echo '<div class="wrap verifact-admin"><h1>'.esc_html($title).'</h1>'; }
    private function end(): void { echo '</div>'; }
    public function dashboard_page(): void {
        $this->start('VeriFact Dashboard'); echo '<div class="verifact-stat-grid">';
        foreach ($this->stats() as $label=>$value) { echo '<div class="verifact-stat-card"><strong>'.esc_html((string)round($value,3)).'</strong><span>'.esc_html(ucwords(str_replace('_',' ',$label))).'</span></div>'; }
        echo '</div><p>Version '.esc_html(self::VERSION).'. Credentials are injected by the server and never displayed.</p>'; $this->end();
    }
    public function checking_page(): void { $this->start('Fact Checking'); echo '<p>Add <code>[verifact]</code> to a page or post.</p>'.do_shortcode('[verifact]'); $this->end(); }
    public function analytics_page(): void { $this->start('Analytics'); echo '<p>Outcome totals are available on the dashboard. Detailed entries are retained in History.</p>'; $this->end(); }
    public function history_page(): void {
        global $wpdb; $rows=$wpdb->get_results("SELECT id,claim,stance,confidence,created_at FROM {$wpdb->prefix}verifact_logs ORDER BY id DESC LIMIT 50",ARRAY_A);
        $this->start('Verification History'); echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Claim</th><th>Stance</th><th>Confidence</th><th>UTC</th></tr></thead><tbody>';
        foreach ($rows as $row) { echo '<tr><td>'.esc_html($row['id']).'</td><td>'.esc_html($row['claim']).'</td><td>'.esc_html($row['stance']).'</td><td>'.esc_html($row['confidence']).'</td><td>'.esc_html($row['created_at']).'</td></tr>'; }
        if (!$rows) { echo '<tr><td colspan="5">No history yet.</td></tr>'; } echo '</tbody></table>'; $this->end();
    }
    public function bulk_page(): void { $this->start('Bulk Tools'); echo '<p>'.esc_html__('Queue up to 100 posts from the Posts bulk-actions menu, the bulk REST endpoint, or WP-CLI: wp verifact bulk queue.','verifact').'</p><p>'.esc_html__('The API also supports asynchronous batch jobs at /api/v1/jobs/batch.','verifact').'</p>'; $this->end(); }
    public function api_page(): void {
        $report=$this->connection_report();$this->start('API Management');echo '<p><strong>'.esc_html__('Connection:','verifact').'</strong> '.esc_html(!empty($report['connected'])?__('Connected and authenticated','verifact'):__('Not connected','verifact')).'</p><p>'.esc_html((string)($report['message']??'')).'</p><p>'.esc_html__('Server version:','verifact').' <code>'.esc_html((string)($report['server_version']??'unknown')).'</code> &nbsp; '.esc_html__('Contract:','verifact').' <code>'.esc_html((string)($report['contract_version']??'unknown')).'</code></p><p>'.esc_html__('Authentication:','verifact').' <code>'.esc_html((string)($report['auth_type']??'unknown')).'</code> &nbsp; '.esc_html__('Key ID:','verifact').' <code>'.esc_html((string)($report['key_id']??'not-disclosed')).'</code></p><p>Endpoint: <code>'.esc_html($this->api_base()?:'Not configured').'</code></p><p>Secret mapping: <code>VERIFACT_API_KEY</code> from OpenClaw Vault or an approved secret store.</p><button class="button" type="button" data-verifact-test-api>'.esc_html__('Test authenticated connection','verifact').'</button> <span aria-live="polite" data-verifact-api-result></span>';$this->end();
    }    public function settings_page(): void {
        $this->start('VeriFact Settings'); ?>
        <form method="post" action="options.php"><?php settings_fields('verifact_settings'); ?>
        <table class="form-table"><tr><th><label for="verifact_api_base">API base URL</label></th><td><input class="regular-text" id="verifact_api_base" name="verifact_api_base" type="url" value="<?php echo esc_attr((string)get_option(self::API_BASE,'')); ?>"><p class="description">Ignored when VERIFACT_API_BASE_URL is injected by the server.</p></td></tr>
        <tr><th>Public access</th><td><label><input name="verifact_public_enabled" type="checkbox" value="1" <?php checked(get_option(self::PUBLIC_ACCESS,false)); ?>> Permit anonymous checks</label></td></tr>
        <tr><th>Require login</th><td><label><input name="verifact_require_login" type="checkbox" value="1" <?php checked(get_option(self::REQUIRE_LOGIN,true)); ?>> Require authentication</label></td></tr>
        <tr><th>Hourly limit</th><td><input name="verifact_rate_limit" type="number" min="0" max="86400" value="<?php echo esc_attr((string)get_option(self::RATE_LIMIT,60)); ?>"></td></tr>
        <tr><th>Cache seconds</th><td><input name="verifact_cache_duration" type="number" min="0" max="86400" value="<?php echo esc_attr((string)get_option(self::CACHE_SECONDS,900)); ?>"></td></tr>
        <tr><th>Client metadata</th><td><label><input name="verifact_log_client_metadata" type="checkbox" value="1" <?php checked(get_option(self::LOG_METADATA,false)); ?>> Store hashed IP and truncated user agent</label></td></tr>
        <tr><th>History retention</th><td><input name="verifact_retention_days" type="number" min="0" max="3650" value="<?php echo esc_attr((string)get_option('verifact_retention_days',90)); ?>"> days <p class="description">Use 0 to retain history until manually deleted.</p></td></tr>
        <tr><th>Publication gate</th><td><label><input name="verifact_gate_enabled" type="checkbox" value="1" <?php checked(get_option('verifact_gate_enabled',false)); ?>> Hold unreviewed or risky content for editorial approval</label><p><?php $gate_types=(array)get_option('verifact_gate_post_types',['post']);foreach(apply_filters('verifact_supported_post_types',[]) as $type): ?><label style="margin-right:12px"><input name="verifact_gate_post_types[]" type="checkbox" value="<?php echo esc_attr($type); ?>" <?php checked(in_array($type,$gate_types,true)); ?>> <?php echo esc_html(get_post_type_object($type)->labels->singular_name); ?></label><?php endforeach; ?></p></td></tr>
        <tr><th>Gate confidence</th><td><input name="verifact_gate_min_confidence" type="number" min="0" max="1" step="0.05" value="<?php echo esc_attr((string)get_option('verifact_gate_min_confidence',0.6)); ?>"></td></tr>
        <tr><th>Allowed API hosts</th><td><textarea name="verifact_allowed_api_hosts[]" rows="4" class="large-text code"><?php echo esc_textarea(implode("\n",(array)get_option('verifact_allowed_api_hosts',[]))); ?></textarea><p class="description">One hostname per line. Leave empty only when the host is enforced with VERIFACT_ALLOWED_API_HOSTS.</p></td></tr>
        </table><?php submit_button(); ?></form>
        <?php $this->end();
    }
    public function users_page(): void {
        $selected=(array)get_option(self::ROLES,['administrator','editor']); $this->start('VeriFact Access'); ?>
        <form method="post" action="options.php"><?php settings_fields('verifact_settings'); ?>
        <?php foreach (wp_roles()->roles as $key=>$role): ?><label style="display:block;margin:8px"><input type="checkbox" name="verifact_user_permissions[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key,$selected,true)); ?>> <?php echo esc_html($role['name']); ?></label><?php endforeach; ?>
        <?php submit_button('Save access rules'); ?></form><?php $this->end();
    }
}
register_activation_hook(__FILE__,['VeriFact_Plugin','activate']);
$GLOBALS['verifact_plugin']=new VeriFact_Plugin();
new VeriFact_Editor($GLOBALS['verifact_plugin']);
new VeriFact_Privacy();
new VeriFact_Diagnostics($GLOBALS['verifact_plugin']);$GLOBALS['verifact_queue']=new VeriFact_Queue($GLOBALS['verifact_plugin']);
new VeriFact_Compatibility($GLOBALS['verifact_queue']);
new VeriFact_Workflow();
new VeriFact_Evidence();
$GLOBALS['verifact_enterprise']=new VeriFact_Enterprise();
new VeriFact_Support($GLOBALS['verifact_plugin'],$GLOBALS['verifact_queue'],$GLOBALS['verifact_enterprise']);
new VeriFact_Bulk($GLOBALS['verifact_queue']);
new VeriFact_Platform($GLOBALS['verifact_plugin']);
new VeriFact_Subscription($GLOBALS['verifact_plugin']);
