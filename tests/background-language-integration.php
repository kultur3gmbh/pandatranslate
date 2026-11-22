<?php
$root=getenv('CONTACTPANDA_TEST_WP_ROOT');
if(!$root||!is_file($root.'/.panda-test-fixture'))throw new RuntimeException('Disposable fixture required');
require $root.'/wp-load.php';
function pt_background_expect($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
class PT_Background_Fixture_API implements PandaTranslate_Provider{
 public static $calls=0;public function id(){return 'background-fixture';}
 public function translate($text,$source,$target,array $context=array()){self::$calls++;return 'Synthetic '.$target.' '.$text;}
}
$settings=array('source_language'=>'de','languages'=>array(array('code'=>'de','active'=>1),array('code'=>'fr','active'=>1),array('code'=>'es','active'=>1)),'auto_publish'=>0,'auto_provider'=>'background-fixture');
$option=function()use(&$settings){return $settings;};
$registry=function($providers){$providers['background-fixture']=new PT_Background_Fixture_API();return $providers;};
add_filter('pre_option_'.PandaTranslate_Plugin::OPTION,$option,999);
add_filter('pandatranslate_providers',$registry,999);
remove_filter('pandatranslate_static_templates_collect',array('PandaTranslate_WooCommerce','collect_templates'));
$template_registry=function($items){return array(array('source'=>'Synthetic background template {first_name}','id'=>'background-template','domain'=>'background-fixture','html'=>false));};
add_filter('pandatranslate_stored_templates',$template_registry,999);
global $wpdb;$table=PandaTranslate_Repository::table();$repo=new PandaTranslate_Repository();
$wpdb->query('START TRANSACTION');$jobs=array();
try{
 $wpdb->query("DELETE FROM $table");delete_option(PandaTranslate_Site_Progress::OPTION);
 $keys=array();for($i=0;$i<11;$i++){$text='Synthetic background unit '.$i;$key=hash('sha256',$text);$keys[]=$key;$repo->save(array('key'=>$key,'source_hash'=>hash('sha256',$text),'source_html'=>$text),'fr','','untranslated');}
 $first=$repo->find($keys[0],'fr');$repo->save_revision($first,'Manual remains','published');
 $overview = new ReflectionMethod( 'PandaTranslate_Plugin', 'render_translation_progress' );
 $overview->setAccessible( true );
 ob_start();
 $overview->invoke( PandaTranslate_Plugin::boot() );
 $overview_html = ob_get_clean();
 preg_match( '/<button[^>]*data-language-translate="es"[^>]*data-mode="missing"[^>]*>[^<]*<\/button>/', $overview_html, $spanish_action );
 pt_background_expect( ! empty( $spanish_action ) && false === strpos( $spanish_action[0], ' disabled' ), 'A newly enabled Spanish target offers automatic translation before its first scan' );

 $job=PandaTranslate_Site_Progress::start_languages(array('fr','es'),'background-fixture','missing','background-request-1');pt_background_expect(!is_wp_error($job),'Three configured languages accept two selected background targets');$jobs[]=$job;
 pt_background_expect(PT_Background_Fixture_API::$calls===0,'Enqueue does not perform a paid/provider operation');
 pt_background_expect(wp_next_scheduled('pandatranslate_process_language_batch',array($job['id'],'es'))!==false,'Continuation is durably registered with WordPress');
 $_GET=array(); // Browser gone: advance only the server action using persisted cursors.
 for($i=0;$i<12&&PandaTranslate_Site_Progress::running(PandaTranslate_Site_Progress::state(true));$i++)foreach(array('fr','es')as$language)do_action('pandatranslate_process_language_batch',$job['id'],$language);
 $done=PandaTranslate_Site_Progress::state(true);
 pt_background_expect(!PandaTranslate_Site_Progress::running($done)&&$done['languages']['es']['phase']==='done','Workers finish both languages without browser batch or polling requests');
 pt_background_expect($repo->find($keys[0],'fr')['translated_html']==='Manual remains','Missing-only background run preserves manual published text');
 pt_background_expect($repo->find($keys[1],'fr')['status']==='automatic','Auto-publication preference remains enforced');
 $stats = $repo->language_stats()['fr'];
 pt_background_expect( 1 === $stats['published'] && 11 === $stats['total'] - $stats['untranslated'] - $stats['published'], 'Translation progress distinguishes saved drafts from published translations' );
 ob_start();PandaTranslate_Site_Progress::render();$html=ob_get_clean();pt_background_expect(strpos($html,'FR')!==false&&strpos($html,'ES')!==false&&strpos($html,'/ 12')!==false,'Reopened progress includes the newly collected stored template in both persisted totals');
 $job=PandaTranslate_Site_Progress::start_languages(array('fr'),'background-fixture','all','background-request-2');$jobs[]=$job;PandaTranslate_Translation_Jobs::cancel($job['id']);PandaTranslate_Site_Progress::cancel($job['id']);$calls=PT_Background_Fixture_API::$calls;do_action('pandatranslate_process_language_batch',$job['id'],'fr');
 pt_background_expect($calls===PT_Background_Fixture_API::$calls,'Cancelled scheduled operation sends no provider requests');
 pt_background_expect(!wp_next_scheduled('pandatranslate_process_language_batch',array($job['id'],'fr')),'Cancellation removes pending continuation');
}finally{
 foreach($jobs as$job)foreach(array_keys($job['languages'])as$language)wp_clear_scheduled_hook('pandatranslate_process_language_batch',array($job['id'],$language));
 $wpdb->query('ROLLBACK');remove_filter('pre_option_'.PandaTranslate_Plugin::OPTION,$option,999);remove_filter('pandatranslate_providers',$registry,999);wp_cache_flush();
}
