<?php
require __DIR__.'/routing-test.php';
$premium=false;
function apply_filters($hook,$value){global $premium;return 'pandatranslate_multilingual_available'===$hook?$premium:$value;}
require dirname(__DIR__).'/includes/class-pandatranslate-edition.php';
$before=$stored;
assert(count(array_filter(PandaTranslate_Plugin::language_rows(),function($row){return $row['active'];}))===2);
assert(count(PandaTranslate_Plugin::configured_language_rows())===3);
assert($stored===$before);
assert(!PandaTranslate_Edition::allows_language('fr'));
assert(PandaTranslate_Edition::allows_language('en'));
$meta['_pandatranslate_slug_en']='translated';$_SERVER['REQUEST_URI']='/parent/haus/';$stored['url_mode']='path';$singular=true;
assert(strpos(PandaTranslate_Routing::url('en'),'/en/parent/haus/')!==false);
$premium=true;
assert(count(array_filter(PandaTranslate_Plugin::language_rows(),function($row){return $row['active'];}))===3);
assert(PandaTranslate_Edition::allows_language('fr'));
assert(strpos(PandaTranslate_Routing::url('en'),'/en/parent/translated/')!==false);
$premium=false;assert($stored['target_languages']==='en,fr');
echo "Free: two active languages with original paths; paid: additional languages and translated slugs; deactivate without deleting stored language data: ok\n";
