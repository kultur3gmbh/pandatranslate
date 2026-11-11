<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-pandatranslate-html.php';

/** Per-page image occurrences. Attachments are never modified or deleted. */
final class PandaTranslate_Media {
	public static function routes() {
		register_rest_route( 'pandatranslate/v1', '/media', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'read' ), 'permission_callback' => array( __CLASS__, 'permission' ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save' ), 'permission_callback' => array( __CLASS__, 'permission' ) ),
		) );
	}
	private static function reference( $post, $key ) { return $post . ':' . $key . ':' . hash_hmac( 'sha256', $post . ':' . $key, wp_salt( 'auth' ) ); }
	private static function identity( $request ) {
		$parts = explode( ':', (string) $request['reference'] );
		if ( 3 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! preg_match( '/^[a-f0-9]{64}$/', $parts[1] ) || ! hash_equals( self::reference( $parts[0], $parts[1] ), (string) $request['reference'] ) ) return false;
		return array( (int) $parts[0], $parts[1] );
	}
	public static function permission( $request ) {
		$id = self::identity( $request );
		return $id && current_user_can( 'edit_post', $id[0] ) && current_user_can( 'upload_files' );
	}
	private static function meta_key( $key, $language ) { return '_pandatranslate_media_' . hash( 'sha256', $key . '|' . $language ); }
	private static function language( $request ) {
		$language = sanitize_key( $request['language'] );
		return PandaTranslate_Edition::allows_language( $language ) && $language !== PandaTranslate_Plugin::get_settings()['source_language'] ? $language : '';
	}
	private static function revision( $row ) { return hash( 'sha256', wp_json_encode( $row ) ); }
	private static function response( $row ) { return array( 'mapping' => $row ?: null, 'revision' => self::revision( $row ), 'image' => $row ? self::image( $row ) : null ); }
	public static function read( $request ) {
		$id = self::identity( $request ); $language = self::language( $request );
		if ( ! $id || ! $language ) return new WP_Error( 'pandatranslate_media_language', __( 'Select an active target language.', 'pandatranslate' ), array( 'status' => 400 ) );
		return rest_ensure_response( self::response( get_post_meta( $id[0], self::meta_key( $id[1], $language ), true ) ) );
	}
	public static function save( $request ) {
		$id = self::identity( $request ); $language = self::language( $request );
		if ( ! $id || ! $language ) return new WP_Error( 'pandatranslate_media_language', __( 'Select an active target language.', 'pandatranslate' ), array( 'status' => 400 ) );
		$key = self::meta_key( $id[1], $language ); $old = get_post_meta( $id[0], $key, true );
		if ( ! hash_equals( self::revision( $old ), (string) $request['revision'] ) ) return new WP_Error( 'pandatranslate_media_changed', __( 'The image mapping changed. Reload before saving.', 'pandatranslate' ), array( 'status' => 409 ) );
		if ( ! empty( $request['remove'] ) ) {
			if ( $old && ! delete_post_meta( $id[0], $key, $old ) ) return new WP_Error( 'pandatranslate_media_changed', __( 'The image mapping changed. Reload before saving.', 'pandatranslate' ), array( 'status' => 409 ) );
			return rest_ensure_response( self::response( '' ) );
		}
		$attachment = absint( $request['attachment_id'] );
		if ( ! $attachment || ! wp_attachment_is_image( $attachment ) || ! current_user_can( 'read_post', $attachment ) ) return new WP_Error( 'pandatranslate_media_invalid', __( 'Select an accessible image from the media library.', 'pandatranslate' ), array( 'status' => 400 ) );
		$row = array( 'attachment_id' => $attachment, 'alt' => sanitize_text_field( $request['alt'] ?? '' ), 'title' => sanitize_text_field( $request['title'] ?? '' ), 'caption' => sanitize_textarea_field( $request['caption'] ?? '' ) );
		if ( $row !== $old ) {
			$saved = $old ? update_post_meta( $id[0], $key, $row, $old ) : add_post_meta( $id[0], $key, $row, true );
			if ( ! $saved ) return new WP_Error( 'pandatranslate_media_changed', __( 'The image mapping changed. Reload before saving.', 'pandatranslate' ), array( 'status' => 409 ) );
		}
		return rest_ensure_response( self::response( $row ) );
	}
	public static function image( $row ) {
		$src = wp_get_attachment_image_src( $row['attachment_id'], 'full' );
		if ( ! $src ) return null;
		return array( 'src' => $src[0], 'width' => $src[1], 'height' => $src[2], 'srcset' => wp_get_attachment_image_srcset( $row['attachment_id'], 'full' ) ?: '', 'sizes' => wp_get_attachment_image_sizes( $row['attachment_id'], 'full' ) ?: '', 'alt' => $row['alt'], 'title' => $row['title'], 'caption' => $row['caption'] );
	}
	public static function apply_image( DOMElement $node, array $image ) {
		foreach ( array( 'src', 'srcset', 'sizes', 'width', 'height', 'alt', 'title' ) as $attr ) { if ( '' === (string) $image[ $attr ] ) $node->removeAttribute( $attr ); else $node->setAttribute( $attr, $image[ $attr ] ); }
		foreach ( array( 'data-src', 'data-lazy-src', 'data-original', 'data-orig-file', 'data-large-file' ) as $attr ) if ( $node->hasAttribute( $attr ) ) $node->setAttribute( $attr, $image['src'] );
		foreach ( array( 'data-srcset', 'data-lazy-srcset' ) as $attr ) if ( $node->hasAttribute( $attr ) ) $node->setAttribute( $attr, $image['srcset'] );
		foreach ( array( 'data-sizes', 'data-lazy-sizes' ) as $attr ) if ( $node->hasAttribute( $attr ) ) $node->setAttribute( $attr, $image['sizes'] );
		$parent = $node->parentNode;
		while ( $parent instanceof DOMElement && 'source' === $parent->tagName ) $parent = $parent->parentNode;
		if ( $parent instanceof DOMElement && 'picture' === $parent->tagName ) {
			foreach ( $parent->getElementsByTagName( 'source' ) as $source ) {
				// An old AVIF/WebP type or art-direction media query must not select the original asset.
				$source->removeAttribute( 'type' ); $source->removeAttribute( 'media' );
				$source->setAttribute( 'srcset', $image['srcset'] ?: $image['src'] ); $source->setAttribute( 'sizes', $image['sizes'] );
				foreach ( array( 'data-srcset', 'data-lazy-srcset' ) as $attr ) if ( $source->hasAttribute( $attr ) ) $source->setAttribute( $attr, $image['srcset'] ?: $image['src'] );
			}
		}
		while ( $parent instanceof DOMElement && ! in_array( $parent->tagName, array( 'figure', 'body' ), true ) ) $parent = $parent->parentNode;
		if ( $parent instanceof DOMElement && 'figure' === $parent->tagName ) {
			$captions = $parent->getElementsByTagName( 'figcaption' );
			if ( ! $captions->length && '' !== $image['caption'] ) $parent->appendChild( $parent->ownerDocument->createElement( 'figcaption' ) )->textContent = $image['caption'];
			else foreach ( $captions as $caption ) $caption->textContent = $image['caption'];
		}
	}
	public static function transform( $html, $language, $editing, $post_id ) {
		if ( ! $post_id || ( ! $language && ! $editing ) || ! class_exists( 'DOMDocument' ) ) return $html;
		$masked = PandaTranslate_HTML::protect_raw( $html );
		$doc = new DOMDocument(); $previous = libxml_use_internal_errors( true ); $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $masked['html'] ); libxml_clear_errors(); libxml_use_internal_errors( $previous );
		$xpath = new DOMXPath( $doc ); $changed = false; $occurrences = array();
		foreach ( $xpath->query( '//img[not(ancestor::*[@id="wpadminbar" or @translate="no" or @data-no-translation or contains(@class,"pandatranslate-") or contains(@class,"notranslate")])]' ) as $node ) {
			$original = $node->getAttribute( 'data-src' ) ?: $node->getAttribute( 'data-lazy-src' ) ?: $node->getAttribute( 'src' );
			$identity = preg_match( '/(?:^|\s)wp-image-([0-9]+)(?:\s|$)/', $node->getAttribute( 'class' ), $match ) ? 'attachment:' . $match[1] : $original;
			$position = $occurrences[$identity] ?? 0; $occurrences[$identity] = $position + 1;
			// Absolute DOM paths change when WordPress adds the logged-in admin toolbar.
			$key = hash( 'sha256', 'image-occurrence|' . $identity . '|' . $position );
			$row = get_post_meta( $post_id, self::meta_key( $key, $language ), true );
			if ( $editing ) { $node->setAttribute( 'data-pandatranslate-media', self::reference( $post_id, $key ) ); $node->setAttribute( 'data-pandatranslate-media-original', wp_json_encode( array( 'src' => $original, 'alt' => $node->getAttribute( 'alt' ), 'title' => $node->getAttribute( 'title' ) ) ) ); $changed = true; }
			if ( $row && ( $image = self::image( $row ) ) ) { self::apply_image( $node, $image ); $changed = true; }
		}
		if ( ! $changed ) return $html;
		$out = $doc->saveHTML(); $out = preg_replace( '/<\?xml[^>]*\?>/', '', $out );
		return PandaTranslate_HTML::restore_raw( $out, $masked );
	}
}
