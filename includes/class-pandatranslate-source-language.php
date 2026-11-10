<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Source_Language {
	public static function wordpress_locale() { return (string) ( get_option( 'WPLANG', 'en_US' ) ?: 'en_US' ); }
	public static function normalized( $locale ) { return strtolower( str_replace( '-', '_', $locale ) ); }
	public static function row_locale( array $row ) { return self::normalized( $row['locale'] ) . ( 'formal' === ( $row['formality'] ?? '' ) ? '_formal' : '' ); }
	public static function wordpress_code( array $rows = array() ) {
		$locale = self::normalized( self::wordpress_locale() );
		foreach ( $rows as $row ) if ( self::row_locale( $row ) === $locale ) return $row['code'];
		foreach ( PandaTranslate_Features::catalog() as $code => $entry ) if ( self::row_locale( $entry ) === $locale ) return $code;
		return str_replace( '_', '-', $locale );
	}
	public static function mismatch( array $rows ) { return $rows && self::row_locale( $rows[0] ) !== self::normalized( self::wordpress_locale() ); }
}
