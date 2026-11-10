<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Migration {
	const BATCH_SIZE = 100;
	const REPORT_OPTION = 'pandatranslate_migration_report';
	public static function boot() {
		add_action( 'admin_post_pandatranslate_import_translatepress', array( __CLASS__, 'import' ) );
		add_action( 'wp_ajax_pandatranslate_migration_batch', array( __CLASS__, 'ajax_batch' ) );
		add_action( 'admin_init', array( __CLASS__, 'repair_language_assignments' ) );
		add_action( 'admin_init', array( __CLASS__, 'restore_imported_publication' ), 20 );
	}
	public static function imported_status( $status ) { return in_array( (int) $status, array( 1, 2 ), true ) ? 'published' : 'untranslated'; }
	public static function preflight() {
		if ( ! class_exists( 'PandaTranslate_Source_Language' ) ) return true;
		$rows = PandaTranslate_Plugin::configured_language_rows();
		if ( PandaTranslate_Source_Language::mismatch( $rows ) ) return new WP_Error( 'pandatranslate_migration_source', __( 'The stored translation source differs from WordPress. Confirm the WordPress source language on the Dashboard before importing. Existing imports are retained.', 'pandatranslate' ) );
		$tp = (array) get_option( 'trp_settings', array() ); $source = $tp['default-language'] ?? '';
		if ( ! $source ) { global $wpdb; foreach ( self::tables() as $table ) if ( preg_match( '/^([a-z]{2,3}_[a-z0-9]{2,8}(?:_formal)?)_/i', substr( $table, strlen( $wpdb->prefix . 'trp_dictionary_' ) ), $match ) ) { $source = $match[1]; break; } }
		$normalize = function ( $locale ) { return preg_replace( '/_formal$/', '', PandaTranslate_Source_Language::normalized( $locale ) ); };
		if ( $source && $normalize( $source ) !== $normalize( PandaTranslate_Source_Language::wordpress_locale() ) ) return new WP_Error( 'pandatranslate_migration_source', sprintf( __( 'TranslatePress source %1$s differs from WordPress source %2$s. Import stopped to prevent incorrect language assignments.', 'pandatranslate' ), $source, PandaTranslate_Source_Language::wordpress_locale() ) );
		return true;
	}
	/** Restore only untouched legacy imports that were public in TranslatePress. */
	public static function restore_imported_publication() {
		if ( ! current_user_can( 'manage_options' ) || get_option( 'pandatranslate_import_publication_v1' ) ) return;
		global $wpdb; $destination = PandaTranslate_Repository::table(); $tables = self::tables(); if ( ! $tables ) return;
		foreach ( $tables as $source ) {
			$language = self::table_language( $source ); if ( ! $language ) continue;
			$sql = "UPDATE {$destination} p INNER JOIN `{$source}` s ON p.unit_key = SHA2(CONCAT(%s,s.id,'|',s.original),256) AND p.target_language = %s SET p.status = 'published' WHERE p.provider = 'translatepress-import' AND p.status = 'reviewed' AND BINARY p.source_html = BINARY s.original AND BINARY p.translated_html = BINARY s.translated AND s.status IN (1,2)";
			if ( false === $wpdb->query( $wpdb->prepare( $sql, 'translatepress|' . $source . '|', $language ) ) ) return;
		}
		update_option( 'pandatranslate_import_publication_v1', 1, false );
	}
	/** TranslatePress dictionary suffixes contain source AND target locales. */
	public static function target_language( $pair ) {
		$pair = strtolower( str_replace( '-', '_', $pair ) );
		if ( ! preg_match( '/^[a-z]{2,3}_[a-z]{2,3}_([a-z]{2,3}_[a-z]{2,3})$/', $pair, $matches ) ) return '';
		$locale = $matches[1]; $code = str_replace( '_', '-', $locale );
		$rows = PandaTranslate_Plugin::language_rows();
		foreach ( $rows as $row ) if ( $row['code'] === $code ) return $code;
		$candidates = array();
		foreach ( $rows as $row ) if ( strtolower( $row['locale'] ) === $locale && 'formal' !== $row['formality'] ) $candidates[] = $row['code'];
		return 1 === count( $candidates ) ? $candidates[0] : $code;
	}
	private static function table_language( $table ) {
		global $wpdb;
		return self::target_language( substr( $table, strlen( $wpdb->prefix . 'trp_dictionary_' ) ) );
	}
	public static function repair_language_assignments() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$signature = md5( wp_json_encode( PandaTranslate_Plugin::language_rows() ) );
		if ( get_option( 'pandatranslate_migration_language_repair' ) === $signature ) return;
		global $wpdb; $table = PandaTranslate_Repository::table();
		$legacy = $wpdb->get_col( "SELECT DISTINCT target_language FROM {$table} WHERE provider = 'translatepress-import'" );
		if ( $wpdb->last_error ) return;
		foreach ( $legacy as $old ) {
			$target = self::target_language( $old ); if ( ! $target || $old === $target ) continue;
			// Preserve an existing target entry on a unique-key collision; never delete translations.
			$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} AS legacy LEFT JOIN {$table} AS existing ON existing.unit_key = legacy.unit_key AND existing.target_language = %s SET legacy.target_language = %s WHERE legacy.target_language = %s AND legacy.provider = 'translatepress-import' AND existing.id IS NULL", $target, $target, $old ) );
			if ( false === $result ) return;
		}
		$report = (array) get_option( self::REPORT_OPTION, array() ); $languages = array();
		foreach ( (array) ( $report['languages'] ?? array() ) as $old => $stats ) { $target = self::target_language( $old ) ?: $old; if ( ! isset( $languages[ $target ] ) ) $languages[ $target ] = array(); foreach ( $stats as $key => $value ) $languages[ $target ][ $key ] = (int) ( $languages[ $target ][ $key ] ?? 0 ) + (int) $value; }
		if ( isset( $report['languages'] ) ) { $report['languages'] = $languages; update_option( self::REPORT_OPTION, $report, false ); }
		update_option( 'pandatranslate_migration_language_repair', $signature, false );
	}
	public static function tables() {
		global $wpdb; $like = $wpdb->esc_like( $wpdb->prefix . 'trp_dictionary_' ) . '%';
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		return array_values( array_filter( $tables, function ( $table ) use ( $wpdb ) { return 0 === strpos( $table, $wpdb->prefix . 'trp_dictionary_' ) && preg_match( '/^[A-Za-z0-9_]+$/', $table ); } ) );
	}
	public static function render() {
		$totals = self::source_totals(); $count = array_sum( $totals );
		$preflight = self::preflight();
		if ( is_wp_error( $preflight ) ) echo '<div class="notice notice-warning inline"><p>' . esc_html( $preflight->get_error_message() ) . '</p><a href="' . esc_url( admin_url( 'admin.php?page=pandatranslate' ) ) . '">' . esc_html__( 'Dashboard', 'pandatranslate' ) . '</a></div>';
		echo '<h2>' . esc_html__( 'TranslatePress migration', 'pandatranslate' ) . '</h2><p>' . esc_html__( 'Imports existing TranslatePress dictionary entries and preserves their publication status. Existing PandaTranslate entries are preserved.', 'pandatranslate' ) . '</p>';
		echo '<div class="pandatranslate-status-row"><span>' . esc_html__( 'Found translations', 'pandatranslate' ) . '</span><strong>' . esc_html( number_format_i18n( $count ) ) . '</strong></div>';
		if ( $count && ! is_wp_error( $preflight ) ) {
			echo '<div data-migration data-total="' . esc_attr( $count ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'pandatranslate_migration_batch' ) ) . '">';
			echo '<p><label><input type="checkbox" data-migration-confirm> ' . esc_html__( 'Database backup created; start the import.', 'pandatranslate' ) . '</label></p>';
			echo '<button class="contactpanda-large-action pandatranslate-action" data-contactpanda-action-mode="labelled" type="button" data-contactpanda-action="import" data-migration-start disabled><span class="dashicons dashicons-database-import"></span>' . esc_html__( 'Import translations', 'pandatranslate' ) . '</button>';
			echo '<div class="pandatranslate-migration-progress" data-migration-progress hidden aria-live="polite"><div class="pandatranslate-migration-progress-head"><strong>' . esc_html__( 'Migration progress', 'pandatranslate' ) . '</strong><b data-migration-percent>0%</b></div><span class="pandatranslate-progress-track"><i data-migration-bar style="width:0%"></i></span><p data-migration-status>' . esc_html__( 'Preparing migration…', 'pandatranslate' ) . '</p><div class="pandatranslate-migration-totals"><span>' . esc_html__( 'Processed', 'pandatranslate' ) . ': <strong data-migration-processed>0</strong> / ' . esc_html( number_format_i18n( $count ) ) . '</span><span>' . esc_html__( 'Imported', 'pandatranslate' ) . ': <strong data-migration-imported>0</strong></span><span>' . esc_html__( 'Skipped', 'pandatranslate' ) . ': <strong data-migration-skipped>0</strong></span><span>' . esc_html__( 'Errors', 'pandatranslate' ) . ': <strong data-migration-errors>0</strong></span></div></div>';
			echo '</div>';
		}
		elseif ( ! $count ) echo '<p>' . esc_html__( 'No TranslatePress dictionary tables were found.', 'pandatranslate' ) . '</p>';
		self::render_language_report( $totals );
	}
	private static function source_totals() {
		global $wpdb; $totals = array();
		foreach ( self::tables() as $table ) { $language = self::table_language( $table ); if ( ! $language ) continue; $totals[ $language ] = ( $totals[ $language ] ?? 0 ) + (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE translated <> ''" ); }
		return $totals;
	}
	private static function report_language_label( $language ) {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			if ( self::table_language( $table ) !== $language ) continue;
			$pair = substr( $table, strlen( $wpdb->prefix . 'trp_dictionary_' ) );
			if ( preg_match( '/^([a-z]{2,3}_[a-z]{2,3})_([a-z]{2,3}_[a-z]{2,3})$/i', $pair, $matches ) ) return strtoupper( str_replace( '_', '-', $matches[1] ) ) . ' → ' . strtoupper( str_replace( '_', '-', $matches[2] ) );
		}
		return PandaTranslate_Features::catalog()[ $language ]['name'] ?? strtoupper( $language );
	}
	private static function render_language_report( $source_totals ) {
		$report = (array) get_option( self::REPORT_OPTION, array() ); $languages = (array) ( $report['languages'] ?? array() ); if ( ! $source_totals && ! $languages ) return;
		echo '<section class="pandatranslate-migration-report"><h3>' . esc_html__( 'Migration status by language', 'pandatranslate' ) . '</h3>';
		if ( ! empty( $report['completed_at'] ) ) echo '<p><small>' . esc_html( sprintf( __( 'Last completed migration: %s', 'pandatranslate' ), $report['completed_at'] ) ) . '</small></p>';
		foreach ( array_unique( array_merge( array_keys( $source_totals ), array_keys( $languages ) ) ) as $language ) { $stats = wp_parse_args( $languages[ $language ] ?? array(), array( 'total' => $source_totals[ $language ] ?? 0, 'processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0 ) ); $total = max( (int) $stats['total'], (int) ( $source_totals[ $language ] ?? 0 ) ); $percent = $total ? min( 100, round( (int) $stats['processed'] / $total * 100 ) ) : 0; $name = self::report_language_label( $language ); echo '<article class="pandatranslate-migration-language" data-migration-language="' . esc_attr( $language ) . '"><div><strong>' . esc_html( PandaTranslate_Features::flag( $language ) . ' ' . $name ) . '</strong><b data-language-percent>' . esc_html( $percent ) . '%</b></div><span class="pandatranslate-progress-track"><i data-language-bar style="width:' . esc_attr( $percent ) . '%"></i></span><p><span>' . esc_html__( 'Processed', 'pandatranslate' ) . ': <strong data-language-processed>' . esc_html( number_format_i18n( $stats['processed'] ) ) . '</strong> / ' . esc_html( number_format_i18n( $total ) ) . '</span><span>' . esc_html__( 'Imported', 'pandatranslate' ) . ': <strong data-language-imported>' . esc_html( number_format_i18n( $stats['imported'] ) ) . '</strong></span><span>' . esc_html__( 'Skipped', 'pandatranslate' ) . ': <strong data-language-skipped>' . esc_html( number_format_i18n( $stats['skipped'] ) ) . '</strong></span><span>' . esc_html__( 'Errors', 'pandatranslate' ) . ': <strong data-language-errors>' . esc_html( number_format_i18n( $stats['errors'] ) ) . '</strong></span></p></article>'; }
		echo '</section>';
	}
	public static function ajax_batch() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Permission required to run this import.', 'pandatranslate' ) ), 403 );
		check_ajax_referer( 'pandatranslate_migration_batch', 'nonce' );
		$preflight = self::preflight(); if ( is_wp_error( $preflight ) ) wp_send_json_error( array( 'message' => $preflight->get_error_message() ), 409 );
		global $wpdb;
		$tables = self::tables();
		$table_index = max( 0, absint( $_POST['table_index'] ?? 0 ) );
		$last_id = max( 0, absint( $_POST['last_id'] ?? 0 ) );
		if ( ! empty( $_POST['reset'] ) ) { $language_totals = self::source_totals(); $initial = array( 'started_at' => current_time( 'mysql' ), 'completed_at' => '', 'languages' => array() ); foreach ( $language_totals as $code => $total ) $initial['languages'][ $code ] = array( 'total' => $total, 'processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0 ); update_option( self::REPORT_OPTION, $initial, false ); }
		if ( $table_index >= count( $tables ) ) wp_send_json_success( array( 'done' => true, 'processed' => 0, 'imported' => 0, 'skipped' => 0 ) );
		$table = $tables[ $table_index ];
		$language = self::table_language( $table );
		if ( ! $language ) wp_send_json_error( array( 'message' => __( 'Invalid target language.', 'pandatranslate' ) ), 400 );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,original,translated,status FROM `{$table}` WHERE translated <> '' AND id > %d ORDER BY id ASC LIMIT %d", $last_id, self::BATCH_SIZE ), ARRAY_A );
		$repository = new PandaTranslate_Repository();
		$imported = 0; $skipped = 0; $errors = 0;
		foreach ( $rows as $row ) {
			$key = hash( 'sha256', 'translatepress|' . $table . '|' . $row['id'] . '|' . $row['original'] );
			if ( $repository->find( $key, $language ) ) { ++$skipped; continue; }
			$unit = array( 'key' => $key, 'source_hash' => hash( 'sha256', $row['original'] ), 'source_html' => $row['original'] );
			is_wp_error( $repository->save( $unit, $language, $row['translated'], self::imported_status( $row['status'] ), 'translatepress-import' ) ) ? ++$errors : ++$imported;
		}
		$processed = count( $rows );
		if ( $processed < self::BATCH_SIZE ) { ++$table_index; $last_id = 0; }
		elseif ( $rows ) $last_id = absint( end( $rows )['id'] );
		$done = $table_index >= count( $tables ); $report = (array) get_option( self::REPORT_OPTION, array() ); if ( ! isset( $report['languages'][ $language ] ) ) $report['languages'][ $language ] = array( 'total' => $processed, 'processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0 ); foreach ( array( 'processed' => $processed, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors ) as $key => $amount ) $report['languages'][ $language ][ $key ] = (int) ( $report['languages'][ $language ][ $key ] ?? 0 ) + $amount; if ( $done ) $report['completed_at'] = current_time( 'mysql' ); update_option( self::REPORT_OPTION, $report, false );
		wp_send_json_success( array( 'done' => $done, 'table_index' => $table_index, 'last_id' => $last_id, 'language' => $language, 'language_stats' => $report['languages'][ $language ], 'processed' => $processed, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors ) );
	}
	public static function import() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permission required to run this import.', 'pandatranslate' ) );
		check_admin_referer( 'pandatranslate_import_translatepress' ); global $wpdb; $repository = new PandaTranslate_Repository(); $imported = 0; $skipped = 0;
		$preflight = self::preflight(); if ( is_wp_error( $preflight ) ) wp_die( esc_html( $preflight->get_error_message() ) );
		foreach ( self::tables() as $table ) {
			$language = self::table_language( $table ); if ( ! $language ) continue;
			$rows = $wpdb->get_results( "SELECT id,original,translated,status FROM `{$table}` WHERE translated <> ''", ARRAY_A );
			foreach ( $rows as $row ) { $key = hash( 'sha256', 'translatepress|' . $table . '|' . $row['id'] . '|' . $row['original'] ); if ( $repository->find( $key, $language ) ) { ++$skipped; continue; } $unit = array( 'key' => $key, 'source_hash' => hash( 'sha256', $row['original'] ), 'source_html' => $row['original'] ); is_wp_error( $repository->save( $unit, $language, $row['translated'], self::imported_status( $row['status'] ), 'translatepress-import' ) ) ? ++$skipped : ++$imported; }
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'pandatranslate', 'tab' => 'migration', 'imported' => $imported, 'skipped' => $skipped ), admin_url( 'admin.php' ) ) ); exit;
	}
}
