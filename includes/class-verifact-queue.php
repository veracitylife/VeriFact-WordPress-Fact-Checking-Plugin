<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Queue {
    private VeriFact_Plugin $core;
    private string $table;

    public function __construct(VeriFact_Plugin $core) {
        global $wpdb;
        $this->core=$core;
        $this->table=$wpdb->prefix.'verifact_jobs';
        add_filter('cron_schedules',[$this,'cron_schedule']);
        add_action('init',[$this,'ensure_schedule']);
        add_action('verifact_queue_worker',[$this,'run']);
        add_action('verifact_queue_action_scheduler',[$this,'run']);
        add_action('action_scheduler_init',[$this,'ensure_action_scheduler']);
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('verifact queue run',function(){ $count=$this->run();WP_CLI::success('Processed '.$count.' VeriFact job(s).'); });
            WP_CLI::add_command('verifact queue retry',function($args){if(empty($args[0])){WP_CLI::error('Provide a job ID.');}$this->requeue(absint($args[0]))?WP_CLI::success('Job requeued.'):WP_CLI::error('Only dead-letter jobs can be requeued.');});
        }
    }

    public static function activate(): void {
        global $wpdb;
        $table=$wpdb->prefix.'verifact_jobs';
        $charset=$wpdb->get_charset_collate();
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'queued',
            content_hash char(64) NOT NULL,
            attempts smallint unsigned NOT NULL DEFAULT 0,
            available_at datetime NOT NULL,
            locked_at datetime DEFAULT NULL,
            requested_by bigint(20) unsigned DEFAULT NULL,
            result_json longtext DEFAULT NULL,
            error_message varchar(500) DEFAULT NULL,
            runtime_ms int unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY ready (status,available_at),
            KEY post_status (post_id,status),
            KEY content_hash (content_hash)
        ) {$charset};");
    }

    public function cron_schedule(array $schedules): array {
        $schedules['verifact_minute']=['interval'=>60,'display'=>__('Every minute','verifact')];
        return $schedules;
    }

    public function ensure_schedule(): void {
        if (!wp_next_scheduled('verifact_queue_worker')) {
            wp_schedule_event(time()+30,'verifact_minute','verifact_queue_worker');
        }
    }

    public function ensure_action_scheduler(): void {
        if (function_exists('as_has_scheduled_action') && function_exists('as_schedule_recurring_action') && !as_has_scheduled_action('verifact_queue_action_scheduler')) {
            as_schedule_recurring_action(time()+15,60,'verifact_queue_action_scheduler',[],'verifact');
        }
    }

    public function enqueue(int $post_id,int $user_id=0) {
        global $wpdb;
        $post=get_post($post_id);
        if (!$post) { return new WP_Error('verifact_post_missing',__('Post not found.','verifact')); }
        $content=$this->content($post);
        if ($content==='') { return new WP_Error('verifact_empty_post',__('The post has no reviewable content.','verifact')); }
        $hash=hash('sha256',$content);
        $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table} WHERE post_id=%d AND content_hash=%s AND status IN ('queued','running') ORDER BY id DESC LIMIT 1",$post_id,$hash));
        if ($existing) { return (int)$existing; }
        $now=current_time('mysql',true);
        $wpdb->insert($this->table,['post_id'=>$post_id,'status'=>'queued','content_hash'=>$hash,'attempts'=>0,'available_at'=>$now,'requested_by'=>$user_id?:null,'created_at'=>$now,'updated_at'=>$now],['%d','%s','%s','%d','%s','%d','%s','%s']);
        if (!$wpdb->insert_id) { return new WP_Error('verifact_queue_insert',__('Could not create the verification job.','verifact')); }
        update_post_meta($post_id,'_verifact_review_status','queued');
        do_action('verifact_job_queued',(int)$wpdb->insert_id,$post_id);
        return (int)$wpdb->insert_id;
    }

    public function run(int $limit=5): int {
        global $wpdb;
        $limit=max(1,min(20,$limit));
        $stale=gmdate('Y-m-d H:i:s',time()-600);
        $wpdb->query($wpdb->prepare("UPDATE {$this->table} SET status='queued',locked_at=NULL,available_at=%s,error_message='Recovered stale lock' WHERE status='running' AND locked_at < %s",current_time('mysql',true),$stale));
        $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM {$this->table} WHERE status='queued' AND available_at<=%s AND attempts<4 ORDER BY id ASC LIMIT %d",current_time('mysql',true),$limit));
        $processed=0;
        foreach($ids as $id){if($this->process((int)$id)){$processed++;}}
        return $processed;
    }

    private function process(int $job_id): bool {
        global $wpdb;
        $now=current_time('mysql',true);
        $claimed=$wpdb->query($wpdb->prepare("UPDATE {$this->table} SET status='running',locked_at=%s,attempts=attempts+1,updated_at=%s WHERE id=%d AND status='queued'",$now,$now,$job_id));
        if ($claimed!==1) { return false; }
        $job=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d",$job_id),ARRAY_A);
        $post=$job?get_post((int)$job['post_id']):null;
        if (!$post) { $this->fail($job_id,__('Post no longer exists.','verifact'),false);return true; }
        $started=microtime(true);
        $content=$this->content($post);
        $result=$this->revision_aware_review($post,$content);
        $runtime=(int)round((microtime(true)-$started)*1000);
        if (is_wp_error($result)) {
            $retry=(int)$job['attempts']<3;
            $this->fail($job_id,$result->get_error_message(),$retry,$runtime);
            return true;
        }
        $wpdb->update($this->table,['status'=>'complete','result_json'=>wp_json_encode($result),'error_message'=>null,'runtime_ms'=>$runtime,'locked_at'=>null,'updated_at'=>current_time('mysql',true)],['id'=>$job_id],['%s','%s','%s','%d','%s','%s'],['%d']);
        update_post_meta($post->ID,'_verifact_last_report',$result);
        update_post_meta($post->ID,'_verifact_review_status','reviewed');
        update_post_meta($post->ID,'_verifact_reviewed_content_hash',hash('sha256',$content));
        do_action('verifact_post_reviewed',$post->ID,$result);
        return true;
    }

    private function revision_aware_review(WP_Post $post,string $content) {
        $claims=$this->core->extract_claims($content,50);
        if (is_wp_error($claims)) { return $this->core->verify_content($content); }
        $prior=(array)get_post_meta($post->ID,'_verifact_last_report',true);
        $max_age=max(0,(int)apply_filters('verifact_claim_reuse_days',30,$post));
        $reviewed_at=(int)get_post_meta($post->ID,'_verifact_reviewed_at',true);
        $reuse_allowed=$max_age===0||($reviewed_at>0&&$reviewed_at>=time()-($max_age*DAY_IN_SECONDS));
        $prior_results=[];
        if($reuse_allowed){foreach((array)($prior['results']??[]) as $item){if(!empty($item['claim'])){$prior_results[hash('sha256',trim((string)$item['claim']))]=$item;}}}
        $results=[];$changed=[];$reused=[];
        foreach($claims as $claim){
            $hash=hash('sha256',trim($claim));
            if(isset($prior_results[$hash])){$results[]=$prior_results[$hash];$reused[]=$hash;continue;}
            $checked=$this->core->verify_claim($claim,(string)$post->ID);
            if(is_wp_error($checked)){return $checked;}
            foreach((array)($checked['results']??[]) as $item){$results[]=$item;}
            $changed[]=$hash;
        }
        update_post_meta($post->ID,'_verifact_reviewed_at',time());
        return ['results'=>$results,'meta'=>['request_id'=>wp_generate_uuid4(),'claims_checked'=>count($changed),'claims_reused'=>count($reused),'runtime_sec'=>0,'providers'=>[],'warning'=>__('Open every cited source before publication.','verifact'),'model_version'=>'verifact-wordpress-3.1'],'history_id'=>'wp-'.$post->ID.'-'.time(),'tenant_id'=>'wordpress','project_id'=>(string)$post->ID,'revision'=>['content_hash'=>hash('sha256',$content),'changed_claim_hashes'=>$changed,'reused_claim_hashes'=>$reused,'reuse_max_age_days'=>$max_age]];
    }

    private function fail(int $job_id,string $message,bool $retry,int $runtime=0): void {
        global $wpdb;
        $status=$retry?'queued':'dead_letter';
        $available=gmdate('Y-m-d H:i:s',time()+($retry?120:0));
        $wpdb->update($this->table,['status'=>$status,'available_at'=>$available,'locked_at'=>null,'error_message'=>mb_substr(sanitize_text_field($message),0,500),'runtime_ms'=>$runtime,'updated_at'=>current_time('mysql',true)],['id'=>$job_id]);
        $post_id=(int)$wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$this->table} WHERE id=%d",$job_id));
        if(!$retry&&$post_id){update_post_meta($post_id,'_verifact_review_status','failed');}
    }

    public function status(int $job_id): ?array {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT id,post_id,status,attempts,error_message,runtime_ms,result_json,created_at,updated_at FROM {$this->table} WHERE id=%d",$job_id),ARRAY_A);
        if(!$row){return null;}$row['id']=(int)$row['id'];$row['post_id']=(int)$row['post_id'];$row['attempts']=(int)$row['attempts'];$row['runtime_ms']=(int)$row['runtime_ms'];$row['result']=$row['result_json']?json_decode($row['result_json'],true):null;unset($row['result_json']);return $row;
    }

    public function cancel(int $job_id): bool { global $wpdb;return $wpdb->update($this->table,['status'=>'cancelled','locked_at'=>null,'updated_at'=>current_time('mysql',true)],['id'=>$job_id,'status'=>'queued'])===1; }
    public function requeue(int $job_id): bool { global $wpdb;return $wpdb->query($wpdb->prepare("UPDATE {$this->table} SET status='queued',attempts=0,available_at=%s,locked_at=NULL,error_message=NULL,updated_at=%s WHERE id=%d AND status IN ('dead_letter','failed')",current_time('mysql',true),current_time('mysql',true),$job_id))===1; }
    public function metrics(): array { global $wpdb;return ['queued'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='queued'"),'running'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='running'"),'dead_letter'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status IN ('dead_letter','failed')"),'average_runtime_ms'=>(int)$wpdb->get_var("SELECT AVG(runtime_ms) FROM {$this->table} WHERE status='complete'")]; }    private function content(WP_Post $post): string { $content=$post->post_title."\n".$post->post_content;return trim(wp_strip_all_tags(strip_shortcodes((string)apply_filters('verifact_post_content',$content,$post)))); }
}
