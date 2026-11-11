<?php
if (!function_exists('apply_filters')) { function apply_filters($hook,$value,...$args){return $value;} }
require __DIR__.'/routing-test.php';
class WP_Error{private $code,$message;function __construct($code,$message='',$data=null){$this->code=$code;$this->message=$message;}function get_error_code(){return $this->code;}}
function is_wp_error($value){return $value instanceof WP_Error;}
function __($value){return $value;}
function get_locale(){return 'fr_FR';} // An administrator's language must not select the website source.
require dirname(__DIR__).'/includes/class-pandatranslate-source-language.php';
require dirname(__DIR__).'/includes/class-pandatranslate-migration.php';
$site_options['WPLANG']='en_US';$site_options['trp_settings']=array('default-language'=>'en_US');
assert(PandaTranslate_Plugin::defaults()['source_language']==='en');
assert(PandaTranslate_Source_Language::mismatch(PandaTranslate_Plugin::configured_language_rows()));
assert(is_wp_error(PandaTranslate_Migration::preflight()));
$rows=PandaTranslate_Plugin::configured_language_rows();
$attempt=$plugin->sanitize_settings(array('source_language'=>'fr','languages'=>$rows));assert($attempt['source_language']==='de');
$aligned=$plugin->sanitize_settings(array('languages'=>$rows,'adopt_wordpress_source'=>1));
assert($aligned['source_language']==='en');assert(count($aligned['languages'])===3);assert($aligned['languages'][0]['code']==='en');
$stored=$aligned;assert(PandaTranslate_Migration::preflight()===true);
$site_options['trp_settings']['default-language']='de_DE';assert(is_wp_error(PandaTranslate_Migration::preflight()));
$site_options['WPLANG']='de_DE_formal';assert(PandaTranslate_Source_Language::wordpress_code()==='de-de-formal');
$site_options['WPLANG']='pt_BR';assert(PandaTranslate_Source_Language::wordpress_code()==='pt-br');
echo "Website source inherits WordPress (not admin locale); arbitrary changes blocked, legacy changes explicitly confirmed without removing rows, and incompatible migrations refused: ok\n";
