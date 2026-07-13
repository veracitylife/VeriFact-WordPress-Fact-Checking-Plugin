<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Bulk {
    private VeriFact_Queue $queue;
    public function __construct(VeriFact_Queue $queue) {
        $this->queue=$queue;
        add_action('admin_init',[$this,'register_bulk_actions']);
        add_action('admin_notices',[$this,'admin_notice']);
        add_action('rest_api_init',[$this,'routes']);
        if(defined('WP_CLI')&&WP_CLI){WP_CLI::add_command('verifact bulk queue',[$this,'cli_queue']);}
    }
    public function register_bulk_actions(): void {
        foreach(apply_filters('verifact_supported_post_types',[]) as $type){add_filter('bulk_actions-edit-'.$type,[$this,'actions']);add_filter('handle_bulk_actions-edit-'.$type,[$this,'handle'],10,3);}
    }
    public function actions(array $actions): array { $actions['verifact_queue']=__('Queue VeriFact review','verifact');return $actions; }
    public function handle(string $redirect,string $action,array $post_ids): string {
        if($action!=='verifact_queue'){return $redirect;}$result=$this->queue_ids($post_ids,get_current_user_id());return add_query_arg(['verifact_bulk_queued'=>count($result['queued']),'verifact_bulk_failed'=>count($result['errors'])],$redirect);
    }
    public function admin_notice(): void {
        if(!isset($_GET['verifact_bulk_queued'])){return;}$queued=absint($_GET['verifact_bulk_queued']);$failed=absint($_GET['verifact_bulk_failed']??0);echo '<div class="notice notice-success is-dismissible"><p>'.esc_html(sprintf(__('VeriFact queued %1$d post(s); %2$d could not be queued.','verifact'),$queued,$failed)).'</p></div>';
    }
    public function routes(): void {
        register_rest_route('verifact/v1','/bulk/queue',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[$this,'rest_queue'],'permission_callback'=>fn()=>current_user_can('verifact_check_content'),'args'=>['post_ids'=>['type'=>'array','required'=>true,'items'=>['type'=>'integer'],'validate_callback'=>fn($value)=>is_array($value)&&count($value)>=1&&count($value)<=100]]]);
    }
    public function rest_queue(WP_REST_Request $request): WP_REST_Response {
        $result=$this->queue_ids((array)$request['post_ids'],get_current_user_id());return new WP_REST_Response($result,$result['queued']?202:422);
    }
    private function queue_ids(array $post_ids,int $user_id): array {
        $queued=[];$errors=[];foreach(array_slice(array_unique(array_map('absint',$post_ids)),0,100) as $post_id){if(!$post_id||!current_user_can('edit_post',$post_id)){$errors[]=['post_id'=>$post_id,'code'=>'forbidden'];continue;}$job=$this->queue->enqueue($post_id,$user_id);if(is_wp_error($job)){$errors[]=['post_id'=>$post_id,'code'=>$job->get_error_code()];}else{$queued[]=['post_id'=>$post_id,'job_id'=>(int)$job];}}return ['queued'=>$queued,'errors'=>$errors,'count'=>count($queued)];
    }
    public function cli_queue(array $args,array $assoc_args): void {
        $post_type=sanitize_key((string)($assoc_args['post_type']??'post'));$limit=max(1,min(1000,absint($assoc_args['limit']??100)));$ids=get_posts(['post_type'=>$post_type,'post_status'=>['draft','pending','publish'],'numberposts'=>$limit,'fields'=>'ids']);$queued=0;foreach($ids as $id){$job=$this->queue->enqueue((int)$id,0);if(!is_wp_error($job)){$queued++;}}WP_CLI::success(sprintf('Queued %d VeriFact review job(s).',$queued));
    }
}