<?php
defined( 'ABSPATH' ) || exit;

/** Conditional content compatibility, independent of importer/plugin detection. */
final class PandaTranslate_Language_Content {
	public static function boot() { add_action( 'init', array( __CLASS__, 'register' ), 20 ); }
	public static function register() {
		foreach ( array( 'language-include', 'language-exclude', 'trp_language' ) as $tag ) if ( ! shortcode_exists( $tag ) ) add_shortcode( $tag, array( __CLASS__, 'render' ) );
	}
	private static function normalize( $value ) { return strtolower( str_replace( '-', '_', trim( $value ) ) ); }
	private static function locale() {
		$code = PandaTranslate_Frontend::current_language();
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] === $code ) return array( $code, $row['locale'] . ( 'formal' === ( $row['formality'] ?? '' ) ? '_formal' : '' ) );
		$locale = get_option( 'WPLANG', 'en_US' ) ?: 'en_US'; return array( str_replace( '_', '-', strtolower( $locale ) ), $locale );
	}
	public static function render( $attributes, $content = null, $tag = 'language-include' ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		list( $code, $locale ) = self::locale();
		$aliases = array_unique( array( self::normalize( $code ), self::normalize( $locale ), explode( '_', self::normalize( $locale ) )[0] ) );
		$specified = (string) ( $attributes[ 'trp_language' === $tag ? 'language' : 'lang' ] ?? '' );
		$languages = array_filter( array_map( array( __CLASS__, 'normalize' ), explode( ',', $specified ) ) );
		$matches = (bool) array_intersect( $aliases, $languages );
		if ( ( 'language-exclude' === $tag && $matches ) || ( 'language-exclude' !== $tag && ! $matches ) ) return '';
		// Explicit yes preserves the original opt-in. Localized include/legacy blocks default to protection.
		$translate = isset( $attributes['enable_translation'] ) ? 'yes' === strtolower( trim( $attributes['enable_translation'] ) ) : 'language-exclude' === $tag;
		$output = do_shortcode( (string) $content );
		if ( $translate ) return $output;
		return '<trp-tag class="pandatranslate-language-content" data-no-translation translate="no" data-pandatranslate-content-language="' . esc_attr( $locale ) . '">' . $output . '</trp-tag>';
	}
}
