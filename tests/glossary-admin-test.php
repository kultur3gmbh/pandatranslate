<?php
require __DIR__.'/bootstrap.php';
function sanitize_key($value){return preg_replace('/[^a-z0-9_-]/','',strtolower($value));}
function sanitize_text_field($value){return trim(strip_tags($value));}
function wp_generate_uuid4(){static $id=0;return 'entry-'.++$id;}
$stored_entries=array();
function get_option($key,$default=array()){global $stored_entries;return $stored_entries;}
function update_option($key,$value,$autoload=false){global $stored_entries;$stored_entries=$value;return true;}
function rest_ensure_response($result){return $result;}
class WP_REST_Request extends ArrayObject {private $method;public function __construct($method,$data){parent::__construct($data);$this->method=$method;}public function get_method(){return $this->method;}public function get_json_params(){return $this->getArrayCopy();}}
require dirname(__DIR__).'/includes/class-pandatranslate-features.php';
require dirname(__DIR__).'/includes/class-pandatranslate-glossary-admin.php';
$result=PandaTranslate_Glossary_Admin::save(new WP_REST_Request('POST',array('term'=>'<b>Panda</b>','translations'=>array('es'=>'Panda español'),'active'=>1,'protect'=>0,'whole_word'=>1)));
assert($result['entries'][0]['term']==='Panda');$id=$result['entries'][0]['id'];
assert(count($stored_entries)===1);
$duplicate=PandaTranslate_Glossary_Admin::save(new WP_REST_Request('POST',array('term'=>'PANDA')));assert(is_wp_error($duplicate));assert(count($stored_entries)===1);
$result=PandaTranslate_Glossary_Admin::save(new WP_REST_Request('POST',array('id'=>$id,'term'=>'Panda','translations'=>array('es'=>'Marca Panda'),'active'=>1,'protect'=>1)));
assert($result['entries'][0]['translations']['es']==='Marca Panda');assert($result['entries'][0]['protect']===1);assert(count($stored_entries)===1);
assert(is_wp_error(PandaTranslate_Glossary_Admin::save(new WP_REST_Request('POST',array('term'=>'  ')))));
assert(is_wp_error(PandaTranslate_Glossary_Admin::save(new WP_REST_Request('POST',array('id'=>'missing','term'=>'Panda')))));
$result=PandaTranslate_Glossary_Admin::save(new WP_REST_Request('DELETE',array('id'=>$id)));assert($result['entries']===array());assert($stored_entries===array());
assert(is_wp_error(PandaTranslate_Glossary_Admin::save(new WP_REST_Request('DELETE',array('id'=>$id)))));
echo "glossary popup persistence: create, sanitize, reject duplicates, update, delete and missing-entry protection: ok\n";
