<?php
/** Disposable WordPress only: formal source, multiple batches, no live/provider calls. */
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php'; wp_set_current_user( 1 );
if ( '--run-import' === ( $argv[1] ?? '' ) ) {
 $_REQUEST['_wpnonce'] = wp_create_nonce( 'pandatranslate_import_translatepress' );
 PandaTranslate_Migration::import(); exit;
}
function formal_check($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
if(PandaTranslate_Migration::tables())throw new RuntimeException('No existing TranslatePress sources allowed');
// This fixture has no core locale packs; persist the test locale without downloading one.
$formal_locale=static function($languages){$languages[]='de_DE_formal';return array_unique($languages);};
add_filter('get_available_languages',$formal_locale);
$before=array();foreach(array(PandaTranslate_Plugin::OPTION,'WPLANG','trp_settings') as $option)$before[$option]=get_option($option,false);
global $wpdb;$table=$wpdb->prefix.'trp_dictionary_de_de_formal_fr_fr';$keys=array();$marker=wp_generate_uuid4();
if(false===$wpdb->query($wpdb->prepare('CREATE TABLE %i (id bigint unsigned NOT NULL PRIMARY KEY, original longtext NOT NULL, translated longtext NOT NULL, status int NOT NULL)',$table)))throw new RuntimeException('Fixture creation failed');
try{
 update_option('WPLANG','de_DE_formal');update_option('trp_settings',array('default-language'=>'de_DE_formal'));
 $s=PandaTranslate_Plugin::get_settings();$s['source_language']='de-de-formal';$s['target_languages']='fr';$s['languages']=PandaTranslate_Features::normalize_languages(array(array('code'=>'de-de-formal','active'=>1),array('code'=>'fr','active'=>1)),'de-de-formal');update_option(PandaTranslate_Plugin::OPTION,$s);
 $pre=PandaTranslate_Migration::preflight();if(is_wp_error($pre))throw new RuntimeException($pre->get_error_message().' '.json_encode(array('wp'=>get_option('WPLANG'),'rows'=>PandaTranslate_Plugin::configured_language_rows())));
 formal_check(true,'WordPress and TranslatePress formal source are compatible');
 $repo=new PandaTranslate_Repository();
 for($id=1;$id<=205;$id++){
  $source='Formal migration fixture '.$marker.' '.$id;$wpdb->insert($table,array('id'=>$id,'original'=>$source,'translated'=>'Résultat '.$id,'status'=>2));
  $key=hash('sha256','translatepress|'.$table.'|'.$id.'|'.$source);$keys[]=$key;
  if(1===$id)$repo->save(array('key'=>$key,'source_hash'=>hash('sha256',$source),'source_html'=>$source),'fr','Manual retained','published','manual');
 }
 ob_start();PandaTranslate_Migration::render();$html=ob_get_clean();
 formal_check(strpos($html,'data-total="205"')!==false,'All formal-source translations counted');
 formal_check(strpos($html,'data-migration-start')!==false,'Migration start control offered');
 formal_check(strpos(html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8'),'DE-DE-FORMAL → FR-FR')!==false,'Source-target report preserves formal source locale');
 $process=proc_open(array(PHP_BINARY,__FILE__,'--run-import'),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
 if(!is_resource($process))throw new RuntimeException('Importer unavailable');
 $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);formal_check(0===proc_close($process)&&!$stderr&&strpos($stdout,'Fatal error')===false,'Formal-source import finishes across bounded batches');
 foreach($keys as $key)if(!$repo->find($key,'fr'))throw new RuntimeException('Formal source assigned incorrectly');
 formal_check(true,'All 205 formal-source rows assigned to French target');
 formal_check('Manual retained'===$repo->find($keys[0],'fr')['translated_html'],'Existing manual target retained');
 formal_check('published'===$repo->find($keys[204],'fr')['status'],'TranslatePress publication status retained');
 $wpdb->query($wpdb->prepare('DELETE FROM %i',$table));
 ob_start();PandaTranslate_Migration::render();$empty=ob_get_clean();
 formal_check(strpos($empty,'No TranslatePress dictionary tables were found.')===false,'Empty existing table is not reported as missing');
}finally{
 foreach($keys as $key)$wpdb->delete(PandaTranslate_Repository::table(),array('unit_key'=>$key,'target_language'=>'fr'));
 $wpdb->query($wpdb->prepare('DROP TABLE %i',$table));
 remove_filter('get_available_languages',$formal_locale);
 foreach($before as $option=>$value){if(false===$value)delete_option($option);else update_option($option,$value);}
}
