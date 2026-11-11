<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Repository {
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
			PRIMARY KEY (id), UNIQUE KEY unit_language (unit_key,target_language), KEY source_hash (source_hash), KEY status (status)
		) {$charset};" );
		dbDelta( 'CREATE TABLE ' . $wpdb->prefix . "pandatranslate_contexts (
			unit_key char(64) NOT NULL, post_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY (unit_key,post_id), KEY post_id (post_id)
		) {$charset};" );
		update_option( 'pandatranslate_schema', 3, false );
	}
	public function record_context( $key, $post_id ) {
		global $wpdb;
		if ( $post_id > 0 ) $wpdb->replace( $wpdb->prefix . 'pandatranslate_contexts', array( 'unit_key' => $key, 'post_id' => (int) $post_id ) );
	}

	public function find( $key, $language, $source_hash = null ) {
		global $wpdb;
		$sql = "SELECT * FROM " . self::table() . " WHERE unit_key = %s AND target_language = %s";
		$args = array( $key, $language );
		if ( null !== $source_hash ) { $sql .= ' AND source_hash = %s'; $args[] = $source_hash; }
		return $wpdb->get_row( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	public function published_imports( $language, array $hashes ) {
		global $wpdb; $rows = array();
		foreach ( array_chunk( $hashes, 250 ) as $chunk ) {
			$sql = 'SELECT source_html,translated_html,provider FROM ' . self::table() . " WHERE target_language = %s AND provider <> 'manual' AND provider <> '' AND status = 'published' AND source_hash IN (" . implode( ',', array_fill( 0, count( $chunk ), '%s' ) ) . ')';
			$rows = array_merge( $rows, $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $language ), $chunk ) ), ARRAY_A ) );
		}
		return $rows;
	}

	/** Create missing source-memory entries in bounded batches without replacing any existing translation. */
	public function seed_language( $language, $cursor = 0 ) {
		global $wpdb; $table = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source.* FROM {$table} source INNER JOIN (SELECT MIN(id) id FROM {$table} WHERE source_html <> '' GROUP BY CASE WHEN source_kind = 'gettext' THEN unit_key ELSE source_hash END) canonical ON canonical.id = source.id WHERE source.id > %d ORDER BY source.id LIMIT 250", $cursor ), ARRAY_A );
		if ( $wpdb->last_error ) return new WP_Error( 'pandatranslate_seed_failed', __( 'Source content could not be prepared.', 'pandatranslate' ) );
		foreach ( $rows as $row ) {
			$exists = 'gettext' === ( $row['source_kind'] ?? '' ) ? $this->find( $row['unit_key'], $language ) : $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE target_language = %s AND source_hash = %s LIMIT 1", $language, $row['source_hash'] ) );
			if ( ! $exists ) { $saved = $this->save( array( 'key' => $row['unit_key'], 'source_hash' => $row['source_hash'], 'source_html' => $row['source_html'], 'source_kind' => $row['source_kind'] ?? 'page', 'source_domain' => $row['source_domain'] ?? '' ), $language, '', 'untranslated' ); if ( is_wp_error( $saved ) ) return $saved; }
			$cursor = (int) $row['id'];
		}
		return array( 'cursor' => $cursor, 'done' => count( $rows ) < 250, 'processed' => count( $rows ) );
	}

	public function record_error( $key, $language, $message ) { global $wpdb; return $wpdb->update( self::table(), array( 'last_error' => $message ), array( 'unit_key' => $key, 'target_language' => $language ) ); }

	public function list_rows( array $filters = array() ) {
		global $wpdb;
		$where = array( '1=1' ); $args = array();
		if ( ! empty( $filters['language'] ) ) { $where[] = 'target_language = %s'; $args[] = sanitize_key( $filters['language'] ); }
		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], self::STATUSES, true ) ) { $where[] = 'status = %s'; $args[] = $filters['status']; }
		if ( 'failed' === ( $filters['status'] ?? '' ) ) $where[] = "last_error IS NOT NULL AND TRIM(last_error) <> ''";
		if ( ! empty( $filters['post_id'] ) ) { $where[] = 'unit_key IN (SELECT unit_key FROM ' . $wpdb->prefix . 'pandatranslate_contexts WHERE post_id = %d)'; $args[] = absint( $filters['post_id'] ); }
		if ( ! empty( $filters['query'] ) ) { $where[] = '(source_html LIKE %s OR translated_html LIKE %s)'; $search = '%' . $wpdb->esc_like( substr( (string) $filters['query'], 0, 200 ) ) . '%'; $args[] = $search; $args[] = $search; }
		foreach ( array( 'source_kind', 'source_domain' ) as $field ) if ( ! empty( $filters[$field] ) ) { $where[] = $field . ' = %s'; $args[] = sanitize_text_field( $filters[$field] ); }
		$limit = min( 200, max( 1, absint( $filters['limit'] ?? 50 ) ) );
		$offset = min( 10000000, absint( $filters['offset'] ?? 0 ) );
		$sql = 'SELECT unit_key,source_hash,source_html,target_language,translated_html,status,provider,source_kind,source_domain,character_count,cost_micros,last_error,updated_at FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
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
		$set = array( $translated_html, $status, $provider, current_time( 'mysql', true ), $known['id'] );
		$where = array( 'id = %d' );
		foreach ( array( 'unit_key', 'target_language', 'source_hash', 'source_html', 'translated_html', 'status', 'provider', 'updated_at' ) as $field ) { $where[] = "BINARY {$field} = BINARY %s"; $set[] = (string) $known[$field]; }
		$result = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET translated_html = %s, status = %s, provider = %s, updated_at = %s WHERE ' . implode( ' AND ', $where ), $set ) );
		if ( false === $result ) return new WP_Error( 'pandatranslate_database', 'Translation could not be stored.', array( 'status' => 500 ) );
		$row = $this->find( $known['unit_key'], $known['target_language'] );
		if ( ! $row || $row['source_hash'] !== $known['source_hash'] || $row['source_html'] !== $known['source_html'] || $row['translated_html'] !== $translated_html || $row['status'] !== $status || $provider !== $row['provider'] || ( 0 === $result && self::revision( $known ) !== self::revision( $row ) ) ) return new WP_Error( 'pandatranslate_conflict', 'Translation changed during the save. Read it again before editing.', array( 'status' => 409 ) );
		return $row;
	}

	public function language_stats() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT target_language, CASE WHEN status = 'untranslated' OR TRIM(translated_html) = '' THEN 'untranslated' WHEN provider = 'manual' THEN 'manual' WHEN provider IN ('translatepress-import','native-locale') THEN 'migrated' WHEN provider LIKE 'google-cloud-translation%' OR provider LIKE 'api:%' OR status = 'automatic' THEN 'automatic' ELSE 'manual' END AS category, COUNT(*) AS total, SUM(character_count) AS characters FROM " . self::table() . ' GROUP BY target_language, category', ARRAY_A );
		$stats = array();
		foreach ( $rows as $row ) { $language = $row['target_language']; if ( ! isset( $stats[ $language ] ) ) $stats[ $language ] = array( 'total' => 0, 'untranslated' => 0, 'automatic' => 0, 'migrated' => 0, 'manual' => 0, 'characters' => array() ); $count = absint( $row['total'] ); $stats[ $language ]['total'] += $count; $stats[ $language ][ $row['category'] ] += $count; $stats[ $language ]['characters'][ $row['category'] ] = absint( $row['characters'] ); }
		return $stats;
	}

	public function public_language_counts() {
		global $wpdb; $counts = array();
		$rows = $wpdb->get_results( "SELECT target_language,COUNT(*) AS total FROM " . self::table() . " WHERE status = 'published' AND TRIM(translated_html) <> '' GROUP BY target_language", ARRAY_A );
		foreach ( $rows as $row ) $counts[$row['target_language']] = (int) $row['total'];
		return $counts;
	}

	public function save( array $unit, $language, $translated_html, $status = 'untranslated', $provider = '' ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) return new WP_Error( 'pandatranslate_status', __( 'Invalid translation status.', 'pandatranslate' ) );
		$data = array(
			'unit_key' => $unit['key'], 'source_hash' => $unit['source_hash'], 'source_html' => $unit['source_html'],
			'target_language' => $language, 'translated_html' => $translated_html, 'status' => $status,
			'provider' => $provider, 'character_count' => function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $unit['source_html'] ) ) : strlen( wp_strip_all_tags( $unit['source_html'] ) ),
			'updated_at' => current_time( 'mysql', true ),
		);
		$existing = $this->find( $unit['key'], $language );
		if ( ! $existing || isset( $unit['source_kind'] ) ) { $data['source_kind'] = sanitize_key( $unit['source_kind'] ?? 'page' ); $data['source_domain'] = sanitize_text_field( $unit['source_domain'] ?? '' ); }
		$result = $existing ? $wpdb->update( self::table(), $data, array( 'id' => $existing['id'] ) ) : $wpdb->insert( self::table(), $data );
		return false === $result ? new WP_Error( 'pandatranslate_database', __( 'The translation could not be stored.', 'pandatranslate' ) ) : true;
	}
}
