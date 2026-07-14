<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Editor {
    private VeriFact_Plugin $core;
    private const REPORT_META='_verifact_last_report';
    private const STATUS_META='_verifact_review_status';

    public function __construct(VeriFact_Plugin $core) {
        $this->core=$core;
        add_action('init',[$this,'register_job_type']);
        add_action('enqueue_block_editor_assets',[$this,'editor_assets']);
        add_action('rest_api_init',[$this,'register_routes']);
        add_action('verifact_process_job',[$this,'process_job']);
        add_action('save_post',[$this,'mark_changed'],10,3);
    }

    public static function activate(bool $network_wide=false): void {
        $apply=static function(): void {
            foreach (['administrator'=>['verifact_check_content','verifact_view_reports','verifact_manage'],'editor'=>['verifact_check_content','verifact_view_reports'],'author'=>['verifact_check_content']] as $role_name=>$caps) {
                $role=get_role($role_name); if(!$role){continue;} foreach($caps as $cap){$role->add_cap($cap);}
            }
        };
        if (is_multisite() && $network_wide) {
            foreach (get_sites(['fields'=>'ids']) as $site_id) { switch_to_blog($site_id); $apply(); restore_current_blog(); }
        } else { $apply(); }
    }

    public function register_job_type(): void {
        register_post_type('verifact_job',['public'=>false,'show_ui'=>current_user_can('verifact_manage'),'label'=>'VeriFact Jobs','supports'=>['title','custom-fields']]);
        register_post_meta('post',self::REPORT_META,['single'=>true,'type'=>'object','show_in_rest'=>false,'auth_callback'=>fn()=>current_user_can('verifact_view_reports')]);
        register_post_meta('post',self::STATUS_META,['single'=>true,'type'=>'string','show_in_rest'=>false,'auth_callback'=>fn()=>current_user_can('verifact_view_reports')]);
    }

    public function editor_assets(): void {
        if (!current_user_can('verifact_check_content')) { return; }
        wp_enqueue_script('verifact-editor',plugins_url('../assets/verifact-editor.js',__FILE__),['wp-plugins','wp-edit-post','wp-element','wp-components','wp-data','wp-blocks'],VeriFact_Plugin::VERSION,true);
        wp_enqueue_style('verifact-editor',plugins_url('../assets/verifact-editor.css',__FILE__),[],VeriFact_Plugin::VERSION);
        wp_localize_script('verifact-editor','verifactEditor',['restRoot'=>esc_url_raw(rest_url('verifact/v1')),'nonce'=>wp_create_nonce('wp_rest')]);
    }

    public function register_routes(): void {
        register_rest_route('verifact/v1','/posts/(?P<id>\d+)/queue',[
            'methods'=>WP_REST_Server::CREATABLE,'callback'=>[$this,'queue_post'],'permission_callback'=>[$this,'can_edit'],
            'args'=>['id'=>['validate_callback'=>fn($v)=>get_post(absint($v))!==null]],
        ]);
        register_rest_route('verifact/v1','/jobs/(?P<id>\d+)',[
            'methods'=>WP_REST_Server::READABLE,'callback'=>[$this,'job_status'],'permission_callback'=>fn()=>current_user_can('verifact_view_reports'),
        ]);
        register_rest_route('verifact/v1','/jobs/(?P<id>\d+)/retry',[
            'methods'=>WP_REST_Server::CREATABLE,'callback'=>[$this,'retry_job'],'permission_callback'=>fn()=>current_user_can('verifact_manage'),
        ]);        register_rest_route('verifact/v1','/posts/(?P<id>\d+)/report',[
            'methods'=>WP_REST_Server::READABLE,'callback'=>[$this,'post_report'],'permission_callback'=>[$this,'can_view'],
        ]);
    }

    public function can_edit(WP_REST_Request $request): bool { return current_user_can('verifact_check_content')&&current_user_can('edit_post',absint($request['id'])); }
    public function can_view(WP_REST_Request $request): bool { return current_user_can('verifact_view_reports')&&current_user_can('read_post',absint($request['id'])); }

    public function queue_post(WP_REST_Request $request) {
        $job_id=$GLOBALS['verifact_queue']->enqueue(absint($request['id']),get_current_user_id());
        if(is_wp_error($job_id)){return $job_id;}
        return new WP_REST_Response(['job_id'=>$job_id,'status'=>'queued'],202);
    }

    public function process_job(int $job_id): void {
        $job=get_post($job_id); if(!$job||$job->post_type!=='verifact_job'){return;}
        update_post_meta($job_id,'_verifact_job_status','running');
        $post=get_post($job->post_parent); if(!$post){update_post_meta($job_id,'_verifact_job_status','failed');return;}
        $content=wp_strip_all_tags(strip_shortcodes($post->post_content));
        $result=$this->core->verify_content($content);
        if (is_wp_error($result)) { update_post_meta($job_id,'_verifact_job_status','failed');update_post_meta($job_id,'_verifact_job_error',sanitize_text_field($result->get_error_message()));update_post_meta($post->ID,self::STATUS_META,'failed');return; }
        update_post_meta($job_id,'_verifact_job_status','complete');
        update_post_meta($job_id,'_verifact_job_result',$result);
        update_post_meta($post->ID,self::REPORT_META,$result);
        update_post_meta($post->ID,self::STATUS_META,'reviewed');
        do_action('verifact_post_reviewed',$post->ID,$result);
    }

    public function job_status(WP_REST_Request $request) {
        $job=$GLOBALS['verifact_queue']->status(absint($request['id']));
        return $job?:new WP_Error('not_found','Job not found.',['status'=>404]);
    }

    public function retry_job(WP_REST_Request $request) {
        $id=absint($request['id']);return $GLOBALS['verifact_queue']->requeue($id)?new WP_REST_Response(['job_id'=>$id,'status'=>'queued'],202):new WP_Error('verifact_job_not_retryable',__('Only dead-letter jobs can be retried.','verifact'),['status'=>409]);
    }
    public function post_report(WP_REST_Request $request): array {
        $id=absint($request['id']); return ['post_id'=>$id,'status'=>(string)get_post_meta($id,self::STATUS_META,true),'report'=>get_post_meta($id,self::REPORT_META,true)?:null];
    }

    public function mark_changed(int $post_id,WP_Post $post,bool $update): void {
        if(!$update||wp_is_post_revision($post_id)||$post->post_type==='verifact_job'){return;}
        if(get_post_meta($post_id,self::REPORT_META,true)){update_post_meta($post_id,self::STATUS_META,'changed_since_review');delete_post_meta($post_id,'_verifact_gate_override');delete_post_meta($post_id,'_verifact_claimreview_approved');}
    }
}