<?php
if (!function_exists('apply_filters')) { function apply_filters($hook,$value,...$args){return $value;} }
require __DIR__ . '/routing-test.php';
require dirname( __DIR__ ) . '/includes/class-pandatranslate-frontend.php';
function is_admin() { global $admin; return ! empty( $admin ); }
function get_query_var( $key ) { global $query_language; return 'pt_lang' === $key ? $query_language : ''; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function url_to_postid( $url ) { return false !== strpos( $url, '/about/' ) ? 10 : 0; }
function wc_get_page_id( $page ) { return 'checkout' === $page ? 11 : 0; }
$stored = array( 'source_language' => 'en', 'target_languages' => 'de,fr,ar', 'url_mode' => 'path', 'languages' => array( array( 'code'=>'en','active'=>1 ), array('code'=>'de','active'=>1), array('code'=>'fr','active'=>1), array('code'=>'ar','active'=>1) ) );
$home = 'https://example.com'; $query_language = 'de'; $meta = array( '_pandatranslate_slug_de'=>'ueber-uns' );
$permalinks = array( 11=>'/checkout/' );
$_SERVER['REQUEST_URI'] = '/de/current/';
check( 'de', PandaTranslate_Frontend::current_language() );
check( 'class="site" lang="de" dir="ltr"', PandaTranslate_Frontend::language_attributes( 'lang="en-US" dir="rtl" class="site"' ) );
check( 'lang="de" dir="ltr" xml:lang="de"', PandaTranslate_Frontend::language_attributes( 'xml:lang="en-US" lang="en-US"', 'xhtml' ) );
check( 'https://example.com/de/', PandaTranslate_Frontend::localize_url( '/', 'de' ) );
check( 'https://example.com/de/', PandaTranslate_Frontend::localize_url( 'https://example.com', 'de' ) );
check( 'https://example.com/de/features/', PandaTranslate_Frontend::localize_url( '/features/', 'de' ) );
check( 'https://example.com/de/about/ueber-uns/?utm_source=test#team', PandaTranslate_Frontend::localize_url( '/about/team/?utm_source=test#team', 'de' ) );
check( 'https://example.com/de/features/?a=1&b=2#x', PandaTranslate_Frontend::localize_url( '//example.com/features/?a=1&amp;b=2#x', 'de' ) );
check( 'https://example.com/de/pricing/', PandaTranslate_Frontend::localize_url( '../../pricing/', 'de' ) );
check( 'news/', PandaTranslate_Frontend::localize_url( 'news/', 'de' ) ); // Resolves below the existing /de/ prefix.
foreach ( array( '#team', '/fr/features/', '/de/features/', '/?pt_lang=en', '/?lang=fr', 'https://external.test/path/', 'https://example.com:8080/path/', 'mailto:info@example.com', 'tel:+431', 'javascript:alert(1)', '/wp-admin/', '/wp-json/wp/v2/posts', '/wp-login.php', '/wp-content/file.css', '/document.pdf', '/archive.zip?x=1', '/?rest_route=/wp/v2/posts', '/checkout/?key=order123', '/?add-to-cart=10', '/?wc-ajax=checkout', '/?action=logout', '/?pandatranslate=edit', '/wp-sitemap.xml' ) as $untouched ) check( $untouched, PandaTranslate_Frontend::localize_url( $untouched, 'de' ) );
check( '/features/', PandaTranslate_Frontend::localize_url( '/features/', 'en' ) );
check( '/features/', PandaTranslate_Frontend::localize_url( '/features/', 'inactive' ) );
check( '/checkout/order-pay/15/', PandaTranslate_Frontend::localize_url( '/checkout/order-pay/15/', 'de' ) );
check( 'https://example.com/de/checkout/order-pay/15/?payment_method=test#pay', PandaTranslate_Frontend::localize_url( '/checkout/order-pay/15/?payment_method=test#pay', 'de', true ) );
check( array( 'href'=>'/fr/' ), PandaTranslate_Frontend::menu_attributes( array('href'=>'/fr/'), (object)array('classes'=>array('pandatranslate-menu-link')) ) );
check( array( 'href'=>'https://example.com/de/features/' ), PandaTranslate_Frontend::menu_attributes( array('href'=>'/features/'), (object)array('classes'=>array('menu-item')) ) );
$html = '<!doctype html><html lang="en-US" dir="ltr"><head><title>Example</title><script>const cp="/features/ &amp;euro;";</script><style>.site{background:url(/same.png)}</style></head><body><div id="wpadminbar"><a href="/">Admin</a></div><header><a id="logo" href="/"><img src="/logo.png"></a><nav><a id="nav" href="/features/">Features</a></nav></header>';
foreach ( array( 'wp-block-group','elementor','fusion-row','et_pb_text','vc_row','fl-row','brxe-container' ) as $builder ) $html .= '<div class="' . $builder . '"><a href="/pricing/">Pricing</a></div>';
$html .= '<a id="download" download href="/report/">Download</a><a id="hreflang" hreflang="fr" href="/">French</a><div class="pandatranslate-switcher"><a id="switch" href="/">English</a></div><a id="outside" href="https://other.test/">Other</a><div><span id="currency">&amp;euro; EUR &amp;amp;pound; GBP</span><span id="check">&amp;#10003;</span></div><p id="escaped">&amp;lt;script&amp;gt;</p><pre>&amp;euro;</pre><code>&amp;#10003;</code><img id="image" src="/same.webp" alt="&amp;euro;"><form action="/submit/"><button>Send</button></form></body></html>';
$result = PandaTranslate_Frontend::transform( $html, 'de' );
$doc = new DOMDocument(); @$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $result ); $xpath = new DOMXPath( $doc );
check( 'de', $doc->documentElement->getAttribute( 'lang' ) );
check( 'ltr', $doc->documentElement->getAttribute( 'dir' ) );
check( 'https://example.com/de/', $doc->getElementById('logo')->getAttribute('href') );
check( 'https://example.com/de/features/', $doc->getElementById('nav')->getAttribute('href') );
check( 7, $xpath->query('//a[@href="https://example.com/de/pricing/"]')->length );
check( '/report/', $doc->getElementById('download')->getAttribute('href') );
check( '/', $doc->getElementById('hreflang')->getAttribute('href') );
check( '/', $doc->getElementById('switch')->getAttribute('href') );
check( '/', $xpath->query('//*[@id="wpadminbar"]/a')->item(0)->getAttribute('href') );
check( '€ EUR £ GBP', $doc->getElementById('currency')->textContent );
check( '✓', $doc->getElementById('check')->textContent );
check( '&lt;script&gt;', $doc->getElementById('escaped')->textContent );
check( '&euro;', $doc->getElementsByTagName('pre')->item(0)->textContent );
check( '&#10003;', $doc->getElementsByTagName('code')->item(0)->textContent );
check( '€', $doc->getElementById('image')->getAttribute('alt') );
check( '/same.webp', $doc->getElementById('image')->getAttribute('src') );
check( '/submit/', $doc->getElementsByTagName('form')->item(0)->getAttribute('action') );
check( 'const cp="/features/ &amp;euro;";', $doc->getElementsByTagName('script')->item(0)->textContent );
check( false, strpos( PandaTranslate_Frontend::transform( $result, 'de' ), '/de/de/' ) !== false );
$query_language='ar'; check( 'lang="ar" dir="rtl"', PandaTranslate_Frontend::language_attributes('lang="en-US"') );
$query_language=''; check( 'lang="en" dir="ltr"', PandaTranslate_Frontend::language_attributes('lang="en-US"') );
$admin=true; check( 'lang="en-US"', PandaTranslate_Frontend::language_attributes('lang="en-US"') ); $admin=false;
$home='https://example.com/site'; $_SERVER['REQUEST_URI']='/site/de/current/';
check( 'https://example.com/site/de/features/?x=1', PandaTranslate_Frontend::localize_url('/site/features/?x=1','de') );
check( '/outside-installation/', PandaTranslate_Frontend::localize_url('/outside-installation/','de') );
$stored['url_mode']='query'; $_SERVER['REQUEST_URI']='/site/current/?pt_lang=de';
check( 'https://example.com/site/features/?a=1&b=2&pt_lang=de#team', PandaTranslate_Frontend::localize_url('/site/features/?a=1&b=2#team','de') );
check( 'https://example.com/site/current/?s=music&pt_lang=de', PandaTranslate_Frontend::localize_url('?s=music','de') );
check( 'https://example.com/site/features/?pt_lang=de', PandaTranslate_Frontend::localize_url('/site/features/','de',false,'https://external.test/base/') );
check( 'relative/', PandaTranslate_Frontend::localize_url('relative/','de',false,'https://external.test/base/') );
echo "Frontend navigation: server-rendered logo/menu/content links across seven builders, explicit switches and technical URLs preserved, path/query/subdirectory URLs and slugs, correct HTML language/direction, safe currency/checkmark entity rendering: ok\n";
