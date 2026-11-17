<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Inline_Protector {
	const TOKEN_PATTERN = '/\[\[PT:(\d+):(OPEN|CLOSE|VOID)\]\]/';
	private static $allowed = array( 'a', 'abbr', 'b', 'br', 'code', 'em', 'i', 'mark', 'small', 'span', 'strong', 'sub', 'sup', 'u' );

	public static function protect( $html ) {
		$tokens = array();
		$index  = 0;
		$protected = preg_replace_callback(
			'/<\/?(?:a|abbr|b|br|code|em|i|mark|small|span|strong|sub|sup|u)\b[^>]*>|(?<![0-9])%(?:\d+\$)?[-+0#]*(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX%]|\{[a-z_][a-z0-9_]*\}/i',
			function ( $match ) use ( &$tokens, &$index ) {
				$tag   = $match[0];
				$void  = in_array( substr( $tag, 0, 1 ), array( '%', '{' ), true ) || (bool) preg_match( '/^<br\b/i', $tag );
				$close = (bool) preg_match( '/^<\//', $tag );
				$type  = $void ? 'VOID' : ( $close ? 'CLOSE' : 'OPEN' );
				$token = '[[PT:' . $index++ . ':' . $type . ']]';
				$tokens[ $token ] = $tag;
				return $token;
			},
			(string) $html
		);
		return array( 'text' => $protected, 'tokens' => $tokens );
	}

	public static function restore( $translated, array $tokens ) {
		$validation = self::validate( $translated, $tokens );
		if ( is_wp_error( $validation ) ) return $validation;
		$html = strtr( (string) $translated, $tokens );
		return wp_kses( $html, self::allowed_html() );
	}

	public static function validate( $translated, array $tokens ) {
		preg_match_all( self::TOKEN_PATTERN, (string) $translated, $matches );
		$found = $matches[0];
		if ( count( $found ) !== count( $tokens ) || count( array_unique( $found ) ) !== count( $found ) ) {
			return new WP_Error( 'pandatranslate_tokens_missing', __( 'All formatting placeholders must occur exactly once.', 'pandatranslate' ) );
		}
		if ( array_diff( array_keys( $tokens ), $found ) || array_diff( $found, array_keys( $tokens ) ) ) {
			return new WP_Error( 'pandatranslate_tokens_changed', __( 'Formatting placeholders may not be changed.', 'pandatranslate' ) );
		}
		$stack = array();
		foreach ( $found as $token ) {
			preg_match( self::TOKEN_PATTERN, $token, $parts );
			if ( 'OPEN' === $parts[2] ) $stack[] = strtolower( preg_replace( '/^<([a-z0-9]+).*$/i', '$1', $tokens[ $token ] ) );
			if ( 'CLOSE' === $parts[2] ) {
				$tag = strtolower( preg_replace( '/^<\/([a-z0-9]+).*$/i', '$1', $tokens[ $token ] ) );
				if ( ! $stack || array_pop( $stack ) !== $tag ) return new WP_Error( 'pandatranslate_tokens_nested', __( 'Formatting placeholders are not validly nested.', 'pandatranslate' ) );
			}
		}
		return $stack ? new WP_Error( 'pandatranslate_tokens_unclosed', __( 'A formatting placeholder is not closed.', 'pandatranslate' ) ) : true;
	}

	public static function allowed_html() {
		$allowed = array();
		foreach ( self::$allowed as $tag ) $allowed[ $tag ] = true;
		$allowed['a'] = array( 'href' => true, 'title' => true, 'target' => true, 'rel' => true, 'class' => true );
		$allowed['span'] = array( 'class' => true, 'lang' => true, 'dir' => true );
		$allowed['abbr'] = array( 'title' => true );
		return $allowed;
	}
}
