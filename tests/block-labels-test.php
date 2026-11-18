<?php
require __DIR__.'/strings-test.php';
$rows=array();
foreach(array(array('Cart','Warenkorb',''),array('Products','Produkte','menu'),array('Order %s','Bestellung %d','')) as $label){$key=hash('sha256','gettext|woocommerce|'.$label[2].'|'.$label[0]);$rows[$key]=array('source_html'=>$label[0],'translated_html'=>$label[1],'source_domain'=>'woocommerce','status'=>'published');}
assert(array('woocommerce'=>array('Cart'=>'Warenkorb'))===PandaTranslate_Strings::block_labels('de'));
assert($saves===0);
echo "Woo block catalog: exact source key/domain, no context guessing, placeholder-safe, read-only: ok\n";
