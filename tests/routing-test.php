<?php
if (!function_exists('apply_filters')) { function apply_filters($hook,$value,...$args){return $value;} }
define( 'ABSPATH', __DIR__ );
class PandaTranslate_Repository { public function public_language_counts() { global $public_counts; return $public_counts ?? array('de'=>1,'en'=>1,'fr'=>1); } }
$stored = array( 'source_language' => 'de', 'target_languages' => 'en,fr', 'url_mode' => 'path' );
$home = 'https://example.com'; $singular = true; $post_id = 10; $meta = array();
$site_options = array();
function get_option( $key, $default = false ) { global $stored, $site_options; return 'pandatranslate_settings' === $key ? $stored : ( $site_options[$key] ?? $default ); }
function wp_parse_args( $value, $defaults ) { return array_merge( $defaults, $value ); }
function sanitize_key( $text ) { return strtolower( $text ); }
function sanitize_title( $text ) { return strtolower( $text ); }
function is_singular() { global $singular; return $singular; }
function get_queried_object_id() { global $post_id; return $post_id; }
function get_permalink( $id ) { global $permalinks; return home_url( $permalinks[$id] ?? '/parent/haus/' ); }
function get_post_meta( $id, $key, $single ) { global $meta; return $meta[$key] ?? ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $text ) { return $text; }
function home_url( $path = '' ) { global $home; return $home . $path; }
function user_trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_posts( $args ) { return array( (object) array( 'ID' => 10, 'post_type' => 'page' ) ); }
function get_registered_nav_menus() { return array( 'main' => 'Main menu' ); }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
require dirname(__DIR__) . '/includes/class-pandatranslate-features.php';
require dirname(__DIR__) . '/includes/class-pandatranslate-plugin.php';
require dirname(__DIR__) . '/includes/class-pandatranslate-routing.php';
function check( $expected, $actual ) { if ( $expected !== $actual ) throw new RuntimeException( var_export( array( 'expected' => $expected, 'actual' => $actual ), true ) ); }
$_SERVER['REQUEST_URI'] = '/parent/haus/';
check( 'https://example.com/en/parent/haus/', PandaTranslate_Routing::url( 'en' ) );
$_SERVER['REQUEST_URI'] = '/en/parent/house/'; $meta['_pandatranslate_slug_en'] = 'house';
check( 'https://example.com/parent/haus/', PandaTranslate_Routing::url( 'de' ) );
check( 'https://example.com/fr/parent/haus/', PandaTranslate_Routing::url( 'fr' ) );
check( 'https://example.com/en/parent/house/', PandaTranslate_Routing::url( 'en' ) );
$wp = (object) array( 'request' => 'en/parent/house', 'query_vars' => array( 'pagename' => 'parent/house', 'pt_lang' => 'en' ) );
PandaTranslate_Routing::resolve_translated_slug( $wp ); check( 10, $wp->query_vars['p'] ); check( 'en', $wp->query_vars['pt_lang'] );
$stored['url_mode'] = 'query';
check( 'https://example.com/parent/haus/?pt_lang=fr', PandaTranslate_Routing::url( 'fr' ) );
$stored['url_mode'] = 'path'; $singular = false; $_SERVER['REQUEST_URI'] = '/en/category/news/page/2/?s=hello&pt_lang=en';
check( 'https://example.com/fr/category/news/page/2/?s=hello', PandaTranslate_Routing::url( 'fr' ) );
$_SERVER['REQUEST_URI'] = '/en/'; check( 'https://example.com/fr/', PandaTranslate_Routing::url( 'fr' ) );
$home = 'https://example.com/site'; $_SERVER['REQUEST_URI'] = '/site/en/category/news/';
check( 'https://example.com/site/fr/category/news/', PandaTranslate_Routing::url( 'fr' ) );
$rules = PandaTranslate_Routing::rules( array( 'parent/(.+?)/?$' => 'index.php?pagename=parent/$matches[1]', 'category/(.+?)/?$' => 'index.php?category_name=$matches[1]', '^en/([^/]+)/?$' => 'index.php?pandatranslate_slug=$matches[1]&pt_lang=en' ) );
check( 'index.php?pagename=parent/$matches[1]&pt_lang=en', $rules['^en/parent/(.+?)/?$'] );
check( 'index.php?pt_lang=fr', $rules['^fr/?$'] );
check( false, isset( $rules['^en/en/([^/]+)/?$'] ) );
echo "path/query URLs, translated slugs, nested paths, archives, subdirectories and rewrite routes: ok\n";
$plugin = ( new ReflectionClass( 'PandaTranslate_Plugin' ) )->newInstanceWithoutConstructor();
$saved = $plugin->sanitize_settings( array( '_section' => 'switcher', 'switcher_label_mode' => 'native', 'switcher_type' => 'dropdown', 'switcher_flag_position' => 'before', 'switcher_background' => '#ffffff', 'menu_switcher_label_mode' => 'flags', 'menu_switcher_type' => 'inline', 'menu_switcher_background' => '#112233', 'menu_switcher_flag_position' => 'after', 'menu_switcher_padding_top' => '4px' ) );
$stored = $saved;
check( 'flags', PandaTranslate_Plugin::switcher_settings( 'menu' )['switcher_label_mode'] );
check( 'inline', PandaTranslate_Plugin::switcher_settings( 'menu' )['switcher_type'] );
check( '#112233', PandaTranslate_Plugin::switcher_settings( 'menu' )['switcher_background'] );
check( '4px', PandaTranslate_Plugin::switcher_settings( 'menu' )['switcher_padding_top'] );
check( 'flag_name', PandaTranslate_Plugin::switcher_settings( 'floating' )['switcher_label_mode'] );
check( 'dropdown', PandaTranslate_Plugin::switcher_settings( 'floating' )['switcher_type'] );
check( '#ffffff', PandaTranslate_Plugin::switcher_settings( 'floating' )['switcher_background'] );
echo "menu and floating configuration persist independently: ok\n";
$site_options = array( 'show_on_front' => 'page', 'page_on_front' => 42 );
$rules = PandaTranslate_Routing::rules( array() );
check( 'index.php?pt_lang=en&page_id=42', $rules['^en/?$'] );
foreach ( array( 'en', 'fr', '' ) as $request ) {
	$wp = (object) array( 'request' => $request, 'query_vars' => array( 'pt_lang' => 'en', 'error' => '404' ) );
	PandaTranslate_Routing::resolve_translated_slug( $wp ); check( 42, $wp->query_vars['page_id'] ); check( false, isset( $wp->query_vars['error'] ) );
}
$wp = (object) array( 'request' => '', 'query_vars' => array( 'pt_lang' => 'en', 's' => 'music' ) );
PandaTranslate_Routing::resolve_translated_slug( $wp ); check( false, isset( $wp->query_vars['page_id'] ) );
$wp = (object) array( 'request' => 'en', 'query_vars' => array( 'pt_lang' => 'en', 'page_id' => 42, 's' => 'music' ) );
PandaTranslate_Routing::resolve_translated_slug( $wp ); check( false, isset( $wp->query_vars['page_id'] ) );
$wp = (object) array( 'request' => '', 'query_vars' => array( 'pt_lang' => 'en', 'p' => 99 ) );
PandaTranslate_Routing::resolve_translated_slug( $wp ); check( 99, $wp->query_vars['p'] ); check( false, isset( $wp->query_vars['page_id'] ) );
$site_options['show_on_front'] = 'posts';
$wp = (object) array( 'request' => 'en', 'query_vars' => array( 'pt_lang' => 'en' ) );
PandaTranslate_Routing::resolve_translated_slug( $wp ); check( false, isset( $wp->query_vars['page_id'] ) );
echo "static homepage remains the same for language paths and query URLs; search, direct posts and blog home preserved: ok\n";
