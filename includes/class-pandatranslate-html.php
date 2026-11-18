<?php
defined( 'ABSPATH' ) || exit;

/** libxml's HTML serializer entity-encodes Unicode in raw script/style text. */
final class PandaTranslate_HTML {
	/** Translation protection is inherited; it never changes a node's visibility or language selection. */
	public static function translation_excluded( $node ) {
		for ( $current = $node; $current instanceof DOMElement; $current = $current->parentNode ) {
			if ( $current->hasAttribute( 'data-no-translation' ) || 'no' === strtolower( trim( $current->getAttribute( 'translate' ) ) ) ) return true;
			if ( preg_match( '/(?:^|\s)(?:notranslate|trp-no-translation)(?:\s|$)/', $current->getAttribute( 'class' ) ) ) return true;
		}
		return false;
	}

	public static function protect_raw( $html ) {
		$tokens = array(); $prefix = 'PANDATRANSLATE_RAW_' . hash( 'sha256', $html ) . '_';
		while ( false !== strpos( $html, $prefix ) ) $prefix .= 'X';
		$masked = preg_replace_callback( '~(<(script|style)\b(?:[^>"\x27]|"[^"]*"|\x27[^\x27]*\x27)*>)(.*?)(</\2\s*>)~is', function ( $match ) use ( &$tokens, $prefix ) {
			$token = $prefix . count( $tokens ) . '_END'; $tokens[$token] = $match[3];
			return $match[1] . $token . $match[4];
		}, $html );
		return array( 'html' => $masked, 'tokens' => $tokens );
	}
	public static function restore_raw( $html, $protected ) { return strtr( $html, $protected['tokens'] ); }
}
