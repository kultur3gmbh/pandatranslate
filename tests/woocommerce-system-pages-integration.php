<?php
// Disposable local WordPress/WooCommerce only. No remote translation, license calls or live data writes.
$_SERVER['HTTP_HOST']='127.0.0.1:8768';$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
function system_check($yes,$label){if(!$yes)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
$keys=array(PandaTranslate_Plugin::OPTION,'rewrite_rules','pandatranslate_path_rules');$before=array();foreach($keys as $key)$before[$key]=get_option($key);
$slugs=array('cart'=>'warenkorb','checkout'=>'kasse','myaccount'=>'konto');$meta=array();$ids=array();$flushes=0;
foreach($slugs as $type=>$slug){$ids[$type]=wc_get_page_id($type);$meta[$ids[$type]]=get_post_meta($ids[$type],'_pandatranslate_slug_de',true);}
add_filter('rewrite_rules_array',function($rules)use(&$flushes){$flushes++;return $rules;},999);
try{
 $s=PandaTranslate_Plugin::get_settings();$s['source_language']='en';$s['url_mode']='path';$s['target_languages']='de';$s['languages']=PandaTranslate_Features::normalize_languages(array(array('code'=>'en','active'=>1),array('code'=>'de','active'=>1)),'en');update_option(PandaTranslate_Plugin::OPTION,$s);
 foreach($slugs as $type=>$slug)update_post_meta($ids[$type],'_pandatranslate_slug_de',$slug);
 delete_option('rewrite_rules');delete_option('pandatranslate_path_rules');
 $cart=get_permalink($ids['cart']);
 system_check(strpos(PandaTranslate_Routing::system_url($cart,'de'),'pt_lang=de')!==false,'Before rules exist: source cart query URL, no broken localized link');
 PandaTranslate_Routing::refresh_rules();$first=$flushes;system_check($first===1,'Changed routing state rebuilt once');PandaTranslate_Routing::refresh_rules();system_check($flushes===$first,'Unchanged request does not flush');
 $cosmetic=$s;$cosmetic['languages'][1]['flag_id']=12345;update_option(PandaTranslate_Plugin::OPTION,$cosmetic);PandaTranslate_Routing::refresh_rules();system_check($flushes===$first,'Flag-only change does not flush routes');
 foreach($slugs as $type=>$slug){
  $source=get_permalink($ids[$type]);$path=trim(wp_parse_url($source,PHP_URL_PATH),'/');
  foreach(array('/'.$path.'/','/de/'.$path.'/','/de/'.$slug.'/') as $request){
   $_SERVER['REQUEST_URI']=$request;$_SERVER['PATH_INFO']='';$wp_request=new WP();$wp_request->parse_request();$q=new WP_Query($wp_request->query_vars);
   system_check((int)$q->get_queried_object_id()===(int)$ids[$type] && $q->is_page(),$type.' route resolves as a WordPress page: '.$request);
  }
  system_check(PandaTranslate_Routing::system_url($source,'de')===home_url('/de/'.$slug.'/'),$type.' localized URL ready only after registration');
 }
 foreach(array('myaccount'=>'orders','checkout'=>'order-pay') as $type=>$endpoint){
  $slug=$slugs[$type];$endpointSlug=WC()->query->get_query_vars()[$endpoint];$_SERVER['REQUEST_URI']='/de/'.$slug.'/'.$endpointSlug.'/15/';$w=new WP();$w->parse_request();
  system_check((int)$w->query_vars['page_id']===(int)$ids[$type]&&($w->query_vars[$endpoint]??null)==='15',$type.' endpoint retained');
 }
 update_post_meta($ids['cart'],'_pandatranslate_slug_de','neuer-warenkorb');
 system_check(strpos(PandaTranslate_Routing::system_url($cart,'de'),'pt_lang=de')!==false,'Changed slug falls back until new route is installed');
 PandaTranslate_Routing::refresh_rules();system_check($flushes===$first+1,'Slug change rebuilds rules once');
 system_check(PandaTranslate_Routing::system_url($cart,'de')===home_url('/de/neuer-warenkorb/'),'Changed slug becomes usable');
 PandaTranslate_Routing::refresh_rules();system_check($flushes===$first+1,'No repeated flush after slug change');
 $rules=get_option('rewrite_rules');foreach(array_keys($rules) as $regex)if(strpos(str_replace('\\','',$regex),'neuer-warenkorb')!==false)unset($rules[$regex]);update_option('rewrite_rules',$rules);
 system_check(strpos(PandaTranslate_Routing::system_url($cart,'de'),'pt_lang=de')!==false,'Missing stored rule falls back safely');PandaTranslate_Routing::refresh_rules();system_check($flushes===$first+2,'Missing rule self-heals even with a current signature');
 $s['languages'][1]['slug']='deutsch';update_option(PandaTranslate_Plugin::OPTION,$s);PandaTranslate_Routing::refresh_rules();
 system_check(PandaTranslate_Routing::system_url($cart,'de')===home_url('/deutsch/neuer-warenkorb/'),'Changed language prefix registered automatically');
 $s['url_mode']='query';update_option(PandaTranslate_Plugin::OPTION,$s);PandaTranslate_Routing::refresh_rules();system_check(strpos(PandaTranslate_Routing::system_url($cart,'de'),'pt_lang=de')!==false,'Query format works without a translated path');
 $signed=home_url('/checkout/order-pay/15/?key=synthetic-order-key');system_check(PandaTranslate_Routing::system_url($signed,'de')===$signed,'Signed checkout URL remains exact');

 $repo=new PandaTranslate_Repository();$fixtureKeys=array();
 foreach(array('Products'=>'Produkte','Features'=>'Funktionen','Pricing'=>'Preise','Footer'=>'Fußbereich','Cart'=>'Warenkorb','Private Customer'=>'WRONG PRIVATE') as $source=>$target){
  $key=hash('sha256','woo-system-repair-fixture|'.$source);$fixtureKeys[]=$key;
  $repo->save(array('key'=>$key,'source_hash'=>hash('sha256',$source),'source_html'=>$source,'source_kind'=>'page'), 'de',$target,'published','translatepress-import');
 }
 $blockKey=hash('sha256','gettext|woocommerce-repair-fixture||Cart');$fixtureKeys[]=$blockKey;
 $repo->save(array('key'=>$blockKey,'source_hash'=>hash('sha256','Cart'),'source_html'=>'Cart','source_kind'=>'gettext','source_domain'=>'woocommerce-repair-fixture'),'de','Warenkorb','published','manual');
 $oldQuery=$GLOBALS['wp_query'];$GLOBALS['wp_query']=new WP_Query(array('page_id'=>$ids['cart'],'pt_lang'=>'de'));
 try{
  global $wpdb;$beforeUnits=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.PandaTranslate_Repository::table());
  $html='<html><head><script>var order="Private Customer";</script></head><body><header><nav><a>Products</a><a>Features</a><a>Pricing</a></nav></header><main><h1 class="wp-block-post-title">Cart</h1><div class="wc-block-cart"><p>Private Customer</p></div></main><footer>Footer</footer></body></html>';
  system_check(false===apply_filters('pandatranslate_capture_allowed',true),'Cart capture disabled before render');
  $result=PandaTranslate_Plugin::boot()->transform_page($html);$visible=html_entity_decode($result,ENT_QUOTES|ENT_HTML5,'UTF-8');
  foreach(array('Produkte','Funktionen','Preise','Fußbereich','Warenkorb') as $label)system_check(strpos($visible,$label)!==false,'Transaction public chrome displays published '.$label);
  system_check(strpos($result,'WRONG PRIVATE')===false&&strpos($result,'Private Customer')!==false,'Transaction private content unchanged');
  system_check($beforeUnits===(int)$wpdb->get_var('SELECT COUNT(*) FROM '.PandaTranslate_Repository::table()),'Transaction rendering creates no captured units');
  system_check((PandaTranslate_Strings::block_labels('de')['woocommerce-repair-fixture']['Cart']??'')==='Warenkorb','Published block catalog available without reading cart values');
 }finally{
  $GLOBALS['wp_query']=$oldQuery;foreach($fixtureKeys as $key)$wpdb->delete(PandaTranslate_Repository::table(),array('unit_key'=>$key));
 }
}finally{
 foreach($meta as $id=>$value){if(''===$value)delete_post_meta($id,'_pandatranslate_slug_de');else update_post_meta($id,'_pandatranslate_slug_de',$value);}
 foreach($before as $key=>$value){if(false===$value)delete_option($key);else update_option($key,$value);}
}
