<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-pandatranslate-javascript-inventory.php';

/** Static gettext strings and published dynamic text. No visitor data or on-render API calls. */
final class PandaTranslate_Strings {
	private static $busy = false;
	private static $cache = array();
	private static $published = array();
	private static $native_locale = false;
	private static $frontend_ready = false;
	private static $frontend_configuration = null;
	private static $lookup_seconds = 0.0;
	private static $lookup_count = 0;
	private static $configuration_count = 0;
	public static function timing_header() { return 'pandatranslate_gettext;dur=' . round( self::$lookup_seconds * 1000, 2 ) . ';desc="' . self::$lookup_count . ' calls", pandatranslate_configuration;desc="' . self::$configuration_count . ' resolutions"'; }
	public static $email_language = '';
	public static function boot() {
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'gettext_context' ), 20, 4 );
		add_filter( 'ngettext', array( __CLASS__, 'plural' ), 20, 5 );
		add_action( 'wp', array( __CLASS__, 'locale' ), 1 );
		foreach ( array( 'added_option', 'updated_option', 'deleted_option' ) as $hook ) add_action( $hook, array( __CLASS__, 'invalidate_language' ), 10, 1 );
		add_action( 'switch_blog', function () { self::invalidate_language(); } );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
	}
	public static function locale() {
		if ( is_admin() ) return; self::$frontend_ready = true; self::invalidate_language(); $language = self::frontend_language(); if ( ! $language ) return;
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] === $language && ! empty( $row['locale'] ) ) { self::$native_locale = switch_to_locale( $row['locale'] ) || get_locale() === $row['locale']; break; }
	}
	public static function invalidate_language( $option = '' ) {
		if ( '' === $option || in_array( $option, array( PandaTranslate_Plugin::OPTION, 'WPLANG' ), true ) ) self::$frontend_configuration = null;
	}
	/** Cache configuration after routing only. Paid entitlement itself is deliberately never cached here. */
	private static function frontend_language() {
		if ( ! self::$frontend_ready ) { $language = PandaTranslate_Frontend::current_language(); return $language && PandaTranslate_Edition::allows_language( $language ) ? $language : ''; }
		global $wp_query;
		$selector = isset( $_GET['pt_lang'] ) ? sanitize_key( wp_unslash( $_GET['pt_lang'] ) ) : ( is_object( $wp_query ) && is_callable( array( $wp_query, 'get' ) ) ? sanitize_key( $wp_query->get( 'pt_lang' ) ) : '' );
		if ( '' === $selector ) return '';
		$blog = get_current_blog_id(); $dynamic = false; $pre = array();
		foreach ( array( PandaTranslate_Plugin::OPTION => array(), 'WPLANG' => 'en_US' ) as $option => $default ) {
			// A global pre_option listener often handles unrelated options. Probe
			// its actual result, preserving live overrides without repeatedly
			// fetching/unserializing the full stored settings when it returns false.
			$value = false;
			if ( has_filter( 'pre_option' ) || has_filter( 'pre_option_' . $option ) ) {
				$value = apply_filters( 'pre_option_' . $option, false, $option, $default );
				$value = apply_filters( 'pre_option', $value, $option, $default );
			}
			$pre[$option] = is_object( $value ) ? (array) $value : $value;
			if ( has_filter( 'option_' . $option ) || has_filter( 'default_option_' . $option ) ) $dynamic = true;
		}
		$configuration = self::$frontend_configuration;
		if ( $dynamic || ! $configuration || $configuration['selector'] !== $selector || $configuration['blog'] !== $blog || $configuration['pre'] !== $pre ) {
			++self::$configuration_count;
			$configuration = array( 'selector' => $selector, 'blog' => $blog, 'pre' => $pre, 'language' => '', 'paid' => false );
			$source = PandaTranslate_Plugin::get_settings()['source_language']; $active = 0;
			foreach ( PandaTranslate_Plugin::configured_language_rows() as $row ) if ( ! empty( $row['active'] ) ) {
				$active++;
				if ( $row['code'] === $selector && $selector !== $source ) { $configuration['language'] = $selector; $configuration['paid'] = $active > 2; break; }
			}
			self::$frontend_configuration = $configuration;
		}
		return $configuration['language'] && ( ! $configuration['paid'] || PandaTranslate_Edition::multilingual() ) ? $configuration['language'] : '';
	}
	private static function published( $language ) {
		if ( ! isset( self::$published[$language] ) ) {
			global $wpdb; self::$published[$language] = array();
			if ( PandaTranslate_Repository::SCHEMA_VERSION !== (int) get_option( 'pandatranslate_schema' ) ) return array();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Published rows from plugin-owned tables only; freshness and current public-post visibility are required. Gettext rows are cached per language for this request.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT unit_key,translated_html,status FROM %i WHERE target_language = %s AND source_kind = 'gettext' AND status = 'published' AND translated_html <> '' LIMIT 10000", PandaTranslate_Repository::table(), $language ), ARRAY_A );
			foreach ( $rows as $row ) self::$published[$language][$row['unit_key']] = $row;
		}
		return self::$published[$language];
	}

	public static function gettext( $translated, $original, $domain ) { return self::lookup( $translated, $original, $domain, '' ); }
	public static function gettext_context( $translated, $original, $context, $domain ) { return self::lookup( $translated, $original, $domain, $context ); }
	public static function plural( $translated, $single, $plural, $number, $domain ) {
		// Native locale plural selection is retained; dictionary lookup is based on the resolved template.
		$format_source = 1 === (int) $number ? $single : $plural;
		return self::lookup( $translated, $single . '|' . $plural, $domain, 'plural:' . $translated, $format_source );
	}
	private static function lookup( $translated, $original, $domain, $context, $format_source = null ) {
		if ( self::$busy || ( is_admin() && ! self::$email_language ) || in_array( $domain, array( 'pandatranslate', 'pandatranslate-multilingual' ), true ) || ! trim( $translated ) || strlen( $translated ) > 4000 ) return $translated;
		// Language/settings filters may themselves translate labels. Guard the entire resolution.
		self::$busy = true;
		$started = microtime( true ); ++self::$lookup_count;
		try {
		$language = self::$email_language ? ( PandaTranslate_Edition::allows_language( self::$email_language ) ? self::$email_language : '' ) : self::frontend_language();
		if ( ! $language ) return $translated;
		// Native language packs can contain the same malformed sprintf strings.
		$format_source = $format_source ?? $original;
		if ( false !== strpos( $format_source, '%' ) && ! self::valid_format_translation( $format_source, $translated ) ) $translated = $format_source;
		$key = hash( 'sha256', 'gettext|' . $domain . '|' . $context . '|' . $original ); $cache_key = $key . '|' . $language;
		if ( array_key_exists( $cache_key, self::$cache ) ) return self::$cache[$cache_key] ?? $translated;
			$repo = new PandaTranslate_Repository();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The isset check is only a selector; editing_request() separately verifies capability and the user-bound editor nonce before collecting any strings.
			$collect = ! self::$email_language && current_user_can( 'manage_options' ) && isset( $_GET['pandatranslate'] ) && PandaTranslate_Plugin::editing_request();
			$row = $collect ? $repo->find( $key, $language ) : ( self::published( $language )[$key] ?? null );
			if ( $collect && ! $row ) { $source = self::$native_locale ? $original : $translated; $native = self::$native_locale && $translated !== $original; $repo->save( array( 'key' => $key, 'source_hash' => hash( 'sha256', $source ), 'source_html' => $source, 'source_kind' => 'gettext', 'source_domain' => $domain ), $language, $native ? $translated : '', $native ? 'published' : 'untranslated', $native ? 'native-locale' : '' ); }
			$value = $row && 'published' === $row['status'] && '' !== $row['translated_html'] ? $row['translated_html'] : null;
			// Printf format signatures must remain identical before interpolation with customer/order values.
			if ( null !== $value && ! self::same_placeholders( $translated, $value ) ) $value = null;
			self::$cache[$cache_key] = $value;
			return $value ?? $translated;
		} finally { self::$lookup_seconds += microtime( true ) - $started; self::$busy = false; }
	}
	public static function same_placeholders( $source, $target ) {
		$format = '/%(?:%|(?:\d+\$)?[-+0#]*(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX])/';
		preg_match_all( $format, $source, $a ); preg_match_all( $format, $target, $b );
		// A bare percentage mark can survive the token comparison yet cause PHP
		// sprintf() to throw (for example, "0 à 100 %" before a preserved %s).
		if ( ! self::valid_format_translation( $source, $target ) ) return false;
		// Conservatively preserve unnumbered argument order as well as types.
		preg_match_all( '/\{[a-z_][a-z0-9_]*\}/i', $source, $c ); preg_match_all( '/\{[a-z_][a-z0-9_]*\}/i', $target, $d ); sort( $c[0] ); sort( $d[0] );
		return $a[0] === $b[0] && $c[0] === $d[0];
	}
	private static function valid_format_translation( $source, $target ) {
		if ( false === strpos( $source, '%' ) || $source === $target ) return true;
		$format = '/%(?:%|(?:\d+\$)?[-+0#]*(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX])/';
		preg_match_all( $format, $source, $source_tokens );
		if ( ! array_diff( $source_tokens[0], array( '%%' ) ) || false !== strpos( preg_replace( $format, '', $source ), '%' ) ) return true;
		preg_match_all( $format, $target, $target_tokens );
		return false === strpos( preg_replace( $format, '', $target ), '%' ) && $source_tokens[0] === $target_tokens[0];
	}
	public static function dictionary( $language ) {
		if ( PandaTranslate_Repository::SCHEMA_VERSION !== (int) get_option( 'pandatranslate_schema' ) ) return array();
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Published rows from plugin-owned tables only; freshness and current public-post visibility are required. Gettext rows are cached per language for this request.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source_html,translated_html FROM %i WHERE target_language = %s AND status = 'published' AND source_kind <> 'gettext' AND translated_html <> '' AND unit_key IN (SELECT c.unit_key FROM %i c INNER JOIN %i p ON p.ID = c.post_id WHERE p.post_status = 'publish' AND p.post_password = '') ORDER BY id LIMIT 10000", PandaTranslate_Repository::table(), $language, $wpdb->prefix . 'pandatranslate_contexts', $wpdb->posts ), ARRAY_A );
		$dictionary = array(); $ambiguous = array();
		foreach ( $rows as $row ) {
			// Text-only replacement avoids executing HTML or destroying builder event handlers.
			if ( $row['source_html'] !== wp_strip_all_tags( $row['source_html'] ) || $row['translated_html'] !== wp_strip_all_tags( $row['translated_html'] ) ) continue;
			$key = html_entity_decode( trim( $row['source_html'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); $value = html_entity_decode( $row['translated_html'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( isset( $dictionary[$key] ) && $dictionary[$key] !== $value ) $ambiguous[$key] = true;
			$dictionary[$key] = $value;
		}
		return array_diff_key( $dictionary, $ambiguous );
	}
	/** Reconstruct JavaScript contexts/plural categories only from the authenticated static source registry. */
	public static function script_catalog( $language ) {
		$empty = array( 'plain'=>array(), 'context'=>array(), 'plural'=>array(), 'locale'=>'en' );
		foreach ( class_exists( 'PandaTranslate_Plugin' ) ? PandaTranslate_Plugin::language_rows() : array() as $row ) if ( $row['code'] === $language ) $empty['locale'] = str_replace( '_', '-', preg_replace( '/_formal$/', '', $row['locale'] ) );
		if ( PandaTranslate_Repository::SCHEMA_VERSION !== (int) get_option( 'pandatranslate_schema' ) ) return $empty;
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Published static gettext labels only, never cart/customer/order values.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT unit_key,source_html,source_domain,translated_html FROM %i WHERE target_language = %s AND source_kind = 'gettext' AND status = 'published' AND translated_html <> '' LIMIT 10000", PandaTranslate_Repository::table(), $language ), ARRAY_A );
		$registry = (array) get_option( PandaTranslate_JavaScript_Inventory::OPTION, array() ); $catalog = $empty;
		foreach ( $rows as $row ) {
			$source = $row['source_html']; $value = $row['translated_html']; $domain = $row['source_domain'];
			if ( $source !== wp_strip_all_tags( $source ) || $value !== wp_strip_all_tags( $value ) || ! self::same_placeholders( $source, $value ) ) continue;
			if ( hash_equals( hash( 'sha256', 'gettext|' . $domain . '||' . $source ), $row['unit_key'] ) ) { $catalog['plain'][$domain][$source] = $value; continue; }
			$entry = $registry[$row['unit_key']] ?? null;
			if ( ! is_array( $entry ) || ( $entry['text'] ?? null ) !== $source || ( $entry['domain'] ?? null ) !== $domain || ! hash_equals( PandaTranslate_JavaScript_Inventory::key( $entry ), $row['unit_key'] ) ) continue;
			if ( isset( $entry['category'], $entry['singular'], $entry['plural'], $entry['original_context'] ) ) {
				$id = hash( 'sha256', json_encode( array( $entry['original_context'], $entry['singular'], $entry['plural'] ) ) );
				if ( ! isset( $catalog['plural'][$domain][$id] ) ) $catalog['plural'][$domain][$id] = array( 'context'=>$entry['original_context'], 'singular'=>$entry['singular'], 'plural'=>$entry['plural'], 'forms'=>array() );
				$catalog['plural'][$domain][$id]['forms'][$entry['category']] = $value;
			} elseif ( '' !== $entry['context'] ) $catalog['context'][$domain][$entry['context']][$source] = $value;
		}
		foreach ( $catalog['plural'] as &$groups ) $groups = array_values( $groups ); unset( $groups );
		return $catalog;
	}
	public static function block_labels( $language ) { return self::script_catalog( $language )['plain']; }
	public static function assets() {
		$language = PandaTranslate_Frontend::current_language(); if ( ! $language ) return;
		$labels = self::script_catalog( $language );
		if ( $labels['plain'] || $labels['context'] || $labels['plural'] ) {
			// wp-hooks loads before wp-i18n and WooCommerce block bundles.
			wp_enqueue_script( 'wp-hooks' );
			wp_add_inline_script( 'wp-hooks', 'window.PandaTranslateBlockLabels=' . wp_json_encode( $labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';' . file_get_contents( __DIR__ . '/../assets/block-labels.js' ), 'after' );
		}
		wp_enqueue_script( 'pandatranslate-dynamic', PANDATRANSLATE_URL . 'assets/dynamic.js', array(), PANDATRANSLATE_VERSION, true );
		wp_localize_script( 'pandatranslate-dynamic', 'PandaTranslateDynamic', array( 'dictionary' => self::dictionary( $language ) ) );
	}
}
