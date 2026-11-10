<?php
require __DIR__ . '/bootstrap.php';
define('PANDATRANSLATE_VERSION','0.4.5'); define('ARRAY_A','ARRAY_A');
$hooks=array(); $allowed=true; $options=array();
function add_action($name,$callback){add_filter($name,$callback);}
function add_filter($name,$callback,$priority=10,$args=1){global $hooks;$hooks[$name][]=$callback;}
function apply_filters($name,$value,...$args){global $hooks;foreach($hooks[$name]??array() as $callback)$value=$callback($value,...$args);return $value;}
function current_user_can($cap,...$args){global $allowed;return $allowed;}
function sanitize_key($value){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$value));}
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
function absint($value){return abs((int)$value);}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
function current_time(){return '2026-11-10 23:00:00';}
function get_current_user_id(){return 1;}
function get_option($key,$default=null){global $options;return $options[$key]??$default;}
function update_option($key,$value,...$args){global $options;$options[$key]=$value;return true;}
class PandaTranslate_Plugin {
 public static function get_settings(){return array('source_language'=>'de','target_languages'=>'en','default_target_language'=>'en','provider'=>'manual','google_api_version'=>'v2','google_api_key_encrypted'=>'must-not-leak');}
 public static function language_rows(){return array(array('code'=>'de','active'=>1),array('code'=>'en','active'=>1),array('code'=>'fr','active'=>0));}
 public static function google_provider(){return new class{function configured(){return new WP_Error('unconfigured','Not configured');}};}
}
class PandaTranslate_Providers { public static function choices(){return array();}public static function default_id(){return '';} }
function get_post($id){return $id===42?(object)array('post_name'=>'original','post_title'=>'Original title','post_excerpt'=>'Description'):null;}
function get_post_meta($id,$key,$single){return $key==='_pandatranslate_seo_title_en'?'Translated SEO title':'';}
require dirname(__DIR__).'/includes/class-pandatranslate-repository.php';
require dirname(__DIR__).'/includes/class-pandatranslate-mcp.php';
class MCP_Test_DB {
 public $prefix='wp_', $rows=array(), $race=false, $last_sql='';
 function prepare($sql,...$args){return array($sql,count($args)===1&&is_array($args[0])?$args[0]:$args);}
 function esc_like($text){return addcslashes($text,'_%\\');}
 function get_row($statement,$format){[$sql,$args]=$statement;foreach($this->rows as $row)if($row['unit_key']===$args[0]&&$row['target_language']===$args[1])return $row;return null;}
 function get_results($statement,$format){[$sql,$args]=is_array($statement)?$statement:array($statement,array());$this->last_sql=$sql;
  $rows=array_values(array_filter($this->rows,function($row)use($args){return !$args||$row['target_language']===$args[0];}));
  preg_match('/LIMIT (\d+) OFFSET (\d+)/',$sql,$m);return array_slice($rows,(int)$m[2],(int)$m[1]);}
 function query($statement){[$sql,$args]=$statement;$this->last_sql=$sql;
  if($this->race){$this->rows[$args[4]]['translated_html']='Concurrent edit';$this->race=false;}
  $row=$this->rows[$args[4]]; $fields=array('unit_key','target_language','source_hash','source_html','translated_html','status','provider','updated_at');
  foreach($fields as $i=>$field){assert(strpos($sql,'BINARY '.$field.' = BINARY %s')!==false);if($row[$field]!==$args[$i+5])return 0;}
  foreach(array('translated_html','status','provider','updated_at') as $i=>$field)$row[$field]=$args[$i];$this->rows[$args[4]]=$row;return 1;}
}
$wpdb=new MCP_Test_DB();$key=hash('sha256','paragraph');
$wpdb->rows[1]=array('id'=>1,'unit_key'=>$key,'target_language'=>'en','source_hash'=>hash('sha256','source'),'source_html'=>'Ein <strong>ganzer</strong> Absatz.','translated_html'=>'A <strong>whole</strong> paragraph.','status'=>'published','provider'=>'translatepress-import','updated_at'=>'2026-11-10 22:00:00');
$wpdb->rows[2]=array_merge($wpdb->rows[1],array('id'=>2,'unit_key'=>hash('sha256','second')));
PandaTranslate_MCP::boot();
$tools=apply_filters('royal_mcp_tools',array());assert(count($tools)===6);
foreach($tools as $tool)assert($tool['annotations']['readOnlyHint']===in_array($tool['name'],array('pandatranslate_settings_get','pandatranslate_translations_list','pandatranslate_translation_get','pandatranslate_seo_get'),true));
assert(apply_filters('royal_mcp_execute_tool_external',null,'other_plugin_tool',array())===null);
assert(apply_filters('royal_mcp_execute_tool_external',array('handled'=>true),'pandatranslate_translation_get',array())===array('handled'=>true));
function royal_call($name,$args){return apply_filters('royal_mcp_execute_tool_external',null,$name,$args);}
$status=royal_call('pandatranslate_settings_get',array());assert($status['source_language']==='de'&&count($status['languages'])===3);assert(strpos(wp_json_encode($status),'must-not-leak')===false);
$page=royal_call('pandatranslate_translations_list',array('language'=>'en','limit'=>1));assert(count($page['translations'])===1&&$page['next_offset']===1);
$page2=royal_call('pandatranslate_translations_list',array('language'=>'en','limit'=>1,'offset'=>1));assert($page2['translations'][0]['unit_key']!==$key);
$search=royal_call('pandatranslate_translations_list',array('language'=>'en','query'=>'whole'));assert(strpos($wpdb->last_sql,'source_html LIKE %s OR translated_html LIKE %s')!==false);
$args=array('unit_key'=>$key,'language'=>'en');$read=royal_call('pandatranslate_translation_get',$args);
assert($read['protected_source_text']==='Ein [[PT:0:OPEN]]ganzer[[PT:1:CLOSE]] Absatz.');
assert($read['protected_translation_text']==='A [[PT:0:OPEN]]whole[[PT:1:CLOSE]] paragraph.');
$save=$args+array('confirm'=>true,'status'=>'reviewed','translated_text'=>'A [[PT:0:OPEN]]complete[[PT:1:CLOSE]] paragraph.');
assert(royal_call('pandatranslate_translation_save',$save)->get_error_code()==='pandatranslate_conflict');
$save['expected_revision']=$read['revision'];$saved=royal_call('pandatranslate_translation_save',$save);
assert($saved['saved']&&$saved['verified']);assert($saved['translation']['status']==='reviewed');assert($saved['translation']['provider']==='manual-dictionary');assert($saved['translation']['scope']==='source_dictionary');
$reread=royal_call('pandatranslate_translation_get',$args);assert($reread['revision']===$saved['translation']['revision']);assert($reread['translated_html']==='A <strong>complete</strong> paragraph.');
assert(royal_call('pandatranslate_translation_save',$save)->get_error_code()==='pandatranslate_conflict');
$save['expected_revision']=$reread['revision'];$save['translated_text']='Missing placeholders';assert(is_wp_error(royal_call('pandatranslate_translation_save',$save)));
$save['translated_text']=$reread['protected_translation_text'];$save['status']='published';$wpdb->race=true;assert(royal_call('pandatranslate_translation_save',$save)->get_error_code()==='pandatranslate_conflict');assert($wpdb->rows[1]['translated_html']==='Concurrent edit');
assert(is_wp_error(royal_call('pandatranslate_translation_get',array('unit_key'=>$key,'language'=>'fr'))));
assert(is_wp_error(royal_call('pandatranslate_translation_get',array('unit_key'=>$key,'language'=>'de'))));
$seo=royal_call('pandatranslate_seo_get',array('post_id'=>42,'language'=>'en'));assert($seo['overrides']['seo_title']==='Translated SEO title');
$allowed=false;foreach($tools as $tool){$result=royal_call($tool['name'],$args);assert(is_wp_error($result)&&$result->get_error_code()==='pandatranslate_forbidden');}
$allowed=true;
echo "MCP Royal hooks: discovery, paging/search, whole-paragraph read/save/re-read, draft status, protected placeholders, stale revision and atomic race refusal, active target validation, SEO read and permission denial: ok\n";
