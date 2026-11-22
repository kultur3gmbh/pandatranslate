<?php
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
require ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );

function pt_dashboard_expect( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
	echo "PASS $message\n";
}

$menu = array( 'index.php', 'pandamercury', 'separator1', 'panda-seasons-dashboard', 'edit.php', 'rank-math', 'separator-woocommerce', 'woocommerce', 'edit.php?post_type=product', 'pandawoo', 'pandatranslate', 'woocommerce-marketing', 'separator2' );
$expected = array( 'index.php', 'separator1', 'edit.php', 'rank-math', 'panda-seasons-dashboard', 'pandatranslate', 'pandamercury', 'separator-woocommerce', 'woocommerce', 'edit.php?post_type=product', 'woocommerce-marketing', 'pandawoo', 'separator2' );
pt_dashboard_expect( $expected === apply_filters( 'menu_order', $menu ), 'Panda modules follow Rank Math and PandaWoo closes the WooCommerce group' );
pt_dashboard_expect( array( 'index.php', 'edit.php', 'pandatranslate', 'separator2' ) === apply_filters( 'menu_order', array( 'index.php', 'pandatranslate', 'edit.php', 'separator2' ) ), 'Menu ordering handles sites without Rank Math or WooCommerce' );

$plugin = PandaTranslate_Plugin::boot();
$submenus = array( 'pandatranslate', 'pandatranslate-translation', 'pandatranslate-glossary', 'pandatranslate-url-bases', 'pandatranslate-texts', 'pandatranslate-settings' );
$register_item = static function ( $slug ) { return array( $slug, 'manage_options', $slug ); };
$submenu['pandatranslate'] = array_map( $register_item, array_reverse( $submenus ) );
$plugin->order_admin_submenus();
pt_dashboard_expect( $submenus === array_column( $submenu['pandatranslate'], 2 ), 'Translation submenu follows the requested order with the optional SEO entry' );

$repository = new PandaTranslate_Repository();
$before = $repository->source_character_total();
$settings = array_replace( PandaTranslate_Plugin::get_settings(), array( 'google_v2_enabled' => 1, 'google_v3_enabled' => 0, 'deepl_enabled' => 1 ) );
$override = static function () use ( $settings ) { return $settings; };
add_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $override, 999 );
add_filter( 'pandatranslate_providers', '__return_empty_array', 999 );

$wpdb->query( 'START TRANSACTION' );
try {
	if ( ! post_type_exists( 'product' ) ) register_post_type( 'product', array( 'label' => 'Products', 'public' => true ) );
	$product_count = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
	wp_insert_post( array( 'post_title' => 'Dashboard product', 'post_type' => 'product', 'post_status' => 'publish' ) );
	wp_insert_post( array( 'post_title' => 'Dashboard draft product', 'post_type' => 'product', 'post_status' => 'draft' ) );

	$source = wp_generate_uuid4();
	$unit = array( 'key' => hash( 'sha256', $source ), 'source_hash' => hash( 'sha256', $source ), 'source_html' => $source );
	$repository->save( $unit, 'fr', '', 'untranslated' );
	$repository->save( $unit, 'it', '', 'untranslated' );
	$total = $before + strlen( $source );
	pt_dashboard_expect( $total === $repository->source_character_total(), 'Recorded characters are counted only once across target languages' );

	$_GET = array( 'page' => 'pandatranslate' );
	ob_start();
	$plugin->settings_page();
	$html = ob_get_clean();
	$document = new DOMDocument();
	$document->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
	$xpath = new DOMXPath( $document );
	$overview = $xpath->query( '//*[@aria-labelledby="pandatranslate-overview-title"]' )->item( 0 );
	$metrics = $xpath->query( './/dd', $overview );
	pt_dashboard_expect( strpos( $html, 'pandatranslate-overview-title' ) < strpos( $html, 'data-language-rows' ), 'Website overview precedes language settings' );
	pt_dashboard_expect( number_format_i18n( $product_count + 1 ) === $metrics->item( 2 )->textContent, 'Dashboard counts published products and excludes drafts' );
	pt_dashboard_expect( number_format_i18n( $total ) === $metrics->item( $metrics->length - 2 )->textContent && '2' === $metrics->item( $metrics->length - 1 )->textContent, 'Dashboard shows the recorded character total and the enabled APIs' );
} finally {
	$wpdb->query( 'ROLLBACK' );
	remove_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $override, 999 );
	remove_filter( 'pandatranslate_providers', '__return_empty_array', 999 );
}
