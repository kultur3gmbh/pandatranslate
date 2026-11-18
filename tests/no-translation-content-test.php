<?php
require __DIR__ . '/language-content-test.php';

// The original source-language wrapper must not erase the matching localized block.
$shortcode_tags = array(); PandaTranslate_Language_Content::register();
$stored = array('source_language'=>'en','target_languages'=>'de','languages'=>array(array('code'=>'en','locale'=>'en_US','active'=>1),array('code'=>'de','locale'=>'de_DE','formality'=>'standard','active'=>1)));
$site_options['WPLANG'] = 'en_US';
$style = 'html:lang(de) .pw-lang-en,html:not(:lang(de)) .pw-lang-de{display:none!important}';
$variants = array('data-no-translation','class="pw trp-no-translation"','translate="no"','class="pw notranslate"');
foreach ($variants as $marker) foreach (array('de','en') as $language) {
 $current_language = 'de' === $language ? 'de' : '';
 $manual = do_shortcode('[language-include lang="en_US"]<div class="pw-lang-en"><h2>Manual English</h2><p title="&amp;euro;">Protected English</p></div>[/language-include][language-include lang="de_DE"]<div class="pw-lang-de"><h2>Manuell Deutsch</h2><p title="&amp;euro;">Geschütztes Deutsch</p></div>[/language-include]');
 $html = '<html lang="en"><head><style>'.$style.'</style></head><body><div id="manual" '.$marker.'>'.$manual.'</div><p>Ordinary outside</p></body></html>';
 $units=array();$html=(new PandaTranslate_Unit_Extractor())->transform($html,function($unit)use(&$units){$units[]=$unit;return 'Outside translated';});check(1,count($units));
 $repo=new class {function published_imports($language,$hashes){return array(array('source_html'=>'Manuell Deutsch','translated_html'=>'WRONG'),array('source_html'=>'Manual English','translated_html'=>'WRONG'));}};
 $html=(new PandaTranslate_Translation_Memory($html,$language,$repo))->transform($html);
 $html=PandaTranslate_Frontend::transform($html,$current_language);
 check(false,strpos($html,'WRONG')!==false);check(true,strpos($html,$style)!==false);
 $previous=libxml_use_internal_errors(true);$doc=new DOMDocument();$doc->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($previous);$container=$doc->getElementById('manual');
 check(true,$container instanceof DOMElement);check(false,$container->hasAttribute('hidden'));check(false,strpos($container->getAttribute('style'),'display:none')!==false);
 check($language,$doc->documentElement->getAttribute('lang'));
 check(true,strpos($container->textContent,'de'===$language?'Manuell Deutsch':'Manual English')!==false);
 check(false,strpos($container->textContent,'de'===$language?'Manual English':'Manuell Deutsch')!==false);
 // Encoding fixes remain presentation-only; protected currency glyphs still render correctly.
 check('€',$doc->getElementsByTagName('p')->item(0)->getAttribute('title'));
}
// Legacy class alone, without an include shortcode that independently supplies protection.
$html='<html><body><div class="pw trp-no-translation"><h2>Legacy manual</h2></div><p>Ordinary outside</p></body></html>';
$units=array();$result=(new PandaTranslate_Unit_Extractor())->transform($html,function($unit)use(&$units){$units[]=$unit;return null;});check(1,count($units));check('Ordinary outside',$units[0]['source_html']);
$repo=new class {function published_imports($language,$hashes){return array(array('source_html'=>'Legacy manual','translated_html'=>'WRONG'));}};
check(false,strpos((new PandaTranslate_Translation_Memory($html,'de',$repo))->transform($html),'WRONG')!==false);
// A formal language remains the same regional language for an ordinary locale include.
$stored['languages'][1]['formality']='formal';$current_language='de';
check(true,strpos(do_shortcode('[language-include lang="de_DE"]German regional manual[/language-include]'),'German regional manual')!==false);
check('',do_shortcode('[language-include lang="de_AT"]Wrong regional manual[/language-include]'));
// Nested WordPress/block queries may not carry pt_lang; use the canonical request as fallback.
$stored['languages'][1]['formality']='standard';$current_language='';$wp=(object)array('query_vars'=>array('pt_lang'=>'de'));
check(true,strpos(do_shortcode('[language-include lang="de_DE"]German in nested query[/language-include]'),'German in nested query')!==false);
check('',do_shortcode('[language-include lang="en_US"]English in nested query[/language-include]'));
echo "Manual DE/EN containers: all exclusion markers, unchanged visibility/CSS, correct root language, legacy dictionary protection, formal/regional matching and canonical-query fallback: ok\n";
