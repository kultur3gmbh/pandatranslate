<?php
/** Real importer regression: source table > two batches; preserve an existing manual target. */
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php'; wp_set_current_user( 1 );
if ( '--run-import' === ( $argv[1] ?? '' ) ) {
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'pandatranslate_import_translatepress' );
	PandaTranslate_Migration::import(); exit;
}
if ( '--run-missing-batch' === ( $argv[1] ?? '' ) ) {
	$_POST = json_decode( $argv[2], true );
	$_POST['nonce'] = wp_create_nonce( 'pandatranslate_migration_batch' );
	$_REQUEST = $_POST;
	PandaTranslate_Migration::ajax_batch(); exit;
}
function pt_run_missing_batch( $table_index, $last_id, $reset ) {
	$params = array( 'table_index' => $table_index, 'last_id' => $last_id, 'reset' => $reset, 'missing_only' => true );
	$worker = proc_open( array( PHP_BINARY, __FILE__, '--run-missing-batch', wp_json_encode( $params ) ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $worker ) ) throw new RuntimeException( 'Missing-only worker unavailable' );
	$output = stream_get_contents( $pipes[1] ); $errors = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] );
	$result = json_decode( $output, true );
	if ( proc_close( $worker ) || $errors || empty( $result['success'] ) ) throw new RuntimeException( 'Missing-only worker failed: ' . $errors . $output );
	return $result['data'];
}
if ( PandaTranslate_Migration::tables() ) throw new RuntimeException( 'An isolated fixture with no existing TranslatePress sources is required' );
global $wpdb; $table = $wpdb->prefix . 'trp_dictionary_en_us_qz_qz'; $keys = array(); $marker = wp_generate_uuid4();
$old_report = get_option( PandaTranslate_Migration::REPORT_OPTION, null );
$created = $wpdb->query( $wpdb->prepare( 'CREATE TABLE %i (id bigint unsigned NOT NULL PRIMARY KEY,original longtext NOT NULL,translated longtext NOT NULL,status int NOT NULL)', $table ) );
if ( false === $created ) throw new RuntimeException( 'Synthetic source unavailable' );
try {
	$repo = new PandaTranslate_Repository();
	for ( $id = 1; $id <= 205; $id++ ) {
		$original = 'Synthetic import ' . $marker . ' ' . $id;
		$wpdb->insert( $table, array( 'id' => $id, 'original' => $original, 'translated' => 'Synthetic result ' . $id, 'status' => 2 ) );
		$key = hash( 'sha256', 'translatepress|' . $table . '|' . $id . '|' . $original ); $keys[] = $key;
		if ( 1 === $id ) $repo->save( array( 'key' => $key, 'source_hash' => hash( 'sha256', $original ), 'source_html' => $original ), 'qz-qz', 'Synthetic protected manual value', 'published', 'manual' );
	}
	$process = proc_open( array( PHP_BINARY, __FILE__, '--run-import' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) throw new RuntimeException( 'Importer worker unavailable' );
	$output = stream_get_contents( $pipes[1] ); $errors = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
	if ( proc_close( $process ) || $errors || str_contains( $output, 'Fatal error' ) || str_contains( $output, 'Warning:' ) ) throw new RuntimeException( 'Importer worker failed' );
	$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE target_language=%s', PandaTranslate_Repository::table(), 'qz-qz' ) );
	if ( 205 !== $count ) throw new RuntimeException( 'Importer omitted a batch' );
	echo "PASS Legacy import processes all 205 source rows across bounded batches\n";
	if ( 'Synthetic protected manual value' !== $repo->find( $keys[0], 'qz-qz' )['translated_html'] ) throw new RuntimeException( 'Manual translation overwritten' );
	echo "PASS Existing manual translation survives repeated-source import\n";
	if ( 'Synthetic result 205' !== $repo->find( $keys[204], 'qz-qz' )['translated_html'] ) throw new RuntimeException( 'Final batch missing' );
	echo "PASS Final partial import batch is retained\n";
	foreach ( array( 2, 102, 205 ) as $id ) {
		$wpdb->delete( PandaTranslate_Repository::table(), array( 'unit_key' => $keys[$id-1], 'target_language' => 'qz-qz' ) );
	}
	$wpdb->update( $table, array( 'translated' => 'Must not replace saved translation' ), array( 'id' => 1 ) );
	$imported = $processed = 0; $batch = array( 'table_index' => 0, 'last_id' => 0 ); $reset = true;
	do {
		$batch = pt_run_missing_batch( $batch['table_index'], $batch['last_id'], $reset );
		$reset = false;
		if ( 3 !== $batch['total'] || ! empty( $batch['errors'] ) || $batch['skipped'] ) throw new RuntimeException( 'Missing-only totals include existing translations' );
		$imported += $batch['imported']; $processed += $batch['processed'];
	} while ( ! $batch['done'] );
	if ( 3 !== $imported || 3 !== $processed || 'Synthetic protected manual value' !== $repo->find( $keys[0], 'qz-qz' )['translated_html'] ) throw new RuntimeException( 'Missing-only run did not preserve and fill the expected rows' );
	$repeat = pt_run_missing_batch( 0, 0, true );
	if ( ! $repeat['done'] || $repeat['total'] || $repeat['processed'] || $repeat['imported'] ) throw new RuntimeException( 'Completed imports should be a no-op' );
	$protected_unit = array( 'key' => $keys[0], 'source_hash' => hash( 'sha256', 'not used' ), 'source_html' => 'not used' );
	if ( false !== $repo->save( $protected_unit, 'qz-qz', 'Race overwrite attempt', 'published', 'translatepress-import', true ) || 'Synthetic protected manual value' !== $repo->find( $keys[0], 'qz-qz' )['translated_html'] ) throw new RuntimeException( 'Insert-only path overwrote an existing record' );
	echo "PASS Missing-only migration fills gaps in three batches, preserves existing edits and finishes immediately when nothing is left\n";
} finally {
	if ( null === $old_report ) delete_option( PandaTranslate_Migration::REPORT_OPTION );
	else update_option( PandaTranslate_Migration::REPORT_OPTION, $old_report, false );
	foreach ( $keys as $key ) $wpdb->delete( PandaTranslate_Repository::table(), array( 'unit_key' => $key, 'target_language' => 'qz-qz' ) );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) );
}
