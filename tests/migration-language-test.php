<?php
define( 'ABSPATH', __DIR__ );
class PandaTranslate_Plugin {
	public static function language_rows() { return array(
		array( 'code' => 'de', 'locale' => 'de_DE', 'formality' => 'standard' ),
		array( 'code' => 'en', 'locale' => 'en_US', 'formality' => 'standard' ),
		array( 'code' => 'fr', 'locale' => 'fr_FR', 'formality' => 'standard' ),
		array( 'code' => 'it', 'locale' => 'it_IT', 'formality' => 'standard' ),
		array( 'code' => 'ko', 'locale' => 'ko_KR', 'formality' => 'standard' ),
		array( 'code' => 'zh', 'locale' => 'zh_CN', 'formality' => 'standard' ),
	); }
}
class PandaTranslate_Repository { public static function table() { return 'wp_pandatranslate_units'; } }
$options = array( 'pandatranslate_migration_report' => array( 'completed_at' => '2026-11-10 13:03:49', 'languages' => array( 'de-de-en-us' => array( 'total' => 19345, 'processed' => 19345, 'imported' => 0, 'skipped' => 19345, 'errors' => 0 ) ) ) );
function current_user_can( $cap ) { return true; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_option( $key, $default = false ) { global $options; return $options[$key] ?? $default; }
function update_option( $key, $value, $autoload = false ) { global $options; $options[$key] = $value; }
class Migration_DB {
	public $last_error = ''; public $queries = array(); public $prefix = 'wp_';
	public function esc_like( $text ) { return $text; }
	public function get_col( $sql ) { return is_array( $sql ) ? array( 'wp_trp_dictionary_de_de_en_us', 'wp_trp_dictionary_de_de_fr_fr' ) : array( 'de-de-en-us', 'de-de-fr-fr', 'en' ); }
	public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	public function query( $query ) { $this->queries[] = $query; return 1; }
}
$wpdb = new Migration_DB();
require dirname(__DIR__) . '/includes/class-pandatranslate-migration.php';
foreach ( array( 'de_de_en_us' => 'en', 'de-de-fr-fr' => 'fr', 'de_de_it_it' => 'it', 'de_de_ko_kr' => 'ko', 'de_de_zh_cn' => 'zh', 'en_us_pt_br' => 'pt-br', 'invalid_table' => '' ) as $pair => $expected ) {
	if ( $expected !== PandaTranslate_Migration::target_language( $pair ) ) throw new RuntimeException( 'Incorrect mapping: ' . $pair );
}
$label = new ReflectionMethod( 'PandaTranslate_Migration', 'report_language_label' ); if ( PHP_VERSION_ID < 80100 ) $label->setAccessible( true );
if ( 'DE-DE → EN-US' !== $label->invoke( null, 'en' ) ) throw new RuntimeException( 'Incorrect source-target label.' );
PandaTranslate_Migration::repair_language_assignments();
if ( 2 !== count( $wpdb->queries ) ) throw new RuntimeException( 'Expected two legacy repairs.' );
if ( array( 'en', 'en', 'de-de-en-us' ) !== $wpdb->queries[0][1] ) throw new RuntimeException( 'Incorrect repair arguments.' );
if ( false === strpos( $wpdb->queries[0][0], 'existing.id IS NULL' ) ) throw new RuntimeException( 'Missing collision protection.' );
if ( 19345 !== $options['pandatranslate_migration_report']['languages']['en']['processed'] ) throw new RuntimeException( 'Report totals lost.' );
if ( isset( $options['pandatranslate_migration_report']['languages']['de-de-en-us'] ) ) throw new RuntimeException( 'Legacy report key remains.' );
PandaTranslate_Migration::repair_language_assignments();
if ( 2 !== count( $wpdb->queries ) ) throw new RuntimeException( 'Repair must run only once.' );
echo "migration language assignment and legacy repair: ok\n";
assert( 'published' === PandaTranslate_Migration::imported_status( 1 ) );
assert( 'published' === PandaTranslate_Migration::imported_status( 2 ) );
assert( 'untranslated' === PandaTranslate_Migration::imported_status( 0 ) );
PandaTranslate_Migration::restore_imported_publication();
assert( 4 === count( $wpdb->queries ) );
assert( false !== strpos( $wpdb->queries[2][0], "p.status = 'reviewed'" ) );
assert( false !== strpos( $wpdb->queries[2][0], 'BINARY p.translated_html = BINARY s.translated' ) );
assert( false !== strpos( $wpdb->queries[2][0], 's.status IN (1,2)' ) );
PandaTranslate_Migration::restore_imported_publication();
assert( 4 === count( $wpdb->queries ) );
echo "TranslatePress publication restored only for untouched legacy imports, once: ok\n";
