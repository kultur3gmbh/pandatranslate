<?php
require __DIR__ . '/bootstrap.php';

$source = 'A <strong>bold <em>and emphasized</em></strong> sentence with <a href="https://example.test">one link</a>.';
$protected = PandaTranslate_Inline_Protector::protect( $source );
assert( 6 === count( $protected['tokens'] ) );
assert( false !== strpos( $protected['text'], '[[PT:0:OPEN]]' ) );

$translated = 'Ein [[PT:0:OPEN]]fetter [[PT:1:OPEN]]und betonter[[PT:2:CLOSE]][[PT:3:CLOSE]] Satz mit [[PT:4:OPEN]]einem Link[[PT:5:CLOSE]].';
$restored = PandaTranslate_Inline_Protector::restore( $translated, $protected['tokens'] );
assert( ! is_wp_error( $restored ) );
assert( false !== strpos( $restored, '<strong>fetter <em>und betonter</em></strong>' ) );
assert( false !== strpos( $restored, '<a href="https://example.test">einem Link</a>' ) );

$missing = PandaTranslate_Inline_Protector::restore( str_replace( '[[PT:5:CLOSE]]', '', $translated ), $protected['tokens'] );
assert( is_wp_error( $missing ) && 'pandatranslate_tokens_missing' === $missing->get_error_code() );

$invalid = PandaTranslate_Inline_Protector::restore( '[[PT:0:OPEN]][[PT:1:OPEN]]Text[[PT:3:CLOSE]][[PT:2:CLOSE]][[PT:4:OPEN]]Link[[PT:5:CLOSE]]', $protected['tokens'] );
assert( is_wp_error( $invalid ) && 'pandatranslate_tokens_nested' === $invalid->get_error_code() );

echo "inline protector: ok\n";
