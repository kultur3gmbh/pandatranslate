<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-pandatranslate-html.php';

/** Language presentation and internal navigation, without filtering global home/admin URLs. */
final class PandaTranslate_Frontend {
	private static $post_ids = array();
	public static function boot() {
		add_filter( 'language_attributes', array( __CLASS__, 'language_attributes' ), 99, 2 );
		add_filter( 'nav_menu_link_attributes', array( __CLASS__, 'menu_attributes' ), 99, 2 );
	}
	public static function current_language() {
		if ( is_admin() ) return '';
		$requested = isset( $_GET['pt_lang'] ) ? sanitize_key( wp_unslash( $_GET['pt_lang'] ) ) : sanitize_key( get_query_var( 'pt_lang' ) );
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( ! empty( $row['active'] ) && $row['code'] === $requested ) return $requested;
		return '';
	}
	private static function row( $language ) {
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( ! empty( $row['active'] ) && $row['code'] === $language ) return $row;
		return null;
	}
	private static function locale( $row ) {
		$locale = str_replace( '_', '-', preg_replace( '/_formal$/i', '', $row['locale'] ?? ( get_option( 'WPLANG', 'en_US' ) ?: 'en_US' ) ) );
		$base = strtolower( explode( '-', $locale )[0] );
		return in_array( $base, array( 'de', 'en' ), true ) ? $base : $locale;
	}
	private static function direction( $locale ) { return in_array( strtolower( explode( '-', $locale )[0] ), array( 'ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug', 'yi', 'dv', 'ckb' ), true ) ? 'rtl' : 'ltr'; }
	public static function language_attributes( $attributes, $doctype = 'html' ) {
		if ( is_admin() ) return $attributes;
		$row = self::row( self::current_language() );
		$locale = self::locale( $row );
		$attributes = preg_replace( '/(?:^|\s)(?:xml:lang|lang|dir)\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27|[^\s>]+)/i', '', $attributes );
		return trim( $attributes . ' lang="' . esc_attr( $locale ) . '" dir="' . self::direction( $locale ) . '"' . ( 'xhtml' === $doctype ? ' xml:lang="' . esc_attr( $locale ) . '"' : '' ) );
	}
	public static function menu_attributes( $attributes, $item ) {
		if ( ! empty( $attributes['hreflang'] ) || preg_match( '/pandatranslate|language[-_]switcher/', implode( ' ', (array) ( $item->classes ?? array() ) ) ) ) return $attributes;
		if ( isset( $attributes['href'] ) ) $attributes['href'] = self::localize_url( $attributes['href'], self::current_language() );
		return $attributes;
	}
	private static function home_path() { return rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' ); }
	private static function relative_path( $path ) {
		$home = self::home_path();
		if ( $home && $path !== $home && 0 !== strpos( $path, $home . '/' ) ) return null;
		return ltrim( substr( $path, strlen( $home ) ), '/' );
	}
	private static function normalize_path( $path ) {
		$parts = array(); foreach ( explode( '/', $path ) as $part ) { if ( '' === $part || '.' === $part ) continue; if ( '..' === $part ) array_pop( $parts ); else $parts[] = $part; }
		return '/' . implode( '/', $parts ) . ( '/' === substr( $path, -1 ) && $parts ? '/' : '' );
	}
	private static function technical_path( $path, $endpoints ) {
		if ( preg_match( '#(?:^|/)(?:wp-admin|wp-json|wp-content|wp-includes)(?:/|$)|(?:^|/)(?:wp-login\.php|wp-cron\.php|xmlrpc\.php|robots\.txt|[^/]*sitemap[^/]*\.xml)(?:/|$)#i', $path ) ) return true;
		if ( preg_match( '/\.[a-z0-9]{1,10}$/i', rtrim( $path, '/' ) ) && ! preg_match( '/\.(?:html?|php)$/i', rtrim( $path, '/' ) ) ) return true;
		if ( ! $endpoints && function_exists( 'wc_get_page_id' ) ) foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$id = wc_get_page_id( $page ); if ( $id <= 0 ) continue;
			$endpoint = self::relative_path( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) );
			if ( $endpoint && ( trim( $path, '/' ) === trim( $endpoint, '/' ) || 0 === strpos( $path, trim( $endpoint, '/' ) . '/' ) ) ) return true;
		}
		return false;
	}
	/** Preserve explicit language choices and technical/signed URLs; localize ordinary site navigation. */
	public static function localize_url( $url, $language, $endpoints = false, $base_url = '' ) {
		$target = self::row( $language ); $settings = PandaTranslate_Plugin::get_settings();
		if ( ! $target || $language === $settings['source_language'] || ! is_string( $url ) || '' === trim( $url ) || '#' === substr( trim( $url ), 0, 1 ) ) return $url;
		$decoded = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( preg_match( '/[\x00-\x20\\\\]/', $decoded ) || ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $decoded, $scheme ) && ! in_array( strtolower( $scheme[1] ), array( 'http', 'https' ), true ) ) ) return $url;
		$parts = wp_parse_url( $decoded ); if ( false === $parts || isset( $parts['user'] ) || isset( $parts['pass'] ) ) return $url;
		$home = wp_parse_url( home_url( '/' ) );
		if ( isset( $parts['host'] ) && ( strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || ( $parts['port'] ?? null ) !== ( $home['port'] ?? null ) ) ) return $url;
		if ( ! isset( $parts['host'] ) && isset( $parts['scheme'] ) ) return $url;
		$base = $base_url ?: ( ( $home['scheme'] ?? 'https' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . ( $_SERVER['REQUEST_URI'] ?? self::home_path() . '/' ) );
		$base_parts = wp_parse_url( $base );
		$path = $parts['path'] ?? ( isset( $parts['host'] ) ? '/' : '' );
		if ( '' === $path || '/' !== substr( $path, 0, 1 ) ) {
			if ( ! empty( $base_parts['host'] ) && strtolower( $base_parts['host'] ) !== strtolower( $home['host'] ) ) return $url;
			$base_path = $base_parts['path'] ?? '/';
			$path = '' === $path ? $base_path : substr( $base_path, 0, strrpos( $base_path, '/' ) + 1 ) . $path;
		}
		$path = self::relative_path( self::normalize_path( $path ) ); if ( null === $path ) return $url;
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( ! empty( $row['active'] ) && $row['code'] !== $settings['source_language'] && ( $path === $row['slug'] || 0 === strpos( $path, $row['slug'] . '/' ) ) ) return $url;
		$args = array(); parse_str( $parts['query'] ?? '', $args );
		foreach ( array( 'pt_lang', 'lang', 'rest_route', '_wpnonce', '_wp_http_referer', 'wc-ajax', 'wc-api', 'add-to-cart', 'download_file', 'key', 'action', 'preview', 'customize_changeset_uuid', 'elementor-preview', 'pandatranslate', 'pandatranslate_scan' ) as $key ) if ( array_key_exists( $key, $args ) ) return $url;
		if ( self::technical_path( rawurldecode( $path ), $endpoints ) ) return $url;
		if ( ! $endpoints && 'path' === $settings['url_mode'] && ( ! class_exists( 'PandaTranslate_Edition' ) || PandaTranslate_Edition::multilingual() ) && function_exists( 'url_to_postid' ) ) {
			$source = home_url( '/' . $path ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
			if ( ! array_key_exists( $source, self::$post_ids ) ) self::$post_ids[$source] = url_to_postid( $source );
			$id = self::$post_ids[$source]; $slug = $id ? get_post_meta( $id, '_pandatranslate_slug_' . $language, true ) : '';
			if ( $slug && $path ) { $segments = explode( '/', trim( $path, '/' ) ); $segments[count( $segments ) - 1] = sanitize_title( $slug ); $path = implode( '/', $segments ) . ( '/' === substr( $path, -1 ) ? '/' : '' ); }
		}
		if ( 'path' === $settings['url_mode'] ) $path = apply_filters( 'pandatranslate_localized_path', $path, $language );
		$result = home_url( '/' . ( 'query' === $settings['url_mode'] ? '' : $target['slug'] . '/' ) . $path );
		$query = $parts['query'] ?? '';
		if ( 'query' === $settings['url_mode'] ) $query .= ( '' !== $query ? '&' : '' ) . 'pt_lang=' . rawurlencode( $language );
		return $result . ( '' !== $query ? '?' . $query : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
	}
	/** Decode visible symbol entities without turning escaped markup or attribute syntax into HTML. */
	public static function glyphs( $text ) {
		return preg_replace_callback( '/&(?:amp;){0,3}(?:#[0-9]{1,8};|#x[0-9a-f]{1,6};|[a-z][a-z0-9]{1,31};)/i', function ( $match ) {
			$value = $match[0]; for ( $i = 0; $i < 4; ++$i ) { $next = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); if ( $next === $value ) break; $value = $next; }
			return preg_match( '/^[^\x00-\x7f]+$/u', $value ) ? $value : $match[0];
		}, $text );
	}
	public static function transform( $html, $language ) {
		if ( is_admin() || ! class_exists( 'DOMDocument' ) || false === stripos( $html, '<html' ) ) return $html;
		$row = self::row( $language );
		$raw = PandaTranslate_HTML::protect_raw( $html );
		$previous = libxml_use_internal_errors( true ); $doc = new DOMDocument( '1.0', 'UTF-8' );
		$loaded = $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $raw['html'], LIBXML_HTML_NODEFDTD ); libxml_clear_errors(); libxml_use_internal_errors( $previous ); if ( ! $loaded ) return $html;
		$root = $doc->documentElement; $locale = self::locale( $row ); $root->setAttribute( 'lang', $locale ); $root->setAttribute( 'dir', self::direction( $locale ) ); if ( $root->hasAttribute( 'xml:lang' ) ) $root->setAttribute( 'xml:lang', $locale );
		$base = ''; foreach ( $doc->getElementsByTagName( 'base' ) as $element ) if ( $element->hasAttribute( 'href' ) ) { $base = $element->getAttribute( 'href' ); break; }
		foreach ( ( new DOMXPath( $doc ) )->query( '//*' ) as $node ) {
			if ( self::excluded( $node ) ) continue;
			if ( 'a' === strtolower( $node->nodeName ) && $node->hasAttribute( 'href' ) && ! $node->hasAttribute( 'download' ) && ! $node->hasAttribute( 'hreflang' ) && ! self::switcher( $node ) ) $node->setAttribute( 'href', self::localize_url( $node->getAttribute( 'href' ), $language, false, $base ) );
			foreach ( array( 'title', 'alt', 'placeholder', 'aria-label' ) as $attribute ) if ( $node->hasAttribute( $attribute ) ) $node->setAttribute( $attribute, self::glyphs( $node->getAttribute( $attribute ) ) );
			foreach ( $node->childNodes as $child ) if ( XML_TEXT_NODE === $child->nodeType ) { $value = self::glyphs( $child->nodeValue ); if ( $value !== $child->nodeValue ) $child->nodeValue = $value; }
		}
		return PandaTranslate_HTML::restore_raw( preg_replace( '/^<\?xml[^>]+>\s*/', '', $doc->saveHTML() ), $raw );
	}
	private static function excluded( $node ) {
		for ( $current = $node; $current && XML_ELEMENT_NODE === $current->nodeType; $current = $current->parentNode ) if ( in_array( strtolower( $current->nodeName ), array( 'script', 'style', 'noscript', 'code', 'pre', 'textarea', 'svg' ), true ) || in_array( $current->getAttribute( 'id' ), array( 'wpadminbar', 'query-monitor-main', 'elementor-editor-wrapper', 'bricks-toolbar' ), true ) || preg_match( '/(?:^|\s)(?:ab-sub-wrapper|fusion-builder-ui|awb-builder|fl-builder-bar|pandatranslate-panel)(?:\s|$)/', $current->getAttribute( 'class' ) ) ) return true;
		return false;
	}
	private static function switcher( $node ) {
		for ( $current = $node; $current && XML_ELEMENT_NODE === $current->nodeType; $current = $current->parentNode ) if ( preg_match( '/(?:^|\s)(?:pandatranslate[^\s]*|language[-_]switcher[^\s]*)(?:\s|$)/', $current->getAttribute( 'class' ) ) || $current->hasAttribute( 'data-pt-language' ) ) return true;
		return false;
	}
}
