<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Text_Admin {
	public static function boot() { add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 ); add_action( 'rest_api_init', array( __CLASS__, 'routes' ) ); }
	public static function allowed() { return current_user_can( 'manage_options' ) || current_user_can( 'pandatranslate_translate' ); }
	public static function menu() { add_submenu_page( 'pandatranslate', __( 'Translation texts', 'pandatranslate' ), __( 'Translation texts', 'pandatranslate' ), current_user_can( 'manage_options' ) ? 'manage_options' : 'pandatranslate_translate', 'pandatranslate-texts', array( __CLASS__, 'render' ) ); }
	public static function routes() {
		register_rest_route( 'pandatranslate/v1', '/texts', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'listing' ), 'permission_callback' => array( __CLASS__, 'allowed' ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save' ), 'permission_callback' => array( __CLASS__, 'allowed' ) ),
		) );
		register_rest_route( 'pandatranslate/v1', '/dynamic', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'capture' ), 'permission_callback' => function ( $r ) { return current_user_can( 'edit_post', absint( $r['post_id'] ) ); } ) );
	}
	public static function listing( $request ) {
		$filters = array(); foreach ( array( 'language', 'status', 'query', 'source_kind', 'source_domain', 'offset' ) as $key ) $filters[$key] = $request[$key] ?? ''; $filters['limit'] = 50;
		$rows = ( new PandaTranslate_Repository() )->list_rows( $filters );
		foreach ( $rows as &$row ) { $row['revision'] = PandaTranslate_Repository::revision( $row ); $row['source_text'] = PandaTranslate_Inline_Protector::protect( $row['source_html'] )['text']; $row['translated_text'] = PandaTranslate_Inline_Protector::protect( $row['translated_html'] )['text']; }
		return rest_ensure_response( $rows );
	}
	public static function save( $request ) {
		$items = $request['items']; if ( ! is_array( $items ) || count( $items ) > 50 ) return new WP_Error( 'pandatranslate_limit', __( 'Select up to 50 translations.', 'pandatranslate' ), array( 'status' => 400 ) );
		$repo = new PandaTranslate_Repository(); $results = array();
		foreach ( $items as $item ) {
			$language = sanitize_key( $item['language'] ?? '' ); $row = $repo->find( sanitize_text_field( $item['key'] ?? '' ), $language );
			if ( ! PandaTranslate_Edition::allows_language( $language ) || ! $row || ! hash_equals( PandaTranslate_Repository::revision( $row ), (string) ( $item['revision'] ?? '' ) ) ) { $results[] = array( 'saved' => false, 'error' => __( 'The translation changed. Reload before saving.', 'pandatranslate' ) ); continue; }
			$status = sanitize_key( $item['status'] ?? $row['status'] );
			if ( ! in_array( $status, PandaTranslate_Repository::STATUSES, true ) ) { $results[] = array( 'saved' => false, 'error' => __( 'Invalid translation status.', 'pandatranslate' ) ); continue; }
			$protected = PandaTranslate_Inline_Protector::protect( $row['source_html'] );
			if ( 'published' === $status && ! current_user_can( 'publish_posts' ) && ! current_user_can( 'manage_options' ) ) { $results[] = array( 'saved' => false, 'error' => __( 'Publishing permission is required.', 'pandatranslate' ) ); continue; }
			$value = isset( $item['text'] ) ? PandaTranslate_Inline_Protector::restore( $item['text'], $protected['tokens'] ) : $row['translated_html'];
			if ( ! is_wp_error( $value ) && ! PandaTranslate_Strings::same_placeholders( $row['source_html'], $value ) ) $value = new WP_Error( 'pandatranslate_placeholders', __( 'Formatting variables must remain unchanged.', 'pandatranslate' ) );
			if ( ! is_wp_error( $value ) && 'published' === $status && '' === trim( $value ) ) $value = new WP_Error( 'pandatranslate_empty', __( 'An empty translation cannot be published.', 'pandatranslate' ) );
			$saved = is_wp_error( $value ) ? $value : $repo->save_revision( $row, $value, $status );
			$results[] = array( 'saved' => ! is_wp_error( $saved ), 'error' => is_wp_error( $saved ) ? $saved->get_error_message() : '' );
		}
		return rest_ensure_response( $results );
	}
	public static function capture( $request ) {
		$post = get_post( absint( $request['post_id'] ) ); $language = sanitize_key( $request['language'] ); $text = (string) $request['source_html'];
		if ( ! $post || 'publish' !== $post->post_status || $post->post_password || ! PandaTranslate_Edition::allows_language( $language ) || strlen( $text ) > 12000 || ! trim( wp_strip_all_tags( $text ) ) ) return new WP_Error( 'pandatranslate_dynamic_invalid', __( 'The content cannot be collected.', 'pandatranslate' ), array( 'status' => 400 ) );
		// Transactional page data is never an automatic translation source, including manual dynamic capture.
		if ( function_exists( 'wc_get_page_id' ) ) foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) if ( $post->ID === wc_get_page_id( $page ) ) return new WP_Error( 'pandatranslate_dynamic_private', __( 'Transactional content is excluded from collection.', 'pandatranslate' ), array( 'status' => 400 ) );
		$text = wp_kses( $text, PandaTranslate_Inline_Protector::allowed_html() ); $key = hash( 'sha256', 'dynamic|' . $post->ID . '|' . $text );
		$unit = array( 'key' => $key, 'source_hash' => hash( 'sha256', $text ), 'source_html' => $text, 'source_kind' => 'dynamic' ); $repo = new PandaTranslate_Repository();
		if ( ! $repo->find( $key, $language ) ) { $saved = $repo->save( $unit, $language, '', 'untranslated' ); if ( is_wp_error( $saved ) ) return $saved; }
		$repo->record_context( $key, $post->ID ); return rest_ensure_response( $unit );
	}
	public static function render() {
		if ( ! self::allowed() ) return;
		PandaTranslate_Family_UI::admin();
		wp_enqueue_script( 'pandatranslate-texts', PANDATRANSLATE_URL . 'assets/texts.js', array( 'contactpanda-admin-ui' ), PANDATRANSLATE_VERSION, true );
		wp_localize_script( 'pandatranslate-texts', 'PandaTranslateTexts', array( 'url' => rest_url( 'pandatranslate/v1/texts' ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'canCollect' => current_user_can( 'manage_options' ), 'canPublish' => current_user_can( 'publish_posts' ) || current_user_can( 'manage_options' ), 'labels' => array( 'edit' => __( 'Edit translation', 'pandatranslate' ), 'save' => __( 'Save translation', 'pandatranslate' ), 'source' => __( 'Source', 'pandatranslate' ), 'translation' => __( 'Translation', 'pandatranslate' ), 'lastError' => __( 'Last translation error', 'pandatranslate' ), 'failed' => __( 'The translation could not be saved.', 'pandatranslate' ), 'saved' => __( 'Saved.', 'pandatranslate' ), 'collect' => __( 'Collect plugin and theme texts', 'pandatranslate' ), 'resume' => __( 'Resume text collection', 'pandatranslate' ), 'pause' => __( 'Pause text collection', 'pandatranslate' ), 'collected' => __( 'Text collection completed.', 'pandatranslate' ), 'collectionProgress' => __( 'Files: %1$d / %2$d · New texts: %3$d · Errors: %4$d', 'pandatranslate' ) ) ) );
		echo '<div class="wrap contactpanda-wrap pandatranslate-text-admin"><h1>' . esc_html__( 'Translation texts', 'pandatranslate' ) . '</h1><form data-text-filters style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0"><input name="query" aria-label="' . esc_attr__( 'Search translations', 'pandatranslate' ) . '" placeholder="' . esc_attr__( 'Search translations', 'pandatranslate' ) . '"><select name="language" aria-label="' . esc_attr__( 'Language', 'pandatranslate' ) . '">';
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] !== PandaTranslate_Plugin::get_settings()['source_language'] ) echo '<option value="' . esc_attr( $row['code'] ) . '">' . esc_html( $row['code'] ) . '</option>';
		echo '</select><select name="status" aria-label="' . esc_attr__( 'Status', 'pandatranslate' ) . '"><option value="">' . esc_html__( 'All statuses', 'pandatranslate' ) . '</option>';
		echo '<option value="failed">' . esc_html__( 'Failed', 'pandatranslate' ) . '</option>';
		foreach ( PandaTranslate_Repository::STATUSES as $status ) echo '<option>' . esc_html( $status ) . '</option>';
		echo '</select><select name="source_kind" aria-label="' . esc_attr__( 'Content type', 'pandatranslate' ) . '"><option value="">' . esc_html__( 'All content types', 'pandatranslate' ) . '</option><option value="page">' . esc_html__( 'Page', 'pandatranslate' ) . '</option><option value="dynamic">' . esc_html__( 'Dynamic content', 'pandatranslate' ) . '</option><option value="gettext">Gettext</option></select><input name="source_domain" aria-label="' . esc_attr__( 'Text domain', 'pandatranslate' ) . '" placeholder="' . esc_attr__( 'Text domain', 'pandatranslate' ) . '"><button class="contactpanda-large-action" data-contactpanda-action="search" data-contactpanda-action-mode="labelled">' . esc_html__( 'Search', 'pandatranslate' ) . '</button></form><button type="button" data-text-publish class="contactpanda-large-action" data-contactpanda-action="publish" data-contactpanda-action-mode="labelled">' . esc_html__( 'Publish selected translations', 'pandatranslate' ) . '</button><p data-text-message role="status"></p><table class="widefat striped"><thead><tr><th><input type="checkbox" data-text-all aria-label="' . esc_attr__( 'Select all', 'pandatranslate' ) . '"></th><th>' . esc_html__( 'Source', 'pandatranslate' ) . '</th><th>' . esc_html__( 'Translation', 'pandatranslate' ) . '</th><th>' . esc_html__( 'Status', 'pandatranslate' ) . '</th><th></th></tr></thead><tbody data-text-rows></tbody></table><p><button type="button" data-text-prev class="contactpanda-large-action">' . esc_html__( 'Previous', 'pandatranslate' ) . '</button> <button type="button" data-text-next class="contactpanda-large-action">' . esc_html__( 'Next', 'pandatranslate' ) . '</button></p></div>';
	}
}
