<?php
/** Real importer regression: source table > two batches; preserve an existing manual target. */
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php'; wp_set_current_user( 1 );
if ( '--run-import' === ( $argv[1] ?? '' ) ) {
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'pandatranslate_import_translatepress' );
	PandaTranslate_Migration::import(); exit;
}
if ( PandaTranslate_Migration::tables() ) throw new RuntimeException( 'An isolated fixture with no existing TranslatePress sources is required' );
global $wpdb; $table = $wpdb->prefix . 'trp_dictionary_en_us_qz_qz'; $keys = array(); $marker = wp_generate_uuid4();
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
} finally {
	foreach ( $keys as $key ) $wpdb->delete( PandaTranslate_Repository::table(), array( 'unit_key' => $key, 'target_language' => 'qz-qz' ) );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) );
}
