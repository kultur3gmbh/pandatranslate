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
	public static function cancel( $id ) {
		$job = self::state(); if ( ( $job['id'] ?? '' ) !== $id ) return;
		$job['cancelled'] = true;
		foreach ( $job['languages'] as &$counts ) if ( ! in_array( $counts['phase'], array( 'done', 'failed' ), true ) ) $counts['phase'] = 'cancelled';
		unset( $counts ); update_option( self::OPTION, $job, false );
		foreach ( $job['languages'] as $language => $counts ) {
			wp_clear_scheduled_hook( 'pandatranslate_process_language_batch', array( $id, $language ) );
			foreach ( $job['posts'] ?? array() as $post ) wp_clear_scheduled_hook( 'pandatranslate_process_post', array( $post, $job['api'], $language, $id ) );
		}

	}
	public static function finish( $job_id, $post_id, $language, $failed ) {
		if ( ! $job_id ) return;
		$job = self::state( true ); $key = $language . ':' . $post_id;
		if ( ! empty( $job['cancelled'] ) || ( $job['id'] ?? '' ) !== $job_id || ! isset( $job['languages'][$language] ) || isset( $job['completed'][$key] ) ) return;
		$job['completed'][$key] = true; ++$job['languages'][$language]['processed']; if ( $failed ) ++$job['languages'][$language]['failed'];
		$ready = $job['languages'][$language]['processed'] >= $job['languages'][$language]['total'];
		if ( $ready ) $job['languages'][$language]['phase'] = 'prepare';
		update_option( self::OPTION, $job, false );
		if ( $ready ) wp_schedule_single_event( time() + 1, 'pandatranslate_process_language_batch', array( $job_id, $language ) );
	}
	public static function process( $job_id, $language ) {
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
			echo '<div><strong>' . esc_html( strtoupper( $language ) ) . '</strong> <progress' . ( in_array( $counts['phase'], array( 'failed', 'cancelled' ), true ) ? ' hidden' : '' ) . ( $total ? ' max="' . esc_attr( $total ) . '" value="' . esc_attr( min( $processed, $total ) ) . '"' : '' ) . '></progress> ' . esc_html( ( 'cancelled' === $counts['phase'] ? __( 'Translation cancelled. Saved translations are retained.', 'pandatranslate' ) : null ) ?? $translation['message'] ?? ( 'failed' === $counts['phase'] ? __( 'Failed', 'pandatranslate' ) : __( 'Collecting source content…', 'pandatranslate' ) ) ) . ' ' . esc_html( $processed . ' / ' . $total ) . ' · ' . esc_html( sprintf( __( '%d failed', 'pandatranslate' ), $counts['failed'] + ( $translation['errors'] ?? 0 ) ) ) . ( isset( $counts['last_error'] ) ? ' · ' . esc_html( $counts['last_error'] ) : '' ) . '</div>';
		}
		echo '</section>';
	}
}
