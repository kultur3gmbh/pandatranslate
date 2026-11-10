<?php
defined( 'ABSPATH' ) || exit;

/** Shared edition boundary; saved configuration and translations are never removed. */
final class PandaTranslate_Edition {
	public static function boot() {
		add_filter( 'contactpanda_license_products', function ( $products ) { $products['pandatranslate'] = array( 'label' => 'PandaTranslate', 'entitlement' => '', 'version' => PANDATRANSLATE_VERSION, 'free' => true, 'modules' => array() ); return $products; } );
		add_action( 'admin_init', function () { if ( current_user_can( 'manage_options' ) && 2 !== (int) get_option( 'pandatranslate_schema' ) ) PandaTranslate_Repository::install(); } );
		add_filter( 'pandatranslate_capture_allowed', function ( $allowed ) { foreach ( array( 'is_cart', 'is_checkout', 'is_account_page' ) as $predicate ) if ( function_exists( $predicate ) && $predicate() ) return false; return $allowed; } );
	}
	public static function multilingual() { return (bool) apply_filters( 'pandatranslate_multilingual_available', false ); }
	public static function effective_languages( array $rows ) {
		if ( self::multilingual() ) return $rows;
		$active = 0;
		foreach ( $rows as &$row ) if ( ! empty( $row['active'] ) && ++$active > 2 ) $row['active'] = 0;
		unset( $row ); return $rows;
	}
	public static function allows_language( $code ) {
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( ! empty( $row['active'] ) && $row['code'] === $code && $code !== PandaTranslate_Plugin::get_settings()['source_language'] ) return true;
		return false;
	}
	public static function promotion() {
		if ( self::multilingual() ) return;
		echo '<aside class="pandatranslate-upgrade"><strong>PandaTranslate Multilingual</strong><p>' . esc_html__( 'More than two languages, multilingual SEO and WooCommerce integrations. Included in the All-in bundle.', 'pandatranslate' ) . '</p><a class="contactpanda-large-action" data-contactpanda-action="view" data-contactpanda-action-mode="labelled" href="' . esc_url( 'https://mycontactpanda.com/' ) . '">' . esc_html__( 'Explore extended features', 'pandatranslate' ) . '</a><p><small>' . esc_html__( 'Free includes the source language and one translation language. Existing additional language data remains stored.', 'pandatranslate' ) . '</small></p></aside>';
	}
	public static function register_updater() {
		if ( ! class_exists( 'Panda_Public_Updater', false ) ) require_once PANDATRANSLATE_DIR . 'includes/class-panda-public-updater.php';
		new Panda_Public_Updater( plugin_basename( PANDATRANSLATE_FILE ), PANDATRANSLATE_VERSION, array( 'repository' => 'kultur3gmbh/pandatranslate', 'slug' => 'pandatranslate', 'name' => 'PandaTranslate', 'asset_prefix' => 'pandatranslate', 'manifest' => 'pandatranslate-update.json' ) );
	}
}
