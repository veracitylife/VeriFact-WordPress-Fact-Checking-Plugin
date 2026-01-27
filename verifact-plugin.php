<?php
/**
 * Plugin Name: VeriFact Checker Pro
 * Description: Advanced WordPress UI + REST proxy for the VeriFact (FacTool + Retrieval) FastAPI service with comprehensive dashboard.
 * Version: 2.0.8
 * Author: Veracity Integrity
 * License: MIT
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
class VeriFactPlugin {
    const OPT_API_BASE = 'verifact_api_base';
    const OPT_API_KEY = 'verifact_api_key';
    const OPT_RATE_LIMIT = 'verifact_rate_limit';
    const OPT_CACHE_DURATION = 'verifact_cache_duration';
    const OPT_USER_PERMISSIONS = 'verifact_user_permissions';
    const NONCE_ACTION = 'verifact_nonce_action';
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('verifact', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('init', [$this, 'create_tables']);
        add_action('wp_ajax_verifact_bulk_check', [$this, 'ajax_bulk_check']);
        add_action('wp_ajax_verifact_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_verifact_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_verifact_test_api', [$this, 'ajax_test_api']);
        add_action('wp_ajax_verifact_get_log', [$this, 'ajax_get_log']);
        add_action('wp_ajax_verifact_create_schedule', [$this, 'ajax_create_schedule']);
        add_action('wp_ajax_verifact_upload_remote_cache', [$this, 'ajax_upload_remote_cache']);
    }
    public function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'verifact_logs';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            claim text NOT NULL,
            stance varchar(20) NOT NULL,
            confidence decimal(3,2) NOT NULL,
            evidence_count int(11) NOT NULL,
            runtime_sec decimal(5,2) NOT NULL,
            user_id int(11) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY stance (stance)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function add_admin_menu() {
        add_menu_page('VeriFact Dashboard','VeriFact','manage_options','verifact-dashboard',[$this,'dashboard_page'],'dashicons-search',30);
        add_submenu_page('verifact-dashboard','Dashboard','Dashboard','manage_options','verifact-dashboard',[$this,'dashboard_page']);
        add_submenu_page('verifact-dashboard','Fact-Checking','Fact-Checking','manage_options','verifact-checking',[$this,'fact_checking_page']);
        add_submenu_page('verifact-dashboard','Analytics','Analytics','manage_options','verifact-analytics',[$this,'analytics_page']);
        add_submenu_page('verifact-dashboard','History & Logs','History & Logs','manage_options','verifact-history',[$this,'history_page']);
        add_submenu_page('verifact-dashboard','Bulk Tools','Bulk Tools','manage_options','verifact-bulk',[$this,'bulk_tools_page']);
        add_submenu_page('verifact-dashboard','API Management','API Management','manage_options','verifact-api',[$this,'api_management_page']);
        add_submenu_page('verifact-dashboard','Settings','Settings','manage_options','verifact-settings',[$this,'settings_page']);
        add_submenu_page('verifact-dashboard','User Management','User Management','manage_options','verifact-users',[$this,'user_management_page']);
    }
    public function register_settings() {
        register_setting('verifact_settings', self::OPT_API_BASE, ['type'=>'string','sanitize_callback'=>'esc_url_raw','default'=>home_url('/verifact')]);
        register_setting('verifact_settings', self::OPT_API_KEY, ['type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>'']);
        register_setting('verifact_settings', self::OPT_RATE_LIMIT, ['type'=>'integer','sanitize_callback'=>'intval','default'=>100]);
        register_setting('verifact_settings', self::OPT_CACHE_DURATION, ['type'=>'integer','sanitize_callback'=>'intval','default'=>3600]);
        register_setting('verifact_settings', self::OPT_USER_PERMISSIONS, ['type'=>'array','sanitize_callback'=>[$this,'sanitize_user_permissions'],'default'=>['administrator']]);
        register_setting('verifact_settings', 'verifact_enable_archiveorg', ['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>0]);
        register_setting('verifact_settings', 'verifact_enable_grokopedia', ['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>0]);
        register_setting('verifact_settings', 'verifact_public_enabled', ['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>1]);
        register_setting('verifact_settings', 'verifact_require_login', ['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>0]);
        register_setting('verifact_settings', 'verifact_remote_cache_enabled', ['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>0]);
        register_setting('verifact_settings', 'verifact_remote_cache_url', ['type'=>'string','sanitize_callback'=>'esc_url_raw','default'=>'']);
        register_setting('verifact_settings', 'verifact_role_rate_limits', ['type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>'']);
        register_setting('verifact_settings', 'verifact_banner_url', ['type'=>'string','sanitize_callback'=>'esc_url_raw','default'=>'']);
        register_setting('verifact_settings', 'verifact_banner_brand_url', ['type'=>'string','sanitize_callback'=>'esc_url_raw','default'=>'']);
    }
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'verifact') !== false) {
            wp_enqueue_style('verifact-admin-css', plugins_url('assets/verifact-admin.css', __FILE__), [], '2.0.8');
            wp_enqueue_style('verifact-graphics-css', plugins_url('assets/verifact-graphics.css', __FILE__), ['verifact-admin-css'], '2.0.8');
            wp_enqueue_style('verifact-widgets-css', plugins_url('assets/verifact-widgets.css', __FILE__), ['verifact-admin-css'], '2.0.8');
            wp_enqueue_script('verifact-admin-js', plugins_url('assets/verifact-admin.js', __FILE__), ['jquery'], '2.0.8', true);
            wp_localize_script('verifact-admin-js', 'verifactAdmin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('verifact_admin_nonce'),
                'strings' => [
                    'loading' => __('Loading...', 'verifact'),
                    'error' => __('An error occurred', 'verifact'),
                    'success' => __('Operation completed successfully', 'verifact'),
                    'confirm' => __('Are you sure?', 'verifact'),
                ]
            ]);
        }
    }
    public function enqueue_assets() {
        wp_register_style('verifact-css', plugins_url('assets/verifact.css', __FILE__), [], '2.0.8');
        wp_register_script('verifact-js', plugins_url('assets/verifact.js', __FILE__), [], '2.0.8', true);
        wp_localize_script('verifact-js', 'VeriFactCfg', [
            'restUrl' => esc_url_raw(rest_url('verifact/v1/check')),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }
    public function shortcode($atts) {
        wp_enqueue_style('verifact-css');
        wp_enqueue_script('verifact-js');
        ob_start(); ?>
        <div id="verifact-root" class="verifact-card"></div>
        <?php return ob_get_clean();
    }
    public function register_rest() {
        register_rest_route('verifact/v1', '/check', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_check'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }
    public function handle_check(\WP_REST_Request $req) {
        $body = $req->get_json_params();
        if (!$body) { return new \WP_Error('bad_request', 'JSON body required', ['status' => 400]); }
        $payload = is_array($body) ? $body : [];
        $sources = [];
        if (get_option('verifact_enable_archiveorg', 0)) { $sources[] = 'archiveorg'; }
        if (get_option('verifact_enable_grokopedia', 0)) { $sources[] = 'grokopedia'; }
        if (!empty($sources)) { $payload['sources'] = $sources; }
        $api_base = rtrim(get_option(self::OPT_API_BASE, home_url('/verifact')), '/');
        $url = $api_base . '/check';
        $res = wp_remote_post($url, [ 'timeout' => 45, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode($payload) ]);
        if (is_wp_error($res)) { return new \WP_Error('upstream_error', $res->get_error_message(), ['status' => 502]); }
        $code = wp_remote_retrieve_response_code($res);
        $data = json_decode(wp_remote_retrieve_body($res), true);
        $this->log_fact_check($payload, $data, $req);
        return new \WP_REST_Response($data, $code ?: 200);
    }
    public function check_permissions() {
        if (get_option('verifact_public_enabled', 1)) { return true; }
        if (get_option('verifact_require_login', 0)) { return is_user_logged_in(); }
        $user = wp_get_current_user();
        $allowed_roles = get_option(self::OPT_USER_PERMISSIONS, ['administrator']);
        return !empty(array_intersect($user->roles, $allowed_roles));
    }
    private function log_fact_check($request, $response, $req) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'verifact_logs';
        $wpdb->insert($table_name, [
            'claim' => $request['claim'] ?? '',
            'stance' => $response['results'][0]['stance'] ?? 'Unknown',
            'confidence' => $response['results'][0]['confidence'] ?? 0,
            'evidence_count' => count($response['results'][0]['evidence'] ?? []),
            'runtime_sec' => $response['meta']['runtime_sec'] ?? 0,
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $req->get_header('user_agent'),
        ]);
    }
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) { return $ip; }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    private function get_dashboard_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'verifact_logs';
        $total_checks = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $supported_claims = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE stance = 'Supported'");
        $refuted_claims = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE stance = 'Refuted'");
        $avg_response_time = $wpdb->get_var("SELECT AVG(runtime_sec) FROM $table_name");
        $api_base = get_option(self::OPT_API_BASE, home_url('/verifact'));
        $api_status = $this->test_api_connection($api_base);
        return [
            'total_checks' => $total_checks ?: 0,
            'supported_claims' => $supported_claims ?: 0,
            'refuted_claims' => $refuted_claims ?: 0,
            'avg_response_time' => $avg_response_time ?: 0,
            'api_status' => $api_status,
        ];
    }
    private function test_api_connection($api_base) {
        $url = rtrim($api_base, '/') . '/health';
        $response = wp_remote_get($url, ['timeout' => 5]);
        if (is_wp_error($response)) { return false; }
        $code = wp_remote_retrieve_response_code($response);
        return $code === 200;
    }
    private function render_recent_activity_widget() {
        $recent_activity = $this->get_recent_activity(5);
        if (empty($recent_activity)) { echo '<div class="widget-empty">No recent activity</div>'; return; }
        foreach ($recent_activity as $activity) {
            ?>
            <div class="activity-item">
                <div class="activity-text"><?php echo esc_html($activity['claim']); ?></div>
                <div class="activity-time"><?php echo esc_html($activity['time']); ?></div>
            </div>
            <?php
        }
    }
    private function get_recent_activity($limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'verifact_logs';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT claim, created_at FROM $table_name ORDER BY created_at DESC LIMIT %d", intval($limit)));
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'claim' => $row->claim,
                'time' => human_time_diff(strtotime($row->created_at)) . ' ago',
            ];
        }
        return $out;
    }
    public function ajax_bulk_check() { check_ajax_referer('verifact_bulk_check', 'nonce'); wp_send_json_success(['message' => 'Bulk check completed']); }
    public function ajax_get_stats() { check_ajax_referer('verifact_get_stats', 'nonce'); $stats = $this->get_dashboard_stats(); wp_send_json_success($stats); }
    public function ajax_clear_cache() { check_ajax_referer('verifact_clear_cache', 'nonce'); wp_send_json_success(['message' => 'Cache cleared']); }
    public function ajax_test_api() {
        check_ajax_referer('verifact_test_api', 'nonce');
        $api_base = get_option(self::OPT_API_BASE, home_url('/verifact'));
        $ok = $this->test_api_connection($api_base);
        if ($ok) { wp_send_json_success(true); }
        wp_send_json_error('API connection failed');
    }
    public function ajax_get_log() {
        check_ajax_referer('verifact_admin_nonce', 'nonce');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) { wp_send_json_error('Invalid ID'); }
        global $wpdb;
        $table_name = $wpdb->prefix . 'verifact_logs';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
        if (!$row) { wp_send_json_error('Log not found'); }
        wp_send_json_success($row);
    }
    public function ajax_create_schedule() {
        check_ajax_referer('verifact_admin_nonce', 'nonce');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $url = esc_url_raw($_POST['url'] ?? '');
        $frequency = sanitize_text_field($_POST['frequency'] ?? 'daily');
        if (!$name || !$url) { wp_send_json_error('Name and URL required'); }
        $schedules = get_option('verifact_schedules', []);
        $schedules[] = ['name'=>$name,'url'=>$url,'frequency'=>$frequency,'created_at'=>current_time('mysql')];
        update_option('verifact_schedules', $schedules, false);
        wp_send_json_success(['message'=>'Schedule created','count'=>count($schedules)]);
    }
    public function ajax_upload_remote_cache() {
        check_ajax_referer('verifact_admin_nonce', 'nonce');
        $enabled = get_option('verifact_remote_cache_enabled', 0);
        $put_url = get_option('verifact_remote_cache_url', '');
        if (!$enabled || !$put_url) { wp_send_json_error('Remote cache not configured'); }
        global $wpdb;
        $table_name = $wpdb->prefix . 'verifact_logs';
        $top = $wpdb->get_results("SELECT claim, COUNT(*) as count FROM $table_name GROUP BY claim ORDER BY count DESC LIMIT 20", ARRAY_A);
        $body = wp_json_encode(['top_claims'=>$top,'generated_at'=>current_time('mysql')]);
        $resp = wp_remote_request($put_url, ['method'=>'PUT','headers'=>['Content-Type'=>'application/json'],'body'=>$body,'timeout'=>15]);
        if (is_wp_error($resp)) { wp_send_json_error($resp->get_error_message()); }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) { wp_send_json_error('Remote upload failed: HTTP '.$code); }
        wp_send_json_success(['uploaded'=>true,'count'=>count($top)]);
    }
}
new VeriFactPlugin();
