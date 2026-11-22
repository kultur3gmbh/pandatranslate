<?php
/** Bundled catalogs supplement partial packs; cron progress uses the viewer locale. */
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
require_once ABSPATH . WPINC . '/pomo/mo.php';
function pt_locale_expect( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS $label\n"; }
$original_job = get_option( PandaTranslate_Site_Progress::OPTION, false );
$locale = 'de_DE'; $filter = function () use ( &$locale ) { return $locale; };
$temporary = tempnam( sys_get_temp_dir(), 'pt-partial-' ); $partial = $temporary . '.mo'; unlink( $temporary );
$mo = new MO(); $mo->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
$mo->add_entry( new Translation_Entry( array( 'singular' => 'Cancel translation', 'translations' => array( 'Individueller Abbruch' ) ) ) ); $mo->export_to_file( $partial );
add_filter( 'locale', $filter, PHP_INT_MAX ); add_filter( 'determine_locale', $filter, PHP_INT_MAX );
try {
 foreach ( array( 'de_DE', 'de_DE_formal' ) as $locale ) {
  unload_textdomain( 'pandatranslate', true ); load_textdomain( 'pandatranslate', $partial, $locale );
  do_action( 'change_locale', $locale );
  pt_locale_expect( 'Fortschritt nach Sprache' === __( 'Progress by language', 'pandatranslate' ), "$locale: partial older pack is supplemented with current labels" );
  pt_locale_expect( 'Individueller Abbruch' === __( 'Cancel translation', 'pandatranslate' ), "$locale: existing customized translation is retained" );
  foreach ( array( 'Background translation progress', 'Overall progress', 'Preparing untranslated content…', 'Retry failed translations', 'Migrated', 'Automatic', 'Manual', 'Not translated', 'Counts cover recorded translation units. Source characters are an estimate; API billing may differ.' ) as $source ) pt_locale_expect( __( $source, 'pandatranslate' ) !== $source, "$locale: $source" );
  update_option( PandaTranslate_Site_Progress::OPTION, array( 'id' => 'locale-fixture', 'api' => 'google-v2', 'languages' => array( 'fr' => array( 'phase' => 'done', 'processed' => 3, 'total' => 3, 'failed' => 0, 'translation' => array( 'success' => 3, 'errors' => 0, 'processed' => 3, 'total' => 3, 'total_units' => 3, 'message' => 'ENGLISH CRON MESSAGE' ) ) ) ), false );
  ob_start(); PandaTranslate_Site_Progress::render(); $html = ob_get_clean();
  pt_locale_expect( false === strpos( $html, 'ENGLISH CRON MESSAGE' ) && false !== strpos( $html, '3 übersetzt' ) && false !== strpos( $html, '0 fehlgeschlagen' ), "$locale: saved English worker message renders from structured counts in German" );
  pt_locale_expect( false !== strpos( $html, 'Gesamtfortschritt' ) && false !== strpos( $html, 'Fortschritt des Hintergrundauftrags' ), "$locale: progress headings are German" );
  pt_locale_expect( __( 'Focus keyphrase / keywords', 'pandatranslate-multilingual' ) !== 'Focus keyphrase / keywords', "$locale: Multilingual catalog is loaded" );
 }
} finally {
 if ( false === $original_job ) delete_option( PandaTranslate_Site_Progress::OPTION ); else update_option( PandaTranslate_Site_Progress::OPTION, $original_job, false );
 remove_filter( 'locale', $filter, PHP_INT_MAX ); remove_filter( 'determine_locale', $filter, PHP_INT_MAX ); unlink( $partial );
 unload_textdomain( 'pandatranslate', true ); unload_textdomain( 'pandatranslate-multilingual', true ); do_action( 'change_locale', determine_locale() );
}
