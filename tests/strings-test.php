<?php
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A');
function get_option($k){return 3;}
class DB {public function prepare($q,...$args){return $q;}public function get_results($q,$format){global $rows;$result=array();foreach($rows as $key=>$row){$row['unit_key']=$key;$result[]=$row;}return $result;}} $wpdb=new DB();
$admin=false;$rows=array();$saves=0;
function is_admin(){global $admin;return $admin;}function current_user_can($cap){return true;}function __( $s,$d){return $s;}
class PandaTranslate_Frontend{public static function current_language(){return 'de';}}
class PandaTranslate_Edition{public static function allows_language($l){return $l==='de';}}
class PandaTranslate_Repository{public static function table(){return 'wp_units';}public function find($key,$language){global $rows;return $rows[$key]??null;} public function save($unit,$language,$value,$status){global $saves;$saves++;}}
require_once __DIR__.'/../includes/class-pandatranslate-strings.php';
$key=hash('sha256','gettext|woocommerce||Email: %s');$rows[$key]=array('status'=>'published','translated_html'=>'E-Mail: %d');$key=hash('sha256','gettext|theme|button|Send');$rows[$key]=array('status'=>'published','translated_html'=>'Senden');
$key=hash('sha256','gettext|woocommerce||Order %s');$rows[$key]=array('status'=>'published','translated_html'=>'Bestellung %s');
assert('Bestellung %s'===PandaTranslate_Strings::gettext('Order %s','Order %s','woocommerce'));
$key=hash('sha256','gettext|woocommerce||Email: %s');$rows[$key]=array('status'=>'published','translated_html'=>'E-Mail: %d');assert('Email: %s'===PandaTranslate_Strings::gettext('Email: %s','Email: %s','woocommerce'));
$key=hash('sha256','gettext|theme|button|Send');$rows[$key]=array('status'=>'published','translated_html'=>'Senden');assert('Senden'===PandaTranslate_Strings::gettext_context('Send','Send','button','theme'));
assert('Private visitor text'===PandaTranslate_Strings::gettext('Private visitor text','Private visitor text','theme'));assert(0===$saves);
$admin=true;assert('Order %s'===PandaTranslate_Strings::gettext('Order %s','Order %s','woocommerce'));
PandaTranslate_Strings::$email_language='de';assert('Bestellung %s'===PandaTranslate_Strings::gettext('Order %s','Order %s','woocommerce'));
assert(!PandaTranslate_Strings::same_placeholders('%s %d','%d %s'));assert(PandaTranslate_Strings::same_placeholders('%1$s %2$d','%1$s %2$d'));
echo "Static strings: domain/context lookup, published-only dictionary, printf types/order retained, no visitor collection or external calls, admin untouched except explicit mail locale: ok\n";
