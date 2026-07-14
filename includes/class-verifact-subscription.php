<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Subscription {
    private const INSTALLATION_ID='verifact_installation_id';
    private const LICENSE_SUFFIX='verifact_license_suffix';
    private const LICENSE_STATUS='verifact_license_status';
    private VeriFact_Plugin $core;
    private static ?string $request_token=null;

    public function __construct(VeriFact_Plugin $core) {
        $this->core=$core;
        add_action('admin_menu',[$this,'menu'],15);
        add_action('admin_post_verifact_subscribe',[$this,'subscribe']);
        add_action('admin_post_verifact_activate_license',[$this,'activate_license']);
        add_action('admin_post_verifact_manage_billing',[$this,'manage_billing']);
        add_action('admin_post_verifact_deactivate_site',[$this,'deactivate_site']);
        add_filter('verifact_api_bearer_token',[$this,'bearer_token']);
    }

    public function menu(): void {
        add_submenu_page('verifact-dashboard',__('Subscription & License','verifact'),__('Subscription & License','verifact'),'manage_options','verifact-subscription',[$this,'page']);
    }

    private function installation_id(): string {
        $id=(string)get_option(self::INSTALLATION_ID,'');
        if (!preg_match('/^[a-f0-9-]{36}$/',$id)) {
            $id=wp_generate_uuid4();
            update_option(self::INSTALLATION_ID,$id,false);
        }
        return $id;
    }

    private function keypair() {
        if (!function_exists('sodium_crypto_sign_seed_keypair') || !defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            return new WP_Error('verifact_site_crypto_unavailable',__('This server needs the PHP Sodium extension and WordPress security salts before secure license activation can be used.','verifact'));
        }
        $seed=hash_hkdf('sha256',AUTH_KEY.SECURE_AUTH_KEY,SODIUM_CRYPTO_SIGN_SEEDBYTES,'verifact-site-auth',$this->installation_id());
        if (!is_string($seed) || strlen($seed)!==SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            return new WP_Error('verifact_site_crypto_failed',__('The site signing identity could not be generated.','verifact'));
        }
        return sodium_crypto_sign_seed_keypair($seed);
    }

    private static function base64url(string $value): string { return rtrim(strtr(base64_encode($value),'+/','-_'),'='); }

    private function public_key() {
        $pair=$this->keypair();
        return is_wp_error($pair)?$pair:self::base64url(sodium_crypto_sign_publickey($pair));
    }

    public function bearer_token($current): string {
        if (is_string($current) && $current!=='') { return $current; }
        if (self::$request_token!==null) { return self::$request_token; }
        $installation=(string)get_option(self::INSTALLATION_ID,'');
        if (!preg_match('/^[a-f0-9-]{36}$/',$installation)) { return ''; }
        $pair=$this->keypair();
        if (is_wp_error($pair)) { return ''; }
        $challenge=$this->core->api_public_request('/api/v1/licenses/challenge',['installation_id'=>$installation]);
        if (is_wp_error($challenge) || empty($challenge['challenge_id']) || empty($challenge['challenge'])) { return ''; }
        $digest=hash('sha256',(string)$challenge['challenge']);
        $message=(string)$challenge['challenge_id'].'.'.$digest.'.'.$installation;
        $signature=self::base64url(sodium_crypto_sign_detached($message,sodium_crypto_sign_secretkey($pair)));
        $token=$this->core->api_public_request('/api/v1/licenses/token',['installation_id'=>$installation,'challenge_id'=>(string)$challenge['challenge_id'],'signature'=>$signature]);
        if (is_wp_error($token) || empty($token['access_token'])) { return ''; }
        self::$request_token=sanitize_text_field((string)$token['access_token']);
        return self::$request_token;
    }

    private function guard(string $action): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You are not allowed to manage VeriFact subscriptions.','verifact'),403); }
        check_admin_referer($action);
    }

    private function redirect(string $notice): void {
        wp_safe_redirect(add_query_arg('verifact_notice',sanitize_key($notice),admin_url('admin.php?page=verifact-subscription')));
        exit;
    }

    private function redirect_external(string $url,array $hosts): void {
        $host=strtolower((string)wp_parse_url($url,PHP_URL_HOST));
        if (!in_array($host,$hosts,true) || wp_parse_url($url,PHP_URL_SCHEME)!=='https') { $this->redirect('billing_redirect_failed'); }
        wp_redirect($url,303,'VeriFact');
        exit;
    }

    public function subscribe(): void {
        $this->guard('verifact_subscribe');
        $plan=sanitize_key((string)($_POST['plan']??''));
        if (!in_array($plan,['starter','professional','agency','enterprise'],true)) { $this->redirect('invalid_plan'); }
        $public=$this->public_key();
        if (is_wp_error($public)) { $this->redirect('crypto_unavailable'); }
        $return=admin_url('admin.php?page=verifact-subscription');
        $success=$return.'&verifact_checkout=success&session_id={CHECKOUT_SESSION_ID}';
        $user=wp_get_current_user();
        $payload=['plan'=>$plan,'site_url'=>home_url('/'),'site_name'=>get_bloginfo('name'),'installation_id'=>$this->installation_id(),'public_key'=>$public,'success_url'=>$success,'cancel_url'=>$return.'&verifact_checkout=cancelled'];
        if (is_email($user->user_email)) { $payload['email']=$user->user_email; }
        $checkout=$this->core->api_public_request('/api/v1/billing/checkout-session',$payload);
        if (is_wp_error($checkout) || empty($checkout['checkout_url'])) { $this->redirect('checkout_failed'); }
        $this->redirect_external((string)$checkout['checkout_url'],['checkout.stripe.com']);
    }

    public function activate_license(): void {
        $this->guard('verifact_activate_license');
        $license=isset($_POST['license_key'])?trim((string)wp_unslash($_POST['license_key'])):'';
        if ($license==='') { $this->redirect('license_required'); }
        $public=$this->public_key();
        if (is_wp_error($public)) { $this->redirect('crypto_unavailable'); }
        $result=$this->core->api_public_request('/api/v1/licenses/activate',['license_key'=>$license,'site_url'=>home_url('/'),'site_name'=>get_bloginfo('name'),'installation_id'=>$this->installation_id(),'public_key'=>$public,'client_version'=>VeriFact_Plugin::VERSION]);
        unset($license,$_POST['license_key']);
        if (is_wp_error($result) || empty($result['activated'])) { $this->redirect('activation_failed'); }
        update_option(self::LICENSE_SUFFIX,sanitize_text_field((string)($result['license_suffix']??'')),false);
        update_option(self::LICENSE_STATUS,'active',false);
        delete_transient('verifact_connection_report');
        $this->redirect('activated');
    }

    public function manage_billing(): void {
        $this->guard('verifact_manage_billing');
        $result=$this->core->api_request('/api/v1/billing/portal-session',['return_url'=>admin_url('admin.php?page=verifact-subscription')]);
        if (is_wp_error($result) || empty($result['portal_url'])) { $this->redirect('portal_failed'); }
        $this->redirect_external((string)$result['portal_url'],['billing.stripe.com']);
    }

    public function deactivate_site(): void {
        $this->guard('verifact_deactivate_site');
        $result=$this->core->api_delete('/api/v1/licenses/installation');
        if (is_wp_error($result)) { $this->redirect('deactivation_failed'); }
        delete_option(self::INSTALLATION_ID);delete_option(self::LICENSE_SUFFIX);delete_option(self::LICENSE_STATUS);
        delete_transient('verifact_connection_report');self::$request_token=null;
        $this->redirect('deactivated');
    }

    private function notice(): void {
        $notice=sanitize_key((string)($_GET['verifact_notice']??''));
        $messages=[
            'activated'=>['success',__('License activated. This website is securely connected to VeriFact.','verifact')],
            'deactivated'=>['success',__('This website has been removed from the license.','verifact')],
            'checkout_failed'=>['error',__('Checkout could not be started. Confirm the VeriFact API URL and billing configuration.','verifact')],
            'activation_failed'=>['error',__('The license could not be activated. Check the key and available website limit.','verifact')],
            'portal_failed'=>['error',__('The billing portal could not be opened. Please test the API connection and try again.','verifact')],
            'crypto_unavailable'=>['error',__('Secure activation is unavailable until PHP Sodium and WordPress security salts are configured.','verifact')],
            'deactivation_failed'=>['error',__('The website could not be deactivated. Please retry before removing the plugin.','verifact')],
            'license_required'=>['warning',__('Enter a VeriFact license key.','verifact')],
            'invalid_plan'=>['warning',__('Choose a valid VeriFact plan.','verifact')],
            'billing_redirect_failed'=>['error',__('The billing provider returned an unexpected address.','verifact')],
        ];
        if (isset($messages[$notice])) { [$type,$message]=$messages[$notice];echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html($message).'</p></div>'; }
        if (sanitize_key((string)($_GET['verifact_checkout']??''))==='success') { echo '<div class="notice notice-success"><p>'.esc_html__('Payment received. VeriFact is finalizing this website connection; refresh once if the plan does not appear immediately.','verifact').'</p></div>'; }
    }

    public function page(): void {
        if (!current_user_can('manage_options')) { return; }
        $this->notice();
        $plans=$this->core->api_public_get('/api/v1/plans');
        $subscription=$this->core->api_get('/api/v1/subscription');
        $usage=is_wp_error($subscription)?$subscription:$this->core->api_get('/api/v1/usage');
        $connected=!is_wp_error($subscription)&&(($subscription['status']??'')!=='development');
        echo '<div class="wrap verifact-admin verifact-subscription"><h1>'.esc_html__('VeriFact Subscription & License','verifact').'</h1>';
        echo '<p class="description">'.esc_html__('Subscribe in a few steps, connect this website securely, and control fact-checking sources without storing payment details, provider credentials, permanent API keys, or raw licenses in WordPress.','verifact').'</p>';
        if ($connected) {
            $remaining=null;$used=0;foreach((array)($usage['items']??[]) as $item){$used+=(int)($item['claims']??0);}if(!empty($subscription['monthly_claim_limit'])){$remaining=max(0,(int)$subscription['monthly_claim_limit']-$used);}
            echo '<section class="verifact-account-card"><div><span class="verifact-status is-active">'.esc_html(ucfirst((string)$subscription['status'])).'</span><h2>'.esc_html((string)($subscription['plan_name']??$subscription['plan'])).'</h2><p>'.esc_html(sprintf(__('%1$d of %2$d websites active','verifact'),(int)($subscription['sites_active']??0),(int)($subscription['site_limit']??0))).'</p></div><div><strong>'.esc_html(number_format_i18n($used)).'</strong><span>'.esc_html__('claims this month','verifact').'</span>'.($remaining!==null?'<small>'.esc_html(sprintf(__('%s remaining','verifact'),number_format_i18n($remaining))).'</small>':'').'</div></section>';
            echo '<h2>'.esc_html__('Available fact-checking sources','verifact').'</h2><div class="verifact-source-list">';foreach((array)($subscription['datasources']??[]) as $source){echo '<span>'.esc_html(ucwords(str_replace('-',' ',$source))).'</span>';}echo '</div>';
            echo '<div class="verifact-actions"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="verifact_manage_billing">';wp_nonce_field('verifact_manage_billing');submit_button(__('Manage billing','verifact'),'primary','submit',false);echo '</form><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\''.esc_js(__('Remove this website from the license?','verifact')).'\');"><input type="hidden" name="action" value="verifact_deactivate_site">';wp_nonce_field('verifact_deactivate_site');submit_button(__('Deactivate this website','verifact'),'secondary','submit',false);echo '</form></div>';
        } else {
            if (is_wp_error($subscription)) { echo '<div class="notice notice-info inline"><p>'.esc_html__('Choose a plan below or activate an existing license. Payment is completed securely on Stripe.','verifact').'</p></div>'; }
            echo '<div class="verifact-plan-grid">';foreach((array)($plans['items']??[]) as $plan){$available=!empty($plan['checkout_available']);echo '<section class="verifact-plan-card"><h2>'.esc_html((string)$plan['name']).'</h2><p>'.esc_html(sprintf(_n('%d website','%d websites',(int)$plan['site_limit'],'verifact'),(int)$plan['site_limit'])).'</p><p>'.esc_html(number_format_i18n((int)$plan['monthly_claim_limit'])).' '.esc_html__('claims per month','verifact').'</p><ul>';foreach((array)$plan['datasources'] as $source){echo '<li>'.esc_html(ucwords(str_replace('-',' ',$source))).'</li>';}echo '</ul><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="verifact_subscribe"><input type="hidden" name="plan" value="'.esc_attr((string)$plan['code']).'">';wp_nonce_field('verifact_subscribe');submit_button($available?__('Choose this plan','verifact'):__('Contact VeriFact','verifact'),$plan['code']==='professional'?'primary':'secondary','submit',false,['disabled'=>!$available]);echo '</form></section>';}echo '</div>';
            echo '<section class="verifact-license-card"><h2>'.esc_html__('Already have a license?','verifact').'</h2><p>'.esc_html__('Enter it once to activate this website. VeriFact stores only a protected license fingerprint; WordPress does not save the license key.','verifact').'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="verifact_activate_license"><label class="screen-reader-text" for="verifact-license-key">'.esc_html__('VeriFact license key','verifact').'</label><input id="verifact-license-key" class="regular-text" type="password" name="license_key" autocomplete="off" required placeholder="vflic_••••••••••••"> ';wp_nonce_field('verifact_activate_license');submit_button(__('Activate license','verifact'),'primary','submit',false);echo '</form></section>';
        }
        echo '<p><a href="'.esc_url(admin_url('admin.php?page=verifact-api')).'">'.esc_html__('Test API connection','verifact').'</a> &nbsp; <a href="https://veracityintegrity.com/verifact/account/" target="_blank" rel="noopener noreferrer">'.esc_html__('Open VeriFact customer account','verifact').'</a></p></div>';
    }
}
