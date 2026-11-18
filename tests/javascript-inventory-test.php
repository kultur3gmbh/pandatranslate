<?php
define('ABSPATH',__DIR__);require dirname(__DIR__).'/includes/class-pandatranslate-javascript-inventory.php';
$js= <<<'JS'
import { __ as translate, _x as contextual } from '@wordpress/i18n';
translate('Cart','woocommerce');contextual('Cart','block title','woocommerce');
const {__:minified,_n:plural} = window.wp.i18n;
minified('Products','woocommerce');(0,x.__)('Pricing','theme');
wp.i18n._nx('%d item','%d items',count,'cart count','woocommerce');
plural('%d product','%d products',count,'woocommerce');
__('Ignore '+customer,'theme');__('Ignore',dynamicDomain);__('Own UI','pandatranslate');
__('€ \u00e4 \uD83D\uDC3C \x21','theme');__('Line\nnext','theme');
// __('Comment','theme');
const code="__('Quoted fake','theme')"; const regex=/__\('Regex fake','theme'\)/;
const template=`Dynamic ${ customer }`;__(template,'theme');
__(`Literal template`,'theme');__(`Dynamic ${customer}`,'theme');
JS;
$found=PandaTranslate_JavaScript_Inventory::literals($js);$texts=array_column($found,'text');
foreach(array('Cart','Products','Pricing','%d item','%d product','€ ä 🐼 !',"Line\nnext",'Literal template') as $text)assert(in_array($text,$texts,true),$text);
foreach(array('Comment','Quoted fake','Regex fake','Own UI','Ignore','Dynamic ${customer}') as $text)assert(!in_array($text,$texts,true),$text);
assert(count(array_filter($found,function($e){return $e['text']==='Cart';}))===2);
$plural=array_values(array_filter($found,function($e){return isset($e['plural']);}));assert(count($plural)===2);
assert(count(PandaTranslate_JavaScript_Inventory::expand(array($plural[0]),'ar'))===6);
assert(count(PandaTranslate_JavaScript_Inventory::expand(array($plural[0]),'de_DE'))===2);
$large=str_repeat('const irrelevant={field:123,value:"safe"};',30000)."wp.i18n.__('Large bundle','theme');";
assert(in_array('Large bundle',array_column(PandaTranslate_JavaScript_Inventory::literals($large),'text'),true));
echo "JavaScript inventory: imports/minified aliases, wrapped calls, contexts, plurals, Unicode, comments/regex/dynamic exclusions, 1MB bundle with bounded token memory: ok\n";

assert(PandaTranslate_JavaScript_Inventory::categories('pt_PT')['one'] === 1);
assert(PandaTranslate_JavaScript_Inventory::categories('pt_BR')['one'] === 0);
