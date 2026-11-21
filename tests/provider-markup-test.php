<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/../includes/class-pandatranslate-provider-markup.php';
$input='Pay € & [[PT:0:OPEN]]value[[PT:1:CLOSE]] [[PG:0]]';$p=PandaTranslate_Provider_Markup::protect($input);
assert(strpos($p['text'],'translate="no"')!==false);
assert(PandaTranslate_Provider_Markup::restore($p['text'],$p['tokens'])===$input);
// Attribute identity restores markers even if provider changes their visible label.
$html=str_replace('[[PT:0:OPEN]]','modified label',$p['text']);assert(PandaTranslate_Provider_Markup::restore($html,$p['tokens'])===$input);
assert(is_wp_error(PandaTranslate_Provider_Markup::restore('lost markers',$p['tokens'])));
$plain=PandaTranslate_Provider_Markup::protect('Plain text €');assert(!$plain['tokens']&&$plain['text']==='Plain text €');
echo "Provider markup: format/glossary markers protected and restored, currency/entities retained, missing markers rejected: ok\n";
$nested='Before [[PT:0:OPEN]]bold [[PT:1:OPEN]]nested[[PT:2:CLOSE]][[PT:3:CLOSE]] after [[PG:0]]';
$p=PandaTranslate_Provider_Markup::protect($nested);
assert(strpos($p['text'],'data-pt-open="0" data-pt-close="3"')!==false);
assert(PandaTranslate_Provider_Markup::restore($p['text'],$p['tokens'])===$nested);
assert(PandaTranslate_Provider_Markup::restore(str_replace('[[PG:0]]','provider changed visible text',$p['text']),$p['tokens'])===$nested);
assert(is_wp_error(PandaTranslate_Provider_Markup::restore(str_replace('data-pt-close="3"','data-pt-close="2"',$p['text']),$p['tokens'])));
$empty=PandaTranslate_Inline_Protector::protect('Legal sentence.<span></span>');$p=PandaTranslate_Provider_Markup::protect($empty['text']);
assert(PandaTranslate_Inline_Protector::restore(PandaTranslate_Provider_Markup::restore($p['text'],$p['tokens']),$empty['tokens'])==='Legal sentence.<span></span>');
echo "Provider receives nested format containers; empty format, glossary identity and invalid-pair rejection retained: ok\n";
