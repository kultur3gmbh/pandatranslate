<?php
// Model the initialized WordPress query used by normal frontend requests.
$wp_query = new class { public function get($key) { return get_query_var($key); } };
require __DIR__.'/routing-test.php';
function __($text){return $text;}
function esc_attr($text){return htmlspecialchars($text,ENT_QUOTES);}
function esc_attr__($text){return esc_attr($text);}
function esc_html($text){return esc_attr($text);}
function esc_url($text){return $text;}
function apply_filters($hook,$value){return $value;}
function is_admin(){return false;}
function get_query_var($key){return '';}
function get_locale(){return 'de_DE';}
function wp_get_attachment_image_url(){return '';}
$stored=array('source_language'=>'de','target_languages'=>'en,fr,es','switcher_type'=>'dropdown');$public_counts=array('en'=>12,'fr'=>0);
check(array('de','en'),array_column(PandaTranslate_Plugin::visible_language_rows(),'code'));
$render=new ReflectionMethod('PandaTranslate_Plugin','switcher_markup');
foreach(array('floating','menu','theme-menu') as $context){$html=$render->invoke($plugin,$context);check(false,strpos($html,'hreflang="fr"')!==false);check(false,strpos($html,'hreflang="es"')!==false);check(true,strpos($html,'hreflang="en"')!==false);}
$preview=$render->invoke($plugin,'floating',array('preview'=>true));check(true,strpos($preview,'hreflang="fr"')!==false);check(true,strpos($preview,'hreflang="es"')!==false);
$items=PandaTranslate_Features::hide_current_menu_language(array((object)array('ID'=>1,'url'=>'#pandatranslate:current','menu_item_parent'=>0),(object)array('ID'=>2,'url'=>'#pandatranslate:en','menu_item_parent'=>1),(object)array('ID'=>3,'url'=>'#pandatranslate:fr','menu_item_parent'=>1),(object)array('ID'=>4,'url'=>'#pandatranslate:es','menu_item_parent'=>1)));
check(array(1,2),array_column($items,'ID'));
echo "zero-public-translation languages hidden in floating, menu, builder/shortcode markup and explicit menu entries; source and full admin preview retained: ok\n";
