<?php
require __DIR__.'/bootstrap.php';
function wp_kses_post($html){return $html;}
require_once __DIR__.'/../includes/class-pandatranslate-template-protector.php';
$source='<table><tr><td style="color:#333">Hello {first_name},<br><strong>Booking [resa_id]</strong>: {{amount}} %currency% %1$s</td></tr></table>';
$p=PandaTranslate_Template_Protector::protect($source,true);
assert(strpos($p['text'],'{first_name}')===false&&strpos($p['text'],'[resa_id]')===false&&strpos($p['text'],'style=')===false);
assert(PandaTranslate_Template_Protector::restore($p['text'],$p['tokens'],true)===$source);
assert(is_wp_error(PandaTranslate_Template_Protector::restore(str_replace('[[PT:0:OPEN]]','',$p['text']),$p['tokens'],true)));
$plain='Hello {first_name}: [resa_id] {{amount}} %currency% %1$s <unchanged@example.test>';
$p=PandaTranslate_Template_Protector::protect($plain);
assert(PandaTranslate_Template_Protector::restore($p['text'],$p['tokens'])===$plain);
echo "Template protector: block HTML, attributes, curly/double-curly/bracket/named-percent/printf variables, plain email addresses and missing-token refusal: ok\n";
$builder='<div class="fusion-column" style="width:104%;margin-left:calc(-4% / 2)"><a href="https://example.test/a%20b"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%27x%27%3E" alt="Source label"></a><p>Hello <strong>{first_name}</strong></p></div>';
$p=PandaTranslate_Template_Protector::prepare_row(array('source_html'=>$builder));
assert($p['template']==='html'&&strpos($p['text'],'%3C')===false&&strpos($p['text'],'104%')===false);
assert(PandaTranslate_Template_Protector::restore_prepared(str_replace('Hello','Bonjour',$p['text']),$p)===str_replace('Hello','Bonjour',$builder));
$injected=PandaTranslate_Template_Protector::restore_prepared('<img src=x onerror=alert(1)>'.$p['text'],$p);
assert(strpos($injected,'<img src=x')===false&&strpos($injected,'&lt;img')!==false);
assert(!PandaTranslate_Template_Protector::has_text('<a href="https://example.test">')&&!PandaTranslate_Template_Protector::has_text('<div><img alt="Alt text" src="image.png"></div>'));
assert(PandaTranslate_Template_Protector::has_text('<div>Hello</div>'));
echo "Builder structure, encoded image URLs, frozen attributes, injected markup and non-text fragment checks: ok\n";
