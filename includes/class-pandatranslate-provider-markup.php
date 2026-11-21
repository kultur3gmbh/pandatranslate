<?php
defined( 'ABSPATH' ) || exit;

/** Keep formatting as nested HTML containers so translation cannot cross marker pairs. */
final class PandaTranslate_Provider_Markup {
	const PATTERN = '/\[\[(?:PT:\d+:(?:OPEN|CLOSE|VOID)|PG:\d+)\]\]/';
	private static function pairs( array $tokens ) {
		$pairs = array(); $stack = array();
		foreach ( $tokens as $id => $token ) {
			if ( preg_match( '/:OPEN\]\]$/', $token ) ) $stack[] = $id;
			elseif ( preg_match( '/:CLOSE\]\]$/', $token ) ) { if ( ! $stack ) return false; $pairs[array_pop( $stack )] = $id; }
		}
		return $stack ? false : $pairs;
	}
	public static function protect( $text ) {
		preg_match_all( self::PATTERN, $text, $matches ); $tokens = $matches[0]; $pairs = self::pairs( $tokens );
		$structured = class_exists( 'DOMDocument' ) && false !== $pairs; $index = 0;
		$html = preg_replace_callback( self::PATTERN, function ( $match ) use ( $tokens, $pairs, $structured, &$index ) {
			$id = $index++;
			if ( $structured && isset( $pairs[$id] ) ) return '<span data-pt-open="' . $id . '" data-pt-close="' . $pairs[$id] . '">';
			if ( $structured && preg_match( '/:CLOSE\]\]$/', $match[0] ) ) return '</span>';
			return '<span translate="no" class="notranslate" data-pt-token="' . $id . '">' . $match[0] . '</span>';
		}, htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		return array( 'text' => $tokens ? $html : $text, 'tokens' => $tokens );
	}
	public static function restore( $text, array $tokens ) {
		if ( ! $tokens ) return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( class_exists( 'DOMDocument' ) ) {
			$previous = libxml_use_internal_errors( true ); $doc = new DOMDocument( '1.0', 'UTF-8' );
			$loaded = $doc->loadHTML( '<?xml encoding="utf-8" ?><html><body><div id="pt-provider-root">' . $text . '</div></body></html>', LIBXML_HTML_NODEFDTD | LIBXML_NONET );
			libxml_clear_errors(); libxml_use_internal_errors( $previous ); $root = $loaded ? $doc->getElementById( 'pt-provider-root' ) : null;
			if ( ! $root ) return self::error();
			$output = self::read( $root, $tokens, self::pairs( $tokens ) );
		} else {
			$output = preg_replace_callback( '~<span\b(?=[^>]*\bdata-pt-token=["\x27](\d+)["\x27])[^>]*>.*?</span>~is', function ( $match ) use ( $tokens ) { return $tokens[(int) $match[1]] ?? ''; }, $text );
			$output = html_entity_decode( wp_strip_all_tags( $output ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		foreach ( $tokens as $token ) if ( 1 !== substr_count( $output, $token ) ) return self::error();
		return $output;
	}
	private static function read( $node, array $tokens, $pairs ) {
		if ( XML_TEXT_NODE === $node->nodeType ) return $node->nodeValue;
		if ( XML_ELEMENT_NODE === $node->nodeType && in_array( strtolower( $node->nodeName ), array( 'script', 'style' ), true ) ) return '';
		if ( XML_ELEMENT_NODE === $node->nodeType && $node->hasAttribute( 'data-pt-token' ) ) { $id = $node->getAttribute( 'data-pt-token' ); return ctype_digit( $id ) ? ( $tokens[(int) $id] ?? '' ) : ''; }
		$inside = ''; foreach ( $node->childNodes as $child ) $inside .= self::read( $child, $tokens, $pairs );
		if ( XML_ELEMENT_NODE === $node->nodeType && $node->hasAttribute( 'data-pt-open' ) ) {
			$open = $node->getAttribute( 'data-pt-open' ); $close = $node->getAttribute( 'data-pt-close' );
			if ( ! ctype_digit( $open ) || ! ctype_digit( $close ) || false === $pairs || ( $pairs[(int) $open] ?? null ) !== (int) $close ) return '';
			return $tokens[(int) $open] . $inside . $tokens[(int) $close];
		}
		return $inside;
	}
	private static function error() { return new WP_Error( 'pandatranslate_provider_tokens', __( 'Formatting or glossary placeholders were not preserved by the translation API.', 'pandatranslate' ) ); }
}
