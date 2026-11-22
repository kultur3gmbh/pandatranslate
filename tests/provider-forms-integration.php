<?php
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
require ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );

function pt_provider_form_expect( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
	echo "PASS $message\n";
}

$plugin = PandaTranslate_Plugin::boot();
$fixture = array_merge( PandaTranslate_Plugin::get_settings(), array(
	'google_v2_enabled' => 1,
	'google_api_version' => 'v2',
	'google_api_key_last4' => 'test',
	'google_project_id' => 'fixture-project',
	'google_tests' => array( 'v2' => array( 'status' => 'valid' ) ),
	'deepl_enabled' => 1,
	'deepl_endpoint' => 'pro',
	'deepl_key_last4' => 'plan',
	'deepl_test' => array( 'status' => 'valid' ),
) );
$override = static function () use ( $fixture ) { return $fixture; };
add_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $override, 999 );

try {
	$google = $plugin->sanitize_settings( array(
		'_section' => 'google', '_api_provider' => 'google', 'google_api_version' => 'v3', 'google_v3_enabled' => 1,
		'google_project_id' => 'new-project', 'deepl_key_remove' => 1,
	) );
	$deepl = $plugin->sanitize_settings( array(
		'_section' => 'google', '_api_provider' => 'deepl', 'deepl_endpoint' => 'free', 'google_api_key_remove' => 1,
	) );
	$google_keys = array_flip( array( 'provider', 'google_v2_enabled', 'google_api_version', 'google_project_id', 'google_api_key_last4', 'google_tests' ) );
	$deepl_keys = array_flip( array( 'deepl_enabled', 'deepl_endpoint', 'deepl_key_last4', 'deepl_test' ) );
	pt_provider_form_expect( 1 === $google['google_v3_enabled'] && 'new-project' === $google['google_project_id'] && array_intersect_key( $google, $deepl_keys ) === array_intersect_key( $fixture, $deepl_keys ), 'Saving Google preserves DeepL settings and test status' );
	pt_provider_form_expect( 'free' === $deepl['deepl_endpoint'] && 0 === $deepl['deepl_enabled'] && array_intersect_key( $deepl, $google_keys ) === array_intersect_key( $fixture, $google_keys ), 'Saving DeepL preserves Google settings and test status' );

	$_GET = array( 'page' => 'pandatranslate-settings', 'tab' => 'google' );
	ob_start();
	$plugin->settings_page();
	$html = ob_get_clean();
	$document = new DOMDocument();
	$document->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
	$xpath = new DOMXPath( $document );
	$forms = $xpath->query( '//*[@class="pandatranslate-api-grid"]/form' );
	pt_provider_form_expect( 2 === $forms->length, 'Two separate provider forms share the card grid' );
	foreach ( array( 'google' => 'DeepL', 'deepl' => 'Google Translate' ) as $provider => $other_provider ) {
		$form = $xpath->query( '//form[@aria-labelledby="pandatranslate-' . $provider . '-provider"]' )->item( 0 );
		pt_provider_form_expect( $form && 1 === $xpath->query( './/button[@type="submit"]', $form )->length && 1 === $xpath->query( './/input[@name="_wpnonce"]', $form )->length && 1 === $xpath->query( './/input[@name="pandatranslate_settings[_api_provider]" and @value="' . $provider . '"]', $form )->length, 'Provider form posts its own section and nonce' );
		pt_provider_form_expect( false === strpos( $form->textContent, $other_provider ), 'Provider card does not include the other provider fields' );
	}
} finally {
	remove_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $override, 999 );
}
