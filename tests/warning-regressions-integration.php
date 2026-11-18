<?php
/** Regression tests against real WordPress/wpdb. Synthetic disposable database only. */
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
function pt_warning_expect( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS $label\n"; }
wp_set_current_user( 1 );
$before_get = $_GET;
$_GET = array( 'pandatranslate' => 'edit' );
pt_warning_expect( ! PandaTranslate_Plugin::editing_request(), 'Editor collection cannot be enabled by its GET selector alone' );
$_GET['_pt_editor_nonce'] = 'synthetic-invalid';
pt_warning_expect( ! PandaTranslate_Plugin::editing_request(), 'Invalid editor nonce is rejected' );
$_GET['_pt_editor_nonce'] = wp_create_nonce( 'pandatranslate_editor' );
pt_warning_expect( PandaTranslate_Plugin::editing_request(), 'Authorized editor nonce enables collection' );
wp_set_current_user( 0 );
pt_warning_expect( ! PandaTranslate_Plugin::editing_request(), 'Editor nonce does not grant an anonymous visitor collection rights' );
wp_set_current_user( 1 ); $_GET = $before_get;
pt_warning_expect( (int) get_option( 'pandatranslate_schema' ) === PandaTranslate_Repository::SCHEMA_VERSION, 'Dictionary and schema installer use the same current schema version' );
$repo = new PandaTranslate_Repository(); global $wpdb;
if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new RuntimeException( 'Transaction unavailable' );
try {
	$source = 'Synthetic dictionary ' . wp_generate_uuid4(); $key = hash( 'sha256', $source );
	$unit = array( 'key' => $key, 'source_hash' => hash( 'sha256', $source ), 'source_html' => $source );
	$repo->save( $unit, 'zz', 'Synthetic translated value', 'published', 'manual' );
	$post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic dictionary context' ) );
	$repo->record_context( $key, $post );
	$dictionary = PandaTranslate_Strings::dictionary( 'zz' );
	pt_warning_expect( ( $dictionary[$source] ?? '' ) === 'Synthetic translated value', 'Published dictionary remains available after schema upgrade' );
	$known = $repo->find( $key, 'zz' );
	$wpdb->update( PandaTranslate_Repository::table(), array( 'translated_html' => 'Synthetic concurrent edit' ), array( 'id' => $known['id'] ) );
	$stale = $repo->save_automatic_revision( $known, 'Unwanted stale overwrite', 'published', 'manual' );
	pt_warning_expect( is_wp_error( $stale ) && 'pandatranslate_conflict' === $stale->get_error_code(), 'Prepared compare-and-swap rejects stale translation edits' );
	pt_warning_expect( 'Synthetic concurrent edit' === $repo->find( $key, 'zz' )['translated_html'], 'Concurrent translation content is preserved' );
	$fresh = $repo->find( $key, 'zz' );
	$saved = $repo->save_automatic_revision( $fresh, 'Synthetic confirmed edit', 'published', 'manual' );
	pt_warning_expect( ! is_wp_error( $saved ) && 'Synthetic confirmed edit' === $saved['translated_html'], 'Fresh compare-and-swap still saves normally' );
	$seeded = $repo->seed_language( 'zx', 0 );
	pt_warning_expect( ! is_wp_error( $seeded ) && ! $wpdb->last_error, 'Bounded source seeding accepts prepared table identifiers on real MariaDB' );
} finally {
	$wpdb->query( 'ROLLBACK' );
	if ( isset( $post ) ) clean_post_cache( $post );
	$_GET = $before_get;
}
