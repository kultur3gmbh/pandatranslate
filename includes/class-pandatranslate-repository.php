<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Repository {
	const SCHEMA_VERSION = 4;
	const TABLE_SUFFIX = 'pandatranslate_units';
	const STATUSES = array( 'untranslated', 'automatic', 'reviewed', 'published' );

	public static function table() { global $wpdb; return $wpdb->prefix . self::TABLE_SUFFIX; }

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table = self::table();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			unit_key char(64) NOT NULL,
			source_hash char(64) NOT NULL,
			source_html longtext NOT NULL,
			target_language varchar(20) NOT NULL,
			translated_html longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'untranslated',
			provider varchar(50) NOT NULL DEFAULT '',
			source_kind varchar(20) NOT NULL DEFAULT 'page',
			source_domain varchar(100) NOT NULL DEFAULT '',
			character_count bigint(20) unsigned NOT NULL DEFAULT 0,
			cost_micros bigint(20) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unit_language (unit_key,target_language),
			KEY source_hash (source_hash),
			KEY status (status)
		) {$charset};" );
		if ( $wpdb->last_error ) return new WP_Error( 'pandatranslate_schema', __( 'The database upgrade could not be completed. Existing data has been retained.', 'pandatranslate' ) );
		dbDelta( 'CREATE TABLE ' . $wpdb->prefix . "pandatranslate_contexts (
			unit_key char(64) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (unit_key,post_id),
			KEY post_id (post_id)
		) {$charset};" );
		if ( $wpdb->last_error ) return new WP_Error( 'pandatranslate_schema', __( 'The database upgrade could not be completed. Existing data has been retained.', 'pandatranslate' ) );
		update_option( 'pandatranslate_schema', self::SCHEMA_VERSION, false );
	}
	public function record_context( $key, $post_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
		if ( $post_id > 0 ) $wpdb->replace( $wpdb->prefix . 'pandatranslate_contexts', array( 'unit_key' => $key, 'post_id' => (int) $post_id ) );
	}

	public function find( $key, $language, $source_hash = null ) {
		global $wpdb;
		$sql = "SELECT * FROM %i WHERE unit_key = %s AND target_language = %s";
		$args = array( self::table(), $key, $language );
		if ( null !== $source_hash ) { $sql .= ' AND source_hash = %s'; $args[] = $source_hash; }
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled only from fixed clauses and generated placeholder lists; table names use %i and all values are bound below. Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
		return $wpdb->get_row( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	/** Snapshot for one public render only; editor and job reads remain uncached. */
	public function find_many( array $keys, $language ) {
		global $wpdb; $rows = array();
		foreach ( array_chunk( array_values( array_unique( $keys ) ), 250 ) as $chunk ) {
			$sql = 'SELECT * FROM %i WHERE target_language = %s AND unit_key IN (' . implode( ',', array_fill( 0, count( $chunk ), '%s' ) ) . ')';
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- The query adds only generated %s placeholders. Bind the table, language and keys and take a fresh public-render snapshot.
			foreach ( $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( self::table(), $language ), $chunk ) ), ARRAY_A ) as $row ) $rows[$row['unit_key']] = $row;
		}
		return $rows;
	}

	public function published_imports( $language, array $hashes ) {
		global $wpdb; $rows = array();
		foreach ( array_chunk( $hashes, 250 ) as $chunk ) {
			$sql = 'SELECT source_html,translated_html,provider FROM %i' . " WHERE target_language = %s AND provider <> 'manual' AND provider <> '' AND status = 'published' AND source_hash IN (" . implode( ',', array_fill( 0, count( $chunk ), '%s' ) ) . ')';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled only from fixed clauses and generated placeholder lists; table names use %i and all values are bound below. Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
			$rows = array_merge( $rows, $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( self::table(), $language ), $chunk ) ), ARRAY_A ) );
		}
		return $rows;
	}

	/** Create missing source-memory entries in bounded batches without replacing any existing translation. */
	public function seed_language( $language, $cursor = 0 ) {
		global $wpdb; $table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source.* FROM %i source INNER JOIN (SELECT MIN(id) id FROM %i WHERE source_html <> '' GROUP BY CASE WHEN source_kind IN ('gettext','stored-template-text','stored-template-html','menu') THEN unit_key ELSE source_hash END) canonical ON canonical.id = source.id WHERE source.id > %d ORDER BY source.id LIMIT 250", $table, $table, $cursor ), ARRAY_A );
		if ( $wpdb->last_error ) return new WP_Error( 'pandatranslate_seed_failed', __( 'Source content could not be prepared.', 'pandatranslate' ) );
		foreach ( $rows as $row ) {
			$cursor = (int) $row['id'];
			if ( ! PandaTranslate_Template_Protector::has_text( $row['source_html'] ) ) {
				// Retain imported technical fragments, but never bill a provider for empty markup.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomically mark only rows that remain untranslated in the plugin-owned table.
				$ignored = $wpdb->query( $wpdb->prepare( "UPDATE %i SET source_kind = 'technical', last_error = '' WHERE source_hash = %s AND BINARY source_html = BINARY %s AND status = 'untranslated' AND translated_html = ''", $table, $row['source_hash'], $row['source_html'] ) );
				if ( false === $ignored ) return new WP_Error( 'pandatranslate_seed_failed', __( 'Source content could not be prepared.', 'pandatranslate' ) );
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
			$exists = in_array( $row['source_kind'] ?? '', array( 'gettext', 'stored-template-text', 'stored-template-html', 'menu' ), true ) ? $this->find( $row['unit_key'], $language ) : $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE target_language = %s AND source_hash = %s LIMIT 1", $table, $language, $row['source_hash'] ) );
			if ( ! $exists ) { $saved = $this->save( array( 'key' => $row['unit_key'], 'source_hash' => $row['source_hash'], 'source_html' => $row['source_html'], 'source_kind' => $row['source_kind'] ?? 'page', 'source_domain' => $row['source_domain'] ?? '' ), $language, '', 'untranslated' ); if ( is_wp_error( $saved ) ) return $saved; }
		}
		return array( 'cursor' => $cursor, 'done' => count( $rows ) < 250, 'processed' => count( $rows ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
	public function record_error( $key, $language, $message ) { global $wpdb; return $wpdb->update( self::table(), array( 'last_error' => $message ), array( 'unit_key' => $key, 'target_language' => $language ) ); }

	public function list_rows( array $filters = array() ) {
		global $wpdb;
		$where = array( '1=1' ); $args = array( self::table() );
		if ( ! empty( $filters['language'] ) ) { $where[] = 'target_language = %s'; $args[] = sanitize_key( $filters['language'] ); }
		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], self::STATUSES, true ) ) { $where[] = 'status = %s'; $args[] = $filters['status']; }
		if ( 'failed' === ( $filters['status'] ?? '' ) ) $where[] = "last_error IS NOT NULL AND TRIM(last_error) <> ''";
		if ( ! empty( $filters['post_id'] ) ) { $where[] = 'unit_key IN (SELECT unit_key FROM %i WHERE post_id = %d)'; $args[] = $wpdb->prefix . 'pandatranslate_contexts'; $args[] = absint( $filters['post_id'] ); }
		if ( ! empty( $filters['query'] ) ) { $where[] = '(source_html LIKE %s OR translated_html LIKE %s)'; $search = '%' . $wpdb->esc_like( substr( (string) $filters['query'], 0, 200 ) ) . '%'; $args[] = $search; $args[] = $search; }
		foreach ( array( 'source_kind', 'source_domain' ) as $field ) if ( ! empty( $filters[$field] ) ) { $where[] = $field . ' = %s'; $args[] = sanitize_text_field( $filters[$field] ); }
		$limit = min( 200, max( 1, absint( $filters['limit'] ?? 50 ) ) );
		$offset = min( 10000000, absint( $filters['offset'] ?? 0 ) );
		$sql = 'SELECT unit_key,source_hash,source_html,target_language,translated_html,status,provider,source_kind,source_domain,character_count,cost_micros,last_error,updated_at FROM %i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id ASC LIMIT %d OFFSET %d';
		$args[] = $limit; $args[] = $offset;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled only from fixed clauses and generated placeholder lists; table names use %i and all values are bound below. Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	/** Content fingerprint, independent of the database's second-resolution timestamp. */
	public static function revision( array $row ) {
		$values = array();
		foreach ( array( 'unit_key', 'target_language', 'source_hash', 'source_html', 'translated_html', 'status', 'provider', 'updated_at' ) as $key ) $values[] = (string) ( $row[$key] ?? '' );
		return hash( 'sha256', wp_json_encode( $values ) );
	}

	/** Atomic compare-and-swap: edits made after the preceding read cannot be overwritten. */
	public function save_revision( array $known, $translated_html, $status ) {
		$provider = $translated_html === $known['translated_html'] ? $known['provider'] : ( in_array( $known['provider'], array( 'translatepress-import', 'manual-dictionary' ), true ) ? 'manual-dictionary' : 'manual' );
		return $this->save_automatic_revision( $known, $translated_html, $status, $provider );
	}
	public function save_automatic_revision( array $known, $translated_html, $status, $provider ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) return new WP_Error( 'pandatranslate_status', __( 'Invalid translation status.', 'pandatranslate' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
		$result = $wpdb->query( $wpdb->prepare(
			'UPDATE %i SET translated_html = %s, status = %s, provider = %s, updated_at = %s WHERE id = %d AND BINARY unit_key = BINARY %s AND BINARY target_language = BINARY %s AND BINARY source_hash = BINARY %s AND BINARY source_html = BINARY %s AND BINARY translated_html = BINARY %s AND BINARY status = BINARY %s AND BINARY provider = BINARY %s AND BINARY updated_at = BINARY %s',
			self::table(), $translated_html, $status, $provider, current_time( 'mysql', true ), $known['id'],
			$known['unit_key'], $known['target_language'], $known['source_hash'], $known['source_html'], $known['translated_html'], $known['status'], $known['provider'], $known['updated_at']
		) );
		if ( false === $result ) return new WP_Error( 'pandatranslate_database', 'Translation could not be stored.', array( 'status' => 500 ) );
		$row = $this->find( $known['unit_key'], $known['target_language'] );
		if ( ! $row || $row['source_hash'] !== $known['source_hash'] || $row['source_html'] !== $known['source_html'] || $row['translated_html'] !== $translated_html || $row['status'] !== $status || $provider !== $row['provider'] || ( 0 === $result && self::revision( $known ) !== self::revision( $row ) ) ) return new WP_Error( 'pandatranslate_conflict', 'Translation changed during the save. Read it again before editing.', array( 'status' => 409 ) );
		return $row;
	}

	public function source_character_total() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current plugin-owned source inventory without counting a copy for each target language.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(characters), 0) FROM (SELECT MAX(character_count) AS characters FROM %i WHERE source_kind <> 'technical' GROUP BY CASE WHEN source_kind IN ('gettext','stored-template-text','stored-template-html') THEN unit_key ELSE source_hash END) AS sources", self::table() ) );
	}

	public function language_stats() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT target_language, CASE WHEN status = 'untranslated' OR TRIM(translated_html) = '' THEN 'untranslated' WHEN provider = 'manual' THEN 'manual' WHEN provider IN ('translatepress-import','native-locale') THEN 'migrated' WHEN provider LIKE %s OR provider LIKE %s OR status = 'automatic' THEN 'automatic' ELSE 'manual' END AS category, COUNT(*) AS total, SUM(character_count) AS characters, SUM(CASE WHEN status = 'published' AND TRIM(translated_html) <> '' THEN 1 ELSE 0 END) AS published, SUM(CASE WHEN TRIM(last_error) <> '' THEN 1 ELSE 0 END) AS failed, SUM(CASE WHEN TRIM(last_error) <> '' THEN character_count ELSE 0 END) AS failed_characters FROM %i WHERE source_kind <> 'technical' GROUP BY target_language, category", 'google-cloud-translation%', 'api:%', self::table() ), ARRAY_A );
		$stats = array();
		foreach ( $rows as $row ) {
			$language = $row['target_language'];
			if ( ! isset( $stats[ $language ] ) ) {
				$stats[ $language ] = array( 'total' => 0, 'untranslated' => 0, 'automatic' => 0, 'migrated' => 0, 'manual' => 0, 'published' => 0, 'failed' => 0, 'failed_characters' => 0, 'characters' => array() );
			}
			$count = absint( $row['total'] );
			$stats[ $language ]['total'] += $count;
			$stats[ $language ]['published'] += absint( $row['published'] ?? 0 );
			$stats[ $language ]['failed'] += absint( $row['failed'] ?? 0 );
			$stats[ $language ]['failed_characters'] += absint( $row['failed_characters'] ?? 0 );
			$stats[ $language ][ $row['category'] ] += $count;
			$stats[ $language ]['characters'][ $row['category'] ] = absint( $row['characters'] );
		}
		return $stats;
	}

	public function public_language_counts() {
		global $wpdb; $counts = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT target_language,COUNT(*) AS total FROM %i WHERE status = 'published' AND TRIM(translated_html) <> '' AND source_kind NOT IN ('stored-template-text','stored-template-html','technical') GROUP BY target_language", self::table() ), ARRAY_A );
		foreach ( $rows as $row ) $counts[$row['target_language']] = (int) $row['total'];
		return $counts;
	}

	public function save( array $unit, $language, $translated_html, $status = 'untranslated', $provider = '', $insert_only = false ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) return new WP_Error( 'pandatranslate_status', __( 'Invalid translation status.', 'pandatranslate' ) );
		$data = array(
			'unit_key' => $unit['key'], 'source_hash' => $unit['source_hash'], 'source_html' => $unit['source_html'],
			'target_language' => $language, 'translated_html' => $translated_html, 'status' => $status,
			'provider' => $provider, 'character_count' => function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $unit['source_html'] ) ) : strlen( wp_strip_all_tags( $unit['source_html'] ) ),
			'updated_at' => current_time( 'mysql', true ),
		);
		$existing = $insert_only ? null : $this->find( $unit['key'], $language );
		if ( ! $existing || isset( $unit['source_kind'] ) ) { $data['source_kind'] = sanitize_key( $unit['source_kind'] ?? 'page' ); $data['source_domain'] = sanitize_text_field( $unit['source_domain'] ?? '' ); }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned translation tables require wpdb. Reads must reflect the current edit revision; writes use the atomic compare-and-swap or wpdb value binding, without a stale persistent object cache.
		if ( $insert_only ) {
			$previous = $wpdb->suppress_errors( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The unique index makes this an atomic create.
			try { $result = $wpdb->insert( self::table(), $data ); }
			finally { $wpdb->suppress_errors( $previous ); }
			if ( false === $result && $this->find( $unit['key'], $language ) ) return false;
			return false === $result ? new WP_Error( 'pandatranslate_database', __( 'The translation could not be stored.', 'pandatranslate' ) ) : true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Write the current translation to the plugin-owned table using wpdb value binding.
		$result = $existing ? $wpdb->update( self::table(), $data, array( 'id' => $existing['id'] ) ) : $wpdb->insert( self::table(), $data );
		return false === $result ? new WP_Error( 'pandatranslate_database', __( 'The translation could not be stored.', 'pandatranslate' ) ) : true;
	}
}
