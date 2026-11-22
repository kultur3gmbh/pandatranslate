<?php
require __DIR__ . '/bootstrap.php';

if ( ! class_exists( 'DOMDocument' ) ) {
	echo "extractor: skipped (DOM extension unavailable)\n";
	exit( 0 );
}

$extractor = new PandaTranslate_Unit_Extractor();
foreach ( array( 'gutenberg', 'elementor', 'divi' ) as $fixture ) {
	$html = file_get_contents( __DIR__ . '/fixtures/' . $fixture . '.html' );
	$units = array();
	$result = $extractor->transform( $html, function ( $unit ) use ( &$units ) { $units[] = $unit; return null; }, 'page-42' );
	assert( count( $units ) >= 1 );
	assert( false !== strpos( $result, 'data-pandatranslate-unit=' ) );
	foreach ( $units as $unit ) {
		assert( 64 === strlen( $unit['key'] ) );
		assert( 64 === strlen( $unit['source_hash'] ) );
	}
}

$complex = '<p>One <strong>complete <em>sentence</em></strong> with <a href="/test">a link</a>.</p>';
$units = array();
$extractor->transform( $complex, function ( $unit ) use ( &$units ) { $units[] = $unit; return null; }, 'complex' );
assert( 1 === count( $units ) );
assert( false !== strpos( $units[0]['source_html'], '<strong>' ) );
assert( false !== strpos( $units[0]['source_html'], '<a href="/test">' ) );

echo "extractor fixtures: ok\n";
$excluded = 0;
$extractor->transform( '<nav class="pandatranslate-switcher"><ul><li>Deutsch</li></ul></nav><p translate="no">Unchanged</p><div class="notranslate"><p>Code</p></div>', function () use ( &$excluded ) { ++$excluded; return 'Changed'; } );
assert( 0 === $excluded );

$document = '<!doctype html><html><head><title>Test</title></head><body><main><p>Full document</p></main></body></html>';
$result = $extractor->transform( $document, function () { return null; }, 'document' );
assert( false !== stripos( $result, '<!DOCTYPE html>' ) );
assert( false !== strpos( $result, '<head><title>Test</title></head>' ) );
assert( false !== strpos( $result, 'data-pandatranslate-unit=' ) );
echo "full document: ok\n";

$form = '<form><label><span>E-Mail-Adresse</span><input type="email" name="email" required></label><label><input type="checkbox" name="calendar_consent" value="1" required><span>Einwilligung</span></label><input name="hb-check-in-date" placeholder="Anreise" value="2026-12-01"><select name="hb-adults"><option selected disabled>Erwachsene</option><option value="2">2</option></select></form>';
$labels = array( 'E-Mail-Adresse' => 'Adresse e-mail', 'Einwilligung' => 'Consentement', 'Anreise' => 'Arrivée', 'Erwachsene' => 'Adultes' );
$keys = array();
assert( $form === $extractor->transform( $form, function ( $unit ) use ( &$keys ) { $keys[] = $unit['key']; return null; }, 'booking', true ) );
$rendered_keys = array();
$result = $extractor->transform( $form, function ( $unit ) use ( $labels, &$rendered_keys ) { $rendered_keys[] = $unit['key']; return $labels[ $unit['source_html'] ] ?? null; }, 'booking' );
assert( $keys === $rendered_keys && 4 === count( $keys ) );
assert( false !== strpos( $result, 'Adresse e-mail' ) && false !== strpos( $result, 'Consentement' ) );
assert( false !== strpos( $result, 'placeholder="Arriv' ) && false !== strpos( $result, '>Adultes</option>' ) );
assert( false !== strpos( $result, 'name="calendar_consent" value="1" required' ) );
assert( false !== strpos( $result, 'value="2026-12-01"' ) && false !== strpos( $result, '<option value="2">2</option>' ) );
