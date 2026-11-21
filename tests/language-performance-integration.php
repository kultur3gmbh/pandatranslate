<?php
/** Exercise repeated gettext and runtime configuration/entitlement changes in disposable WordPress. */
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
function pt_performance_expect( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS $label\n"; }
$settings = array( 'source_language'=>'de', 'languages'=>array( array('code'=>'de','active'=>1), array('code'=>'fr','active'=>1), array('code'=>'es','active'=>1) ) );
$option = function () use ( &$settings ) { return $settings; };
$normalizations = 0;
$slug = function ( $value ) use ( &$normalizations ) { $normalizations++; return $value; };
$entitled = false;
$edition = function () use ( &$entitled ) { return $entitled; };
$before = $_GET;
add_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $option, 999 );
add_filter( 'sanitize_title', $slug, 999 );
add_filter( 'pandatranslate_multilingual_available', $edition, PHP_INT_MAX );
try {
	unset($_GET['pt_lang']);
	for ($i=0;$i<1000;$i++) PandaTranslate_Strings::gettext('Source label','Source label','synthetic-theme');
	pt_performance_expect($normalizations === 0, 'Source gettext skips target configuration entirely');
	$_GET['pt_lang']='fr';
	PandaTranslate_Frontend::current_language(); $initial=$normalizations;
	for ($i=0;$i<1000;$i++) PandaTranslate_Strings::gettext('Translated label','Translated label','synthetic-theme');
	pt_performance_expect($normalizations === $initial, 'Repeated target gettext does not normalize slugs again');
	$settings['languages'][1]['active']=0;
	pt_performance_expect(PandaTranslate_Frontend::current_language() === '', 'Deactivation takes effect in the same request');
	$settings['languages'][1]['active']=1;
	$_GET['pt_lang']='es';
	pt_performance_expect(PandaTranslate_Frontend::current_language() === '', 'Free language limit remains enforced');
	$entitled=true;
	pt_performance_expect(PandaTranslate_Frontend::current_language() === 'es', 'Fresh entitlement activates additional language without stale cache');
	$entitled=false;
	pt_performance_expect(PandaTranslate_Frontend::current_language() === '', 'Entitlement withdrawal takes effect immediately');
	$settings['source_language']='fr';
	pt_performance_expect(PandaTranslate_Plugin::get_settings()['source_language'] === 'fr', 'Changed source configuration is visible immediately');
} finally {
	$_GET=$before;
	remove_filter('pre_option_' . PandaTranslate_Plugin::OPTION,$option,999);
	remove_filter('sanitize_title',$slug,999);
	remove_filter('pandatranslate_multilingual_available',$edition,PHP_INT_MAX);
}
