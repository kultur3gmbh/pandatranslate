<?php
defined( 'ABSPATH' ) || exit;

/** Preserve registered template structure and variables before recipient data is expanded. */
final class PandaTranslate_Template_Protector {
	public static function prepare_row( array $row ) {
		$source = (string) ( $row['source_html'] ?? '' ); $kind = $row['source_kind'] ?? '';
		if ( 'stored-template-text' === $kind ) return self::protect( $source, false );
		// Core/plugin gettext templates can contain positional attribute placeholders (for example %2$s).
		// They must survive until the caller performs sprintf, before any HTML sanitization is possible.
		if ( 'gettext' === $kind && preg_match( '~</?[a-z][a-z0-9:-]*(?=[\s/>])~i', $source ) ) return self::protect( $source, true );
		if ( 'stored-template-html' === $kind || preg_match( '~<(?:div|p|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|h[1-6]|img|svg|section|figure|form|input|label|script|style)\b~i', $source ) ) return self::protect( $source, true );
		return PandaTranslate_Inline_Protector::protect( $source );
	}
	public static function restore_prepared( $translated, array $protected ) {
		return isset( $protected['template'] ) ? self::restore( $translated, $protected['tokens'], 'html' === $protected['template'] ) : PandaTranslate_Inline_Protector::restore( $translated, $protected['tokens'] );
	}
	public static function has_text( $source ) {
		$text = preg_replace( '~<!--[\s\S]*?-->~', '', (string) $source );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return (bool) preg_match( '/[\pL\pN]/u', $text );
	}
	public static function protect( $source, $html = false ) {
		$tokens = array();
		$tag = '<\/?[a-z][a-z0-9:-]*(?=[\s/>])(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>';
		$patterns = array( '<[a-z0-9._%+-]+@[a-z0-9.-]+>', '\{\{\s*[a-z_][a-z0-9_.:-]*\s*\}\}', '\{[a-z_][a-z0-9_.:-]*\}', '\[[a-z_][a-z0-9_.:-]*\]', '%%[a-z_][a-z0-9_]*%%', '%[a-z_][a-z0-9_]*%', '(?<![0-9])%(?:\d+\$)?[-+0#]*(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX%]', '\bPanda Translate(?: Multilingual)?\b' );
		if ( $html ) array_unshift( $patterns, '<(?:script|style)\b[^>]*>[\s\S]*?</(?:script|style)\s*>', '<!--[\s\S]*?-->', '<([a-z][a-z0-9:-]*)\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>\s*</\1\s*>', $tag );
		$text = preg_replace_callback( '~' . implode( '|', $patterns ) . '~i', function ( $match ) use ( &$tokens, $html ) {
			$value = $match[0]; $type = 'VOID';
			if ( $html && ! preg_match( '~^<(?:script|style)\b|^<[a-z][\s\S]*</[a-z][a-z0-9:-]*\s*>$~i', $value ) && preg_match( '~^<(/?)([a-z][a-z0-9:-]*)\b~i', $value, $tag ) ) {
				$void = in_array( strtolower( $tag[2] ), array( 'area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr' ), true ) || preg_match( '~/\s*>$~', $value );
				$type = $void ? 'VOID' : ( $tag[1] ? 'CLOSE' : 'OPEN' );
			}
			$token = '[[PT:' . count( $tokens ) . ':' . $type . ']]'; $tokens[$token] = $value; return $token;
		}, (string) $source );
		return array( 'text' => $text, 'tokens' => $tokens, 'template' => $html ? 'html' : 'text' );
	}
	public static function restore( $translated, array $tokens, $html = false ) {
		$valid = PandaTranslate_Inline_Protector::validate( $translated, $tokens ); if ( is_wp_error( $valid ) ) return $valid;
		// Only the registered source supplies markup/attributes. Never accept new HTML from a provider or translator.
		$text = $html ? htmlspecialchars( (string) $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8', false ) : wp_strip_all_tags( (string) $translated );
		return strtr( $text, $tokens );
	}
}
