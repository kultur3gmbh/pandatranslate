<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Inline_Protector {
	const TOKEN_PATTERN = '/\[\[PT:(\d+):(OPEN|CLOSE|VOID)\]\]/';
	private static $allowed = array( 'a', 'abbr', 'b', 'br', 'code', 'em', 'i', 'mark', 'small', 'span', 'strong', 'sub', 'sup', 'u' );

	public static function protect( $html ) {
		$tokens = array();
		$index  = 0;
		$protected = preg_replace_callback(
			'/<(a|abbr|b|code|em|i|mark|small|span|strong|sub|sup|u)\b[^>]*>\s*<\/\1\s*>|<\/?(?:a|abbr|b|br|code|em|i|mark|small|span|strong|sub|sup|u)\b[^>]*>|(?<![0-9])%(?:\d+\$)?[-+0#]*(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX%]|\{[a-z_][a-z0-9_]*\}|\bPanda Translate(?: Multilingual)?\b/i',
			function ( $match ) use ( &$tokens, &$index ) {
				$tag   = $match[0];
				$void  = ! empty( $match[1] ) || in_array( substr( $tag, 0, 1 ), array( '%', '{', 'P', 'p' ), true ) || (bool) preg_match( '/^<br\b/i', $tag );
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

	/** Map existing inline markup back to the original tokens before exposing editable text runs. */
	public static function editor_view( $source_html, $translated_html ) {
		$source = self::protect( $source_html );
		$current = '' !== $translated_html ? self::protect( $translated_html ) : $source;
		$available = $source['tokens'];
		$mapping = array();
		foreach ( $current['tokens'] as $token => $value ) {
			$match = array_search( $value, $available, true );
			if ( false === $match ) break;
			$mapping[ $token ] = $match;
			unset( $available[ $match ] );
		}
		$text = strtr( $current['text'], $mapping );
		if ( $available || count( $mapping ) !== count( $current['tokens'] ) || is_wp_error( self::validate( $text, $source['tokens'] ) ) ) {
			// An older import can have a different inline layout. Keep the original
			// structure and show the available translation in its first text run.
			$plain = wp_strip_all_tags( $translated_html );
			foreach ( $source['tokens'] as $value ) {
				if ( '<' !== substr( $value, 0, 1 ) ) $plain = str_replace( $value, '', $plain );
			}
			$parts = preg_split( '/(\[\[PT:\d+:(?:OPEN|CLOSE|VOID)\]\])/', $source['text'], -1, PREG_SPLIT_DELIM_CAPTURE );
			$inserted = false;
			foreach ( $parts as $index => $part ) {
				if ( $index % 2 || '' === trim( $part ) ) continue;
				$parts[ $index ] = $inserted ? '' : trim( $plain );
				$inserted = true;
			}
			$text = implode( '', $parts );
		}
		$visible_source = preg_replace( '~<br\s*/?>~i', "\n", $source_html );
		return array(
			'source' => html_entity_decode( wp_strip_all_tags( $visible_source ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'text' => wp_strip_all_tags( $text ),
			'preview_tokens' => array_map( static function ( $token ) {
				return wp_kses( $token, self::allowed_html() );
			}, $source['tokens'] ),
		);
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
			if ( 'OPEN' === $parts[2] ) { preg_match( '/^<([a-z0-9]+)/i', $tokens[ $token ], $name ); $stack[] = strtolower( $name[1] ?? '' ); }
			if ( 'CLOSE' === $parts[2] ) {
				preg_match( '/^<\/([a-z0-9]+)/i', $tokens[ $token ], $name ); $tag = strtolower( $name[1] ?? '' );
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
