<?php
defined( 'ABSPATH' ) || exit;

/** Stable translations attached to individual WordPress navigation items. */
final class PandaTranslate_Menu_Labels {
	const JOB = 'pandatranslate_translate_menu_item';
	private static $rows = array();

	public static function boot() {
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'preload' ), 30 );
		add_filter( 'nav_menu_item_title', array( __CLASS__, 'title' ), 20, 2 );
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'schedule' ), 20, 2 );
		add_action( self::JOB, array( __CLASS__, 'translate' ) );
	}

	private static function unit( $item ) {
		if ( empty( $item->ID ) || 'nav_menu_item' !== ( $item->post_type ?? '' ) || 'publish' !== $item->post_status || 0 === strpos( (string) $item->url, '#pandatranslate:' ) ) return null;
		$post = get_post( $item->ID );
		if ( ! $post ) return null;
		$label = $post->post_title;
		if ( '' === $label && 'post_type' === $item->type ) $label = (string) get_post_field( 'post_title', $item->object_id, 'raw' );
		if ( '' === $label && 'taxonomy' === $item->type ) {
			$term = WP_Term::get_instance( (int) $item->object_id, $item->object );
			if ( $term && ! is_wp_error( $term ) ) $label = $term->name;
		}
		if ( '' === $label && 'post_type_archive' === $item->type ) {
			$type = get_post_type_object( $item->object );
			if ( $type ) $label = $type->labels->archives;
		}
		$label = trim( wp_strip_all_tags( $label ) );
		if ( ! PandaTranslate_Template_Protector::has_text( $label ) ) return null;
		$label = esc_html( $label );
		$hash = hash( 'sha256', $label );
		return array( 'key' => hash( 'sha256', 'menu|' . $item->ID . '|' . $hash ), 'source_hash' => $hash, 'source_html' => $label, 'source_kind' => 'menu', 'source_domain' => (string) $item->ID );
	}

	private static function manual( $id, $language ) {
		return (string) get_post_meta( $id, '_pandatranslate_menu_label_' . $language, true );
	}

	public static function preload( $items ) {
		$language = PandaTranslate_Frontend::current_language();
		if ( ! $language || ! PandaTranslate_Edition::allows_language( $language ) || PandaTranslate_Plugin::editing_request() ) return $items;
		$cache_key = get_current_blog_id() . '|' . $language;
		$keys = array();
		foreach ( $items as $item ) {
			$unit = self::unit( $item );
			if ( $unit && ! array_key_exists( $unit['key'], self::$rows[ $cache_key ] ?? array() ) ) $keys[] = $unit['key'];
		}
		if ( $keys ) {
			$rows = ( new PandaTranslate_Repository() )->find_many( $keys, $language );
			foreach ( $keys as $key ) self::$rows[ $cache_key ][ $key ] = $rows[ $key ] ?? null;
		}
		return $items;
	}

	public static function title( $title, $item ) {
		if ( is_admin() ) return $title;
		$language = PandaTranslate_Frontend::current_language();
		if ( ! $language || ! PandaTranslate_Edition::allows_language( $language ) ) return $title;
		$unit = self::unit( $item );
		if ( ! $unit ) return $title;
		$editing = PandaTranslate_Plugin::editing_request();
		$manual = self::manual( $item->ID, $language );
		// The existing menu-item field remains authoritative over generated labels.
		if ( '' !== $manual ) return self::markup( esc_html( $manual ) );
		$repository = new PandaTranslate_Repository();
		$cached = self::$rows[ get_current_blog_id() . '|' . $language ] ?? array();
		$row = ! $editing && array_key_exists( $unit['key'], $cached ) ? $cached[ $unit['key'] ] : $repository->find( $unit['key'], $language, $unit['source_hash'] );
		$label = $row && ( $editing || 'published' === $row['status'] ) && '' !== $row['translated_html'] ? $row['translated_html'] : '';
		if ( $editing ) {
			if ( ! apply_filters( 'pandatranslate_capture_allowed', true ) ) return $title;
			if ( ! $row && is_wp_error( $repository->save( $unit, $language, '', 'untranslated', '', true ) ) ) return $title;
			return self::markup( $label ?: $unit['source_html'], $unit );
		}
		// Older rendered-page translations still apply while the item has no published label.
		return '' !== $label ? self::markup( wp_strip_all_tags( $label ) ) : $title;
	}

	private static function markup( $label, $unit = null ) {
		$attributes = $unit ? ' data-pandatranslate-unit="' . esc_attr( $unit['key'] ) . '" data-pandatranslate-source-hash="' . esc_attr( $unit['source_hash'] ) . '"' : '';
		return '<span translate="no" data-pandatranslate-menu-label' . $attributes . '>' . $label . '</span>';
	}

	public static function collect( $language ) {
		$repository = new PandaTranslate_Repository();
		foreach ( wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				$unit = self::unit( $item );
				if ( ! $unit || '' !== self::manual( $item->ID, $language ) || $repository->find( $unit['key'], $language, $unit['source_hash'] ) ) continue;
				$saved = $repository->save( $unit, $language, '', 'untranslated', '', true );
				if ( is_wp_error( $saved ) ) return $saved;
			}
		}
		return true;
	}

	public static function schedule( $menu_id, $id ) {
		if ( empty( PandaTranslate_Plugin::get_settings()['auto_enabled'] ) ) return;
		$args = array( (int) $id );
		if ( ! wp_next_scheduled( self::JOB, $args ) ) wp_schedule_single_event( time() + 10, self::JOB, $args );
	}

	public static function translate( $id ) {
		$settings = PandaTranslate_Plugin::get_settings();
		if ( empty( $settings['auto_enabled'] ) ) return;
		$post = get_post( $id );
		$unit = $post ? self::unit( wp_setup_nav_menu_item( $post ) ) : null;
		if ( ! $unit ) return;
		$repository = new PandaTranslate_Repository();
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) {
			$language = $row['code'];
			if ( ! PandaTranslate_Edition::allows_language( $language ) || '' !== self::manual( $id, $language ) ) continue;
			$known = $repository->find( $unit['key'], $language );
			if ( $known && 'untranslated' !== $known['status'] && '' !== $known['translated_html'] ) continue;
			if ( ! $known && true !== $repository->save( $unit, $language, '', 'untranslated', '', true ) ) continue;
			PandaTranslate_Plugin::auto_translate_known( $unit['key'], $language, $settings['auto_provider'] );
		}
	}

	public static function is_current( array $row, $language ) {
		$id = (int) $row['source_domain'];
		$post = get_post( $id );
		$unit = $post ? self::unit( wp_setup_nav_menu_item( $post ) ) : null;
		return $unit && $unit['key'] === $row['unit_key'] && '' === self::manual( $id, $language );
	}
}
