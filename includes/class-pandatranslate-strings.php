<?php
defined( 'ABSPATH' ) || exit;

/** Static gettext strings and published dynamic text. No visitor data or on-render API calls. */
final class PandaTranslate_Strings {
	private static $busy = false;
	private static $cache = array();
	private static $published = array();
	private static $native_locale = false;
	public static $email_language = '';
	public static function boot() {
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'gettext_context' ), 20, 4 );
		add_filter( 'ngettext', array( __CLASS__, 'plural' ), 20, 5 );
		add_action( 'wp', array( __CLASS__, 'locale' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
	}
	public static function locale() {
		if ( is_admin() ) return; $language = PandaTranslate_Frontend::current_language(); if ( ! $language ) return;
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] === $language && ! empty( $row['locale'] ) ) { self::$native_locale = switch_to_locale( $row['locale'] ) || get_locale() === $row['locale']; break; }
	}
	private static function published( $language ) {
		if ( ! isset( self::$published[$language] ) ) {
			global $wpdb; self::$published[$language] = array();
			if ( 3 !== (int) get_option( 'pandatranslate_schema' ) ) return array();
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT unit_key,translated_html,status FROM ' . PandaTranslate_Repository::table() . " WHERE target_language = %s AND source_kind = 'gettext' AND status = 'published' AND translated_html <> '' LIMIT 10000", $language ), ARRAY_A );
			foreach ( $rows as $row ) self::$published[$language][$row['unit_key']] = $row;
		}
		return self::$published[$language];
	}

	public static function gettext( $translated, $original, $domain ) { return self::lookup( $translated, $original, $domain, '' ); }
	public static function gettext_context( $translated, $original, $context, $domain ) { return self::lookup( $translated, $original, $domain, $context ); }
	public static function plural( $translated, $single, $plural, $number, $domain ) {
		// Native locale plural selection is retained; dictionary lookup is based on the resolved template.
		return self::lookup( $translated, $single . '|' . $plural, $domain, 'plural:' . $translated );
	}
	private static function lookup( $translated, $original, $domain, $context ) {
		if ( self::$busy || ( is_admin() && ! self::$email_language ) || in_array( $domain, array( 'pandatranslate', 'pandatranslate-multilingual' ), true ) || ! trim( $translated ) || strlen( $translated ) > 4000 ) return $translated;
		$language = self::$email_language ?: PandaTranslate_Frontend::current_language();
		if ( ! $language || ! PandaTranslate_Edition::allows_language( $language ) ) return $translated;
		$key = hash( 'sha256', 'gettext|' . $domain . '|' . $context . '|' . $original ); $cache_key = $key . '|' . $language;
		if ( array_key_exists( $cache_key, self::$cache ) ) return self::$cache[$cache_key] ?? $translated;
		self::$busy = true;
		try {
			$repo = new PandaTranslate_Repository();
			$collect = ! self::$email_language && current_user_can( 'manage_options' ) && isset( $_GET['pandatranslate'] ) && 'edit' === $_GET['pandatranslate'];
			$row = $collect ? $repo->find( $key, $language ) : ( self::published( $language )[$key] ?? null );
			if ( $collect && ! $row ) { $source = self::$native_locale ? $original : $translated; $native = self::$native_locale && $translated !== $original; $repo->save( array( 'key' => $key, 'source_hash' => hash( 'sha256', $source ), 'source_html' => $source, 'source_kind' => 'gettext', 'source_domain' => $domain ), $language, $native ? $translated : '', $native ? 'published' : 'untranslated', $native ? 'native-locale' : '' ); }
			$value = $row && 'published' === $row['status'] && '' !== $row['translated_html'] ? $row['translated_html'] : null;
			// Printf format signatures must remain identical before interpolation with customer/order values.
			if ( null !== $value && ! self::same_placeholders( $translated, $value ) ) $value = null;
			self::$cache[$cache_key] = $value;
			return $value ?? $translated;
		} finally { self::$busy = false; }
	}
	public static function same_placeholders( $source, $target ) {
		preg_match_all( '/(?<![0-9])%(?:\d+\$)?[-+0#]*(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX%]/', $source, $a ); preg_match_all( '/(?<![0-9])%(?:\d+\$)?[-+0#]*(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX%]/', $target, $b );
		// Conservatively preserve unnumbered argument order as well as types.
		preg_match_all( '/\{[a-z_][a-z0-9_]*\}/i', $source, $c ); preg_match_all( '/\{[a-z_][a-z0-9_]*\}/i', $target, $d ); sort( $c[0] ); sort( $d[0] );
		return $a[0] === $b[0] && $c[0] === $d[0];
	}
	public static function dictionary( $language ) {
		if ( 3 !== (int) get_option( 'pandatranslate_schema' ) ) return array();
		global $wpdb;
		$sql = 'SELECT source_html,translated_html FROM ' . PandaTranslate_Repository::table() . " WHERE target_language = %s AND status = 'published' AND source_kind <> 'gettext' AND translated_html <> '' AND unit_key IN (SELECT c.unit_key FROM " . $wpdb->prefix . 'pandatranslate_contexts c INNER JOIN ' . $wpdb->posts . " p ON p.ID = c.post_id WHERE p.post_status = 'publish' AND p.post_password = '') ORDER BY id LIMIT 10000";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $language ), ARRAY_A );
		$dictionary = array(); $ambiguous = array();
		foreach ( $rows as $row ) {
			// Text-only replacement avoids executing HTML or destroying builder event handlers.
			if ( $row['source_html'] !== strip_tags( $row['source_html'] ) || $row['translated_html'] !== strip_tags( $row['translated_html'] ) ) continue;
			$key = html_entity_decode( trim( $row['source_html'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); $value = html_entity_decode( $row['translated_html'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( isset( $dictionary[$key] ) && $dictionary[$key] !== $value ) $ambiguous[$key] = true;
			$dictionary[$key] = $value;
		}
		return array_diff_key( $dictionary, $ambiguous );
	}
	public static function assets() {
		$language = PandaTranslate_Frontend::current_language(); if ( ! $language ) return;
		wp_enqueue_script( 'pandatranslate-dynamic', PANDATRANSLATE_URL . 'assets/dynamic.js', array(), PANDATRANSLATE_VERSION, true );
		wp_localize_script( 'pandatranslate-dynamic', 'PandaTranslateDynamic', array( 'dictionary' => self::dictionary( $language ) ) );
	}
}
