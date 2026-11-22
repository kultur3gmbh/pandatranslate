<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-pandatranslate-html.php';

/** Language presentation and internal navigation, without filtering global home/admin URLs. */
final class PandaTranslate_Frontend {
	private static $post_ids = array();
	private static $slug_languages = array();
	public static function boot() {
		add_filter( 'language_attributes', array( __CLASS__, 'language_attributes' ), 99, 2 );
		add_filter( 'nav_menu_link_attributes', array( __CLASS__, 'menu_attributes' ), 99, 2 );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( __CLASS__, 'invalidate_slugs' ), 10, 3 );
		add_action( 'switch_blog', array( __CLASS__, 'invalidate_slugs' ) );
	}
	public static function invalidate_slugs( $unused = null, $post_id = null, $key = null ) {
		if ( null === $key || 0 === strpos( $key, '_pandatranslate_slug_' ) ) { self::$slug_languages = array(); self::$post_ids = array(); }
	}
	/** Avoid WordPress's full rewrite-rule scan for every link when no translated post slug exists. */
	private static function has_post_slugs( $language ) {
		global $wpdb;
		// Virtual metadata providers may supply slugs without storing post meta.
		if ( has_filter( 'get_post_metadata' ) || ! isset( $wpdb->postmeta ) ) return true;
		$key = get_current_blog_id() . '|' . $language;
		if ( ! array_key_exists( $key, self::$slug_languages ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Request-local existence guard, invalidated by metadata writes and blog switches.
			self::$slug_languages[$key] = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM %i WHERE meta_key = %s AND meta_value <> '' LIMIT 1", $wpdb->postmeta, '_pandatranslate_slug_' . $language ) );
		}
		return self::$slug_languages[$key];
	}
	public static function current_language() {
		if ( is_admin() ) return '';
		// Gettext also runs during plugin loading, before WordPress creates its query.
		global $wp_query;
		$query_language = is_object( $wp_query ) && is_callable( array( $wp_query, 'get' ) ) ? $wp_query->get( 'pt_lang' ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value.
		$requested = isset( $_GET['pt_lang'] ) ? sanitize_key( wp_unslash( $_GET['pt_lang'] ) ) : sanitize_key( $query_language );
		// Source-page gettext runs thousands of times; no selector means no target to validate.
		if ( '' === $requested ) return '';
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
		$base = $base_url ?: ( ( $home['scheme'] ?? 'https' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? self::home_path() . '/' ) ) );
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
		if ( $endpoints && is_callable( array( 'PandaTranslate_Routing', 'system_url' ) ) ) { $system = PandaTranslate_Routing::system_url( $decoded, $language ); if ( null !== $system ) return $system; }
		if ( ! $endpoints && 'path' === $settings['url_mode'] && ( ! class_exists( 'PandaTranslate_Edition' ) || PandaTranslate_Edition::multilingual() ) && function_exists( 'url_to_postid' ) && self::has_post_slugs( $language ) ) {
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
	/** Translate approved public chrome only; transaction content never enters capture or the lookup source list. */
	public static function public_chrome( $html, $language ) {
		if ( ! $language || ! class_exists( 'PandaTranslate_Strings' ) || ! class_exists( 'DOMDocument' ) ) return $html;
		$dictionary = PandaTranslate_Strings::dictionary( $language );
		if ( is_callable( array( 'PandaTranslate_Strings', 'block_labels' ) ) ) {
			$static = array(); $ambiguous = array();
			foreach ( PandaTranslate_Strings::block_labels( $language ) as $catalog ) foreach ( $catalog as $source => $target ) { if ( isset( $static[$source] ) && $static[$source] !== $target ) $ambiguous[$source] = true; $static[$source] = $target; }
			$dictionary += array_diff_key( $static, $ambiguous );
		}
		$raw = PandaTranslate_HTML::protect_raw( $html ); $previous = libxml_use_internal_errors( true ); $doc = new DOMDocument( '1.0', 'UTF-8' );
		$ok = $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $raw['html'], LIBXML_HTML_NODEFDTD ); libxml_clear_errors(); libxml_use_internal_errors( $previous ); if ( ! $ok ) return $html;
		$xpath = new DOMXPath( $doc ); $changed = false; $nodes = array();
		$public_title = '';
		if ( function_exists( 'wc_get_page_id' ) && is_singular() && in_array( (int) get_queried_object_id(), array( (int) wc_get_page_id( 'cart' ), (int) wc_get_page_id( 'checkout' ), (int) wc_get_page_id( 'myaccount' ) ), true ) ) $public_title = ' | //*[contains(concat(" ", normalize-space(@class), " "), " wp-block-post-title ")]';
		foreach ( $xpath->query( '//header[not(ancestor::main or ancestor::form or ancestor::header)] | //footer[not(ancestor::main or ancestor::form or ancestor::footer)] | //nav[not(ancestor::main or ancestor::form or ancestor::header or ancestor::footer)]' . $public_title ) as $root ) {
			foreach ( $xpath->query( './/text()', $root ) as $node ) {
				$parent = $node->parentNode; if ( self::excluded( $parent ) || PandaTranslate_HTML::translation_excluded( $parent ) ) continue;
				$private = false;
				for ( $p = $parent; $p instanceof DOMElement; $p = $p->parentNode ) if ( in_array( $p->tagName, array( 'form', 'script', 'style', 'input', 'textarea', 'select' ), true ) || preg_match( '/(?:^|\s)(?:woocommerce-cart|woocommerce-checkout|woocommerce-MyAccount-content|wc-block-cart|wc-block-checkout)(?:\s|$)/', $p->getAttribute( 'class' ) ) ) { $private = true; break; }
				if ( $private ) continue;
				$text = trim( $node->nodeValue );
				if ( '' !== $text ) $nodes[] = $node;
			}
		}
		// Imported public dictionaries can lack page-context records. Query only the whitelisted chrome text,
		// never the main transaction body; no source units are recorded and no translation API is invoked.
		$memory = null;
		if ( method_exists( 'PandaTranslate_Repository', 'published_imports' ) ) {
			$source = ''; foreach ( $nodes as $node ) $source .= '<span>' . htmlspecialchars( trim( $node->nodeValue ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '</span>';
			$memory = new PandaTranslate_Translation_Memory( '<div>' . $source . '</div>', $language, new PandaTranslate_Repository() );
		}
		foreach ( $nodes as $node ) {
			$text = trim( $node->nodeValue ); $value = $dictionary[$text] ?? ( $memory ? $memory->lookup( $text ) : null );
			if ( null !== $value && $value === wp_strip_all_tags( $value ) ) { $node->nodeValue = str_replace( $text, $value, $node->nodeValue ); $changed = true; }
		}
		if ( ! $changed ) return $html;
		return PandaTranslate_HTML::restore_raw( PandaTranslate_HTML::remove_encoding_marker( $doc->saveHTML() ), $raw );
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
		return PandaTranslate_HTML::restore_raw( PandaTranslate_HTML::remove_encoding_marker( $doc->saveHTML() ), $raw );
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
