<?php
defined( 'ABSPATH' ) || exit;

/** Persistent, language-specific progress for the background site queue. */
final class PandaTranslate_Site_Progress {
	const OPTION = 'pandatranslate_site_job';
	public static function state( $fresh = false ) { if ( $fresh && function_exists( 'wp_cache_delete' ) ) wp_cache_delete( self::OPTION, 'options' ); return (array) get_option( self::OPTION, array() ); }
	public static function start( array $ids, array $languages, $api ) {
		$job = array( 'id' => wp_generate_uuid4(), 'api' => $api, 'languages' => array(), 'completed' => array(), 'posts' => $ids );
		foreach ( $languages as $language ) $job['languages'][$language] = array( 'total' => count( $ids ), 'processed' => 0, 'failed' => 0, 'phase' => count( $ids ) ? 'collect' : 'done' );
		update_option( self::OPTION, $job, false ); return $job['id'];
	}
	/** Collect source IDs in bounded keyset batches instead of a site-sized option/cron array. */
	public static function start_query( array $types, array $languages, $api ) {
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		if ( ! $types || ! $languages ) return new WP_Error( 'pandatranslate_empty_source' );
		$query = new WP_Query( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC', 'ignore_sticky_posts' => true ) );
		$total = (int) $query->found_posts;
		if ( ! $total || ! $languages ) return new WP_Error( 'pandatranslate_empty_source', __( 'No source content was collected. Check published content types and source-page collection.', 'pandatranslate' ) );
		$job = array( 'id' => wp_generate_uuid4(), 'api' => $api, 'languages' => array(), 'source_types' => $types, 'source_max_id' => (int) $query->posts[0] );
		foreach ( $languages as $language ) $job['languages'][$language] = array( 'total' => $total, 'processed' => 0, 'failed' => 0, 'phase' => 'collect', 'cursor' => 0 );
		update_option( self::OPTION, $job, false );
		foreach ( $languages as $language ) wp_schedule_single_event( time() + 5, 'pandatranslate_collect_site_batch', array( $job['id'], $language ) );
		return array( 'id' => $job['id'], 'total' => $total );
	}
	public static function collect( $id, $language ) {
		$job = self::state( true );
		if ( ( $job['id'] ?? '' ) !== $id || ! empty( $job['cancelled'] ) || empty( $job['source_types'] ) || 'collect' !== ( $job['languages'][$language]['phase'] ?? '' ) ) return;
		$lock = 'pandatranslate_collect_' . substr( hash( 'sha256', $id ), 0, 24 );
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) < time() - 600 ) delete_option( $lock );
			wp_schedule_single_event( time() + 60, 'pandatranslate_collect_site_batch', array( $id, $language ) ); return;
		}
		try {
			global $wpdb;
			$types = array_values( array_map( 'sanitize_key', $job['source_types'] ) );
			$args = array_merge( array( (int) $job['languages'][$language]['cursor'], (int) $job['source_max_id'] ), $types );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read at most three current published source IDs for this fixed job snapshot; post types and values are sanitized and bound. Persistent caching would stale the resume cursor.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND ID>%d AND ID<=%d AND post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') ORDER BY ID ASC LIMIT 3', $args ) );
			foreach ( $ids as $post ) {
				$current = self::state( true ); if ( ( $current['id'] ?? '' ) !== $id || ! empty( $current['cancelled'] ) ) return;
				PandaTranslate_Plugin::boot()->process_post( (int) $post, $job['api'], $language, $id );
			}
			$current = self::state( true ); if ( ( $current['id'] ?? '' ) !== $id || ! empty( $current['cancelled'] ) ) return;
			if ( count( $ids ) < 3 && 'collect' === $current['languages'][$language]['phase'] ) {
				$current['languages'][$language]['phase'] = 'prepare';
				$current['languages'][$language]['total'] = $current['languages'][$language]['processed'];
				update_option( self::OPTION, $current, false );
				wp_schedule_single_event( time() + 1, 'pandatranslate_process_language_batch', array( $id, $language ) );
			} elseif ( 'collect' === $current['languages'][$language]['phase'] ) wp_schedule_single_event( time() + 5, 'pandatranslate_collect_site_batch', array( $id, $language ) );
		} finally { delete_option( $lock ); }
	}

	public static function cancel( $id ) {
		$job = self::state(); if ( ( $job['id'] ?? '' ) !== $id ) return;
		$job['cancelled'] = true;
		foreach ( $job['languages'] as &$counts ) if ( ! in_array( $counts['phase'], array( 'done', 'failed' ), true ) ) $counts['phase'] = 'cancelled';
		unset( $counts ); update_option( self::OPTION, $job, false );
		foreach ( $job['languages'] as $language => $counts ) {
			wp_clear_scheduled_hook( 'pandatranslate_process_language_batch', array( $id, $language ) );
			wp_clear_scheduled_hook( 'pandatranslate_collect_site_batch', array( $id, $language ) );
			foreach ( $job['posts'] ?? array() as $post ) wp_clear_scheduled_hook( 'pandatranslate_process_post', array( $post, $job['api'], $language, $id ) );
		}

	}
	public static function finish( $job_id, $post_id, $language, $failed ) {
		if ( ! $job_id ) return;
		$job = self::state( true ); $key = $language . ':' . $post_id;
		if ( ! empty( $job['cancelled'] ) || ( $job['id'] ?? '' ) !== $job_id || ! isset( $job['languages'][$language] ) || isset( $job['completed'][$key] ) ) return;
		if ( ! empty( $job['source_types'] ) ) {
			if ( $post_id <= (int) $job['languages'][$language]['cursor'] ) return;
			$job['languages'][$language]['cursor'] = (int) $post_id;
		} else $job['completed'][$key] = true;
		++$job['languages'][$language]['processed']; if ( $failed ) ++$job['languages'][$language]['failed'];
		$ready = $job['languages'][$language]['processed'] >= $job['languages'][$language]['total'];
		if ( $ready ) $job['languages'][$language]['phase'] = 'prepare';
		update_option( self::OPTION, $job, false );
		if ( $ready ) wp_schedule_single_event( time() + 1, 'pandatranslate_process_language_batch', array( $job_id, $language ) );
	}
	public static function process( $job_id, $language ) {
		$lock = 'pandatranslate_collect_' . substr( hash( 'sha256', $job_id ), 0, 24 );
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) < time() - 600 ) delete_option( $lock );
			wp_schedule_single_event( time() + 60, 'pandatranslate_process_language_batch', array( $job_id, $language ) ); return;
		}
		try { self::process_locked( $job_id, $language ); } finally { delete_option( $lock ); }
	}
	private static function process_locked( $job_id, $language ) {
		$job = self::state(); if ( ! empty( $job['cancelled'] ) || ( $job['id'] ?? '' ) !== $job_id || ! isset( $job['languages'][$language] ) ) return;
		$counts = $job['languages'][$language]; if ( in_array( $counts['phase'], array( 'collect', 'done', 'failed', 'cancelled' ), true ) ) return;
		$input = array_merge( $counts['translation'] ?? array(), array( 'language' => $language, 'api' => $job['api'], 'phase' => $counts['phase'], 'mode' => 'missing', 'run_id' => $job_id ) );
		$result = PandaTranslate_Translation_Jobs::batch( $input );
		if ( is_wp_error( $result ) ) { $counts['phase'] = 'failed'; $counts['last_error'] = $result->get_error_message(); }
		else { $counts['translation'] = $result; $counts['phase'] = ! empty( $result['cancelled'] ) ? 'cancelled' : ( ! empty( $result['failed'] ) ? 'failed' : ( $result['done'] ? 'done' : $result['phase'] ) ); }
		$job = self::state( true ); if ( ( $job['id'] ?? '' ) !== $job_id ) return;
		if ( ! empty( $job['cancelled'] ) ) $counts['phase'] = 'cancelled';
		$job['languages'][$language] = $counts; update_option( self::OPTION, $job, false );
		if ( ! in_array( $counts['phase'], array( 'done', 'failed', 'cancelled' ), true ) ) wp_schedule_single_event( time() + 1, 'pandatranslate_process_language_batch', array( $job_id, $language ) );
	}
	public static function render() {
		$job = self::state(); if ( empty( $job['languages'] ) ) return;
		echo '<section class="pandatranslate-site-job"><h3>' . esc_html__( 'Background translation progress', 'pandatranslate' ) . '</h3><p>' . esc_html( PandaTranslate_Providers::label( $job['api'] ) ) . '</p>';
		$running = false; foreach ( $job['languages'] as $counts ) if ( ! in_array( $counts['phase'], array( 'done', 'failed', 'cancelled' ), true ) ) $running = true;
		if ( $running ) echo '<button type="button" class="contactpanda-large-action" data-contactpanda-action="cancel" data-contactpanda-action-mode="labelled" data-site-job-cancel="' . esc_attr( $job['id'] ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'pandatranslate_language_batch' ) ) . '">' . esc_html__( 'Cancel translation', 'pandatranslate' ) . '</button>';
		if ( ! empty( $job['cancelled'] ) ) echo '<p>' . esc_html__( 'Translation cancelled. Saved translations are retained.', 'pandatranslate' ) . '</p>';
		foreach ( $job['languages'] as $language => $counts ) {
			$translation = $counts['translation'] ?? array(); $processed = 'collect' === $counts['phase'] ? $counts['processed'] : ( $translation['processed'] ?? $counts['processed'] ); $total = 'collect' === $counts['phase'] ? $counts['total'] : ( $translation['total'] ?? $counts['total'] );
			echo '<div><strong>' . esc_html( strtoupper( $language ) ) . '</strong> <progress' . ( in_array( $counts['phase'], array( 'failed', 'cancelled' ), true ) ? ' hidden' : '' ) . ( $total ? ' max="' . esc_attr( $total ) . '" value="' . esc_attr( min( $processed, $total ) ) . '"' : '' ) . '></progress> ' . esc_html( ( 'cancelled' === $counts['phase'] ? __( 'Translation cancelled. Saved translations are retained.', 'pandatranslate' ) : null ) ?? $translation['message'] ?? ( 'failed' === $counts['phase'] ? __( 'Failed', 'pandatranslate' ) : __( 'Collecting source content…', 'pandatranslate' ) ) ) . ' ' . esc_html( $processed . ' / ' . $total ) . ' · ' . esc_html( sprintf( /* translators: %d: number of failed translations. */
		__( '%d failed', 'pandatranslate' ), $counts['failed'] + ( $translation['errors'] ?? 0 ) ) ) . ( isset( $counts['last_error'] ) ? ' · ' . esc_html( $counts['last_error'] ) : '' ) . '</div>';
		}
		echo '</section>';
	}
}
