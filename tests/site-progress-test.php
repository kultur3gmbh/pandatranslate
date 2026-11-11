<?php
require __DIR__.'/bootstrap.php';
$options=array();$scheduled=array();
function get_option($key,$default=array()){global $options;return $options[$key]??$default;}
function update_option($key,$value,$autoload=false){global $options;$options[$key]=$value;}
function wp_generate_uuid4(){static $i=0;return 'fixture-job-'.++$i;}
function wp_clear_scheduled_hook($hook,$args) {global $scheduled;$scheduled=array_values(array_filter($scheduled,function($event)use($hook,$args){return $event!==array($hook,$args);}));}
function wp_schedule_single_event($time,$hook,$args){global $scheduled;$scheduled[]=array($hook,$args);}
class PandaTranslate_Translation_Jobs{
 public static function batch($input){assert($input['api']==='google-v2');assert($input['mode']==='missing');assert($input['language']==='es');return $input['phase']==='prepare'?array('phase'=>'translate','done'=>false):array('phase'=>'translate','done'=>true,'processed'=>2,'total'=>2,'success'=>1,'errors'=>1);}
}
require dirname(__DIR__).'/includes/class-pandatranslate-site-progress.php';
$id=PandaTranslate_Site_Progress::start(array(42,43),array('es','fr'),'google-v2');
PandaTranslate_Site_Progress::finish($id,42,'es',false);PandaTranslate_Site_Progress::finish($id,43,'es',true);PandaTranslate_Site_Progress::finish($id,43,'es',true);
$state=PandaTranslate_Site_Progress::state();assert($state['languages']['es']['processed']===2&&$state['languages']['es']['failed']===1);assert(count($scheduled)===1);
PandaTranslate_Site_Progress::process($id,'es');assert(PandaTranslate_Site_Progress::state()['languages']['es']['phase']==='translate');
PandaTranslate_Site_Progress::process($id,'es');$state=PandaTranslate_Site_Progress::state();assert($state['languages']['es']['phase']==='done');assert($state['languages']['es']['translation']['errors']===1);
$second=PandaTranslate_Site_Progress::start(array(44),array('es'),'google-v2');PandaTranslate_Site_Progress::finish($id,44,'es',false);assert(PandaTranslate_Site_Progress::state()['languages']['es']['processed']===0);
echo "Persistent site progress: separate language stages, duplicate completion ignored, bounded translation worker, selected API pinned, failures retained and stale jobs ignored: ok\n";

PandaTranslate_Site_Progress::cancel($id); assert(empty(PandaTranslate_Site_Progress::state()['cancelled']));
PandaTranslate_Site_Progress::cancel($second); $state=PandaTranslate_Site_Progress::state(); assert($state['cancelled'] && $state['languages']['es']['phase']==='cancelled');
$before=count($scheduled); PandaTranslate_Site_Progress::finish($second,44,'es',false); PandaTranslate_Site_Progress::process($second,'es'); assert(count($scheduled)===$before); assert(PandaTranslate_Site_Progress::state()['languages']['es']['processed']===0);
echo "Background cancellation: matching job only, no further scheduling or processing: ok\n";
