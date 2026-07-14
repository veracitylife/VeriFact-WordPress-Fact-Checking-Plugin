<?php
if (!defined('ABSPATH')) { exit; }

final class VeriFact_Inline {
    private const META='_verifact_inline_citations';

    public function __construct() {
        add_action('verifact_post_reviewed',[$this,'sync'],20,2);
        add_filter('the_content',[$this,'render'],20);
        add_action('wp_enqueue_scripts',[$this,'assets']);
        add_shortcode('verifact_source',[$this,'shortcode']);
    }

    public function sync(int $post_id,array $report): void {
        $items=[];
        foreach((array)($report['inline_citations']??[]) as $citation){$normalized=$this->normalize($citation);if($normalized){$items[]=$normalized;}}
        if(!$items){foreach((array)($report['results']??[]) as $result){$evidence=(array)($result['evidence']??[]);$first=$evidence[0]??null;if(!$first){continue;}$normalized=$this->normalize(['claim'=>$result['claim']??'','stance'=>$result['stance']??'insufficient_evidence','confidence'=>$result['confidence']??0,'source_url'=>$first['url']??'','source_title'=>$first['title']??$first['publisher']??'Supporting source']);if($normalized){$items[]=$normalized;}}}
        update_post_meta($post_id,self::META,$items);
    }

    private function normalize(array $item): ?array {
        $url=esc_url_raw((string)($item['source_url']??$item['url']??''),['http','https']);
        if(!$url||!wp_http_validate_url($url)){return null;}
        $claim=sanitize_textarea_field((string)($item['claim']??''));$stance=sanitize_key((string)($item['stance']??'insufficient_evidence'));$confidence=max(0,min(1,(float)($item['confidence']??0)));$source=sanitize_text_field((string)($item['source_title']??__('Supporting source','verifact')));
        $labels=['supported'=>__('supports this claim','verifact'),'refuted'=>__('conflicts with this claim','verifact'),'disputed'=>__('shows this claim is disputed','verifact'),'outdated'=>__('indicates this claim may be outdated','verifact'),'insufficient_evidence'=>__('provides limited evidence for this claim','verifact')];
        $note=sanitize_text_field((string)($item['note']??sprintf(__('%1$s %2$s; VeriFact confidence %3$d%%.','verifact'),$source,$labels[$stance]??$labels['insufficient_evidence'],round($confidence*100))));
        return ['claim'=>$claim,'stance'=>$stance,'confidence'=>$confidence,'source_url'=>$url,'source_title'=>$source,'note'=>mb_substr($note,0,240)];
    }

    private function link(array $item): string {
        $note=(string)$item['note'];$label=sprintf(__('Open supporting source in a new tab. VeriFact note: %s','verifact'),$note);
        return '<a class="verifact-cite" href="'.esc_url((string)$item['source_url']).'" target="_blank" rel="noopener noreferrer external" data-verifact-version="'.esc_attr(VeriFact_Plugin::VERSION).'" data-verifact-note="'.esc_attr($note).'" title="'.esc_attr($note).'" aria-label="'.esc_attr($label).'">'.esc_html__('source↗','verifact').'</a>';
    }

    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['url'=>'','note'=>'','claim'=>'','stance'=>'supported','confidence'=>'1'],$atts,'verifact_source');$item=$this->normalize(['claim'=>$atts['claim'],'stance'=>$atts['stance'],'confidence'=>$atts['confidence'],'source_url'=>$atts['url'],'source_title'=>__('Supporting source','verifact'),'note'=>$atts['note']]);return $item?$this->link($item):'';
    }

    private function markers(string $content): string {
        return (string)preg_replace_callback('/\{\{verifact\|([^|{}]+)\|([^{}]+)\}\}/i',function(array $match): string {$item=$this->normalize(['source_url'=>trim($match[1]),'note'=>trim($match[2]),'stance'=>'supported','confidence'=>1]);return $item?$this->link($item):'';},$content);
    }

    public function render(string $content): string {
        $content=$this->markers($content);if(is_admin()||!is_singular()){return $content;}$post_id=get_the_ID();if(!$post_id){return $content;}$items=(array)get_post_meta($post_id,self::META,true);if(!$items){return $content;}
        foreach($items as $raw){$item=$this->normalize((array)$raw);if(!$item||$item['claim']===''){continue;}$claim=(string)$item['claim'];$link=$this->link($item);if(str_contains($content,$link)){continue;}$replaced=false;foreach([$claim,htmlspecialchars($claim,ENT_QUOTES|ENT_HTML5,'UTF-8')] as $needle){if($needle!==''&&str_contains($content,$needle)){$content=preg_replace('/'.preg_quote($needle,'/').'/',static fn($m)=>$m[0].$link,$content,1);$replaced=true;break;}}if($replaced){continue;}
            $content=(string)preg_replace_callback('/<(p|li|blockquote)\b([^>]*)>(.*?)<\/\1>/is',function(array $match)use($claim,$link,&$replaced):string{if($replaced){return $match[0];}$visible=trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(html_entity_decode($match[3],ENT_QUOTES|ENT_HTML5,'UTF-8'))));$target=trim(preg_replace('/\s+/u',' ',$claim));$percent=0;similar_text($visible,$target,$percent);if($target!==''&&(str_contains($visible,$target)||$percent>=72)){$replaced=true;return '<'.$match[1].$match[2].'>'.$match[3].$link.'</'.$match[1].'>'; }return $match[0];},$content);
        }
        return $content;
    }

    public function assets(): void {if(is_singular()){wp_enqueue_style('verifact-inline',plugins_url('../assets/verifact-inline.css',__FILE__),[],VeriFact_Plugin::VERSION);}}
}
