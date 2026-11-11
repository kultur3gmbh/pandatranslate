<?php
// Integration fixture: responsive images, page isolation, API authorization and optimistic concurrency.
define( 'ABSPATH', __DIR__ );
class WP_Error { public $code; public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; } }
class PandaTranslate_Edition { public static function allows_language( $language ) { return in_array( $language, array( 'en', 'de' ), true ); } }
class PandaTranslate_Plugin { public static function get_settings() { return array( 'source_language' => 'en' ); } }
$meta = array(); $can = true;
function wp_salt( $scheme ) { return 'test-secret'; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_key( $s ) { return strtolower( $s ); }
function sanitize_text_field( $s ) { return strip_tags( $s ); }
function sanitize_textarea_field( $s ) { return strip_tags( $s ); }
function absint( $v ) { return abs( (int) $v ); }
function __( $s, $d ) { return $s; }
function rest_ensure_response( $v ) { return $v; }
function current_user_can( $cap, $id = 0 ) { global $can; return $can && 999 !== $id; }
function wp_attachment_is_image( $id ) { return 42 === $id; }
function wp_get_attachment_image_src( $id, $size ) { return array( '/de/image.jpg', 800, 600 ); }
function wp_get_attachment_image_srcset( $id, $size ) { return '/de/small.jpg 400w, /de/image.jpg 800w'; }
function wp_get_attachment_image_sizes( $id, $size ) { return '(max-width:800px) 100vw, 800px'; }
function get_post_meta( $post, $key, $single ) { global $meta; return $meta[$post][$key] ?? ''; }
function add_post_meta( $post, $key, $value, $unique ) { global $meta; if ( isset( $meta[$post][$key] ) ) return false; $meta[$post][$key] = $value; return true; }
function update_post_meta( $post, $key, $value, $old ) { global $meta; if ( $meta[$post][$key] !== $old ) return false; $meta[$post][$key] = $value; return true; }
function delete_post_meta( $post, $key, $old ) { global $meta; if ( $meta[$post][$key] !== $old ) return false; unset( $meta[$post][$key] ); return true; }
require_once __DIR__ . '/../includes/class-pandatranslate-media.php';
$html = '<html><head><script>let currency="€";</script></head><body><div id="wpadminbar"><img src="/admin.png"></div><figure><picture><source type="image/webp" media="(min-width:500px)" srcset="/old.webp"><img id="target" src="/old.jpg" data-src="/old-full.jpg" data-srcset="/old-small.jpg 400w" alt="Original"></picture><figcaption>Original caption</figcaption></figure></body></html>';
function image( $html ) { $doc = new DOMDocument(); @$doc->loadHTML( $html ); return $doc->getElementById( 'target' ); }
$editing = PandaTranslate_Media::transform( $html, 'de', true, 7 ); $ref = image( $editing )->getAttribute( 'data-pandatranslate-media' ); assert( '' !== $ref );
assert( 1 === substr_count( $editing, 'data-pandatranslate-media=' ) );
$request = array( 'reference' => $ref, 'language' => 'de' );
assert( PandaTranslate_Media::permission( $request ) ); $can = false; assert( ! PandaTranslate_Media::permission( $request ) ); $can = true;
assert( ! PandaTranslate_Media::permission( array( 'reference' => str_replace( '7:', '8:', $ref ) ) ) );
$read = PandaTranslate_Media::read( $request ); $request += array( 'revision' => $read['revision'], 'attachment_id' => 42, 'alt' => 'Bild', 'title' => 'Titel', 'caption' => 'Beschreibung' );
$saved = PandaTranslate_Media::save( $request ); assert( ! $saved instanceof WP_Error );
assert( PandaTranslate_Media::save( $request ) instanceof WP_Error ); // stale request cannot overwrite.
$translated = PandaTranslate_Media::transform( $html, 'de', false, 7 ); $img = image( $translated );
assert( '/de/image.jpg' === $img->getAttribute( 'src' ) ); assert( '/de/image.jpg' === $img->getAttribute( 'data-src' ) );
assert( 'Bild' === $img->getAttribute( 'alt' ) );
$logged_out = str_replace( '<div id="wpadminbar"><img src="/admin.png"></div>', '', $html ); assert( 'Bild' === image( PandaTranslate_Media::transform( $logged_out, 'de', false, 7 ) )->getAttribute( 'alt' ) ); assert( $img->getAttribute('srcset') === $img->getAttribute('data-srcset') );
assert( false === strpos( $translated, '/old.webp' ) ); assert( false === strpos( $translated, '/old-small.jpg' ) ); assert( false !== strpos( $translated, 'let currency="€";' ) );
assert( false !== strpos( $translated, 'Beschreibung' ) );
assert( $html === PandaTranslate_Media::transform( $html, 'fr', false, 7 ) );
assert( $html === PandaTranslate_Media::transform( $html, 'de', false, 8 ) );
$request['revision'] = $saved['revision']; $request['remove'] = true; assert( ! PandaTranslate_Media::save( $request ) instanceof WP_Error );
assert( $html === PandaTranslate_Media::transform( $html, 'de', false, 7 ) );
echo "Media: page-scoped language mapping, responsive/lazy/picture replacement, original fallback, raw-script integrity, signed references, permissions and stale-write protection: ok\n";
