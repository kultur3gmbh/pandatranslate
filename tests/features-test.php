<?php
class WP_Error { private $code; public function __construct($code){$this->code=$code;} public function get_error_code(){return $this->code;} }
function __( $text ) { return $text; }
function sanitize_key( $key ) { return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$key)); }
function sanitize_title( $key ) { return sanitize_key($key); }
function sanitize_text_field( $text ) { return trim(strip_tags((string)$text)); }
function wp_generate_uuid4() { return 'test-id'; }
function get_option( $name, $default=array() ) { global $test_glossary; return 'pandatranslate_glossary'===$name ? $test_glossary : $default; }
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/includes/class-pandatranslate-features.php';
$rows=PandaTranslate_Features::normalize_languages(array(array('code'=>'de','active'=>1),array('code'=>'en','slug'=>'english','formality'=>'formal','active'=>1),array('code'=>'en','active'=>1)),'de');
assert(2===count($rows)); assert('de_DE'===$rows[0]['locale']); assert('english'===$rows[1]['slug']);
assert('🇯🇵'===PandaTranslate_Features::flag('ja')); assert('🇧🇷'===PandaTranslate_Features::flag('pt-br')); assert('Bahasa Indonesia'===PandaTranslate_Features::catalog()['id']['name']);
$clean=PandaTranslate_Features::sanitize_glossary(array(array('term'=>'ContactPanda','protect'=>1,'active'=>1),array('term'=>'ContactPanda','protect'=>1)));
assert(1===count($clean));
$test_glossary=$clean; $protected=PandaTranslate_Features::protect_glossary('Use ContactPanda now','en');
assert(false!==strpos($protected['text'],'[[PG:0]]')); assert('Use ContactPanda now'===PandaTranslate_Features::restore_glossary($protected['text'],$protected['tokens']));
echo "languages and glossary: ok\n";
