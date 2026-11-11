<?php
define('ABSPATH', __DIR__); define('ARRAY_A', 'ARRAY_A');
function is_admin(){return false;} function sanitize_key($s){return strtolower((string)$s);} function wp_unslash($s){return $s;}
function get_query_var($k){throw new RuntimeException('The query is not initialized during plugins_loaded.');}
function get_option($k){return 0;} function current_user_can($cap){return false;}
class PandaTranslate_Plugin {
 public static $nested = false;
 public static function language_rows(){
  if (self::$nested) { self::$nested=false; assert(PandaTranslate_Strings::gettext('Nested label','Nested label','theme')==='Nested label'); }
  return array(array('code'=>'fr','active'=>1));
 }
}
class PandaTranslate_Edition {public static function allows_language($l){return true;}}
class PandaTranslate_Repository {}
require __DIR__.'/../includes/class-pandatranslate-frontend.php';
require __DIR__.'/../includes/class-pandatranslate-strings.php';
assert(PandaTranslate_Frontend::current_language()==='');
assert(PandaTranslate_Strings::gettext('Early label','Early label','theme')==='Early label');
$_GET['pt_lang']='fr'; PandaTranslate_Plugin::$nested=true;
assert(PandaTranslate_Strings::gettext('Outer label','Outer label','theme')==='Outer label');
unset($_GET['pt_lang']);
$wp_query=new class {public function get($key){return 'fr';}};
assert(PandaTranslate_Frontend::current_language()==='fr');
echo "Frontend bootstrap: early gettext without query, nested language filters and resolved query: ok\n";
