<?php
require __DIR__.'/page-translation-test.php';
class PandaTranslate_Strings {public static function dictionary($language){return array('Products'=>'Produkte','Features'=>'Funktionen','Pricing'=>'Preise','Footer'=>'Fußbereich','Private Customer'=>'WRONG PRIVATE','Hidden private'=>'WRONG HIDDEN');}public static function block_labels($language){return array('woocommerce'=>array('Cart'=>'Warenkorb'));}}
$html='<html><head><script>var privateOrder="Private Customer";</script></head><body><header><nav><a href="/products/">Products</a><a>Features</a><a>Pricing</a></nav><form><span>Private Customer</span></form><div class="wc-block-cart"><p>Hidden private</p></div></header><main><h1>Cart</h1><div class="wc-block-cart"><p>Private Customer</p><form><input value="Private Customer"></form></div></main><footer><p>Footer</p></footer></body></html>';
$beforeWrites=count($repository->writes);$result=PandaTranslate_Frontend::public_chrome($html,'de');$visible=html_entity_decode($result,ENT_QUOTES|ENT_HTML5,'UTF-8');
foreach(array('Produkte','Funktionen','Preise','Fußbereich') as $label)check(true,strpos($visible,$label)!==false);
check(false,strpos($result,'WRONG')!==false);check(true,strpos($result,'Private Customer')!==false);check(true,strpos($result,'<h1>Cart</h1>')!==false);check(true,strpos($result,'var privateOrder="Private Customer";')!==false);
check($beforeWrites,count($repository->writes));
echo "Transaction chrome: published header/footer labels display, no capture/write, main/forms/cart/private values and scripts remain intact: ok\n";
