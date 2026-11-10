<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-pandatranslate-translation-memory.php';
function wp_kses_post( $html ) { return $html; }
final class Memory_Repository {
	public $lookups = 0;
	public function published_imports( $language, $hashes ) {
		++$this->lookups;
		$dictionary = array( 'Hallo <strong>Welt</strong>' => 'Hello <strong>world</strong>', 'Guten Tag' => 'Good day', 'Freunde' => 'friends', 'Titel & mehr' => 'Title & more', 'Foto' => 'Photo', 'Hallo' => 'Hello', 'Geheim' => 'Secret' );
		$rows = array(); foreach ( $dictionary as $source => $translation ) if ( in_array( hash( 'sha256', $source ), $hashes, true ) ) $rows[] = array( 'source_html' => $source, 'translated_html' => $translation );
		return $rows;
	}
}
$html = '<!doctype html><html><head><title>Titel &amp; mehr</title><script>Hallo</script><style>.Hallo{color:red}</style></head><body><div id="wpadminbar"><span>Hallo</span></div><p>Hallo <strong>Welt</strong></p><div>Guten Tag <em>Freunde</em></div><img alt="Foto" src="/same.jpg"><p data-pandatranslate-resolved="1">Hallo</p><p translate="no">Hallo</p><p class="notranslate">Geheim</p><div class="pandatranslate-switcher"><span>Hallo</span></div></body></html>';
$repository = new Memory_Repository(); $memory = new PandaTranslate_Translation_Memory( $html, 'en', $repository );
$result = $memory->transform( $html );
assert( 1 === $repository->lookups );
assert( false !== strpos( $result, 'Hello <strong>world</strong>' ) );
assert( false !== strpos( $result, 'Good day <em>friends</em>' ) );
assert( false !== strpos( $result, 'Title &amp; more' ) );
assert( false !== strpos( $result, 'alt="Photo" src="/same.jpg"' ) );
assert( false !== strpos( $result, '<script>Hallo</script>' ) );
assert( false !== strpos( $result, '<style>.Hallo{color:red}</style>' ) );
assert( false !== strpos( $result, 'id="wpadminbar"><span>Hallo</span>' ) );
assert( false !== strpos( $result, 'data-pandatranslate-resolved="1">Hallo</p>' ) );
assert( false !== strpos( $result, 'translate="no">Hallo</p>' ) );
assert( false !== strpos( $result, 'class="notranslate">Geheim</p>' ) );
assert( false !== strpos( $result, 'class="pandatranslate-switcher"><span>Hallo</span>' ) );
$extractor = new PandaTranslate_Unit_Extractor();
$page = $extractor->transform( '<p>Hallo</p>', function () { return 'Manual &amp; checked<br>translation'; } );
assert( false !== strpos( $page, 'Manual &amp; checked<br>translation' ) );
assert( false !== strpos( $memory->transform( $page ), 'Manual &amp; checked<br>translation' ) );
$ambiguous = new class { public function published_imports( $language, $hashes ) { return array( array( 'source_html'=>'Hallo','translated_html'=>'Hello' ),array('source_html'=>'Hallo','translated_html'=>'Hi') ); } };
assert( null === ( new PandaTranslate_Translation_Memory( '<p>Hallo</p>', 'en', $ambiguous ) )->lookup( 'Hallo' ) );
$corrected = new class { public function published_imports( $language, $hashes ) { return array( array( 'source_html'=>'Hallo','translated_html'=>'Hello','provider'=>'translatepress-import' ),array('source_html'=>'Hallo','translated_html'=>'Good day','provider'=>'manual-dictionary') ); } };
$corrected_memory = new PandaTranslate_Translation_Memory( '<p>Hallo</p>', 'en', $corrected );
assert( 'Good day' === $corrected_memory->lookup( 'Hallo' ) );
assert( 'Good day' === $corrected_memory->manual_lookup( 'Hallo' ) );
assert( false !== strpos( $corrected_memory->transform( '<p>Hallo</p>' ), 'Good day' ) );
assert( false !== strpos( $corrected_memory->transform( $page ), 'Manual &amp; checked<br>translation' ) );
echo "published dictionary reuse: full units, inline segments, entities, attributes, manual precedence, exclusions and ambiguity: ok\n";
