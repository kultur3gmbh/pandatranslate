<?php
require __DIR__.'/bootstrap.php';
define('ARRAY_A','ARRAY_A');
$flags=array();$api_calls=0;$mode='cancel';$records=array();$run='test-job-one';
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));} function absint($v){return abs((int)$v);}
function set_transient($key,$value,$ttl){global $flags;$flags[$key]=array($value,time()+$ttl);return true;}
class DB {
 public $options='wp_options';
 public function prepare($sql,...$args){return array($sql,$args);}
 public function get_var($q){global $flags;if(is_string($q))return 5;list($sql,$args)=$q;if(strpos($sql,'v.option_value')!==false){$key=substr($args[1],strlen('_transient_'));$entry=$flags[$key]??null;return $entry&&$entry[1]>time()?$entry[0]:null;}return 5;}
 public function get_results($q,$format){return array_map(function($id){return array('id'=>$id,'unit_key'=>'unit-'.$id);},range(1,5));}
}
$wpdb=new DB();
class PandaTranslate_Repository {public static function table(){return 'wp_units';} public function record_error($key,$lang,$message){global $records;$records[$key]=$message;}}
class PandaTranslate_Providers {public static function get($id){return new stdClass();}public static function label($id){return 'Mock API';}}
class PandaTranslate_Plugin {
 public static function get_settings(){return array('source_language'=>'de');}
 public static function language_rows(){return array(array('code'=>'es','active'=>1));}
 public static function auto_translate_known($key,$language,$api){global $api_calls,$mode,$run;++$api_calls;if($mode==='cancel'){PandaTranslate_Translation_Jobs::cancel($run);return array('saved'=>true);}return new WP_Error('provider_403','Denied ?key=synthetic-key Bearer synthetic-token');}
}
require __DIR__.'/../includes/class-pandatranslate-translation-jobs.php';
$input=array('language'=>'es','phase'=>'translate','api'=>'mock','run_id'=>$run);
$r=PandaTranslate_Translation_Jobs::batch($input);assert($api_calls===1&&$r['cancelled']&&$r['done']&&$r['success']===1);
assert(PandaTranslate_Translation_Jobs::cancel($run));assert(!PandaTranslate_Translation_Jobs::is_cancelled('other-job'));assert(!PandaTranslate_Translation_Jobs::cancel('bad'));
$api_calls=0;$mode='error';$input['run_id']='test-job-two';$r=PandaTranslate_Translation_Jobs::batch($input);
assert($api_calls===3&&$r['failed']&&$r['errors']===3&&$r['last_error_code']==='provider_403');assert(count($records)===3);assert(strpos($r['message'],'synthetic-key')===false&&strpos($r['message'],'synthetic-token')===false);
PandaTranslate_Translation_Jobs::cancel($input['run_id']);$before=$api_calls;$r=PandaTranslate_Translation_Jobs::batch($input);assert($r['cancelled']&&$api_calls===$before);
echo "Translation cancellation: stop between requests, retain saved result, isolate runs, stop three repeated errors, redact credentials, cancelled batch makes no API calls: ok\n";
