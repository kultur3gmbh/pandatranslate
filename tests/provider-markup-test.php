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
