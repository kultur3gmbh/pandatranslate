<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Theme_Colors {
	public static function palette() {
		$colors = array();
		if ( is_callable( array( 'ContactPanda_Basic_Newsletters', 'detected_theme_colors' ) ) ) self::append( $colors, ContactPanda_Basic_Newsletters::detected_theme_colors() );
		$settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();
		self::append( $colors, $settings['color']['palette'] ?? array() );
		self::append( $colors, get_theme_support( 'editor-color-palette' ) );
		foreach ( array( 'background_color', 'header_textcolor' ) as $key ) { $value = get_theme_mod( $key, '' ); if ( is_string( $value ) && $value ) self::append( $colors, array( '#' . ltrim( $value, '#' ) ) ); }
		$fusion = (array) get_option( 'fusion_options', array() );
		foreach ( $fusion as $key => $value ) if ( in_array( $key, array( 'global_colors_palette', 'fusion_global_colors', 'color_palette', 'primary_color' ), true ) || preg_match( '/^color[0-9]+$/', $key ) ) self::append( $colors, array( $value ) );
		$kit_id = absint( get_option( 'elementor_active_kit' ) );
		$kit = $kit_id ? (array) get_post_meta( $kit_id, '_elementor_page_settings', true ) : array();
		self::append( $colors, $kit['system_colors'] ?? array() ); self::append( $colors, $kit['custom_colors'] ?? array() );
		$divi = (array) get_option( 'et_divi', array() );
		foreach ( array( 'accent_color', 'header_color', 'font_color', 'primary_nav_font_color', 'secondary_nav_font_color', 'footer_bg', 'custom_color_palette' ) as $key ) self::append( $colors, array( $divi[$key] ?? '' ) );
		return array_values( array_unique( apply_filters( 'pandatranslate_theme_colors', $colors ) ) );
	}
	private static function append( &$colors, $items ) {
		foreach ( (array) $items as $item ) {
			if ( is_array( $item ) ) { self::append( $colors, $item ); continue; }
			if ( ! is_string( $item ) ) continue;
			$decoded = json_decode( $item, true ); if ( is_array( $decoded ) ) { self::append( $colors, $decoded ); continue; }
			$color = sanitize_hex_color( $item ); if ( $color ) $colors[] = strtolower( $color );
		}
	}
}
