<?php
defined( 'ABSPATH' ) || exit;

/** Persistent, language-specific progress for the background site queue. */
final class PandaTranslate_Site_Progress {
	const OPTION = 'pandatranslate_site_job';
	private static $wake_registered = false;
	/** Continue through WordPress's existing cron endpoint after its lock is released.
	 * No public plugin endpoint or browser-held credential is introduced. External
	 * cron configurations are respected; their scheduled events remain durable.
	 */
	private static function wake_after_request() {
		if ( self::$wake_registered || ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) || ! function_exists( 'wp_safe_remote_post' ) || ! function_exists( 'site_url' ) ) return;
		self::$wake_registered = true;
		register_shutdown_function( function () {
			if ( self::running( self::state( true ) ) ) wp_safe_remote_post( site_url( 'wp-cron.php' ), array( 'timeout' => 0.01, 'blocking' => false, 'body' => array() ) );
		} );
	}

	public static function state( $fresh = false ) { if ( $fresh && function_exists( 'wp_cache_delete' ) ) wp_cache_delete( self::OPTION, 'options' ); return (array) get_option( self::OPTION, array() ); }
	public static function start( array $ids, array $languages, $api ) {
		$job = array( 'id' => wp_generate_uuid4(), 'api' => $api, 'languages' => array(), 'completed' => array(), 'posts' => $ids );
		foreach ( $languages as $language ) $job['languages'][$language] = array( 'total' => count( $ids ), 'processed' => 0, 'failed' => 0, 'phase' => count( $ids ) ? 'collect' : 'done' );
		update_option( self::OPTION, $job, false ); return $job['id'];
	}
	/** Collect source IDs in bounded keyset batches instead of a site-sized option/cron array. */
	public static function start_query( array $types, array $languages, $api ) {
		$lock = 'pandatranslate_job_start';
		if ( ! add_option( $lock, time(), '', false ) ) return new WP_Error( 'pandatranslate_busy', __( 'A translation is already running.', 'pandatranslate' ) );
		try {
			if ( self::running( self::state( true ) ) ) return new WP_Error( 'pandatranslate_busy', __( 'A translation is already running.', 'pandatranslate' ) );
			return self::create_query( $types, $languages, $api );
		} finally { delete_option( $lock ); }
	}
	private static function create_query( array $types, array $languages, $api ) {
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		if ( ! $types || ! $languages ) return new WP_Error( 'pandatranslate_empty_source' );
		$query = new WP_Query( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC', 'ignore_sticky_posts' => true ) );
		$total = (int) $query->found_posts;
		if ( ! $total || ! $languages ) return new WP_Error( 'pandatranslate_empty_source', __( 'No source content was collected. Check published content types and source-page collection.', 'pandatranslate' ) );
		$job = array( 'id' => wp_generate_uuid4(), 'api' => $api, 'languages' => array(), 'source_types' => $types, 'source_max_id' => (int) $query->posts[0] );
		foreach ( $languages as $language ) $job['languages'][$language] = array( 'total' => $total, 'processed' => 0, 'failed' => 0, 'phase' => 'collect', 'cursor' => 0 );
		update_option( self::OPTION, $job, false );
		foreach ( $languages as $language ) wp_schedule_single_event( time(), 'pandatranslate_collect_site_batch', array( $job['id'], $language ) );
		self::wake_after_request();
		return array( 'id' => $job['id'], 'total' => $total );
	}
	public static function running( array $job ) {
		if ( ! empty( $job['cancelled'] ) ) return false;
		foreach ( $job['languages'] ?? array() as $counts ) if ( ! in_array( $counts['phase'] ?? '', array( 'done', 'failed', 'cancelled' ), true ) ) return true;
		return false;
	}
	/** Persist the selected inventory operation before any provider request. */
	public static function start_languages( array $languages, $api, $mode, $request_id ) {
		if ( ! in_array( $mode, array( 'missing', 'all', 'failed' ), true ) ) return new WP_Error( 'pandatranslate_mode', __( 'Invalid translation mode.', 'pandatranslate' ) );
		if ( ! $languages || ! preg_match( '/^[a-zA-Z0-9_-]{8,80}$/', $request_id ) ) return new WP_Error( 'pandatranslate_language', __( 'Invalid target language.', 'pandatranslate' ) );
		foreach ( $languages as $language ) if ( ! PandaTranslate_Edition::allows_language( $language ) ) return new WP_Error( 'pandatranslate_language', __( 'Invalid target language.', 'pandatranslate' ) );
		$api = $api ?: PandaTranslate_Providers::default_id();
		$provider = PandaTranslate_Providers::get( $api ); if ( is_wp_error( $provider ) ) return $provider;
		$lock = 'pandatranslate_job_start';
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) < time() - 60 ) delete_option( $lock );
			return new WP_Error( 'pandatranslate_busy', __( 'A translation is already running.', 'pandatranslate' ) );
		}
		try {
			$old = self::state( true );
			if ( ( $old['request_id'] ?? '' ) === $request_id ) return $old;
			if ( self::running( $old ) ) return new WP_Error( 'pandatranslate_busy', __( 'A translation is already running.', 'pandatranslate' ) );
			$job = array( 'id' => wp_generate_uuid4(), 'request_id' => $request_id, 'inventory' => true, 'mode' => $mode, 'api' => $api, 'languages' => array() );
			foreach ( array_unique( $languages ) as $language ) $job['languages'][$language] = array( 'total' => 0, 'processed' => 0, 'failed' => 0, 'phase' => 'start' );
			update_option( self::OPTION, $job, false );
			foreach ( array_keys( $job['languages'] ) as $language ) wp_schedule_single_event( time(), 'pandatranslate_process_language_batch', array( $job['id'], $language ) );
			self::wake_after_request();
			return $job;
		} finally { delete_option( $lock ); }
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
				wp_schedule_single_event( time(), 'pandatranslate_process_language_batch', array( $id, $language ) );
			} elseif ( 'collect' === $current['languages'][$language]['phase'] ) wp_schedule_single_event( time(), 'pandatranslate_collect_site_batch', array( $id, $language ) );
		} finally { delete_option( $lock ); self::wake_after_request(); }
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
	public static function finish( $job_id, $post_id, $language, $failed, $message = '' ) {
		if ( ! $job_id ) return;
		$job = self::state( true ); $key = $language . ':' . $post_id;
		if ( ! empty( $job['cancelled'] ) || ( $job['id'] ?? '' ) !== $job_id || ! isset( $job['languages'][$language] ) || isset( $job['completed'][$key] ) ) return;
		if ( ! empty( $job['source_types'] ) ) {
			if ( $post_id <= (int) $job['languages'][$language]['cursor'] ) return;
			$job['languages'][$language]['cursor'] = (int) $post_id;
		} else $job['completed'][$key] = true;
		++$job['languages'][$language]['processed']; if ( $failed ) { ++$job['languages'][$language]['failed']; if ( $message ) $job['languages'][$language]['last_error'] = $message; }
		$ready = $job['languages'][$language]['processed'] >= $job['languages'][$language]['total'];
		if ( $ready ) $job['languages'][$language]['phase'] = 'prepare';
		update_option( self::OPTION, $job, false );
		if ( $ready ) wp_schedule_single_event( time(), 'pandatranslate_process_language_batch', array( $job_id, $language ) );
	}
	public static function process( $job_id, $language ) {
		$lock = 'pandatranslate_collect_' . substr( hash( 'sha256', $job_id ), 0, 24 );
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) < time() - 600 ) delete_option( $lock );
			wp_schedule_single_event( time() + 60, 'pandatranslate_process_language_batch', array( $job_id, $language ) ); return;
		}
		try { self::process_locked( $job_id, $language ); } finally { delete_option( $lock ); self::wake_after_request(); }
	}
	private static function process_locked( $job_id, $language ) {
		$job = self::state( true ); if ( ! empty( $job['cancelled'] ) || ( $job['id'] ?? '' ) !== $job_id || ! isset( $job['languages'][$language] ) ) return;
		$counts = $job['languages'][$language]; if ( in_array( $counts['phase'], array( 'done', 'failed', 'cancelled' ), true ) || ( 'collect' === $counts['phase'] && empty( $job['inventory'] ) ) ) return;
		$input = array_merge( $counts['translation'] ?? array(), array( 'language' => $language, 'api' => $job['api'], 'phase' => $counts['phase'], 'mode' => $job['mode'] ?? 'missing', 'run_id' => $job_id ) );
		// Leave a durable continuation if PHP stops during a bounded batch.
		wp_schedule_single_event( time() + 90, 'pandatranslate_process_language_batch', array( $job_id, $language ) );
		$result = PandaTranslate_Translation_Jobs::batch( $input );
		if ( is_wp_error( $result ) ) { $counts['phase'] = 'failed'; $counts['last_error'] = $result->get_error_message(); }
		else { $counts['translation'] = $result; $counts['phase'] = ! empty( $result['cancelled'] ) ? 'cancelled' : ( ! empty( $result['failed'] ) ? 'failed' : ( $result['done'] ? 'done' : $result['phase'] ) ); }
		$job = self::state( true ); if ( ( $job['id'] ?? '' ) !== $job_id ) return;
		if ( ! empty( $job['cancelled'] ) ) $counts['phase'] = 'cancelled';
		$job['languages'][$language] = $counts; update_option( self::OPTION, $job, false );
		wp_clear_scheduled_hook( 'pandatranslate_process_language_batch', array( $job_id, $language ) );
		if ( ! in_array( $counts['phase'], array( 'done', 'failed', 'cancelled' ), true ) ) wp_schedule_single_event( time(), 'pandatranslate_process_language_batch', array( $job_id, $language ) );
	}
	public static function render() {
		$job = self::state( true ); if ( empty( $job['languages'] ) ) return;
		echo '<section class="pandatranslate-site-job"><h3>' . esc_html__( 'Background translation progress', 'pandatranslate' ) . '</h3><p>' . esc_html( PandaTranslate_Providers::label( $job['api'] ) ) . '</p>';
		$running = self::running( $job );
		if ( $running ) echo '<button type="button" class="contactpanda-large-action" data-contactpanda-action="cancel" data-contactpanda-action-mode="labelled" data-site-job-cancel="' . esc_attr( $job['id'] ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'pandatranslate_language_batch' ) ) . '">' . esc_html__( 'Cancel translation', 'pandatranslate' ) . '</button>';
		if ( ! empty( $job['cancelled'] ) ) echo '<p>' . esc_html__( 'Translation cancelled. Saved translations are retained.', 'pandatranslate' ) . '</p>';
		$overall_total = 0; $overall_processed = 0; $totals_ready = true;
		foreach ( $job['languages'] as $counts ) {
			$translation = $counts['translation'] ?? array();
			if ( ! in_array( $counts['phase'], array( 'translate', 'done', 'failed', 'cancelled' ), true ) || ! isset( $translation['total_units'] ) ) $totals_ready = false;
			$overall_total += $translation['total_units'] ?? 0;
			$overall_processed += ( $translation['success'] ?? 0 ) + ( $translation['errors'] ?? 0 );
		}
		echo '<div data-job-overall><strong>' . esc_html__( 'Overall progress', 'pandatranslate' ) . '</strong> <progress' . ( $totals_ready && $overall_total ? ' max="' . esc_attr( $overall_total ) . '" value="' . esc_attr( min( $overall_processed, $overall_total ) ) . '"' : '' ) . '></progress>';
		if ( $totals_ready ) echo ' ' . esc_html( $overall_processed . ' / ' . $overall_total . ( $overall_total ? ' (' . (int) floor( min( 1, $overall_processed / $overall_total ) * 100 ) . '%)' : '' ) );
		else echo ' ' . esc_html__( 'Preparing untranslated content…', 'pandatranslate' );
		echo '</div>';

		foreach ( $job['languages'] as $language => $counts ) {
			$translation = $counts['translation'] ?? array(); $processed = 'collect' === $counts['phase'] && empty( $job['inventory'] ) ? $counts['processed'] : ( $translation['processed'] ?? $counts['processed'] ); $total = 'collect' === $counts['phase'] && empty( $job['inventory'] ) ? $counts['total'] : ( $translation['total'] ?? $counts['total'] );
			echo '<div><strong>' . esc_html( strtoupper( $language ) ) . '</strong> <progress' . ( in_array( $counts['phase'], array( 'failed', 'cancelled' ), true ) ? ' hidden' : '' ) . ( $total ? ' max="' . esc_attr( $total ) . '" value="' . esc_attr( min( $processed, $total ) ) . '"' : '' ) . '></progress> ' . esc_html( ( 'cancelled' === $counts['phase'] ? __( 'Translation cancelled. Saved translations are retained.', 'pandatranslate' ) : null ) ?? $translation['message'] ?? ( 'failed' === $counts['phase'] ? __( 'Failed', 'pandatranslate' ) : ( 'start' === $counts['phase'] ? __( 'Translation queued. The tab can be closed.', 'pandatranslate' ) : __( 'Collecting source content…', 'pandatranslate' ) ) ) ) . ' ' . esc_html( $total ? $processed . ' / ' . $total : (string) $processed ) . ' · ' . esc_html( sprintf( /* translators: %d: number of failed translations. */
		__( '%d failed', 'pandatranslate' ), $counts['failed'] + ( $translation['errors'] ?? 0 ) ) ) . ( isset( $counts['last_error'] ) ? ' · ' . esc_html( $counts['last_error'] ) : '' ) . '</div>';
		}
		echo '</section>';
	}
}
