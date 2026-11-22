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
		if ( ! $source ) { global $wpdb; foreach ( self::tables() as $table ) if ( $pair = self::language_pair( substr( $table, strlen( $wpdb->prefix . 'trp_dictionary_' ) ) ) ) { $source = $pair[0]; break; } }
		$normalize = function ( $locale ) { return preg_replace( '/_formal$/', '', PandaTranslate_Source_Language::normalized( $locale ) ); };
		if ( $source && $normalize( $source ) !== $normalize( PandaTranslate_Source_Language::wordpress_locale() ) ) return new WP_Error( 'pandatranslate_migration_source', sprintf( /* translators: %1$s: TranslatePress source language, %2$s: WordPress source language. */
		__( 'TranslatePress source %1$s differs from WordPress source %2$s. Import stopped to prevent incorrect language assignments.', 'pandatranslate' ), $source, PandaTranslate_Source_Language::wordpress_locale() ) );
		return true;
	}
	/** Restore only untouched legacy imports that were public in TranslatePress. */
	public static function restore_imported_publication() {
		if ( ! current_user_can( 'manage_options' ) || get_option( 'pandatranslate_import_publication_v1' ) ) return;
		global $wpdb; $destination = PandaTranslate_Repository::table(); $tables = self::tables(); if ( ! $tables ) return;
		foreach ( $tables as $source ) {
			$language = self::table_language( $source ); if ( ! $language ) continue;
			$sql = "UPDATE %i p INNER JOIN %i s ON p.unit_key = SHA2(CONCAT(%s,s.id,'|',s.original),256) AND p.target_language = %s SET p.status = 'published' WHERE p.provider = 'translatepress-import' AND p.status = 'reviewed' AND BINARY p.source_html = BINARY s.original AND BINARY p.translated_html = BINARY s.translated AND s.status IN (1,2)";
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- The fixed UPDATE statement above binds both table identifiers with %i and all comparison values with %s. Read current import source and preserve target records. Source tables are prefix/regex allowlisted; table identifiers and values are bound. No persistent cache during migration/repair.
			if ( false === $wpdb->query( $wpdb->prepare( $sql, $destination, $source, 'translatepress|' . $source . '|', $language ) ) ) return;
		}
		update_option( 'pandatranslate_import_publication_v1', 1, false );
	}
	/** Preserve locale variants on either side; the source variant is not part of the target. */
	private static function language_pair( $pair ) {
		$pair = strtolower( str_replace( '-', '_', $pair ) );
		$locale = '[a-z]{2,3}_[a-z0-9]{2,8}(?:_formal)?';
		if ( ! preg_match( '/^(' . $locale . ')_(' . $locale . ')$/', $pair, $matches ) ) return array();
		return array( $matches[1], $matches[2] );
	}
	/** TranslatePress dictionary suffixes contain source AND target locales. */
	public static function target_language( $pair ) {
		$pair = self::language_pair( $pair );
		return $pair ? self::target_for_locale( $pair[1] ) : '';
	}
	private static function target_for_locale( $locale ) {
		$code = str_replace( '_', '-', $locale );
		$rows = PandaTranslate_Plugin::configured_language_rows();
		$candidates = array();
		foreach ( $rows as $row ) {
			$row_locale = strtolower( str_replace( '-', '_', $row['locale'] ) );
			if ( 'formal' === ( $row['formality'] ?? '' ) && ! preg_match( '/_formal$/', $row_locale ) ) $row_locale .= '_formal';
			if ( $row_locale === $locale ) { if ( $row['code'] === $code ) return $code; $candidates[] = $row['code']; }
		}
		return 1 === count( $candidates ) ? $candidates[0] : $code;
	}
	private static function normalize_target( $code ) {
		foreach ( PandaTranslate_Plugin::configured_language_rows() as $row ) {
			if ( $row['code'] === $code ) return $code;
		}
		$locale = strtolower( str_replace( '-', '_', $code ) );
		return self::target_language( $code ) ?: self::target_for_locale( $locale );
	}
	private static function normalize_report_languages( $languages ) {
		$result = array();
		foreach ( $languages as $code => $stats ) {
			$target = self::normalize_target( $code );
			$previous = $result[ $target ] ?? null;
			$processed = (int) ( $stats['processed'] ?? 0 );
			$previous_processed = (int) ( $previous['processed'] ?? 0 );
			// Older reports could contain the same run under both short and regional codes.
			if ( null === $previous || $processed > $previous_processed || ( $processed === $previous_processed && $target === $code ) ) {
				$result[ $target ] = $stats;
			}
		}
		return $result;
	}
	private static function table_language( $table ) {
		global $wpdb;
		return self::target_language( substr( $table, strlen( $wpdb->prefix . 'trp_dictionary_' ) ) );
	}
	public static function repair_language_assignments() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$signature = md5( 'locale-pair-v3|' . wp_json_encode( PandaTranslate_Plugin::configured_language_rows() ) );
		if ( get_option( 'pandatranslate_migration_language_repair' ) === $signature ) return;
		global $wpdb; $table = PandaTranslate_Repository::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read current import source and preserve target records. Source tables are prefix/regex allowlisted; table identifiers and values are bound. No persistent cache during migration/repair.
		$legacy = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT target_language FROM %i WHERE provider = 'translatepress-import'", $table ) );
		if ( $wpdb->last_error ) return;
		foreach ( $legacy as $old ) {
			$target = self::normalize_target( $old ); if ( ! $target || $old === $target ) continue;
			// Preserve an existing target entry on a unique-key collision; never delete translations.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read current import source and preserve target records. Source tables are prefix/regex allowlisted; table identifiers and values are bound. No persistent cache during migration/repair.
			$result = $wpdb->query( $wpdb->prepare( "UPDATE %i AS legacy LEFT JOIN %i AS existing ON existing.unit_key = legacy.unit_key AND existing.target_language = %s SET legacy.target_language = %s WHERE legacy.target_language = %s AND legacy.provider = 'translatepress-import' AND existing.id IS NULL", $table, $table, $target, $target, $old ) );
			if ( false === $result ) return;
		}
		$report = (array) get_option( self::REPORT_OPTION, array() );
		if ( isset( $report['languages'] ) ) {
			$normalized = self::normalize_report_languages( $report['languages'] );
			if ( $normalized !== $report['languages'] ) {
				$report['languages'] = $normalized;
				update_option( self::REPORT_OPTION, $report, false );
			}
		}
		update_option( 'pandatranslate_migration_language_repair', $signature, false );
	}
	public static function tables() {
		global $wpdb; $like = $wpdb->esc_like( $wpdb->prefix . 'trp_dictionary_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read current import source and preserve target records. Source tables are prefix/regex allowlisted; table identifiers and values are bound. No persistent cache during migration/repair.
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		return array_values( array_filter( $tables, function ( $table ) use ( $wpdb ) { return 0 === strpos( $table, $wpdb->prefix . 'trp_dictionary_' ) && preg_match( '/^[A-Za-z0-9_]+$/', $table ); } ) );
	}
	public static function render() {
		$totals = self::source_totals(); $count = array_sum( $totals );
		$preflight = self::preflight();
		if ( is_wp_error( $preflight ) ) echo '<div class="notice notice-warning inline"><p>' . esc_html( $preflight->get_error_message() ) . '</p><a href="' . esc_url( admin_url( 'admin.php?page=pandatranslate' ) ) . '">' . esc_html__( 'Dashboard', 'pandatranslate' ) . '</a></div>';
		echo '<h2>' . esc_html__( 'TranslatePress migration', 'pandatranslate' ) . '</h2><p>' . esc_html__( 'Imports existing TranslatePress dictionary entries and preserves their publication status. Existing Panda Translate entries are preserved.', 'pandatranslate' ) . '</p>';
		echo '<div class="pandatranslate-status-row"><span>' . esc_html__( 'Found translations', 'pandatranslate' ) . '</span><strong>' . esc_html( number_format_i18n( $count ) ) . '</strong></div>';
		if ( $count && ! is_wp_error( $preflight ) ) {
			echo '<div data-migration data-total="' . esc_attr( $count ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'pandatranslate_migration_batch' ) ) . '">';
			echo '<p><label><input type="checkbox" data-migration-confirm> ' . esc_html__( 'Database backup created; start the import.', 'pandatranslate' ) . '</label></p>';
			echo '<p><label><input type="checkbox" data-migration-missing checked> ' . esc_html__( 'Import only missing translations', 'pandatranslate' ) . '</label></p>';
			echo '<button class="contactpanda-large-action pandatranslate-action" data-contactpanda-action-mode="labelled" type="button" data-contactpanda-action="import" data-migration-start disabled><span class="dashicons dashicons-database-import"></span>' . esc_html__( 'Import translations', 'pandatranslate' ) . '</button>';
			echo '<div class="pandatranslate-migration-progress" data-migration-progress hidden aria-live="polite"><div class="pandatranslate-migration-progress-head"><strong>' . esc_html__( 'Migration progress', 'pandatranslate' ) . '</strong><b data-migration-percent>0%</b></div><span class="pandatranslate-progress-track"><i data-migration-bar style="width:0%"></i></span><p data-migration-status>' . esc_html__( 'Preparing migration…', 'pandatranslate' ) . '</p><div class="pandatranslate-migration-totals"><span>' . esc_html__( 'Processed', 'pandatranslate' ) . ': <strong data-migration-processed>0</strong> / <strong data-migration-total>' . esc_html( number_format_i18n( $count ) ) . '</strong></span><span>' . esc_html__( 'Imported', 'pandatranslate' ) . ': <strong data-migration-imported>0</strong></span><span>' . esc_html__( 'Skipped', 'pandatranslate' ) . ': <strong data-migration-skipped>0</strong></span><span>' . esc_html__( 'Errors', 'pandatranslate' ) . ': <strong data-migration-errors>0</strong></span></div></div>';
			echo '</div>';
		}
		elseif ( ! $count ) {
			$tables = self::tables();
			$message = ! $tables ? __( 'No TranslatePress dictionary tables were found.', 'pandatranslate' ) : ( ! $totals ? __( 'TranslatePress tables were found, but their language pairs could not be assigned. Check the configured languages.', 'pandatranslate' ) : __( 'No translated entries were found in the available TranslatePress dictionary tables.', 'pandatranslate' ) );
			echo '<p>' . esc_html( $message ) . '</p>';
		}
		self::render_language_report( $totals );
	}
	private static function source_totals() {
		global $wpdb; $totals = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read current import source and preserve target records. Source tables are prefix/regex allowlisted; table identifiers and values are bound. No persistent cache during migration/repair.
		foreach ( self::tables() as $table ) { $language = self::table_language( $table ); if ( ! $language ) continue; $totals[ $language ] = ( $totals[ $language ] ?? 0 ) + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE translated <> ''", $table ) ); }
		return $totals;
	}
	private static function report_language_label( $language ) {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			if ( self::table_language( $table ) !== $language ) continue;
			$pair = substr( $table, strlen( $wpdb->prefix . 'trp_dictionary_' ) );
			if ( $locales = self::language_pair( $pair ) ) return strtoupper( str_replace( '_', '-', $locales[0] ) ) . ' → ' . strtoupper( str_replace( '_', '-', $locales[1] ) );
		}
		return PandaTranslate_Features::catalog()[ $language ]['name'] ?? strtoupper( $language );
	}
	private static function render_language_report( $source_totals ) {
		$report = (array) get_option( self::REPORT_OPTION, array() ); $languages = self::normalize_report_languages( (array) ( $report['languages'] ?? array() ) ); if ( ! $source_totals && ! $languages ) return;
		echo '<section class="pandatranslate-migration-report"><h3>' . esc_html__( 'Migration status by language', 'pandatranslate' ) . '</h3>';
		echo '<p data-migration-completed-at' . ( empty( $report['completed_at'] ) ? ' hidden' : '' ) . '><small>' . esc_html( self::completed_label( $report ) ) . '</small></p>';
		foreach ( array_unique( array_merge( array_keys( $source_totals ), array_keys( $languages ) ) ) as $language ) { $stats = wp_parse_args( $languages[ $language ] ?? array(), array( 'total' => $source_totals[ $language ] ?? 0, 'processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0 ) ); $total = isset( $languages[ $language ] ) && ! empty( $report['missing_only'] ) ? (int) $stats['total'] : max( (int) $stats['total'], (int) ( $source_totals[ $language ] ?? 0 ) ); $percent = $total ? min( 100, round( (int) $stats['processed'] / $total * 100 ) ) : ( isset( $languages[ $language ] ) && ! empty( $report['missing_only'] ) ? 100 : 0 ); $name = self::report_language_label( $language ); echo '<article class="pandatranslate-migration-language" data-migration-language="' . esc_attr( $language ) . '"><div><strong>' . esc_html( PandaTranslate_Features::flag( $language ) . ' ' . $name ) . '</strong><b data-language-percent>' . esc_html( $percent ) . '%</b></div><span class="pandatranslate-progress-track"><i data-language-bar style="width:' . esc_attr( $percent ) . '%"></i></span><p><span>' . esc_html__( 'Processed', 'pandatranslate' ) . ': <strong data-language-processed>' . esc_html( number_format_i18n( $stats['processed'] ) ) . '</strong> / <strong data-language-total>' . esc_html( number_format_i18n( $total ) ) . '</strong></span><span>' . esc_html__( 'Imported', 'pandatranslate' ) . ': <strong data-language-imported>' . esc_html( number_format_i18n( $stats['imported'] ) ) . '</strong></span><span>' . esc_html__( 'Skipped', 'pandatranslate' ) . ': <strong data-language-skipped>' . esc_html( number_format_i18n( $stats['skipped'] ) ) . '</strong></span><span>' . esc_html__( 'Errors', 'pandatranslate' ) . ': <strong data-language-errors>' . esc_html( number_format_i18n( $stats['errors'] ) ) . '</strong></span></p></article>'; }
		echo '</section>';
	}
	private static function completed_label( $report ) {
		if ( empty( $report['completed_at'] ) ) return '';
		return sprintf( /* translators: %s: completion date and time. */
			__( 'Last completed migration: %s', 'pandatranslate' ), $report['completed_at'] );
	}

	private static function source_key( $table, $row ) {
		return hash( 'sha256', 'translatepress|' . $table . '|' . $row['id'] . '|' . $row['original'] );
	}

	private static function source_batch( $table, $language, $cursor, $missing_only ) {
		global $wpdb;
		if ( $missing_only && ! self::uses_sqlite() ) {
			// Match the existing import identity to include gaps and changed source strings below a previous cursor.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration reads current source and destination rows using bound table identifiers and values.
			return $wpdb->get_results( $wpdb->prepare( 'SELECT s.id,s.original,s.translated,s.status FROM %i s WHERE s.translated <> %s AND s.id > %d AND NOT EXISTS (SELECT 1 FROM %i p WHERE p.target_language = %s AND p.unit_key = SHA2(CONCAT(%s,s.id,%s,s.original),256)) ORDER BY s.id LIMIT %d', $table, '', $cursor, PandaTranslate_Repository::table(), $language, 'translatepress|' . $table . '|', '|', self::BATCH_SIZE ), ARRAY_A );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The source and cursor are bound and the scan must see current rows.
		return $wpdb->get_results( $wpdb->prepare( "SELECT id,original,translated,status FROM %i WHERE translated <> '' AND id > %d ORDER BY id ASC LIMIT %d", $table, $cursor, self::BATCH_SIZE ), ARRAY_A );
	}

	private static function uses_sqlite() {
		return defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE;
	}

	private static function missing_totals( $tables, $repository ) {
		global $wpdb;
		$totals = array();
		foreach ( $tables as $table ) {
			$language = self::table_language( $table );
			if ( ! $language ) continue;
			if ( self::uses_sqlite() ) {
				$count = 0;
				$cursor = 0;
				do {
					$rows = self::source_batch( $table, $language, $cursor, false );
					if ( $wpdb->last_error ) return false;
					if ( ! $rows ) break;
					$cursor = (int) end( $rows )['id'];
					$keys = array_map( function ( $row ) use ( $table ) { return self::source_key( $table, $row ); }, $rows );
					$count += count( $rows ) - count( $repository->find_many( $keys, $language ) );
					if ( $wpdb->last_error ) return false;
				} while ( count( $rows ) === self::BATCH_SIZE );
			} else {
				$sql = 'SELECT COUNT(*) FROM %i s WHERE s.translated <> %s AND NOT EXISTS (SELECT 1 FROM %i p WHERE p.target_language = %s AND p.unit_key = SHA2(CONCAT(%s,s.id,%s,s.original),256))';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Fixed query and bound identifiers; the count is specific to this run.
				$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $table, '', PandaTranslate_Repository::table(), $language, 'translatepress|' . $table . '|', '|' ) );
				if ( $wpdb->last_error ) return false;
			}
			$totals[ $language ] = ( $totals[ $language ] ?? 0 ) + $count;
		}
		return $totals;
	}

	public static function ajax_batch() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Permission required to run this import.', 'pandatranslate' ) ), 403 );
		check_ajax_referer( 'pandatranslate_migration_batch', 'nonce' );
		$preflight = self::preflight(); if ( is_wp_error( $preflight ) ) wp_send_json_error( array( 'message' => $preflight->get_error_message() ), 409 );
		global $wpdb;
		$tables = array_values( array_filter( self::tables(), array( __CLASS__, 'table_language' ) ) );
		$repository = new PandaTranslate_Repository();
		$missing_only = ! empty( $_POST['missing_only'] );
		$table_index = absint( $_POST['table_index'] ?? 0 );
		$last_id = absint( $_POST['last_id'] ?? 0 );
		$report = (array) get_option( self::REPORT_OPTION, array() );

		if ( ! empty( $_POST['reset'] ) ) {
			$totals = $missing_only ? self::missing_totals( $tables, $repository ) : self::source_totals();
			if ( false === $totals ) wp_send_json_error( array( 'message' => __( 'The translation could not be stored.', 'pandatranslate' ) ), 500 );
			$report = array( 'started_at' => current_time( 'mysql' ), 'completed_at' => '', 'languages' => array(), 'missing_only' => $missing_only );
			foreach ( $totals as $code => $total ) $report['languages'][ $code ] = array( 'total' => $total, 'processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0 );
		}

		$totals = array_column( $report['languages'] ?? array(), 'total' );
		$total = array_sum( $totals );
		if ( $table_index >= count( $tables ) || ( $missing_only && ! empty( $_POST['reset'] ) && 0 === $total ) ) {
			$report['completed_at'] = current_time( 'mysql' );
			update_option( self::REPORT_OPTION, $report, false );
			wp_send_json_success( array( 'done' => true, 'total' => $total, 'processed' => 0, 'imported' => 0, 'skipped' => 0, 'languages' => $report['languages'] ?? array(), 'completed_label' => self::completed_label( $report ) ) );
		}

		$table = $tables[ $table_index ];
		$language = self::table_language( $table );
		$rows = self::source_batch( $table, $language, $last_id, $missing_only );
		if ( $wpdb->last_error ) wp_send_json_error( array( 'message' => __( 'The translation could not be stored.', 'pandatranslate' ) ), 500 );
		$keys = array_map( function ( $row ) use ( $table ) { return self::source_key( $table, $row ); }, $rows );
		$existing = $missing_only && ! self::uses_sqlite() ? array() : $repository->find_many( $keys, $language );
		if ( $wpdb->last_error ) wp_send_json_error( array( 'message' => __( 'The translation could not be stored.', 'pandatranslate' ) ), 500 );
		$imported = 0;
		$skipped = 0;
		$errors = 0;
		$processed = 0;
		foreach ( $rows as $index => $row ) {
			$key = $keys[ $index ];
			if ( isset( $existing[ $key ] ) ) {
				if ( ! $missing_only ) { ++$skipped; ++$processed; }
				continue;
			}
			++$processed;
			$unit = array( 'key' => $key, 'source_hash' => hash( 'sha256', $row['original'] ), 'source_html' => $row['original'] );
			$result = $repository->save( $unit, $language, $row['translated'], self::imported_status( $row['status'] ), 'translatepress-import', true );
			if ( is_wp_error( $result ) ) ++$errors;
			elseif ( $result ) ++$imported;
			else ++$skipped;
		}
		if ( count( $rows ) < self::BATCH_SIZE ) { ++$table_index; $last_id = 0; }
		else $last_id = absint( end( $rows )['id'] );
		$done = $table_index >= count( $tables );
		$batch = compact( 'processed', 'imported', 'skipped', 'errors' );
		if ( ! isset( $report['languages'][ $language ] ) ) $report['languages'][ $language ] = array( 'total' => $processed, 'processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0 );
		foreach ( $batch as $key => $amount ) $report['languages'][ $language ][ $key ] += $amount;
		if ( $done && 0 === array_sum( array_column( $report['languages'], 'errors' ) ) ) $report['completed_at'] = current_time( 'mysql' );
		update_option( self::REPORT_OPTION, $report, false );
		wp_send_json_success( $batch + compact( 'done', 'total', 'table_index', 'last_id', 'language' ) + array( 'languages' => $report['languages'], 'completed_label' => self::completed_label( $report ) ) );
	}

	public static function import() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permission required to run this import.', 'pandatranslate' ) );
		check_admin_referer( 'pandatranslate_import_translatepress' ); global $wpdb; $repository = new PandaTranslate_Repository(); $imported = 0; $skipped = 0;
		$preflight = self::preflight(); if ( is_wp_error( $preflight ) ) wp_die( esc_html( $preflight->get_error_message() ) );
		foreach ( self::tables() as $table ) {
			$language = self::table_language( $table ); if ( ! $language ) continue;
			$last_id = 0;
			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read current import source and preserve target records. Source tables are prefix/regex allowlisted; table identifiers and values are bound. No persistent cache during migration/repair.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,original,translated,status FROM %i WHERE translated <> '' AND id > %d ORDER BY id ASC LIMIT %d", $table, $last_id, self::BATCH_SIZE ), ARRAY_A );
				if ( $wpdb->last_error ) wp_die( esc_html__( 'The translation could not be stored.', 'pandatranslate' ) );
				foreach ( $rows as $row ) { $key = self::source_key( $table, $row ); if ( $repository->find( $key, $language ) ) { ++$skipped; continue; } $unit = array( 'key' => $key, 'source_hash' => hash( 'sha256', $row['original'] ), 'source_html' => $row['original'] ); true === $repository->save( $unit, $language, $row['translated'], self::imported_status( $row['status'] ), 'translatepress-import', true ) ? ++$imported : ++$skipped; }
				if ( $rows ) $last_id = (int) end( $rows )['id'];
			} while ( count( $rows ) === self::BATCH_SIZE );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'pandatranslate', 'tab' => 'migration', 'imported' => $imported, 'skipped' => $skipped ), admin_url( 'admin.php' ) ) ); exit;
	}
}
