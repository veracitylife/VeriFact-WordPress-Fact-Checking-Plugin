<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Platform {
    private VeriFact_Plugin $core;

    public function __construct(VeriFact_Plugin $core) {
        $this->core=$core;
        add_action('admin_menu',[$this,'menu'],20);
        add_action('rest_api_init',[$this,'routes']);
        add_action('admin_init',[$this,'settings']);
    }

    public function settings(): void {
        register_setting('verifact_platform','verifact_policy_pack',['type'=>'string','sanitize_callback'=>'sanitize_key','default'=>'editorial-default']);
        register_setting('verifact_platform','verifact_private_profile',['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>false]);
        register_setting('verifact_platform','verifact_review_notifications',['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>true]);
    }

    public function menu(): void {
        add_submenu_page('verifact-dashboard','Platform','Platform','manage_options','verifact-platform',[$this,'page']);
    }

    public function routes(): void {
        $permission=fn()=>current_user_can('verifact_manage');
        register_rest_route('verifact/v1','/platform/claims',['methods'=>WP_REST_Server::READABLE,'callback'=>[$this,'claims'],'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/claims',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[$this,'register_claim'],'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/review-cases',['methods'=>WP_REST_Server::READABLE,'callback'=>fn()=>$this->proxy_get('/api/v1/review-cases'),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/review-cases',['methods'=>WP_REST_Server::CREATABLE,'callback'=>fn(WP_REST_Request $r)=>$this->proxy_post('/api/v1/review-cases',$r),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/review-cases/(?P<id>[a-f0-9-]+)',['methods'=>WP_REST_Server::CREATABLE,'callback'=>fn(WP_REST_Request $r)=>$this->proxy_post('/api/v1/review-cases/'.sanitize_text_field($r['id']),$r),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/providers',['methods'=>WP_REST_Server::READABLE,'callback'=>fn()=>$this->proxy_get('/api/v1/providers'),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/providers',['methods'=>WP_REST_Server::CREATABLE,'callback'=>fn(WP_REST_Request $r)=>$this->proxy_post('/api/v1/providers',$r),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/receipts/verify',['methods'=>WP_REST_Server::CREATABLE,'callback'=>fn(WP_REST_Request $r)=>$this->proxy_post('/api/v1/receipts/verify',$r),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/history/(?P<id>[a-f0-9-]+)/evidence-map',['methods'=>WP_REST_Server::READABLE,'callback'=>fn(WP_REST_Request $r)=>$this->proxy_get('/api/v1/history/'.sanitize_text_field($r['id']).'/evidence-map'),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/policies',['methods'=>WP_REST_Server::READABLE,'callback'=>fn()=>$this->proxy_get('/api/v1/policy-packs'),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/policies',['methods'=>WP_REST_Server::CREATABLE,'callback'=>fn(WP_REST_Request $r)=>$this->proxy_post('/api/v1/policy-packs',$r),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/deployment',['methods'=>WP_REST_Server::READABLE,'callback'=>fn()=>$this->proxy_get('/api/v1/deployment'),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/entitlements',['methods'=>WP_REST_Server::READABLE,'callback'=>fn()=>$this->proxy_get('/api/v1/entitlements'),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/entitlements',['methods'=>WP_REST_Server::CREATABLE,'callback'=>fn(WP_REST_Request $r)=>$this->proxy_post('/api/v1/entitlements',$r),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/conformance',['methods'=>WP_REST_Server::CREATABLE,'callback'=>fn(WP_REST_Request $r)=>$this->proxy_post('/api/v1/integrations/conformance',$r),'permission_callback'=>$permission]);
        register_rest_route('verifact/v1','/platform/transparency',['methods'=>WP_REST_Server::READABLE,'callback'=>fn()=>$this->proxy_get('/api/v1/transparency'),'permission_callback'=>$permission]);
    }

    public function claims(WP_REST_Request $request) {
        $query=sanitize_text_field((string)$request->get_param('query'));
        if ($query==='') { return new WP_Error('verifact_query_required','A claim search query is required.',['status'=>422]); }
        return $this->proxy_get('/api/v1/claims/registry/search?query='.rawurlencode($query));
    }

    public function register_claim(WP_REST_Request $request) {
        return $this->proxy_post('/api/v1/claims/registry',$request);
    }

    private function proxy_get(string $path) {
        $result=$this->core->api_get($path);
        return is_wp_error($result)?$result:rest_ensure_response($result);
    }

    private function proxy_post(string $path,WP_REST_Request $request) {
        $payload=$request->get_json_params();
        $result=$this->core->api_request($path,is_array($payload)?$payload:[]);
        return is_wp_error($result)?$result:rest_ensure_response($result);
    }

    public function page(): void {
        if (!current_user_can('manage_options')) { return; }
        $deployment=$this->core->api_get('/api/v1/deployment');
        $entitlement=$this->core->api_get('/api/v1/entitlements');
        $connected=!is_wp_error($deployment);
        echo '<div class="wrap verifact-wrap"><h1>VeriFact Platform</h1>';
        echo '<p>Manage shared claims, review cases, evidence providers, policy packs, signed receipts, private deployments, organization entitlements, and certified integrations.</p>';
        echo '<div class="verifact-grid">';
        $cards=[
            ['Claim registry','Reuse prior decisions and find semantically similar claims.'],
            ['Review cases','Assign uncertain or high-risk claims and preserve decisions.'],
            ['Evidence providers','Register sandboxed provider manifests and allowed hosts.'],
            ['Signed receipts','Validate tamper-evident verification receipts and transparency entries.'],
            ['Evidence maps','Inspect support, conflict, and independent-source diversity.'],
            ['Policy packs','Apply versioned editorial and jurisdiction rules.'],
            ['Private deployment','Use managed, self-hosted, private, edge, or offline API profiles.'],
            ['Organization','Inspect plan, seats, feature entitlements, budgets, and grace periods.'],
            ['Integrations','Run the VeriFact 3.3 compatibility conformance suite.'],
        ];
        foreach($cards as [$title,$body]){echo '<section class="verifact-card"><h2>'.esc_html($title).'</h2><p>'.esc_html($body).'</p></section>';}
        echo '</div><h2>Current API profile</h2>';
        if($connected){echo '<pre>'.esc_html(wp_json_encode(['deployment'=>$deployment,'entitlements'=>$entitlement],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre>';}
        else{echo '<div class="notice notice-warning inline"><p>'.esc_html($deployment->get_error_message()).'</p></div>';}
        echo '<form method="post" action="options.php">';settings_fields('verifact_platform');
        echo '<table class="form-table"><tr><th>Default policy pack</th><td><input name="verifact_policy_pack" value="'.esc_attr((string)get_option('verifact_policy_pack','editorial-default')).'"></td></tr>';
        echo '<tr><th>Private API profile</th><td><label><input type="checkbox" name="verifact_private_profile" value="1" '.checked((bool)get_option('verifact_private_profile',false),true,false).'> Require private/self-hosted deployment capability</label></td></tr>';
        echo '<tr><th>Review notifications</th><td><label><input type="checkbox" name="verifact_review_notifications" value="1" '.checked((bool)get_option('verifact_review_notifications',true),true,false).'> Enable editorial review notifications</label></td></tr></table>';
        submit_button('Save platform settings');echo '</form></div>';
    }
}
