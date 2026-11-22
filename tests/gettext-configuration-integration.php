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
 $unrelated = function ( $value ) { return $value; }; add_filter( 'pre_option', $unrelated );
 $initial = $reads;
 for ( $i = 0; $i < 10000; $i++ ) PandaTranslate_Strings::gettext( 'Configuration test', 'Configuration test', 'synthetic-config' );
 // Pre-option probes still run, but full settings resolution must stay cached.
 $counter = new ReflectionProperty( PandaTranslate_Strings::class, 'configuration_count' ); $counter->setAccessible( true );
 $resolved = $counter->getValue();
 for ( $i = 0; $i < 100; $i++ ) $resolve->invoke( null );
 pt_configuration_expect( $counter->getValue() === $resolved, 'Unrelated global pre_option listeners do not rebuild configuration for every text' );
 remove_filter( 'pre_option', $unrelated );
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
 remove_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $dynamic, 999 );
 $generic = function ( $value, $key ) use ( &$settings ) { return PandaTranslate_Plugin::OPTION === $key ? $settings : $value; }; add_filter( 'pre_option', $generic, 999, 2 );
 $settings['languages'][1]['active'] = 1; pt_configuration_expect( 'fr' === $resolve->invoke( null ), 'Global pre_option overrides remain live' );
 $settings['languages'][1]['active'] = 0; pt_configuration_expect( '' === $resolve->invoke( null ), 'Changed global pre_option result immediately invalidates configuration' );
 remove_filter( 'pre_option', $generic, 999 );
 $post = function ( $value ) use ( &$settings ) { return $settings; }; add_filter( 'option_' . PandaTranslate_Plugin::OPTION, $post, 999 );
 $settings['languages'][1]['active'] = 1; pt_configuration_expect( 'fr' === $resolve->invoke( null ), 'Post-option overrides remain live' );
 $settings['languages'][1]['active'] = 0; pt_configuration_expect( '' === $resolve->invoke( null ), 'Post-option changes are never memoized' ); remove_filter( 'option_' . PandaTranslate_Plugin::OPTION, $post, 999 );
 unset( $_GET['pt_lang'] ); pt_configuration_expect( '' === $resolve->invoke( null ), 'Source selector never reuses target configuration' );
} finally {
 remove_action( 'all', $observe ); remove_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $dynamic, 999 ); remove_filter( 'pandatranslate_multilingual_available', $edition, PHP_INT_MAX );
 update_option( PandaTranslate_Plugin::OPTION, $saved ); $_GET = $query; $ready->setValue( $was_ready ); PandaTranslate_Strings::invalidate_language();
}
