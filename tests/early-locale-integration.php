<?php
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
function pt_early_expect( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS $label\n"; }
$settings = array( 'source_language' => 'de', 'url_mode' => 'path', 'languages' => array( array( 'code' => 'de', 'active' => 1 ), array( 'code' => 'fr', 'active' => 1 ), array( 'code' => 'es', 'active' => 0 ) ) );
$option = function () use ( &$settings ) { return $settings; }; add_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $option, 999 );
$saved_uri = $_SERVER['REQUEST_URI'] ?? null; $saved_get = $_GET; $saved_screen = $GLOBALS['current_screen'] ?? null;
$filters = array(); foreach ( array( 'locale', 'determine_locale' ) as $hook ) $filters[$hook] = isset( $GLOBALS['wp_filter'][$hook] ) ? clone $GLOBALS['wp_filter'][$hook] : null;
$reset = function () use ( $filters ) { foreach ( $filters as $hook => $value ) { if ( $value ) $GLOBALS['wp_filter'][$hook] = clone $value; else unset( $GLOBALS['wp_filter'][$hook] ); } };
try {
 $_GET = array(); $_SERVER['REQUEST_URI'] = '/fr/example/'; PandaTranslate_Strings::prepare_request_locale();
 pt_early_expect( 'fr_FR' === determine_locale(), 'Valid public language is selected before template generation' );
 $mail = static function () { return 'en_US'; }; add_filter( 'locale', $mail, 10 ); add_filter( 'determine_locale', $mail, 10 );
 pt_early_expect( 'en_US' === determine_locale(), 'Later explicit WordPress recipient locale overrides the early public language' );
 remove_filter( 'locale', $mail, 10 ); remove_filter( 'determine_locale', $mail, 10 ); pt_early_expect( 'fr_FR' === determine_locale(), 'Recipient scope restoration returns to public language' );
 $old_switcher = $GLOBALS['wp_locale_switcher']; $scope = new WP_Locale_Switcher(); $GLOBALS['wp_locale_switcher'] = $scope; $scope->init();
 try {
  pt_early_expect( switch_to_locale( 'en_US' ) && 'en_US' === determine_locale(), 'Actual WordPress locale switch overrides early frontend language' );
  restore_previous_locale(); pt_early_expect( 'fr_FR' === determine_locale(), 'Actual WordPress restoration returns to the early frontend language' );
 } finally { while ( $scope->is_switched() ) $scope->restore_previous_locale(); remove_filter( 'locale', array( $scope, 'filter_locale' ) ); remove_filter( 'determine_locale', array( $scope, 'filter_locale' ) ); $GLOBALS['wp_locale_switcher'] = $old_switcher; }
 $reset(); $before = determine_locale();
 foreach ( array( '/es/example/', '/frankenstein/', '/wp-json/fr/', '/wp-admin/' ) as $path ) { $_SERVER['REQUEST_URI'] = $path; PandaTranslate_Strings::prepare_request_locale(); pt_early_expect( $before === determine_locale(), 'Inactive, nonmatching or technical path keeps native locale: ' . $path ); }
 $_SERVER['REQUEST_URI'] = '/example/'; $_GET['pt_lang'] = 'fr'; PandaTranslate_Strings::prepare_request_locale(); pt_early_expect( 'fr_FR' === determine_locale(), 'Explicit validated query language is supported' );
 $reset(); $_GET['pt_lang'] = 'unknown'; PandaTranslate_Strings::prepare_request_locale(); pt_early_expect( $before === determine_locale(), 'Unknown explicit query language never changes locale' );
 $reset(); $_GET['pt_lang'] = 'fr';
 // is_admin() asks the screen method, so model the WordPress screen contract.
 $GLOBALS['current_screen'] = new class { public function in_admin() { return true; } }; PandaTranslate_Strings::prepare_request_locale(); pt_early_expect( $before === determine_locale(), 'Administration never adopts frontend query language' );
} finally { $reset(); $_GET = $saved_get; if ( null === $saved_uri ) unset( $_SERVER['REQUEST_URI'] ); else $_SERVER['REQUEST_URI'] = $saved_uri; if ( null === $saved_screen ) unset( $GLOBALS['current_screen'] ); else $GLOBALS['current_screen'] = $saved_screen; remove_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $option, 999 ); }
