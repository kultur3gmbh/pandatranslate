<?php
$root=getenv('CONTACTPANDA_TEST_WP_ROOT');
if(!$root||!is_file($root.'/.panda-test-fixture'))throw new RuntimeException('Disposable fixture required');
$_SERVER['HTTP_HOST']='127.0.0.1:8768';$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';
require $root.'/wp-load.php';
$old=get_option(PandaTranslate_Plugin::OPTION);$plugin=PandaTranslate_Plugin::boot();
remove_filter('sanitize_option_'.PandaTranslate_Plugin::OPTION,array($plugin,'sanitize_settings'));
$s=PandaTranslate_Plugin::get_settings();$s['source_language']='de';$s['languages']=array(array('code'=>'de','active'=>1),array('code'=>'fr','active'=>1));update_option(PandaTranslate_Plugin::OPTION,$s);
$id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Synthetic HTTP collection','post_content'=>'<p>Synthetic HTTP collection content.</p>'));
try{
 $result=PandaTranslate_Translation_Jobs::scan($id,'fr','',true);
 if(is_wp_error($result))throw new RuntimeException($result->get_error_code().': '.$result->get_error_message());
 echo "PASS Actual loopback HTTP collection acknowledges the exact X-PandaTranslate-Scan header\n";
}finally{wp_delete_post($id,true);update_option(PandaTranslate_Plugin::OPTION,$old);}
