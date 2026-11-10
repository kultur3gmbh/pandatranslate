<?php
require __DIR__ . '/bootstrap.php';
define( 'PANDATRANSLATE_VERSION', '0.4.5' ); define( 'MINUTE_IN_SECONDS', 60 );
$stored = array( 'source_language'=>'de', 'target_languages'=>'en,es', 'auto_post_types'=>array('page'), 'auto_provider'=>'other-api', 'auto_publish'=>1 ); $transients=array(); $calls=array(); $scan_valid=true;
function get_option( $key, $default=array() ) { global $stored; return 'pandatranslate_settings'===$key?$stored:$default; }
function wp_parse_args( $args, $defaults ) { return array_merge($defaults,$args); }
function sanitize_key( $value ) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',$value)); }
function sanitize_title( $value ) { return sanitize_key($value); }
function sanitize_text_field( $value ) { return trim(strip_tags((string)$value)); }
function current_time() { return '2026-11-10 22:00:00'; }
function absint( $value ) { return abs((int)$value); }
function get_posts( $args ) { return empty($args['offset'])?array(42):array(); }
function get_permalink() { return 'https://example.com/source/'; }
function wp_generate_password() { return 'local-scan-token'; }
function set_transient( $key,$value,$ttl ) { global $transients;$transients[$key]=$value; }
function get_transient( $key ) { global $transients;return $transients[$key]??false; }
function delete_transient( $key ) { global $transients;unset($transients[$key]); }
function add_query_arg( $args,$url ) { return $url.'?'.http_build_query($args); }
function wp_remote_retrieve_response_code( $response ) { return 200; }
function wp_remote_retrieve_header( $response,$name ) { global $scan_valid;return $scan_valid?'1':''; }
function wp_safe_remote_get( $url,$options ) { global $wpdb,$transients;assert(strpos($url,'pt_lang=es')!==false);assert(!empty($transients['pandatranslate_scan_local-scan-token']['collect_only']));$repository=new PandaTranslate_Repository();$repository->save(array('key'=>hash('sha256','new-page'),'source_hash'=>hash('sha256','New page text'),'source_html'=>'New page text'),'es','','untranslated');return array(); }
function apply_filters( $hook,$value ) { if('pandatranslate_providers'===$hook)return array('mock-api'=>array('label'=>'Chosen API','provider'=>new Job_Test_Provider('chosen')),'other-api'=>array('label'=>'Other API','provider'=>new Job_Test_Provider('other')));return $value; }
require dirname(__DIR__).'/includes/interface-pandatranslate-provider.php';
require dirname(__DIR__).'/includes/class-pandatranslate-google-provider.php';
require dirname(__DIR__).'/includes/class-pandatranslate-providers.php';
require dirname(__DIR__).'/includes/class-pandatranslate-translation-jobs.php';
require dirname(__DIR__).'/includes/class-pandatranslate-repository.php';
require dirname(__DIR__).'/includes/class-pandatranslate-features.php';
require dirname(__DIR__).'/includes/class-pandatranslate-plugin.php';
final class Job_Test_Provider implements PandaTranslate_Provider { private $name;public function __construct($name){$this->name=$name;}public function id(){return $this->name;}public function translate($text,$source,$target,array $context=array()){global $calls;$calls[]=$this->name;assert($target==='es');return $this->name.': '.$text;} }
final class Job_Test_DB {
	public $prefix='wp_',$rows=array(),$last_error='';
	public function prepare($sql,...$args){return array($sql,count($args)===1&&is_array($args[0])?$args[0]:$args);}
	public function get_row($statement,$format){[$sql,$args]=$statement;foreach($this->rows as $row)if($row['unit_key']===$args[0]&&$row['target_language']===$args[1])return $row;return null;}
	public function insert($table,$data){$data['id']=count($this->rows)+1;$this->rows[$data['id']]=$data;return 1;}
	public function update($table,$data,$where){$this->rows[$where['id']]=array_merge($this->rows[$where['id']],$data);return 1;}
	public function get_var($statement){[$sql,$args]=$statement;if(strpos($sql,'COUNT(*)')!==false)return count(array_filter($this->rows,function($row)use($args){return $row['target_language']===$args[0]&&$row['id']<=$args[1]&&('untranslated'===$row['status']||''===$row['translated_html']);}));$rows=array_filter($this->rows,function($row)use($args){return $row['target_language']===$args[0]&&(!isset($args[1])||$row['source_hash']===$args[1]);});return strpos($sql,'MAX(id)')!==false?($rows?max(array_keys($rows)):0):($rows?reset($rows)['id']:null);}
	public function get_results($statement,$format){[$sql,$args]=$statement;if(strpos($sql,'canonical')!==false){$seen=array();$rows=array();foreach($this->rows as $row){if(isset($seen[$row['source_hash']]))continue;$seen[$row['source_hash']]=true;if($row['id']>$args[0])$rows[]=$row;}return array_slice($rows,0,250);}if(strpos($sql,'SELECT id,unit_key')!==false){return array_values(array_filter($this->rows,function($row)use($args){return $row['target_language']===$args[0]&&$row['id']>$args[1]&&$row['id']<=$args[2]&&('untranslated'===$row['status']||''===$row['translated_html']);}));}throw new RuntimeException('Unexpected SQL: '.$sql);}
}
define('ARRAY_A','ARRAY_A');$wpdb=new Job_Test_DB();$repository=new PandaTranslate_Repository();
foreach(array(array('en','Old imported text','Old translation','translatepress-import'),array('es','Manual source','Manual text','manual')) as $row)$repository->save(array('key'=>hash('sha256',$row[1]),'source_hash'=>hash('sha256',$row[1]),'source_html'=>$row[1]),$row[0],$row[2],'published',$row[3]);
assert(count(PandaTranslate_Providers::choices())===2);
assert(PandaTranslate_Providers::default_id()==='other-api');
$state=array('language'=>'es','api'=>'mock-api','mode'=>'missing');$phases=array();
for($i=0;$i<10;$i++){$result=PandaTranslate_Translation_Jobs::batch($state);assert(!is_wp_error($result));$phases[]=$result['phase'];$state=array_merge($state,$result);if($result['done'])break;}
assert($result['done']);assert($result['total_units']===2);assert($result['processed']===2);assert(in_array('collect',$phases,true)&&in_array('prepare',$phases,true)&&in_array('translate',$phases,true));
assert($calls===array('chosen','chosen'));assert($result['success']===2);
assert($repository->find(hash('sha256','Manual source'),'es')['translated_html']==='Manual text');
assert($repository->find(hash('sha256','Old imported text'),'es')['status']==='published');
assert($repository->find(hash('sha256','Old imported text'),'es')['provider']==='api:mock-api');
$scan_valid=false;$result=PandaTranslate_Translation_Jobs::batch(array('language'=>'es','api'=>'mock-api','phase'=>'collect'));
assert(is_wp_error($result));assert(count($calls)===2);
assert(is_wp_error(PandaTranslate_Providers::get('deleted-api')));
echo "new-language jobs: collect, seed imported sources, selected API pinned over default, preserve manual translations, publish setting and failed scan detection: ok\n";
