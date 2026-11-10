<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Translation_Jobs {
	/** One loopback page per call. Tokens contain the job settings, never credentials. */
	public static function collect_page( $language, $offset ) {
		$s = PandaTranslate_Plugin::get_settings();
		if ( empty( $s['auto_post_types'] ) ) return false;
		$ids = get_posts( array( 'post_type' => $s['auto_post_types'], 'post_status' => 'publish', 'posts_per_page' => 1, 'offset' => $offset, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
		if ( ! $ids ) return false;
		$result = self::scan( $ids[0], $language, '', true );
		return is_wp_error( $result ) ? $result : true;
	}
	public static function scan( $post_id, $language, $api_id, $collect_only = false ) {
		$url = get_permalink( $post_id ); if ( ! $url ) return new WP_Error( 'pandatranslate_page_missing', __( 'The source page could not be found.', 'pandatranslate' ) );
		$token = wp_generate_password( 32, false, false );
		set_transient( 'pandatranslate_scan_' . $token, array( 'language' => $language, 'api' => $api_id, 'collect_only' => $collect_only ), 5 * MINUTE_IN_SECONDS );
		$response = wp_safe_remote_get( add_query_arg( array( 'pandatranslate_scan' => $token, 'pt_lang' => $language ), $url ), array( 'timeout' => 90, 'redirection' => 2, 'user-agent' => 'PandaTranslate/' . PANDATRANSLATE_VERSION ) );
		delete_transient( 'pandatranslate_scan_' . $token );
		if ( is_wp_error( $response ) ) return $response;
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || '1' !== wp_remote_retrieve_header( $response, 'x-pandatranslate-scan' ) ) return new WP_Error( 'pandatranslate_scan_failed', __( 'Source content could not be collected. Check loopback requests and page caching.', 'pandatranslate' ) );
		return true;
	}
	public static function batch( $input ) {
		$language = sanitize_key( $input['language'] ?? '' ); $valid = false;
		$s = PandaTranslate_Plugin::get_settings();
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] === $language && $row['active'] && $language !== $s['source_language'] ) $valid = true;
		if ( ! $valid ) return new WP_Error( 'pandatranslate_language', __( 'Invalid target language.', 'pandatranslate' ) );
		$api_id = sanitize_key( $input['api'] ?? '' ) ?: PandaTranslate_Providers::default_id(); $provider = PandaTranslate_Providers::get( $api_id ); if ( is_wp_error( $provider ) ) return $provider;
		$phase = $input['phase'] ?? 'collect'; $cursor = absint( $input['cursor'] ?? 0 ); $limit = absint( $input['limit_id'] ?? 0 );
		$result = array( 'phase' => $phase, 'cursor' => $cursor, 'limit_id' => $limit, 'api' => $api_id, 'done' => false, 'success' => absint( $input['success'] ?? 0 ), 'errors' => absint( $input['errors'] ?? 0 ) );
		$result['total_pages'] = absint( $input['total_pages'] ?? 0 ); $result['total_units'] = absint( $input['total_units'] ?? 0 ); $result['prepared'] = absint( $input['prepared'] ?? 0 ); $result['stage'] = $phase;
		if ( 'collect' === $phase ) {
			if ( ! $result['total_pages'] && function_exists( 'wp_count_posts' ) ) foreach ( $s['auto_post_types'] as $type ) $result['total_pages'] += (int) ( wp_count_posts( $type )->publish ?? 0 );
			$collected = self::collect_page( $language, $cursor ); if ( is_wp_error( $collected ) ) return $collected;
			$result['phase'] = $collected ? 'collect' : 'prepare'; $result['cursor'] = $collected ? $cursor + 1 : 0;
			$count = $collected ? $cursor + 1 : $cursor;
			$result['processed'] = $count; $result['total'] = $result['total_pages'];
			$result['message'] = __( 'Collecting source content…', 'pandatranslate' ) . ' ' . $count . ( $result['total_pages'] ? ' / ' . $result['total_pages'] : '' ); return $result;
		}
		$repository = new PandaTranslate_Repository();
		if ( 'prepare' === $phase ) {
			$seeded = $repository->seed_language( $language, $cursor ); if ( is_wp_error( $seeded ) ) return $seeded;
			$result['phase'] = $seeded['done'] ? 'translate' : 'prepare'; $result['cursor'] = $seeded['done'] ? 0 : $seeded['cursor'];
			$result['prepared'] += $seeded['processed'] ?? 0;
			$result['processed'] = $result['prepared']; $result['total'] = 0;
			$result['message'] = __( 'Preparing untranslated content…', 'pandatranslate' ) . ' ' . $result['prepared']; return $result;
		}
		if ( 'translate' !== $phase ) return new WP_Error( 'pandatranslate_phase', __( 'Invalid translation phase.', 'pandatranslate' ) );
		global $wpdb; $table = PandaTranslate_Repository::table();
		if ( ! $limit ) $limit = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$table} WHERE target_language = %s", $language ) );
		$condition = 'all' === ( $input['mode'] ?? '' ) ? '' : " AND (status = 'untranslated' OR TRIM(translated_html) = '')";
		if ( ! $result['total_units'] ) $result['total_units'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE target_language = %s AND id <= %d {$condition}", $language, $limit ) );
		if ( ! $limit ) return new WP_Error( 'pandatranslate_no_sources', __( 'No source content was collected. Check published content types and source-page collection.', 'pandatranslate' ) );
		$units = $wpdb->get_results( $wpdb->prepare( "SELECT id,unit_key FROM {$table} WHERE target_language = %s AND id > %d AND id <= %d {$condition} ORDER BY id ASC LIMIT 5", $language, $cursor, $limit ), ARRAY_A );
		foreach ( $units as $unit ) { $translated = PandaTranslate_Plugin::auto_translate_known( $unit['unit_key'], $language, $api_id ); if ( is_wp_error( $translated ) ) ++$result['errors']; else ++$result['success']; $result['cursor'] = (int) $unit['id']; }
		$result['limit_id'] = $limit; $result['done'] = count( $units ) < 5;
		$result['message'] = sprintf( __( '%1$d translated · %2$d failed', 'pandatranslate' ), $result['success'], $result['errors'] ) . ' · ' . PandaTranslate_Providers::label( $api_id );
		$result['processed'] = $result['success'] + $result['errors']; $result['total'] = $result['total_units'];
		$result['message'] .= ' · ' . $result['processed'] . ' / ' . $result['total_units'];
		return $result;
	}
}
