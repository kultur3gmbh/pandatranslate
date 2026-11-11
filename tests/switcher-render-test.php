<?php
// Model the initialized WordPress query used by normal frontend requests.
$wp_query = new class { public function get($key) { return get_query_var($key); } };
require __DIR__ . '/routing-test.php';
function __( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_url( $text ) { return $text; }
function esc_attr__( $text ) { return esc_attr( $text ); }
function get_query_var( $key ) { return 'en'; }
function get_locale() { return 'de_DE'; }
function apply_filters( $hook, $value ) { return $value; }
function wp_get_attachment_image_url( $id, $size ) { return '/flag.png'; }
$render = new ReflectionMethod( 'PandaTranslate_Plugin', 'switcher_markup' );
if ( PHP_VERSION_ID < 80100 ) $render->setAccessible( true );
foreach ( array( 'flags', 'flag_code', 'flag_name', 'code', 'name' ) as $mode ) {
	$html = $render->invoke( $plugin, 'floating', array( 'display' => $mode ) );
	$doc = new DOMDocument(); @$doc->loadHTML( '<?xml encoding="UTF-8">' . $html ); $xpath = new DOMXPath( $doc );
	check( 0, $xpath->query( '//nav/ul/li/a[@hreflang="en"]' )->length );
	check( 1, $xpath->query( '//nav/button[@data-pandatranslate-toggle]' )->length );
	check( 2, $xpath->query( '//nav/ul/li/a' )->length );
	$has_flags = in_array( $mode, array( 'flags', 'flag_code', 'flag_name' ), true );
	check( $has_flags, $xpath->query( '//span[contains(@class,"pandatranslate-flag ")]' )->length > 0 );
	check( 'flags' !== $mode, $xpath->query( '//span[@class="pandatranslate-language-name"]' )->length > 0 );
}
check( 'Français', PandaTranslate_Features::language_name( 'fr', true ) );
if ( false === strpos( PandaTranslate_Features::language_name( 'fr', false ), 'Französisch' ) ) throw new RuntimeException( 'Site-language display failed.' );
echo "five display modes, native names and no active language in dropdown options: ok\n";
$theme = $render->invoke( $plugin, 'theme-menu' );
check( false, false !== strpos( $theme, '<nav' ) );
check( true, false !== strpos( $theme, 'class="sub-menu"' ) );
check( false, false !== strpos( $theme, 'style=' ) );
$doc = new DOMDocument(); @$doc->loadHTML( '<?xml encoding="UTF-8">' . $theme );
check( 0, ( new DOMXPath( $doc ) )->query( '//ul/li/a[@hreflang="en"]' )->length );
$stored['menu_switcher_container_background'] = '#123456';
$standalone = $render->invoke( $plugin, 'menu' );
check( true, false !== strpos( $standalone, '--pt-container-bg:#123456' ) );
check( false, false !== strpos( $render->invoke( $plugin, 'floating' ), '--pt-container-bg' ) );
echo "theme menu markup inherits styling; standalone container background is independent of floating selector: ok\n";
