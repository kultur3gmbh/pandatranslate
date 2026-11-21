<?php
require __DIR__.'/bootstrap.php';
$options=array();$scheduled=array();$calls=array();
function get_option($k,$d=array()){global $options;return $options[$k]??$d;}
function update_option($k,$v,$a=false){global $options;$options[$k]=$v;return true;}
function add_option($k,$v,$d='',$a=false){global $options;if(isset($options[$k]))return false;$options[$k]=$v;return true;}
function delete_option($k){global $options;unset($options[$k]);}
function wp_generate_uuid4(){static $n=0;return 'job-fixture-'.++$n;}
function wp_schedule_single_event($t,$h,$args){global $scheduled;$scheduled[]=array($h,$args);return true;}
function wp_clear_scheduled_hook($h,$args){global $scheduled;$scheduled=array_values(array_filter($scheduled,function($x)use($h,$args){return $x!==array($h,$args);}));}
class PandaTranslate_Edition{public static function allows_language($l){return in_array($l,array('fr','es'));}}
class PandaTranslate_Providers{public static function get($api){return $api==='chosen-api' ? new stdClass() : new WP_Error('bad-api');}}
class PandaTranslate_Translation_Jobs{
 public static function batch($input){global $calls;$calls[]=$input;assert($input['api']==='chosen-api');assert(in_array($input['language'],array('es','fr')));$phase=$input['phase'];return array('phase'=>$phase==='start'?'collect':'translate','done'=>$phase==='translate','processed'=>$phase==='translate'?5:1,'total'=>5,'success'=>$phase==='translate'?5:0,'errors'=>0);}
}
require __DIR__.'/../includes/class-pandatranslate-site-progress.php';
$job=PandaTranslate_Site_Progress::start_languages(array('fr','es'),'chosen-api','all','request-00001');assert(!is_wp_error($job));assert(count($calls)===0&&count($scheduled)===2);
assert(PandaTranslate_Site_Progress::start_languages(array('fr','es'),'chosen-api','all','request-00001')['id']===$job['id']);assert(count($scheduled)===2);
assert(is_wp_error(PandaTranslate_Site_Progress::start_languages(array('es'),'chosen-api','missing','request-00002')));
assert(PandaTranslate_Site_Progress::state()['id']===$job['id']);
// No browser or polling call: only persisted state and the scheduled worker.
for($i=0;$i<3;$i++)foreach(array('fr','es')as$language)PandaTranslate_Site_Progress::process($job['id'],$language);
$state=PandaTranslate_Site_Progress::state();assert(!PandaTranslate_Site_Progress::running($state));assert($state['languages']['fr']['translation']['success']===5);assert($state['languages']['es']['phase']==='done');
foreach($calls as$input)assert($input['mode']==='all');
$job=PandaTranslate_Site_Progress::start_languages(array('es'),'chosen-api','failed','request-00003');PandaTranslate_Site_Progress::cancel($job['id']);$before=count($calls);PandaTranslate_Site_Progress::process($job['id'],'es');assert(count($calls)===$before);assert(PandaTranslate_Site_Progress::state()['languages']['es']['phase']==='cancelled');
assert(is_wp_error(PandaTranslate_Site_Progress::start_languages(array('de'),'chosen-api','missing','request-00004')));
assert(is_wp_error(PandaTranslate_Site_Progress::start_languages(array('es'),'chosen-api','invalid','request-00004')));
echo "Durable language jobs: worker-only continuation after browser closes, multi-language progress, API/mode pinned, idempotent start, busy guard, cancellation and validation: ok\n";
