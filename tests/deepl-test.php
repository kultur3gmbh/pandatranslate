<?php
define('ABSPATH',__DIR__);
class WP_Error { public $code; public function __construct($code,$message=''){$this->code=$code;} }
function __($s,$d){return $s;} function is_wp_error($v){return $v instanceof WP_Error;} function apply_filters($hook,$value){return 'test-key:fx';} function wp_json_encode($v){return json_encode($v);} function do_action(...$args){}
class PandaTranslate_Plugin { public static $plan='free';public static $rows=array();public static function get_settings(){return array('deepl_endpoint'=>self::$plan);}public static function language_rows(){return self::$rows;} }
$code=200;$captured=null;
function wp_remote_post($url,$args){global $captured,$code;$captured=array($url,$args);$body=json_decode($args['body'],true);return array('code'=>$code,'body'=>json_encode(array('translations'=>array(array('text'=>str_replace('Hello','Hallo',$body['text'][0]))))));}
function wp_remote_retrieve_response_code($r){return $r['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
require_once __DIR__.'/../includes/interface-pandatranslate-provider.php';require_once __DIR__.'/../includes/class-pandatranslate-deepl-provider.php';
$p=new PandaTranslate_DeepL_Provider(); assert('Hallo [[PT:0:VOID]] & €'===$p->translate('Hello [[PT:0:VOID]] & €','en','de'));
assert('https://api-free.deepl.com/v2/translate'===$captured[0]);assert('DeepL-Auth-Key test-key:fx'===$captured[1]['headers']['Authorization']);assert(0===$captured[1]['redirection']);
$code=456; assert('pandatranslate_deepl_456'===$p->translate('Hello','en','de')->code);$code=429;assert('pandatranslate_deepl_429'===$p->translate('Hello','en','de')->code);
echo "DeepL: fixed Free endpoint, header authentication, no redirects, XML/token/entity round trip, quota and rate-limit failures: ok\n";

$code=200; PandaTranslate_Plugin::$plan='pro'; PandaTranslate_Plugin::$rows=array(array('code'=>'en','locale'=>'en_GB','formality'=>'standard')); $p->translate('Hello [[PG:0]]','de','en',array('requested_language'=>'en')); $body=json_decode($captured[1]['body'],true); assert($captured[0]==='https://api.deepl.com/v2/translate');assert($body['target_lang']==='EN-GB'&&strpos($body['text'][0],'<pt>[[PG:0]]</pt>')!==false);
PandaTranslate_Plugin::$rows=array(array('code'=>'de-de-formal','locale'=>'de_DE','formality'=>'formal'));$p->translate('Hello','en','de',array('requested_language'=>'de-de-formal'));assert(json_decode($captured[1]['body'],true)['formality']==='prefer_more');
echo "DeepL: Pro endpoint, locale-specific English, glossary protection and formality: ok\n";
