<?php
// Model the initialized WordPress query used by normal frontend requests.
$wp_query = new class { public function get($key) { return get_query_var($key); } };
require __DIR__.'/routing-test.php';
require dirname(__DIR__).'/includes/class-pandatranslate-frontend.php';
require dirname(__DIR__).'/includes/class-pandatranslate-language-content.php';
require dirname(__DIR__).'/includes/class-pandatranslate-unit-extractor.php';
require dirname(__DIR__).'/includes/class-pandatranslate-translation-memory.php';
// Official WordPress shortcode parser; only unrelated WordPress dependencies are stubbed.
require __DIR__.'/fixtures/wordpress-shortcodes.php';
function is_admin(){return false;}
function get_query_var($key){global $current_language;return $current_language;}
function esc_attr($value){return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
function wp_strip_all_tags($value){return strip_tags($value);}
function wp_kses_post($value){return $value;}
function has_filter(){return false;}
function add_filter(){return true;}
function remove_filter(){return true;}
function apply_filters($hook,$value){return $value;}
function wp_html_split($html){return preg_split('/(<[^>]*>)/',$html,-1,PREG_SPLIT_DELIM_CAPTURE);}
function _doing_it_wrong(){throw new RuntimeException('Unexpected invalid WordPress shortcode');}
$shortcode_tags=[];PandaTranslate_Language_Content::register();
$stored=['source_language'=>'en','target_languages'=>'de,fr','languages'=>[['code'=>'en','active'=>1],['code'=>'de','active'=>1],['code'=>'fr','active'=>1]]];
$site_options['WPLANG']='en_US';$current_language='de';
$content='[language-include lang="en_US"]<h2>English original</h2>[/language-include][language-include lang="de_DE"]<h2>Redaktioneller deutscher Text</h2>[/language-include]';
$de=do_shortcode($content);check(false,strpos($de,'English original')!==false);check(true,strpos($de,'Redaktioneller deutscher Text')!==false);check(false,strpos($de,'[language-')!==false);
check(true,strpos($de,'data-no-translation')!==false);
$current_language='';$en=do_shortcode($content);check(true,strpos($en,'English original')!==false);check(false,strpos($en,'Redaktioneller deutscher Text')!==false);
$current_language='de';
check('',do_shortcode('[language-exclude lang="de_DE,fr_FR"]Excluded[/language-exclude]'));
check('Visible',do_shortcode('[language-exclude lang="en_US"]Visible[/language-exclude]'));
check('',do_shortcode('[trp_language language="en_US"]English[/trp_language]'));
check(true,strpos(do_shortcode('[trp_language language="de_DE"]Deutsch[/trp_language]'),'Deutsch')!==false);
check(true,strpos(do_shortcode('[language-include lang="DE-de,fr_FR"]Deutsch[/language-include]'),'Deutsch')!==false);
check(true,strpos(do_shortcode('[language-include lang="de"]Deutsch[/language-include]'),'Deutsch')!==false);
check('',do_shortcode('[language-include lang="de_AT"]Austrian only[/language-include]'));
check('Opt-in',do_shortcode('[language-include lang="de_DE" enable_translation="yes"]Opt-in[/language-include]'));
check(true,strpos(do_shortcode('[language-exclude lang="fr_FR" enable_translation="no"]Keep original[/language-exclude]'),'data-no-translation')!==false);
check(true,strpos(do_shortcode('[language-include lang="de_DE"][language-exclude lang="en_US"]Nested[/language-exclude][/language-include]'),'Nested')!==false);
$units=[];$extractor=new PandaTranslate_Unit_Extractor();$page=$extractor->transform('<html><body>'.$de.'<div data-no-translation><p>Protected legacy content</p></div><p>Translate this outside text</p></body></html>',function($unit)use(&$units){$units[]=$unit;return null;});check(1,count($units));check('Translate this outside text',$units[0]['source_html']);
$repo=new class{function published_imports($language,$hashes){return [['source_html'=>'Redaktioneller deutscher Text','translated_html'=>'WRONG OVERRIDE'],['source_html'=>'Protected legacy content','translated_html'=>'WRONG OVERRIDE']];}};
$memory=new PandaTranslate_Translation_Memory($page,'de',$repo);check(false,strpos($memory->transform($page),'WRONG OVERRIDE')!==false);
$shortcode_tags['language-include']=function(){return 'Existing provider';};PandaTranslate_Language_Content::register();check('Existing provider',do_shortcode('[language-include lang="de_DE"]Content[/language-include]'));
echo "Official WordPress shortcode parser: locale-specific include/exclude/legacy content, mixed nesting, no duplicate languages, localized/manual block protection, explicit translation opt-in and existing-provider coexistence: ok\n";
