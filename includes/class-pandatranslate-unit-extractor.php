<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-pandatranslate-html.php';

final class PandaTranslate_Unit_Extractor {
	private $selector = '//p|//h1|//h2|//h3|//h4|//h5|//h6|//li|//td|//th|//button|//a[@role="button"]|//input[@type="submit"]|//label/span[not(*)]';

	public function transform( $html, callable $resolver, $context = '', $collect_only = false ) {
		if ( ! class_exists( 'DOMDocument' ) || false === stripos( (string) $html, '<' ) ) return $html;
		$raw = PandaTranslate_HTML::protect_raw( $html );
		$previous = libxml_use_internal_errors( true );
		$document = new DOMDocument( '1.0', 'UTF-8' );
		$is_document = false !== stripos( $html, '<html' ) || false !== stripos( $html, '<!doctype' );
		$markup = $is_document ? $raw['html'] : '<div data-pandatranslate-root="1">' . $raw['html'] . '</div>';
		$options = $is_document ? LIBXML_HTML_NODEFDTD : LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;
		$loaded = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $markup, $options );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) return $html;

		$xpath = new DOMXPath( $document );
		foreach ( iterator_to_array( $xpath->query( $this->selector ) ) as $node ) {
			if ( $this->has_block_child( $node ) || $this->is_excluded( $node ) ) continue;
			$is_input = 'input' === strtolower( $node->nodeName );
			$is_label_text = 'span' === strtolower( $node->nodeName );
			$source   = $is_input ? $node->getAttribute( 'value' ) : $this->inner_html( $node );
			if ( '' === trim( wp_strip_all_tags( $source ) ) ) continue;
			$key = hash( 'sha256', $context . '|' . $this->node_path( $node ) . '|' . $source );
			$unit = array(
				'key'         => $key,
				'source_html' => $source,
				'source_hash' => hash( 'sha256', $source ),
				'kind'        => strtolower( $node->nodeName ),
				'context'     => $context,
			);
			$replacement = call_user_func( $resolver, $unit );
			if ( $collect_only ) continue;
			$node->setAttribute( 'data-pandatranslate-unit', $key );
			$node->setAttribute( 'data-pandatranslate-source-hash', $unit['source_hash'] );
			if ( is_string( $replacement ) && '' !== $replacement ) {
				$node->setAttribute( 'data-pandatranslate-resolved', '1' );
				if ( $is_input ) $node->setAttribute( 'value', wp_strip_all_tags( $replacement ) );
				// Only replace the text next to the control, never the control itself.
				elseif ( $is_label_text ) $node->textContent = html_entity_decode( wp_strip_all_tags( $replacement ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				else $this->set_inner_html( $node, $replacement );
			}
		}
		// HBook stores date labels as placeholders and guest labels in disabled
		// options. Collect only those fixed captions, never the reservation values.
		$booking_labels = '//input[(@name="hb-check-in-date" or @name="hb-check-out-date") and @placeholder]|//select[@name="hb-adults" or @name="hb-children"]/option[@disabled and not(*)]';
		foreach ( $xpath->query( $booking_labels ) as $node ) {
			if ( $this->is_excluded( $node ) ) continue;
			$is_placeholder = 'input' === $node->nodeName;
			$source = $is_placeholder ? $node->getAttribute( 'placeholder' ) : $node->textContent;
			if ( '' === trim( $source ) ) continue;
			$kind = $is_placeholder ? 'placeholder' : 'option-label';
			$unit = array(
				'key' => hash( 'sha256', $context . '|' . $this->node_path( $node ) . '|' . $kind . '|' . $source ),
				'source_html' => htmlspecialchars( $source, ENT_NOQUOTES, 'UTF-8' ),
				'kind' => $kind,
				'context' => $context,
			);
			$unit['source_hash'] = hash( 'sha256', $unit['source_html'] );
			$replacement = $resolver( $unit );
			if ( $collect_only ) continue;
			if ( ! is_string( $replacement ) || '' === $replacement ) continue;
			$text = html_entity_decode( wp_strip_all_tags( $replacement ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( $is_placeholder ) $node->setAttribute( 'placeholder', $text );
			else $node->textContent = $text;
		}
		if ( $collect_only ) return $html;
		if ( $is_document ) {
			$result = $document->saveHTML();
			return PandaTranslate_HTML::restore_raw( PandaTranslate_HTML::remove_encoding_marker( $result ), $raw );
		}
		$root = $xpath->query( '//*[@data-pandatranslate-root="1"]' )->item( 0 );
		return $root ? PandaTranslate_HTML::restore_raw( $this->inner_html( $root ), $raw ) : $html;
	}

	private function is_excluded( DOMNode $node ) {
		if ( PandaTranslate_HTML::translation_excluded( $node ) ) return true;
		$ids = array( 'wpadminbar', 'query-monitor-main', 'elementor-editor-wrapper', 'bricks-toolbar', 'wp-toolbar' );
		$classes = array( 'ab-sub-wrapper', 'ab-item', 'elementor-editor-active', 'elementor-editor-preview', 'fusion-builder-live', 'fusion-builder-ui', 'awb-builder', 'bricks-is-frontend', 'et-fb', 'fl-builder-bar', 'pandatranslate-panel', 'pandatranslate-floating', 'pandatranslate-switcher', 'pandatranslate-menu-item', 'pandatranslate-menu-link', 'notranslate' );
		for ( $current = $node; $current && XML_ELEMENT_NODE === $current->nodeType; $current = $current->parentNode ) {
			if ( in_array( strtolower( $current->nodeName ), array( 'script', 'style', 'textarea', 'pre', 'code', 'svg' ), true ) ) return true;
			if ( method_exists( $current, 'getAttribute' ) && in_array( $current->getAttribute( 'id' ), $ids, true ) ) return true;
			if ( method_exists( $current, 'getAttribute' ) ) { $value = ' ' . $current->getAttribute( 'class' ) . ' '; foreach ( $classes as $class ) if ( false !== strpos( $value, ' ' . $class . ' ' ) ) return true; }
		}
		return false;
	}

	private function has_block_child( DOMNode $node ) {
		foreach ( $node->childNodes as $child ) if ( XML_ELEMENT_NODE === $child->nodeType && in_array( strtolower( $child->nodeName ), array( 'p','h1','h2','h3','h4','h5','h6','li','td','th','button' ), true ) ) return true;
		return false;
	}

	private function node_path( DOMNode $node ) {
		$parts = array();
		while ( $node && XML_ELEMENT_NODE === $node->nodeType ) {
			$position = 1;
			for ( $sibling = $node->previousSibling; $sibling; $sibling = $sibling->previousSibling ) if ( $sibling->nodeName === $node->nodeName ) ++$position;
			$parts[] = strtolower( $node->nodeName ) . '[' . $position . ']';
			$node = $node->parentNode;
		}
		return implode( '/', array_reverse( $parts ) );
	}

	private function inner_html( DOMNode $node ) {
		$html = '';
		foreach ( $node->childNodes as $child ) $html .= $node->ownerDocument->saveHTML( $child );
		return $html;
	}

	private function set_inner_html( DOMNode $node, $html ) {
		$previous = libxml_use_internal_errors( true );
		$fragment = new DOMDocument( '1.0', 'UTF-8' );
		$fragment->loadHTML( '<?xml encoding="utf-8" ?><html><body><div id="pt-fragment">' . $html . '</div></body></html>', LIBXML_HTML_NODEFDTD );
		libxml_clear_errors(); libxml_use_internal_errors( $previous );
		$root = $fragment->getElementById( 'pt-fragment' ); if ( ! $root ) return;
		while ( $node->firstChild ) $node->removeChild( $node->firstChild );
		foreach ( iterator_to_array( $root->childNodes ) as $child ) $node->appendChild( $node->ownerDocument->importNode( $child, true ) );
	}
}
