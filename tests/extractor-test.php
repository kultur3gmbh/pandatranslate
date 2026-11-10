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
