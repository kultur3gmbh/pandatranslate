<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Routing {
	private static $booted = false;
	public static function boot() {
		if ( self::$booted ) return; self::$booted = true;
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'refresh_rules' ), 30 );
		add_action( 'parse_request', array( __CLASS__, 'resolve_translated_slug' ), 20 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'canonical_redirect' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'ticket_registration_redirect' ), 20 );
		add_filter( 'woocommerce_get_checkout_url', array( __CLASS__, 'ticket_registration_redirect' ), 99 );
		add_filter( 'pandatranslate_capture_allowed', array( __CLASS__, 'ticket_registration_capture' ) );
	}
	private static function ticket_registration() {
		return function_exists( 'tribe' ) && class_exists( 'Tribe__Tickets__Attendee_Registration__Main' ) ? tribe( 'tickets.attendee_registration' ) : null;
	}
	public static function ticket_registration_redirect( $url ) {
		if ( ! is_string( $url ) || ! class_exists( 'PandaTranslate_Frontend' ) ) return $url;
		$language = PandaTranslate_Frontend::current_language();
		if ( ! $language ) return $url;
		$registration = self::ticket_registration();
		if ( ! is_callable( array( $registration, 'get_url' ) ) ) return $url;
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$registration_path = (string) wp_parse_url( $registration->get_url(), PHP_URL_PATH );
		if ( '' === trim( $registration_path, '/' ) || untrailingslashit( $path ) !== untrailingslashit( $registration_path ) ) return $url;
		return PandaTranslate_Frontend::localize_url( $url, $language, true );
	}
	public static function ticket_registration_capture( $allowed ) {
		if ( ! $allowed ) return false;
		$registration = self::ticket_registration();
		// Attendee forms can contain personal information, like checkout forms.
		return ! is_callable( array( $registration, 'is_on_page' ) ) || ! $registration->is_on_page();
	}
	public static function query_vars( $vars ) { $vars[] = 'pt_lang'; return array_values( array_unique( $vars ) ); }
	public static function rules( $rules ) {
		$base = array_filter( $rules, function ( $query ) { return false === strpos( $query, 'pt_lang=' ) && false === strpos( $query, 'pandatranslate_slug=' ); } );
		$translated = array(); $source = PandaTranslate_Plugin::get_settings()['source_language'];
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) {
			if ( empty( $row['active'] ) || $row['code'] === $source ) continue;
			$prefix = preg_quote( $row['slug'], '#' ); $lang = rawurlencode( $row['code'] );
			$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
			$translated[ '^' . $prefix . '/?$' ] = 'index.php?pt_lang=' . $lang . ( $front ? '&page_id=' . $front : '' );
			foreach ( $base as $pattern => $query ) {
				if ( 0 !== strpos( $query, 'index.php?' ) || preg_match( '/(?:wp-json|wp-sitemap|robots|favicon)/', $pattern ) ) continue;
				$translated[ '^' . $prefix . '/' . ltrim( $pattern, '^' ) ] = $query . '&pt_lang=' . $lang;
			}
		}
		return self::system_rules() + $translated + $base;
	}
	public static function refresh_rules() {
		$languages = array_map( function ( $row ) { return array( 'code' => $row['code'], 'slug' => $row['slug'], 'active' => ! empty( $row['active'] ) ); }, PandaTranslate_Plugin::language_rows() );
		$system_rules = self::system_rules();
		$signature = md5( wp_json_encode( array( 'paths-v4-system-pages', $languages, PandaTranslate_Plugin::get_settings()['source_language'], PandaTranslate_Plugin::get_settings()['url_mode'], $system_rules, get_option( 'permalink_structure' ), get_option( 'show_on_front' ), get_option( 'page_on_front' ), get_option( 'pandatranslate_url_bases' ), defined( 'PANDATRANSLATE_VERSION' ) ? PANDATRANSLATE_VERSION : '', defined( 'PANDATRANSLATE_MULTILINGUAL_VERSION' ) ? PANDATRANSLATE_MULTILINGUAL_VERSION : '', defined( 'WC_VERSION' ) ? WC_VERSION : '' ) ) );
		if ( get_option( 'pandatranslate_path_rules' ) === $signature && ! array_diff_assoc( $system_rules, (array) get_option( 'rewrite_rules', array() ) ) ) return;
		flush_rewrite_rules( false );
		// A deferred or blocked flush must not mark stale routes as current.
		if ( array_diff_assoc( $system_rules, (array) get_option( 'rewrite_rules', array() ) ) ) return;
		update_option( 'pandatranslate_path_rules', $signature, false );
	}
	/** Published WooCommerce pages and their static translated slugs; no customer/session data. */
	private static function system_pages() {
		if ( ! function_exists( 'wc_get_page_id' ) || ! function_exists( 'get_post_status' ) ) return array();
		$pages = array();
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $type ) {
			$id = (int) wc_get_page_id( $type );
			if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) continue;
			$path = self::relative_path( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) );
			if ( '' === $path ) continue;
			$pages[$id] = array( 'type' => $type, 'path' => $path, 'slugs' => array() );
			foreach ( PandaTranslate_Plugin::language_rows() as $row ) $pages[$id]['slugs'][$row['code']] = sanitize_title( (string) get_post_meta( $id, '_pandatranslate_slug_' . $row['code'], true ) );
		}
		return $pages;
	}
	private static function system_path( $page, $language ) {
		$parts = explode( '/', $page['path'] );
		if ( ! empty( $page['slugs'][$language] ) && ( ! class_exists( 'PandaTranslate_Edition' ) || PandaTranslate_Edition::multilingual() ) ) $parts[count( $parts ) - 1] = $page['slugs'][$language];
		return implode( '/', $parts );
	}
	private static function endpoints() {
		if ( ! function_exists( 'WC' ) || ! is_object( WC() ) || ! isset( WC()->query ) || ! is_callable( array( WC()->query, 'get_query_vars' ) ) ) return array();
		return (array) WC()->query->get_query_vars();
	}
	private static function system_rules() {
		$rules = array(); $source = PandaTranslate_Plugin::get_settings()['source_language'];
		foreach ( self::system_pages() as $id => $page ) foreach ( PandaTranslate_Plugin::language_rows() as $row ) {
			if ( empty( $row['active'] ) || $row['code'] === $source ) continue;
			foreach ( array_unique( array( $page['path'], self::system_path( $page, $row['code'] ) ) ) as $path ) {
				$prefix = '^' . preg_quote( trim( $row['slug'] . '/' . $path, '/' ), '#' );
				$query = 'index.php?page_id=' . $id . '&pt_lang=' . rawurlencode( $row['code'] );
				$rules[$prefix . '/?$'] = $query;
				if ( 'cart' !== $page['type'] ) foreach ( self::endpoints() as $key => $endpoint ) {
					if ( ! $endpoint || ! preg_match( '/^[a-z0-9_-]+$/i', $key ) ) continue;
					$rules[$prefix . '/' . preg_quote( trim( $endpoint, '/' ), '#' ) . '(?:/(.*?))?/?$'] = $query . '&' . $key . '=$matches[1]';
				}
			}
		}
		return $rules;
	}
	/** Return null for ordinary URLs; choose only a registered system-page route, otherwise the original query route. */
	public static function system_url( $url, $language ) {
		$home = wp_parse_url( home_url( '/' ) ); $parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || empty( $parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || ( $parts['port'] ?? null ) !== ( $home['port'] ?? null ) ) return null;
		$args = array(); parse_str( $parts['query'] ?? '', $args );
		foreach ( array( 'key', 'wc-api', 'wc-ajax', 'add-to-cart', 'download_file', '_wpnonce', 'action' ) as $key ) if ( array_key_exists( $key, $args ) ) return $url;
		$settings = PandaTranslate_Plugin::get_settings(); $target = null; $path = self::relative_path( $parts['path'] ?? '/' );
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) {
			if ( ! empty( $row['active'] ) && $row['code'] === $language ) $target = $row;
			if ( ! empty( $row['active'] ) && $row['code'] !== $settings['source_language'] && 0 === strpos( $path, $row['slug'] . '/' ) ) $path = substr( $path, strlen( $row['slug'] ) + 1 );
		}
		if ( ! $target ) return null;
		foreach ( self::system_pages() as $id => $page ) {
			$variants = array( $page['path'] );
			foreach ( PandaTranslate_Plugin::language_rows() as $row ) $variants[] = self::system_path( $page, $row['code'] );
			foreach ( array_unique( $variants ) as $base ) {
				$tail = $path === $base ? '' : ( 0 === strpos( $path, $base . '/' ) ? substr( $path, strlen( $base ) ) : null );
				if ( null === $tail ) continue;
				$original = home_url( user_trailingslashit( '/' . $page['path'] . $tail ) );
				unset( $args['pt_lang'] );
				if ( $language !== $settings['source_language'] ) {
					$localized = trim( $target['slug'] . '/' . self::system_path( $page, $language ) . $tail, '/' );
					$ready = false;
					foreach ( (array) get_option( 'rewrite_rules', array() ) as $regex => $query ) {
						if ( ! preg_match( '#^' . str_replace( '#', '\\#', $regex ) . '#', $localized ) ) continue;
						$expected = 'index.php?page_id=' . $id . '&pt_lang=' . rawurlencode( $language );
						$ready = $query === $expected || 0 === strpos( $query, $expected . '&' ); break;
					}
					if ( 'path' === $settings['url_mode'] && $ready ) $original = home_url( user_trailingslashit( '/' . $localized ) );
					else $args['pt_lang'] = $language;
				}
				return ( $args ? add_query_arg( $args, $original ) : $original ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
			}
		}
		return null;
	}

	/** Build every switcher URL from the original content path, never another language's prefix. */
	public static function url( $code ) {
		$settings = PandaTranslate_Plugin::get_settings(); $rows = PandaTranslate_Plugin::language_rows(); $target = null;
		foreach ( $rows as $row ) if ( $row['code'] === $code && ! empty( $row['active'] ) ) $target = $row;
		if ( ! $target ) return home_url( '/' );
		$base = is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' . ltrim( self::original_path( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ), '/' ) );
		parse_str( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_QUERY ), $system_args );
		unset( $system_args['pt_lang'], $system_args['pandatranslate'], $system_args['pandatranslate_scan'], $system_args['_pt_editor_nonce'] );
		$system = self::system_url( $system_args ? add_query_arg( $system_args, $base ) : $base, $code );
		if ( null !== $system ) return $system;
		$path = self::relative_path( (string) wp_parse_url( $base, PHP_URL_PATH ) );
		if ( 'query' === $settings['url_mode'] ) {
			$url = home_url( user_trailingslashit( '/' . $path ) );
			$query = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_QUERY ); parse_str( $query, $args );
			unset( $args['pt_lang'], $args['pandatranslate'], $args['pandatranslate_scan'], $args['_pt_editor_nonce'] );
			if ( $code !== $settings['source_language'] ) $args['pt_lang'] = $code;
			return $args ? add_query_arg( $args, $url ) : $url;
		}
		if ( is_singular() && $code !== $settings['source_language'] && ( ! class_exists( 'PandaTranslate_Edition' ) || PandaTranslate_Edition::multilingual() ) ) {
			$slug = get_post_meta( get_queried_object_id(), '_pandatranslate_slug_' . $code, true );
			if ( $slug && $path ) { $parts = explode( '/', trim( $path, '/' ) ); $parts[count( $parts ) - 1] = sanitize_title( $slug ); $path = implode( '/', $parts ); }
		}
		$path = apply_filters( 'pandatranslate_localized_path', $path, $code );
		$url = home_url( user_trailingslashit( '/' . ( $code === $settings['source_language'] ? '' : $target['slug'] . '/' ) . ltrim( $path, '/' ) ) );
		$query = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_QUERY ); parse_str( $query, $args );
		unset( $args['pt_lang'], $args['pandatranslate'], $args['pandatranslate_scan'], $args['_pt_editor_nonce'] );
		return $args ? add_query_arg( $args, $url ) : $url;
	}
	private static function relative_path( $path ) {
		$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( $home && ( $path === $home || 0 === strpos( $path, $home . '/' ) ) ) $path = substr( $path, strlen( $home ) );
		return trim( $path, '/' );
	}
	public static function original_path( $url ) {
		$path = self::relative_path( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( ! empty( $row['active'] ) && $row['code'] !== PandaTranslate_Plugin::get_settings()['source_language'] && ( $path === $row['slug'] || 0 === strpos( $path, $row['slug'] . '/' ) ) ) return apply_filters( 'pandatranslate_original_path', ltrim( substr( $path, strlen( $row['slug'] ) ), '/' ), $row['code'] );
		return $path;
	}
	public static function resolve_translated_slug( $wp ) {
		$language = $wp->query_vars['pt_lang'] ?? '';
		// A cache or another rewrite generator can omit or shadow a persisted rule. Recover only an exact, configured WooCommerce route.
		if ( false !== strpos( $wp->request, '/' ) && self::resolve_system_request( $wp, $language ) ) return;
		if ( ! $language ) return;
		// Explicit system routes already resolve the original page and preserve Woo endpoint variables.
		if ( ! empty( $wp->query_vars['page_id'] ) && isset( self::system_pages()[(int) $wp->query_vars['page_id']] ) ) return;
		$path = self::original_path( home_url( '/' . $wp->request ) );
		if ( ! $path ) {
			// A custom language query variable prevents WP_Query's implicit static-front-page detection.
			// Pin the configured page explicitly for both /en/ and /?pt_lang=en.
			$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
			if ( ! $front ) return;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value.
			foreach ( array( 's', 'p', 'name', 'cat', 'tag', 'author', 'feed', 'taxonomy' ) as $selector ) if ( ! empty( $wp->query_vars[$selector] ) && (int) ( $wp->query_vars['page_id'] ?? 0 ) === $front && empty( $_GET['page_id'] ) ) { unset( $wp->query_vars['page_id'] ); return; }
			foreach ( array( 's', 'p', 'page_id', 'pagename', 'name', 'post_type', 'cat', 'category_name', 'tag', 'author', 'author_name', 'feed', 'taxonomy', 'rest_route' ) as $selector ) if ( ! empty( $wp->query_vars[$selector] ) ) return;
			$wp->query_vars['page_id'] = $front; unset( $wp->query_vars['error'] );
			if ( ! empty( $wp->query_vars['paged'] ) ) { $wp->query_vars['page'] = $wp->query_vars['paged']; unset( $wp->query_vars['paged'] ); }
			return;
		}
		if ( class_exists( 'PandaTranslate_Edition' ) && ! PandaTranslate_Edition::multilingual() ) return;
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Read-only translated-slug lookup is bounded to two published posts; standard postmeta API is needed for existing stored slug assignments.
		$posts = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 2, 'meta_key' => '_pandatranslate_slug_' . $language, 'meta_value' => sanitize_title( basename( $path ) ) ) );
		foreach ( $posts as $post ) {
			$original = self::relative_path( (string) wp_parse_url( get_permalink( $post->ID ), PHP_URL_PATH ) );
			$parts = explode( '/', $original ); $parts[count( $parts ) - 1] = sanitize_title( basename( $path ) );
			if ( implode( '/', $parts ) !== trim( $path, '/' ) ) continue;
			$wp->query_vars = array( 'pt_lang' => $language, 'post_type' => $post->post_type );
			$wp->query_vars[ 'page' === $post->post_type ? 'page_id' : 'p' ] = $post->ID;
			return;
		}
	}

	private static function resolve_system_request( $wp, $language ) {
		$prefix = explode( '/', $wp->request, 2 )[0];
		$source = PandaTranslate_Plugin::get_settings()['source_language'];
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) {
			if ( empty( $row['active'] ) || $source === $row['code'] || $row['slug'] !== $prefix || ( $language && $language !== $row['code'] ) ) continue;
			foreach ( self::system_rules() as $pattern => $query ) {
				if ( ! preg_match( '#' . $pattern . '#', $wp->request, $matches ) && ! preg_match( '#' . $pattern . '#', urldecode( $wp->request ), $matches ) ) continue;
				parse_str( WP_MatchesMapRegex::apply( substr( $query, strlen( 'index.php?' ) ), $matches ), $route );
				foreach ( array( 'error', 'name', 'pagename', 'p', 'post_type', 'attachment', 'attachment_id' ) as $key ) unset( $wp->query_vars[$key] );
				$wp->query_vars = $route + $wp->query_vars;
				return true;
			}
			break;
		}
		return false;
	}
	public static function canonical_redirect( $redirect ) { return get_query_var( 'pt_lang' ) ? false : $redirect; }
}
