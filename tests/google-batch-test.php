<?php
require __DIR__.'/bootstrap.php';
function sanitize_text_field($s){return trim(strip_tags((string)$s));}function sanitize_key($s){return strtolower($s);}function wp_json_encode($v){return json_encode($v);}function apply_filters($h,$v){return $v;}function do_action(){}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
$requests=array();$short=false;
function wp_remote_post($url,$args){global $requests,$short;$body=json_decode($args['body'],true);$requests[]=$body;assert(count($body['q'])<=25);$values=array_map(function($text){return array('translatedText'=>'Translated '.$text);},$body['q']);if($short)array_pop($values);return array('response'=>array('code'=>200),'body'=>json_encode(array('data'=>array('translations'=>$values))));}
class PandaTranslate_Translation_Jobs{public static $cancelled=false;public static function is_cancelled(){return self::$cancelled;}}
define('PANDATRANSLATE_GOOGLE_API_KEY','synthetic-test-key');
require __DIR__.'/../includes/interface-pandatranslate-provider.php';require __DIR__.'/../includes/class-pandatranslate-google-provider.php';
$p=new PandaTranslate_Google_Provider('','global','v2');
$input=array('a'=>'Hello','b'=>'Pay € & < 5 [[PT:0:OPEN]]bold[[PT:1:CLOSE]]','c'=>'Another');
$r=$p->translate_many($input,'de','fr');assert(count($requests)===1);assert(array_keys($r)===array_keys($input));assert($r['b']==='Translated '.$input['b']);assert($requests[0]['format']==='html');
$requests=array();$r=$p->translate_many(array_fill(0,28,'Short source'),'de','en');assert(count($requests)===2&&count($r)===28);
$requests=array();$r=$p->translate_many(array_fill(0,5,str_repeat('x',3000)),'de','en');assert(count($requests)===3);
$short=true;$r=$p->translate_many(array('a'=>'One','b'=>'Two'),'de','en');assert(is_wp_error($r['a'])&&is_wp_error($r['b']));
PandaTranslate_Translation_Jobs::$cancelled=true;$n=count($requests);assert(is_wp_error($p->translate_many(array('Source'),'de','en')));assert(count($requests)===$n);
echo "Google v2 batches: one request for multiple units, stable keys, mixed markup/entities, count/size bounds, response-count rejection and cancellation: ok\n";
