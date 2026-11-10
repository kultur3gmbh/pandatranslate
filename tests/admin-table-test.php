<?php
require __DIR__ . '/switcher-render-test.php';
function selected( $a, $b, $echo = false ) { return $a === $b ? 'selected' : ''; }
function checked( $a, $b, $echo = false ) { return $a == $b ? 'checked' : ''; }
function esc_html__( $text ) { return esc_html( $text ); }
$method = new ReflectionMethod( 'PandaTranslate_Plugin', 'render_language_settings' );
$repository = new class { public function language_stats() { return array( 'en' => array( 'total'=>100, 'migrated'=>25, 'automatic'=>35, 'manual'=>20, 'untranslated'=>20 ) ); } };
( new ReflectionProperty( 'PandaTranslate_Plugin', 'repository' ) )->setValue( $plugin, $repository );
ob_start(); $method->invoke( $plugin ); $html = ob_get_clean();
$doc = new DOMDocument(); @$doc->loadHTML( '<?xml encoding="UTF-8">' . $html ); $xpath = new DOMXPath( $doc );
check( 11, $xpath->query( '//table/thead/tr/th' )->length );
$rows = $xpath->query( '//table/tbody/tr' );
check( 3, $rows->length );
check( 11, $xpath->query( './td', $rows->item( 0 ) )->length );
check( 'Language', $xpath->query( '//table/thead/tr/th[4]' )->item(0)->textContent );
check( 1, $xpath->query( '//table/thead/tr/th[5]/span[@class="screen-reader-text"]' )->length );
check( 1, $xpath->query( '//table/thead/tr/th[1]/span[@class="screen-reader-text"]' )->length );
check( 'Translation status', $xpath->query( '//table/thead/tr/th[6]' )->item(0)->textContent );
check( '80%', $xpath->query( './/td[6]//strong', $rows->item(1) )->item(0)->textContent );
check( 1, $xpath->query( './/td[6]//*[@class="is-translated" and @style="width:80%"]', $rows->item(1) )->length );
check( 1, $xpath->query( './/td[6]//*[@class="is-untranslated" and @style="width:20%"]', $rows->item(1) )->length );
check( 0, $xpath->query( './/td[6]//span[@class="is-migrated" or @class="is-automatic" or @class="is-manual"]', $rows->item(1) )->length );
check( '0%', $xpath->query( './/td[6]//strong', $rows->item(2) )->item(0)->textContent );
check( 1, $xpath->query( './/td[6]//*[@class="is-untranslated" and @style="width:100%"]', $rows->item(2) )->length );
check( 0, $xpath->query( './/*[@data-language-drag or @data-remove-language]', $rows->item( 0 ) )->length );
check( 1, $xpath->query( './/*[@class="pandatranslate-default-dot"]', $rows->item( 0 ) )->length );
foreach ( array( 1,2 ) as $index ) { check( 1, $xpath->query( './/*[@data-language-drag]', $rows->item( $index ) )->length ); check( 1, $xpath->query( './/*[@data-remove-language]', $rows->item( $index ) )->length ); }
check( 1, $xpath->query( '//template[@data-language-row-template]//tr' )->length );
check( true, $xpath->query( '//*[@data-language-option and @data-local-name]' )->length > 20 );
echo "compact language table, hidden headings, source indicator, reorder handles, gray status bars including zero, and localized search: ok\n";
