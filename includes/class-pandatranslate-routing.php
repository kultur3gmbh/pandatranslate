<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Routing {
	private static $booted = false;
	public static function boot() {
		if ( self::$booted ) return; self::$booted = true;
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'rules' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'refresh_rules' ), 30 );
		add_action( 'parse_request', array( __CLASS__, 'resolve_translated_slug' ), 20 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'canonical_redirect' ) );
	}
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
		return $translated + $base;
	}
	public static function refresh_rules() {
		$signature = md5( 'paths-v2|' . wp_json_encode( PandaTranslate_Plugin::language_rows() ) . '|' . get_option( 'permalink_structure' ) . '|' . get_option( 'show_on_front' ) . '|' . get_option( 'page_on_front' ) );
		if ( get_option( 'pandatranslate_path_rules' ) === $signature ) return;
		flush_rewrite_rules( false ); update_option( 'pandatranslate_path_rules', $signature, false );
	}
	/** Build every switcher URL from the original content path, never another language's prefix. */
	public static function url( $code ) {
		$settings = PandaTranslate_Plugin::get_settings(); $rows = PandaTranslate_Plugin::language_rows(); $target = null;
		foreach ( $rows as $row ) if ( $row['code'] === $code && ! empty( $row['active'] ) ) $target = $row;
		if ( ! $target ) return home_url( '/' );
		$base = is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' . ltrim( self::original_path( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), '/' ) );
		$path = self::relative_path( (string) wp_parse_url( $base, PHP_URL_PATH ) );
		if ( 'query' === $settings['url_mode'] ) {
			$url = home_url( user_trailingslashit( '/' . $path ) );
			$query = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY ); parse_str( $query, $args );
			unset( $args['pt_lang'], $args['pandatranslate'], $args['pandatranslate_scan'] );
			if ( $code !== $settings['source_language'] ) $args['pt_lang'] = $code;
			return $args ? add_query_arg( $args, $url ) : $url;
		}
		if ( is_singular() && $code !== $settings['source_language'] && ( ! class_exists( 'PandaTranslate_Edition' ) || PandaTranslate_Edition::multilingual() ) ) {
			$slug = get_post_meta( get_queried_object_id(), '_pandatranslate_slug_' . $code, true );
			if ( $slug && $path ) { $parts = explode( '/', trim( $path, '/' ) ); $parts[count( $parts ) - 1] = sanitize_title( $slug ); $path = implode( '/', $parts ); }
		}
		$path = apply_filters( 'pandatranslate_localized_path', $path, $code );
		$url = home_url( user_trailingslashit( '/' . ( $code === $settings['source_language'] ? '' : $target['slug'] . '/' ) . ltrim( $path, '/' ) ) );
		$query = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY ); parse_str( $query, $args );
		unset( $args['pt_lang'], $args['pandatranslate'], $args['pandatranslate_scan'] );
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
		$language = $wp->query_vars['pt_lang'] ?? ''; if ( ! $language ) return;
		$path = self::original_path( home_url( '/' . $wp->request ) );
		if ( ! $path ) {
			// A custom language query variable prevents WP_Query's implicit static-front-page detection.
			// Pin the configured page explicitly for both /en/ and /?pt_lang=en.
			$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
			if ( ! $front ) return;
			foreach ( array( 's', 'p', 'name', 'cat', 'tag', 'author', 'feed', 'taxonomy' ) as $selector ) if ( ! empty( $wp->query_vars[$selector] ) && (int) ( $wp->query_vars['page_id'] ?? 0 ) === $front && empty( $_GET['page_id'] ) ) { unset( $wp->query_vars['page_id'] ); return; }
			foreach ( array( 's', 'p', 'page_id', 'pagename', 'name', 'post_type', 'cat', 'category_name', 'tag', 'author', 'author_name', 'feed', 'taxonomy', 'rest_route' ) as $selector ) if ( ! empty( $wp->query_vars[$selector] ) ) return;
			$wp->query_vars['page_id'] = $front; unset( $wp->query_vars['error'] );
			if ( ! empty( $wp->query_vars['paged'] ) ) { $wp->query_vars['page'] = $wp->query_vars['paged']; unset( $wp->query_vars['paged'] ); }
			return;
		}
		if ( class_exists( 'PandaTranslate_Edition' ) && ! PandaTranslate_Edition::multilingual() ) return;
		$posts = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 2, 'meta_key' => '_pandatranslate_slug_' . $language, 'meta_value' => sanitize_title( basename( $path ) ) ) );
		foreach ( $posts as $post ) {
			$original = self::relative_path( (string) wp_parse_url( get_permalink( $post->ID ), PHP_URL_PATH ) );
			$parts = explode( '/', $original ); $parts[count( $parts ) - 1] = sanitize_title( basename( $path ) );
			if ( implode( '/', $parts ) !== trim( $path, '/' ) ) continue;
			$wp->query_vars = array( 'pt_lang' => $language, 'post_type' => $post->post_type, 'p' => $post->ID );
			return;
		}
	}
	public static function canonical_redirect( $redirect ) { return get_query_var( 'pt_lang' ) ? false : $redirect; }
}
