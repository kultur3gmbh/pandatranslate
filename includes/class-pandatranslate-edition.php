<?php
defined( 'ABSPATH' ) || exit;

/** Shared edition boundary; saved configuration and translations are never removed. */
final class PandaTranslate_Edition {
	private static $updater;
	public static function boot() {
		add_action( 'admin_post_pandatranslate_check_updates', array( __CLASS__, 'check_updates' ) );
		add_action( 'admin_init', function () { if ( current_user_can( 'manage_options' ) && 3 !== (int) get_option( 'pandatranslate_schema' ) ) PandaTranslate_Repository::install(); } );
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
		echo '<aside class="pandatranslate-upgrade"><strong>PandaTranslate Multilingual</strong><p>' . esc_html__( 'More than two languages, multilingual SEO and WooCommerce integrations. Included in the All-in bundle.', 'pandatranslate' ) . '</p><a class="contactpanda-large-action" data-contactpanda-action="view" data-contactpanda-action-mode="labelled" href="' . esc_url( 'https://pandacosmo.com/' ) . '">' . esc_html__( 'Explore extended features', 'pandatranslate' ) . '</a><p><small>' . esc_html__( 'Free includes the source language and one translation language. Existing additional language data remains stored.', 'pandatranslate' ) . '</small></p></aside>';
	}
	public static function register_updater() {
		if ( ! class_exists( 'Panda_Public_Updater', false ) ) require_once PANDATRANSLATE_DIR . 'includes/class-panda-public-updater.php';
		self::$updater = new Panda_Public_Updater( plugin_basename( PANDATRANSLATE_FILE ), PANDATRANSLATE_VERSION, array( 'repository' => 'kultur3gmbh/pandatranslate', 'slug' => 'pandatranslate', 'name' => 'PandaTranslate', 'asset_prefix' => 'pandatranslate', 'manifest' => 'pandatranslate-update.json' ) );
	}

	public static function check_updates() {
		if ( ! current_user_can( 'update_plugins' ) ) wp_die( esc_html__( 'Access denied.', 'pandatranslate' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'pandatranslate_check_updates' );
		$result = self::$updater ? self::$updater->release( true ) : new WP_Error( 'updater_unavailable', __( 'The update service could not be reached.', 'pandatranslate' ) );
		$state = array( 'checked_at' => current_time( 'mysql' ), 'version' => '', 'error' => '' );
		if ( is_wp_error( $result ) ) $state['error'] = PandaTranslate_Translation_Jobs::safe_error( $result );
		else $state['version'] = sanitize_text_field( $result['version'] ?? '' );
		update_site_option( 'pandatranslate_public_update_status', $state );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'admin.php?page=pandatranslate-settings&tab=advanced' ) );
		exit;
	}

	public static function render_update_status() {
		if ( ! current_user_can( 'update_plugins' ) ) return;
		$state = (array) get_site_option( 'pandatranslate_public_update_status', array() );
		echo '<h3>' . esc_html__( 'Plugin updates', 'pandatranslate' ) . '</h3><p>GitHub · kultur3gmbh/pandatranslate</p>';
		if ( ! empty( $state['checked_at'] ) ) {
			echo '<p>' . esc_html__( 'Last update check', 'pandatranslate' ) . ': ' . esc_html( $state['checked_at'] ) . '</p>';
			echo '<p role="status">' . esc_html( ! empty( $state['error'] ) ? $state['error'] : sprintf( __( 'Latest published version: %s', 'pandatranslate' ), $state['version'] ?? '' ) ) . '</p>';
		}
		echo '<a class="contactpanda-large-action" data-contactpanda-action="test" data-contactpanda-action-mode="labelled" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pandatranslate_check_updates' ), 'pandatranslate_check_updates' ) ) . '">' . esc_html__( 'Check updates now', 'pandatranslate' ) . '</a>';
	}
}
