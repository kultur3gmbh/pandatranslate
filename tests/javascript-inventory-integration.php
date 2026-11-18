<?php
// Isolated local fixture: static files and synthetic labels, no provider requests.
$_SERVER['HTTP_HOST']='127.0.0.1:8768';$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';
$root=getenv('CONTACTPANDA_TEST_WP_ROOT');
if(!$root||!is_file($root.'/.panda-test-fixture'))throw new RuntimeException('Disposable fixture required');
require $root.'/wp-load.php';
function js_check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$before=get_option(PandaTranslate_JavaScript_Inventory::OPTION,false);$keys=array();
try{
 $bundle=file_get_contents(WP_PLUGIN_DIR.'/woocommerce/assets/client/blocks/cart-frontend.js');
 $entries=PandaTranslate_JavaScript_Inventory::literals($bundle);
 js_check(count($entries)>10,'Real WooCommerce cart bundle: static source labels parsed');
 js_check(in_array('Proceed to Checkout',array_column($entries,'text'),true),'Real bundled checkout action discovered');
 $entries=PandaTranslate_JavaScript_Inventory::expand(PandaTranslate_JavaScript_Inventory::literals("wp.i18n._x('Fixture label','js-context','panda-js-fixture'); wp.i18n._n('%d fixture','%d fixtures',count,'panda-js-fixture');"),'de_DE');
 PandaTranslate_JavaScript_Inventory::remember($entries);$repo=new PandaTranslate_Repository();
 foreach($entries as $entry){$key=PandaTranslate_JavaScript_Inventory::key($entry);$keys[]=$key;
  js_check(true===PandaTranslate_Static_Inventory::store($entry,'de',false),'Literal source stored without provider call');
  $row=$repo->find($key,'de');js_check($row['status']==='untranslated','Unknown native translation remains untranslated');
  $target=isset($entry['category'])?('one'===$entry['category']?'%d Beispiel':'%d Beispiele'):'Bezeichnung';
  $repo->save(array('key'=>$key,'source_hash'=>hash('sha256',$entry['text']),'source_html'=>$entry['text'],'source_kind'=>'gettext','source_domain'=>$entry['domain']),'de',$target,'published','manual');
  js_check(false===PandaTranslate_Static_Inventory::store($entry,'de',false),'Repeated source collection preserves published manual text');
 }
 $catalog=PandaTranslate_Strings::script_catalog('de');
 js_check(($catalog['context']['panda-js-fixture']['js-context']['Fixture label']??'')==='Bezeichnung','Stored context reaches frontend catalog exactly');
 $forms=$catalog['plural']['panda-js-fixture'][0]['forms']??array();
 js_check(($forms['one']??'')==='%d Beispiel'&&($forms['other']??'')==='%d Beispiele','Both published German plural forms reach frontend catalog');
}finally{
 global $wpdb;foreach($keys as $key)$wpdb->delete(PandaTranslate_Repository::table(),array('unit_key'=>$key));
 if(false===$before)delete_option(PandaTranslate_JavaScript_Inventory::OPTION);else update_option(PandaTranslate_JavaScript_Inventory::OPTION,$before,false);
}
