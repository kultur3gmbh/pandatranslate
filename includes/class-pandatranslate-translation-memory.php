<?php
defined( 'ABSPATH' ) || exit;

/** Indexed reuse of published dictionary entries, independent of their old page keys. */
final class PandaTranslate_Translation_Memory {
	private $translations = array();
	private $manual_dictionary = array();
	public function __construct( $html, $language, $repository ) {
		$document = self::document( $html ); if ( ! $document ) return;
		$sources = array();
		foreach ( ( new DOMXPath( $document ) )->query( '//*' ) as $node ) {
			if ( self::excluded( $node ) ) continue;
			if ( self::inline_content( $node ) ) $sources[] = self::inner( $node );
			foreach ( $node->childNodes as $child ) if ( XML_TEXT_NODE === $child->nodeType ) $sources[] = $child->nodeValue;
			foreach ( self::attributes( $node ) as $attribute ) $sources[] = $node->getAttribute( $attribute );
		}
		$hashes = array(); foreach ( $sources as $source ) foreach ( self::variants( $source ) as $variant ) if ( '' !== trim( strip_tags( $variant ) ) ) $hashes[hash( 'sha256', $variant )] = true;
		foreach ( $repository->published_imports( $language, array_keys( $hashes ) ) as $row ) {
			$key = self::canonical( $row['source_html'] );
			if ( 'manual-dictionary' === ( $row['provider'] ?? '' ) ) {
				if ( isset( $this->manual_dictionary[$key] ) && $this->manual_dictionary[$key] !== $row['translated_html'] ) $this->manual_dictionary[$key] = false;
				elseif ( ! array_key_exists( $key, $this->manual_dictionary ) ) $this->manual_dictionary[$key] = $row['translated_html'];
			}
			// Ambiguous dictionary entries must not silently choose a context-specific translation.
			if ( isset( $this->translations[$key] ) && $this->translations[$key] !== $row['translated_html'] ) $this->translations[$key] = false;
			elseif ( ! array_key_exists( $key, $this->translations ) ) $this->translations[$key] = $row['translated_html'];
		}
		foreach ( $this->manual_dictionary as $key => $translation ) $this->translations[$key] = $translation;
	}
	public function manual_lookup( $source ) { $value = $this->manual_dictionary[self::canonical( $source )] ?? null; return is_string( $value ) && '' !== $value ? $value : null; }
	private static function variants( $source ) {
		$decoded = html_entity_decode( $source, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return array_unique( array( $source, trim( $source ), $decoded, trim( $decoded ), self::canonical( $source ), htmlentities( $decoded, ENT_NOQUOTES | ENT_HTML5, 'UTF-8' ), htmlspecialchars( $decoded, ENT_NOQUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}
	private static function canonical( $source ) { return trim( preg_replace( '/[\s\x{00a0}]+/u', ' ', html_entity_decode( $source, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ); }
	public function lookup( $source ) { $value = $this->translations[self::canonical( $source )] ?? null; return is_string( $value ) && '' !== $value ? $value : null; }
	public function transform( $html ) {
		if ( ! $this->translations ) return $html;
		$document = self::document( $html ); if ( ! $document ) return $html;
		$this->walk( $document );
		return preg_replace( '/^<\?xml[^>]+>\s*/', '', $document->saveHTML() );
	}
	private function walk( $node ) {
		if ( XML_ELEMENT_NODE === $node->nodeType ) {
			if ( self::excluded( $node ) || $node->hasAttribute( 'data-pandatranslate-resolved' ) ) return;
			foreach ( self::attributes( $node ) as $attribute ) { $value = $this->lookup( $node->getAttribute( $attribute ) ); if ( null !== $value ) $node->setAttribute( $attribute, html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); }
			if ( self::inline_content( $node ) ) { $value = $this->lookup( self::inner( $node ) ); if ( null !== $value ) { self::replace_inner( $node, wp_kses_post( $value ) ); return; } }
		}
		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType ) { $value = $this->lookup( $child->nodeValue ); if ( null !== $value ) { preg_match( '/^\s*/u', $child->nodeValue, $before ); preg_match( '/\s*$/u', $child->nodeValue, $after ); $child->nodeValue = $before[0] . html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . $after[0]; } }
			else $this->walk( $child );
		}
	}
	private static function attributes( $node ) { $attrs = array(); foreach ( array( 'alt', 'title', 'placeholder', 'aria-label' ) as $name ) if ( $node->hasAttribute( $name ) ) $attrs[] = $name; if ( 'input' === $node->nodeName && in_array( strtolower( $node->getAttribute( 'type' ) ), array( 'submit', 'button' ), true ) ) $attrs[] = 'value'; return $attrs; }
	private static function inline_content( $node ) { foreach ( $node->childNodes as $child ) if ( XML_ELEMENT_NODE === $child->nodeType && ! in_array( strtolower( $child->nodeName ), array( 'a','span','strong','em','b','i','u','small','sup','sub','br','s','mark' ), true ) ) return false; return true; }
	private static function excluded( $node ) {
		for ( $current = $node; $current && XML_ELEMENT_NODE === $current->nodeType; $current = $current->parentNode ) {
			if ( in_array( strtolower( $current->nodeName ), array( 'script','style','noscript','textarea','pre','code','svg' ), true ) || in_array( $current->getAttribute( 'id' ), array( 'wpadminbar','query-monitor-main','elementor-editor-wrapper','bricks-toolbar' ), true ) || 'no' === $current->getAttribute( 'translate' ) ) return true;
			if ( preg_match( '/(?:^|\s)(?:notranslate|ab-sub-wrapper|ab-item|fusion-builder-ui|awb-builder|fl-builder-bar|pandatranslate-panel|pandatranslate-floating|pandatranslate-switcher|pandatranslate-menu-item|pandatranslate-menu-link)(?:\s|$)/', $current->getAttribute( 'class' ) ) ) return true;
		}
		return false;
	}
	private static function document( $html ) { if ( ! class_exists( 'DOMDocument' ) ) return null; $previous = libxml_use_internal_errors( true ); $doc = new DOMDocument( '1.0', 'UTF-8' ); $ok = $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD ); libxml_clear_errors(); libxml_use_internal_errors( $previous ); return $ok ? $doc : null; }
	private static function inner( $node ) { $html = ''; foreach ( $node->childNodes as $child ) $html .= $node->ownerDocument->saveHTML( $child ); return $html; }
	public static function replace_inner( $node, $html ) { $fragment = self::document( '<html><body><div id="pt-fragment">' . $html . '</div></body></html>' ); if ( ! $fragment ) return; $root = $fragment->getElementById( 'pt-fragment' ); if ( ! $root ) return; while ( $node->firstChild ) $node->removeChild( $node->firstChild ); foreach ( iterator_to_array( $root->childNodes ) as $child ) $node->appendChild( $node->ownerDocument->importNode( $child, true ) ); }
}
