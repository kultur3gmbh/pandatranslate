<?php
// Disposable local WordPress/WooCommerce only. No remote translation, license calls or live data writes.
$_SERVER['HTTP_HOST']='127.0.0.1:8768';$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
function system_check($yes,$label){if(!$yes)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
// Minimal Event Tickets contract. No attendees, orders, or network requests are needed.
class Tribe__Tickets__Attendee_Registration__Main {
 public $on_page = false;
 public function get_url() { return home_url( '/attendee-registration/' ); }
 public function is_on_page() { return $this->on_page; }
}
function tribe( $service ) {
 static $registration;
 if ( 'tickets.attendee_registration' !== $service ) throw new LogicException( $service );
 return $registration ?? ( $registration = new Tribe__Tickets__Attendee_Registration__Main() );
}
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
 wp_cache_delete( 'rewrite_rules', 'options' );
 wp_cache_delete( 'alloptions', 'options' );
 PandaTranslate_Routing::refresh_rules();
 system_check( $flushes === $first && PandaTranslate_Routing::system_url( $cart, 'de' ) === home_url( '/de/warenkorb/' ), 'Clearing the options cache retains the persisted route without another flush' );
 update_option( 'pandatranslate_path_rules', 'signature-from-an-earlier-version' );
 PandaTranslate_Routing::refresh_rules();
 ++$first;
 system_check( $flushes === $first, 'An outdated routing signature is rebuilt once after plugin update' );
 update_post_meta($ids['cart'],'_pandatranslate_slug_de','neuer-warenkorb');
 system_check(strpos(PandaTranslate_Routing::system_url($cart,'de'),'pt_lang=de')!==false,'Changed slug falls back until new route is installed');
 PandaTranslate_Routing::refresh_rules();system_check($flushes===$first+1,'Slug change rebuilds rules once');
 system_check(PandaTranslate_Routing::system_url($cart,'de')===home_url('/de/neuer-warenkorb/'),'Changed slug becomes usable');
 PandaTranslate_Routing::refresh_rules();system_check($flushes===$first+1,'No repeated flush after slug change');
 $rules=get_option('rewrite_rules');foreach(array_keys($rules) as $regex)if(strpos(str_replace('\\','',$regex),'neuer-warenkorb')!==false)unset($rules[$regex]);update_option('rewrite_rules',$rules);
 system_check(strpos(PandaTranslate_Routing::system_url($cart,'de'),'pt_lang=de')!==false,'Missing stored rule falls back safely');PandaTranslate_Routing::refresh_rules();system_check($flushes===$first+2,'Missing rule self-heals even with a current signature');
 foreach ( array( 'cart' => 'neuer-warenkorb', 'checkout' => 'kasse', 'myaccount' => 'konto' ) as $type => $slug ) {
  $stale = new WP(); $stale->request = 'de/' . $slug; $stale->query_vars = array( 'error' => '404', 'pagename' => $slug );
  PandaTranslate_Routing::resolve_translated_slug( $stale );
  system_check( $ids[$type] === (int) ( $stale->query_vars['page_id'] ?? 0 ) && 'de' === ( $stale->query_vars['pt_lang'] ?? '' ) && ! isset( $stale->query_vars['error'] ) && ! isset( $stale->query_vars['pagename'] ), $type . ' recovers the route even when WordPress loses the language and the stored rule' );
 }
 $bad = new WP(); $bad->request = 'de/neuer-warenkorb/unknown'; $bad->query_vars = array( 'error' => '404' );
 PandaTranslate_Routing::resolve_translated_slug( $bad );
 system_check( $bad->query_vars === array( 'error' => '404' ), 'Unknown cart paths still return 404' );
 $s['languages'][1]['slug']='deutsch';update_option(PandaTranslate_Plugin::OPTION,$s);PandaTranslate_Routing::refresh_rules();
 system_check(PandaTranslate_Routing::system_url($cart,'de')===home_url('/deutsch/neuer-warenkorb/'),'Changed language prefix registered automatically');
 $s['url_mode']='query';update_option(PandaTranslate_Plugin::OPTION,$s);PandaTranslate_Routing::refresh_rules();system_check(strpos(PandaTranslate_Routing::system_url($cart,'de'),'pt_lang=de')!==false,'Query format works without a translated path');
 $signed=home_url('/checkout/order-pay/15/?key=synthetic-order-key');system_check(PandaTranslate_Routing::system_url($signed,'de')===$signed,'Signed checkout URL remains exact');

 $s['url_mode'] = 'path'; $s['languages'][1]['slug'] = 'de';
 update_option( PandaTranslate_Plugin::OPTION, $s );
 delete_post_meta( $ids['cart'], '_pandatranslate_slug_de' );
 PandaTranslate_Routing::refresh_rules();
 system_check( PandaTranslate_Routing::system_url( $cart, 'de' ) === home_url( '/de/cart/' ), 'With no translated slug, the original cart slug keeps its German language prefix' );
 update_post_meta( $ids['cart'], '_pandatranslate_slug_de', 'warenkorb' );
 PandaTranslate_Routing::refresh_rules();
 wc_load_cart();
 $original_cart = WC()->cart->get_cart();
 $product = new WC_Product_Simple(); $product->set_name( 'Private Customer' );
 $cart_states = array( 'empty' => array(), 'filled' => array( 'synthetic' => array( 'data' => $product, 'quantity' => 1 ) ) );

 $repo=new PandaTranslate_Repository();$fixtureKeys=array();
 foreach(array('Products'=>'Produkte','Features'=>'Funktionen','Pricing'=>'Preise','Footer'=>'Fußbereich','Cart'=>'Warenkorb','Private Customer'=>'WRONG PRIVATE','Language, currency and account'=>'Sprache, Währung und Konto','Compare &amp; learn'=>'Vergleichen &amp; lernen') as $source=>$target){
  $key=hash('sha256','woo-system-repair-fixture|'.$source);$fixtureKeys[]=$key;
  $repo->save(array('key'=>$key,'source_hash'=>hash('sha256',$source),'source_html'=>$source,'source_kind'=>'page'), 'de',$target,'published','translatepress-import');
 }
 $blockKey=hash('sha256','gettext|woocommerce-repair-fixture||Cart');$fixtureKeys[]=$blockKey;
 $repo->save(array('key'=>$blockKey,'source_hash'=>hash('sha256','Cart'),'source_html'=>'Cart','source_kind'=>'gettext','source_domain'=>'woocommerce-repair-fixture'),'de','Warenkorb','published','manual');
 $oldQuery=$GLOBALS['wp_query'];$GLOBALS['wp_query']=new WP_Query(array('page_id'=>$ids['cart'],'pt_lang'=>'de'));
 try{
  global $wpdb;$beforeUnits=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.PandaTranslate_Repository::table());
  foreach ( array( 'cart' => 'woocommerce-cart', 'checkout' => 'woocommerce-checkout', 'myaccount' => 'woocommerce-account' ) as $type => $body_class ) {
   $GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $ids[$type], 'pt_lang' => 'de' ) );
   system_check( false === apply_filters( 'pandatranslate_capture_allowed', true ), $type . ' capture disabled before render' );
   foreach ( $cart_states as $cart_state => $cart_contents ) {
    WC()->cart->set_cart_contents( $cart_contents );
    system_check( ( 'empty' === $cart_state ) === WC()->cart->is_empty(), $type . ' uses the expected WooCommerce cart state' );
    $body = WC()->cart->is_empty() ? '' : '<p>' . esc_html( WC()->cart->get_cart_item( 'synthetic' )['data']->get_name() ) . '</p>';
    $html='<html><head><script>var order="Private Customer";</script></head><body class="' . $body_class . '"><header aria-label="Language, currency and account"><nav><a>Products</a><a>Features</a><a>Pricing</a></nav><div class="wc-block-mini-cart__drawer" aria-label="Private Customer">' . $body . '</div></header><main><h1 class="wp-block-post-title">Cart</h1><div class="wc-block-cart">' . $body . '</div></main><footer>Footer <span>Compare &amp; learn</span></footer></body></html>';
    $result = PandaTranslate_Plugin::boot()->transform_page( $html );
    $visible = html_entity_decode( $result, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    foreach ( array( 'Produkte', 'Funktionen', 'Preise', 'Fußbereich', 'Warenkorb', 'aria-label="Sprache, Währung und Konto"', 'Vergleichen & lernen' ) as $label ) system_check( false !== strpos( $visible, $label ), $type . ' ' . $cart_state . ' cart translates public chrome: ' . $label );
    system_check( false === strpos( $result, 'WRONG PRIVATE' ) && false !== strpos( $result, 'aria-label="Private Customer"' ), $type . ' ' . $cart_state . ' cart preserves private values' );
    if ( 'checkout' === $type ) {
     $redirect = ''; $levels = ob_get_level();
     $intercept = static function ( $location ) use ( &$redirect ) { $redirect = $location; throw new LogicException( 'Intercept the fixture checkout redirect' ); };
     add_filter( 'wp_redirect', $intercept );
     try { wc_template_redirect(); }
     catch ( LogicException $error ) { if ( ! $redirect ) throw $error; }
     finally { remove_filter( 'wp_redirect', $intercept ); while ( ob_get_level() > $levels ) ob_end_clean(); }
     system_check( $redirect === ( 'empty' === $cart_state ? home_url( '/de/warenkorb/' ) : '' ), 'Checkout ' . $cart_state . ' cart preserves the German destination' );
    }
   }
  }
  system_check($beforeUnits===(int)$wpdb->get_var('SELECT COUNT(*) FROM '.PandaTranslate_Repository::table()),'Transaction rendering creates no captured units');
  system_check((PandaTranslate_Strings::block_labels('de')['woocommerce-repair-fixture']['Cart']??'')==='Warenkorb','Published block catalog available without reading cart values');
  $registration = tribe( 'tickets.attendee_registration' );
  $provider_query = '?tickets_provider=tribe_wooticket&step=details';
  $redirect = $registration->get_url() . $provider_query;
  $localized = home_url( '/de/attendee-registration/' ) . $provider_query;
  system_check( apply_filters( 'wp_redirect', $redirect ) === $localized, 'Event Tickets redirect keeps the language and all parameters' );
  system_check( apply_filters( 'wp_redirect', $localized ) === $localized, 'An already localized registration URL is stable' );
  $external = 'https://example.org/attendee-registration/' . $provider_query;
  system_check( apply_filters( 'wp_redirect', $external ) === $external, 'External registration URLs are untouched' );
  $registration->on_page = true;
  system_check( ! apply_filters( 'pandatranslate_capture_allowed', true ), 'Attendee registration is excluded from translation capture' );
  $registration->on_page = false;

  $account_url = PandaTranslate_Routing::system_url( get_permalink( $ids['myaccount'] ), 'de' );
  $account_title = get_post_field( 'post_title', $ids['myaccount'] );
  $avada_links = '<li class="avada-menu-login-box"><a href="' . esc_url( $account_url ) . '"><span class="menu-text">' . esc_html( $account_title ) . '</span></a></li>';
  $avada_links .= '<li class="avada-menu-login-box"><a href="' . esc_url( $account_url ) . '"><span class="menu-text">Custom label</span></a></li>';
  $avada_links .= '<li class="avada-menu-login-box"><a href="https://example.org/de/konto/"><span class="menu-text">' . esc_html( $account_title ) . '</span></a></li>';
  $avada = '<html><body><nav><ul>' . $avada_links . '</ul></nav></body></html>';
  $woo_label = static function ( $translation, $text, $context, $domain ) { return array( 'My account', 'Page title', 'woocommerce' ) === array( $text, $context, $domain ) ? 'Mein Konto' : $translation; };
  add_filter( 'gettext_with_context', $woo_label, 99, 4 );
  try {
   $translated = PandaTranslate_Frontend::transform( $avada, 'de' );
   system_check( 1 === substr_count( $translated, '>Mein Konto<' ) && false !== strpos( $translated, '>Custom label<' ) && false !== strpos( $translated, 'https://example.org/de/konto/' ), 'Avada localizes only the original label of the configured WooCommerce account link' );
  } finally { remove_filter( 'gettext_with_context', $woo_label, 99 ); }
 }finally{
  WC()->cart->set_cart_contents( $original_cart );
  $GLOBALS['wp_query']=$oldQuery;foreach($fixtureKeys as $key)$wpdb->delete(PandaTranslate_Repository::table(),array('unit_key'=>$key));
 }
}finally{
 foreach($meta as $id=>$value){if(''===$value)delete_post_meta($id,'_pandatranslate_slug_de');else update_post_meta($id,'_pandatranslate_slug_de',$value);}
 foreach($before as $key=>$value){if(false===$value)delete_option($key);else update_option($key,$value);}
}
