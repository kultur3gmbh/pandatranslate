<?php
defined( 'ABSPATH' ) || exit;

/** Protect format/glossary markers as non-translatable HTML, with exact restoration. */
final class PandaTranslate_Provider_Markup {
	const PATTERN = '/\[\[(?:PT:\d+:(?:OPEN|CLOSE|VOID)|PG:\d+)\]\]/';
	public static function protect( $text ) {
		$tokens = array();
		$html = preg_replace_callback( self::PATTERN, function ( $match ) use ( &$tokens ) {
			$id = count( $tokens ); $tokens[$id] = $match[0];
			return '<span translate="no" class="notranslate" data-pt-token="' . $id . '">' . $match[0] . '</span>';
		}, htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		return array( 'text' => $tokens ? $html : $text, 'tokens' => $tokens );
	}
	public static function restore( $text, array $tokens ) {
		if ( ! $tokens ) return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$output = preg_replace_callback( '~<span\b(?=[^>]*\bdata-pt-token=["\x27](\d+)["\x27])[^>]*>.*?</span>~is', function ( $match ) use ( $tokens ) { return $tokens[(int) $match[1]] ?? ''; }, $text );
		$output = html_entity_decode( strip_tags( $output ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( $tokens as $token ) if ( 1 !== substr_count( $output, $token ) ) return new WP_Error( 'pandatranslate_provider_tokens', __( 'Formatting or glossary placeholders were not preserved by the translation API.', 'pandatranslate' ) );
		return $output;
	}
}
