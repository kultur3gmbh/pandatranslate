<?php
/** Configuration memoization must not retain stale languages or paid grants. */
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
function pt_configuration_expect( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS $label\n"; }
$saved = get_option( PandaTranslate_Plugin::OPTION ); $query = $_GET;
$ready = new ReflectionProperty( PandaTranslate_Strings::class, 'frontend_ready' ); $ready->setAccessible( true ); $was_ready = $ready->getValue();
$resolve = new ReflectionMethod( PandaTranslate_Strings::class, 'frontend_language' ); $resolve->setAccessible( true );
$settings = array( 'source_language' => 'de', 'languages' => array( array( 'code' => 'de', 'active' => 1 ), array( 'code' => 'fr', 'active' => 1 ), array( 'code' => 'es', 'active' => 1 ) ) );
$reads = 0; $observe = function ( $hook ) use ( &$reads ) { if ( 'pre_option_' . PandaTranslate_Plugin::OPTION === $hook ) $reads++; };
$entitled = false; $edition = function () use ( &$entitled ) { return $entitled; };
$dynamic = function () use ( &$settings ) { return $settings; };
try {
 update_option( PandaTranslate_Plugin::OPTION, $settings ); $ready->setValue( true ); PandaTranslate_Strings::invalidate_language();
 add_action( 'all', $observe ); add_filter( 'pandatranslate_multilingual_available', $edition, PHP_INT_MAX );
 $_GET['pt_lang'] = 'fr'; pt_configuration_expect( 'fr' === $resolve->invoke( null ), 'Included language resolves after routing' );
 $initial = $reads;
 for ( $i = 0; $i < 10000; $i++ ) PandaTranslate_Strings::gettext( 'Configuration test', 'Configuration test', 'synthetic-config' );
 pt_configuration_expect( $reads - $initial < 5, 'Ten thousand repeated gettext calls do not repeatedly read language settings' );
 $settings['languages'][1]['active'] = 0; update_option( PandaTranslate_Plugin::OPTION, $settings );
 pt_configuration_expect( '' === $resolve->invoke( null ), 'Saved deactivation invalidates configuration immediately' );
 $settings['languages'][1]['active'] = 1; update_option( PandaTranslate_Plugin::OPTION, $settings );
 $_GET['pt_lang'] = 'es'; pt_configuration_expect( '' === $resolve->invoke( null ), 'Additional language requires current entitlement' );
 $entitled = true; pt_configuration_expect( 'es' === $resolve->invoke( null ), 'New paid grant is visible without configuration invalidation' );
 $entitled = false; pt_configuration_expect( '' === $resolve->invoke( null ), 'Paid grant withdrawal is visible immediately' );
 $_GET['pt_lang'] = 'fr'; add_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $dynamic, 999 );
 pt_configuration_expect( 'fr' === $resolve->invoke( null ), 'Dynamic configuration filter is respected' );
 $settings['languages'][1]['active'] = 0;
 pt_configuration_expect( '' === $resolve->invoke( null ), 'Dynamic filter changes bypass configuration cache' );
 unset( $_GET['pt_lang'] ); pt_configuration_expect( '' === $resolve->invoke( null ), 'Source selector never reuses target configuration' );
} finally {
 remove_action( 'all', $observe ); remove_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $dynamic, 999 ); remove_filter( 'pandatranslate_multilingual_available', $edition, PHP_INT_MAX );
 update_option( PandaTranslate_Plugin::OPTION, $saved ); $_GET = $query; $ready->setValue( $was_ready ); PandaTranslate_Strings::invalidate_language();
}
