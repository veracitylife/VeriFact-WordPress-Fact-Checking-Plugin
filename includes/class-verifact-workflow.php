<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Workflow {
    private const OVERRIDE='_verifact_gate_override';
    public function __construct() {
        add_action('admin_init',[$this,'settings']);
        add_filter('wp_insert_post_data',[$this,'gate'],20,2);
        add_action('rest_api_init',[$this,'routes']);
    }
    public function settings(): void {
        register_setting('verifact_settings','verifact_gate_enabled',['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean','default'=>false]);
        register_setting('verifact_settings','verifact_gate_post_types',['type'=>'array','sanitize_callback'=>fn($v)=>array_values(array_filter(array_map('sanitize_key',(array)$v),'post_type_exists')),'default'=>['post']]);
        register_setting('verifact_settings','verifact_gate_min_confidence',['type'=>'number','sanitize_callback'=>fn($v)=>max(0,min(1,(float)$v)),'default'=>0.6]);
    }
    public function gate(array $data,array $postarr): array {
        if(!(bool)get_option('verifact_gate_enabled',false)||($data['post_status']??'')!=='publish'){return $data;}
        if(!in_array($data['post_type']??'post',(array)get_option('verifact_gate_post_types',['post']),true)){return $data;}
        $id=absint($postarr['ID']??0);$override=$id?(array)get_post_meta($id,self::OVERRIDE,true):[];
        $content_hash=hash('sha256',trim(wp_strip_all_tags(strip_shortcodes(($data['post_title']??'')."\n".($data['post_content']??'')))));if($override&&!empty($override['approved'])&&hash_equals((string)($override['content_hash']??''),$content_hash)){return $data;}
        $report=$id?(array)get_post_meta($id,'_verifact_last_report',true):[];$status=$id?(string)get_post_meta($id,'_verifact_review_status',true):'';
        $minimum=(float)get_option('verifact_gate_min_confidence',0.6);$blocked=$status!=='reviewed'||empty($report['results']);
        foreach((array)($report['results']??[]) as $result){if(in_array($result['stance']??'', ['refuted','disputed','outdated','insufficient_evidence'],true)||(float)($result['confidence']??0)<$minimum){$blocked=true;break;}}
        if($blocked){$data['post_status']='pending';if($id){$this->audit($id,'publication_blocked',['minimum_confidence'=>$minimum]);}}
        return $data;
    }
    public function routes(): void {
        register_rest_route('verifact/v1','/posts/(?P<id>\d+)/override',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[$this,'override'],'permission_callback'=>fn($r)=>current_user_can('publish_post',absint($r['id']))&&current_user_can('verifact_view_reports'),'args'=>['reason'=>['type'=>'string','required'=>true,'sanitize_callback'=>'sanitize_textarea_field']]]);
    }
    public function override(WP_REST_Request $request) {
        $id=absint($request['id']);$reason=trim((string)$request['reason']);if(mb_strlen($reason)<10){return new WP_Error('verifact_override_reason',__('Provide an override reason of at least 10 characters.','verifact'),['status'=>422]);}
        $post=get_post($id);if(!$post){return new WP_Error('not_found',__('Post not found.','verifact'),['status'=>404]);}$record=['approved'=>true,'reason'=>mb_substr($reason,0,1000),'user_id'=>get_current_user_id(),'created_at'=>current_time('mysql',true),'content_hash'=>hash('sha256',trim(wp_strip_all_tags(strip_shortcodes($post->post_title."\n".$post->post_content))))];update_post_meta($id,self::OVERRIDE,$record);$this->audit($id,'publication_override',$record);return $record;
    }
    private function audit(int $post_id,string $event,array $metadata): void {
        $events=(array)get_post_meta($post_id,'_verifact_workflow_audit',true);$events[]=['event'=>$event,'actor'=>get_current_user_id(),'created_at'=>current_time('mysql',true),'metadata'=>$metadata];update_post_meta($post_id,'_verifact_workflow_audit',array_slice($events,-100));
    }
}
