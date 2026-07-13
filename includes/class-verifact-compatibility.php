<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Compatibility {
    private VeriFact_Queue $queue;
    public function __construct(VeriFact_Queue $queue) {
        $this->queue=$queue;
        add_action('add_meta_boxes',[$this,'meta_boxes']);
        add_action('admin_post_verifact_classic_queue',[$this,'queue_from_classic']);
        add_filter('verifact_supported_post_types',[$this,'supported_post_types']);
        add_filter('verifact_post_content',[$this,'page_builder_content'],10,2);
    }
    public function supported_post_types(array $types=[]): array {
        $objects=get_post_types(['show_ui'=>true],'objects');
        foreach($objects as $name=>$object){if(post_type_supports($name,'editor')&&$name!=='verifact_job'&&$name!=='verifact_evidence'){$types[]=$name;}}
        return array_values(array_unique($types));
    }
    public function meta_boxes(): void {
        foreach(apply_filters('verifact_supported_post_types',[]) as $type){add_meta_box('verifact-review',__('VeriFact Review','verifact'),[$this,'render'],$type,'side','high');}
    }
    public function render(WP_Post $post): void {
        $status=(string)get_post_meta($post->ID,'_verifact_review_status',true);
        wp_nonce_field('verifact_classic_queue_'.$post->ID,'verifact_classic_nonce');
        echo '<p><strong>'.esc_html__('Status:','verifact').'</strong> <span aria-live="polite">'.esc_html($status?:__('Not reviewed','verifact')).'</span></p>';
        echo '<p><a class="button button-primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=verifact_classic_queue&post_id='.$post->ID),'verifact_classic_queue_'.$post->ID)).'">'.esc_html__('Queue fact review','verifact').'</a></p>';
        echo '<p class="description">'.esc_html__('Works with the Classic Editor, custom post types, block themes, and page-builder content saved into the post body. Integrations may filter verifact_supported_post_types.','verifact').'</p>';
    }
    public function queue_from_classic(): void {
        $post_id=absint($_GET['post_id']??0);check_admin_referer('verifact_classic_queue_'.$post_id);
        if(!$post_id||!current_user_can('edit_post',$post_id)||!current_user_can('verifact_check_content')){wp_die(esc_html__('Permission denied.','verifact'),403);}
        $result=$this->queue->enqueue($post_id,get_current_user_id());
        $url=get_edit_post_link($post_id,'raw');
        wp_safe_redirect(add_query_arg('verifact_queued',is_wp_error($result)?'0':'1',$url));exit;
    }
    public function page_builder_content(string $content,WP_Post $post): string {
        $values=[];foreach(['_elementor_data','_fl_builder_data','_ct_builder_shortcodes'] as $key){$value=get_post_meta($post->ID,$key,true);if($value!==''&&$value!==null){$values[]=$value;}}
        $walk=static function($value)use(&$walk):string{if(is_string($value)){$decoded=json_decode($value,true);return is_array($decoded)?$walk($decoded):$value;}if(is_object($value)){$value=(array)$value;}if(!is_array($value)){return '';} $parts=[];foreach($value as $key=>$item){if(is_array($item)||is_object($item)||in_array((string)$key,['title','text','editor','description','content','html'],true)){$parts[]=$walk($item);}}return implode("\n",$parts);};
        foreach($values as $value){$extracted=trim($walk($value));if($extracted!==''){$content.="\n".$extracted;}}return $content;
    }}
