<?php
$root=getenv('CONTACTPANDA_TEST_WP_ROOT');if(!$root||!is_file($root.'/.panda-test-fixture'))throw new RuntimeException('Disposable fixture required');require $root.'/wp-load.php';
function pt_bulk_expect($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$settings=array('source_language'=>'de','languages'=>array(array('code'=>'de','active'=>1),array('code'=>'fr','active'=>1)),'auto_publish'=>0,'google_v2_enabled'=>1,'auto_provider'=>'google-v2');
$option=function()use(&$settings){return $settings;};$key=function(){return 'synthetic-not-a-live-key';};$requests=0;$racing_key='';
$http=function($pre,$args,$url)use(&$requests,&$racing_key){if(strpos($url,'https://translation.googleapis.com/language/translate/v2?')!==0)throw new RuntimeException('Unexpected external request');$requests++;$body=json_decode($args['body'],true);if($racing_key){$repo=new PandaTranslate_Repository();$row=$repo->find($racing_key,'fr');$repo->save_revision($row,'Manual during request','published');$racing_key='';}return array('response'=>array('code'=>200),'body'=>wp_json_encode(array('data'=>array('translations'=>array_map(function($text){return array('translatedText'=>'Synthetic '.$text);},$body['q'])))),'headers'=>array());};
add_filter('pre_option_'.PandaTranslate_Plugin::OPTION,$option,999);add_filter('pandatranslate_google_api_key',$key,999);add_filter('pre_http_request',$http,999,3);
global $wpdb;$wpdb->query('START TRANSACTION');$repo=new PandaTranslate_Repository();
try{
 $keys=array();for($i=0;$i<10;$i++){$source='Synthetic bulk '.wp_generate_uuid4().($i===0?" <a\n href=\"https://example.test\">link</a>":'');$unit=hash('sha256',$source);$keys[]=$unit;$repo->save(array('key'=>$unit,'source_hash'=>hash('sha256',$source),'source_html'=>$source),'fr','','untranslated');}
 $result=PandaTranslate_Plugin::auto_translate_many($keys,'fr','google-v2','missing');
 pt_bulk_expect($requests===1&&count($result)===10&&!array_filter($result,'is_wp_error'),'Ten units with multiline markup translate in one intercepted Google request');
 pt_bulk_expect($repo->find($keys[1],'fr')['status']==='automatic','Bulk output respects unpublished automatic preference');
 $result=PandaTranslate_Plugin::auto_translate_many($keys,'fr','google-v2','missing');pt_bulk_expect($requests===1&&$result[$keys[1]]['skipped'],'Already translated units are not sent again');
 $racing_key=$keys[0];$result=PandaTranslate_Plugin::auto_translate_many(array($keys[0]),'fr','google-v2','all');pt_bulk_expect(is_wp_error($result[$keys[0]])&&$repo->find($keys[0],'fr')['translated_html']==='Manual during request','Atomic revision preserves edits made while a bulk API request is in flight');
}finally{$wpdb->query('ROLLBACK');remove_filter('pre_option_'.PandaTranslate_Plugin::OPTION,$option,999);remove_filter('pandatranslate_google_api_key',$key,999);remove_filter('pre_http_request',$http,999);}
