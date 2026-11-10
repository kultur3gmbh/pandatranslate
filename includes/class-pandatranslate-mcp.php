<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_MCP {
	const ROUTE = 'pandatranslate/v1';
	const AUDIT_OPTION = 'pandatranslate_mcp_audit';

	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_filter( 'contactpanda_mcp_tools', array( __CLASS__, 'register_contactpanda_tools' ) );
		add_filter( 'contactpanda_mcp_tool_call', array( __CLASS__, 'call_contactpanda_tool' ), 10, 3 );
		// Royal MCP >= 1.5.11: reuse its authenticated transport and safety switches.
		add_filter( 'royal_mcp_tools', array( __CLASS__, 'register_contactpanda_tools' ) );
		add_filter( 'royal_mcp_execute_tool_external', array( __CLASS__, 'call_contactpanda_tool' ), 10, 3 );
	}

	public static function register_route() {
		register_rest_route( self::ROUTE, '/mcp', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'handle' ), 'permission_callback' => array( __CLASS__, 'allowed' ) ) );
	}

	public static function allowed() { return current_user_can( 'manage_options' ); }

	public static function endpoint() { return rest_url( self::ROUTE . '/mcp' ); }

	public static function handle( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) return self::error( null, -32700, 'Parse error' );
		$id = $payload['id'] ?? null; $method = sanitize_text_field( $payload['method'] ?? '' );
		if ( 'initialize' === $method ) return self::result( $id, array( 'protocolVersion' => '2025-03-26', 'capabilities' => array( 'tools' => (object) array( 'listChanged' => false ) ), 'serverInfo' => array( 'name' => 'PandaTranslate', 'version' => PANDATRANSLATE_VERSION ), 'instructions' => __( 'Read tools are safe. Translation writes and paid Google requests require confirm=true.', 'pandatranslate' ) ) );
		if ( 'ping' === $method ) return self::result( $id, (object) array() );
		if ( 'notifications/initialized' === $method ) return new WP_REST_Response( null, 202 );
		if ( 'tools/list' === $method ) return self::result( $id, array( 'tools' => self::tools() ) );
		if ( 'tools/call' !== $method ) return self::error( $id, -32601, 'Method not found' );
		$params = is_array( $payload['params'] ?? null ) ? $payload['params'] : array();
		$name = sanitize_key( $params['name'] ?? '' ); $arguments = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : array();
		$outcome = self::call( $name, $arguments );
		self::audit( $name, ! is_wp_error( $outcome ) );
		if ( is_wp_error( $outcome ) ) return self::result( $id, array( 'isError' => true, 'content' => array( array( 'type' => 'text', 'text' => $outcome->get_error_message() ) ) ) );
		return self::result( $id, array( 'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $outcome, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ), 'structuredContent' => $outcome ) );
	}

	public static function register_contactpanda_tools( $tools ) { return array_merge( (array) $tools, self::tools() ); }
	public static function call_contactpanda_tool( $outcome, $name, $arguments ) {
		if ( null !== $outcome || 0 !== strpos( $name, 'pandatranslate_' ) ) return $outcome;
		$result = self::call( $name, (array) $arguments ); self::audit( $name, ! is_wp_error( $result ) ); return $result;
	}

	public static function tools() {
		$tools = array(
			array( 'name' => 'pandatranslate_settings_get', 'description' => 'Read configured source and target languages and provider status.', 'inputSchema' => array( 'type' => 'object', 'properties' => (object) array() ) ),
			array( 'name' => 'pandatranslate_translations_list', 'description' => 'List locally stored translation units.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'language' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string', 'enum' => PandaTranslate_Repository::STATUSES ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ) ) ) ),
			array( 'name' => 'pandatranslate_translation_save', 'description' => 'Save a reviewed or published translation. Requires confirm=true.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'unit_key', 'language', 'translated_text', 'status', 'confirm' ), 'properties' => array( 'unit_key' => array( 'type' => 'string' ), 'language' => array( 'type' => 'string' ), 'translated_text' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string', 'enum' => array( 'reviewed', 'published' ) ), 'confirm' => array( 'type' => 'boolean' ) ) ) ),
			array( 'name' => 'pandatranslate_google_translate', 'description' => 'Create a paid Google translation draft for one known unit. Requires confirm=true.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'unit_key', 'language', 'confirm' ), 'properties' => array( 'unit_key' => array( 'type' => 'string' ), 'language' => array( 'type' => 'string' ), 'api' => array( 'type' => 'string', 'description' => 'Registered translation API ID, for example google-v2 or google-v3.' ), 'confirm' => array( 'type' => 'boolean' ) ) ) ),
		);
		foreach ( $tools as &$tool ) {
			$read = in_array( $tool['name'], array( 'pandatranslate_settings_get', 'pandatranslate_translations_list' ), true );
			$tool['annotations'] = array( 'readOnlyHint' => $read, 'destructiveHint' => ! $read, 'idempotentHint' => $read );
			if ( 'pandatranslate_translations_list' === $tool['name'] ) {
				$tool['description'] = 'Search complete stored source units and translations. No collection or paid requests. Stable ID order; use next_offset for pagination. Read one unit before saving.';
				$tool['inputSchema']['properties']['post_id'] = array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Page/post ID; associations are recorded on the next source-page collection.' );
				$tool['inputSchema']['properties']['query'] = array( 'type' => 'string', 'maxLength' => 200 );
				$tool['inputSchema']['properties']['offset'] = array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 10000000 );
			}
			if ( 'pandatranslate_translation_save' === $tool['name'] ) {
				$tool['description'] = 'Save one complete manual translation after translation_get. Requires its expected_revision and confirm=true. Preserve every inline placeholder exactly once. Conflicting edits fail with 409; returns the verified saved unit. Published is public, reviewed is a draft.';
				$tool['inputSchema']['required'][] = 'expected_revision';
				$tool['inputSchema']['properties']['expected_revision'] = array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' );
			}
		}
		unset( $tool );
		$tools[] = array( 'name' => 'pandatranslate_translation_get', 'description' => 'Read one complete source paragraph, existing translation, formatting placeholders and revision for a subsequent manual save. Use unit_key and language from translations_list.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'unit_key', 'language' ), 'properties' => array( 'unit_key' => array( 'type' => 'string' ), 'language' => array( 'type' => 'string' ) ) ), 'annotations' => array( 'readOnlyHint' => true ) );
		$tools[] = array( 'name' => 'pandatranslate_seo_get', 'description' => 'Read language-specific PandaTranslate slug, SEO and social overrides for a post, with Rank Math/Yoast source values. Read only; change SEO overrides in the PandaTranslate SEO metabox.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id', 'language' ), 'properties' => array( 'post_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'language' => array( 'type' => 'string' ) ) ), 'annotations' => array( 'readOnlyHint' => true ) );
		if ( class_exists( 'PandaTranslate_Edition' ) && ! PandaTranslate_Edition::multilingual() ) $tools = array_values( array_filter( $tools, function ( $tool ) { return 'pandatranslate_seo_get' !== $tool['name']; } ) );
		return $tools;
	}

	public static function call( $name, array $args ) {
		if ( ! self::allowed() ) return new WP_Error( 'pandatranslate_forbidden', 'PandaTranslate requires manage_options permission.', array( 'status' => 403 ) );
		$repository = new PandaTranslate_Repository();
		if ( 'pandatranslate_settings_get' === $name ) {
			$s = PandaTranslate_Plugin::get_settings();
			$languages = array(); foreach ( PandaTranslate_Plugin::language_rows() as $row ) $languages[] = array_intersect_key( $row, array_flip( array( 'code', 'locale', 'slug', 'active', 'formality' ) ) );
			return array( 'version' => PANDATRANSLATE_VERSION, 'source_language' => $s['source_language'], 'target_languages' => explode( ',', $s['target_languages'] ), 'languages' => $languages, 'default_target_language' => $s['default_target_language'], 'provider' => $s['provider'], 'google_api_version' => $s['google_api_version'], 'google_configured' => ! is_wp_error( PandaTranslate_Plugin::google_provider()->configured() ), 'available_apis' => PandaTranslate_Providers::choices(), 'default_api' => PandaTranslate_Providers::default_id(), 'capabilities' => array( 'manual_read_write' => true, 'optimistic_concurrency' => true, 'menu_text' => 'Collected menu text units use the same translation tools; menu structure is not changed.', 'seo' => 'Read overrides with seo_get; write overrides in the SEO metabox.', 'inventory' => 'Stored units only; collect pages through the editor or automatic translation workflow before listing.', 'royal_mcp_minimum' => '1.5.11' ) );
		}
		if ( 'pandatranslate_translations_list' === $name ) {
			$args['query'] = substr( sanitize_text_field( $args['query'] ?? '' ), 0, 200 );
			$rows = $repository->list_rows( $args );
			foreach ( $rows as &$row ) $row = self::present( $row );
			unset( $row );
			$limit = min( 200, max( 1, absint( $args['limit'] ?? 50 ) ) ); $offset = min( 10000000, absint( $args['offset'] ?? 0 ) );
			return array( 'translations' => $rows, 'offset' => $offset, 'next_offset' => count( $rows ) === $limit ? $offset + $limit : null );
		}
		if ( 'pandatranslate_seo_get' === $name ) { if ( class_exists( 'PandaTranslate_Edition' ) && ! PandaTranslate_Edition::multilingual() ) return new WP_Error( 'pandatranslate_multilingual_required', 'SEO requires PandaTranslate Multilingual.' ); return self::seo_get( $args ); }
		if ( in_array( $name, array( 'pandatranslate_translation_get', 'pandatranslate_translation_save', 'pandatranslate_google_translate' ), true ) ) {
			$language = sanitize_key( $args['language'] ?? '' );
			if ( ! self::target_allowed( $language ) ) return new WP_Error( 'pandatranslate_language', 'Select an active target language.', array( 'status' => 400 ) );
			$key = (string) ( $args['unit_key'] ?? '' );
			if ( ! preg_match( '/^[a-f0-9]{64}$/D', $key ) ) return new WP_Error( 'pandatranslate_unit', 'Invalid unit key.', array( 'status' => 400 ) );
			$known = $repository->find( $key, $language );
			if ( ! $known ) return new WP_Error( 'pandatranslate_not_found', 'Translation unit not found. Collect the source page first.', array( 'status' => 404 ) );
			if ( 'pandatranslate_translation_get' === $name ) return self::present( $known );
		}
		if ( 'pandatranslate_translation_save' === $name ) {
			if ( true !== ( $args['confirm'] ?? false ) ) return new WP_Error( 'confirmation_required', __( 'Set confirm to true to save the translation.', 'pandatranslate' ) );
			$status = sanitize_key( $args['status'] ?? '' );
			if ( ! in_array( $status, array( 'reviewed', 'published' ), true ) ) return new WP_Error( 'invalid_status', __( 'Use reviewed or published status.', 'pandatranslate' ) );
			$revision = (string) ( $args['expected_revision'] ?? '' );
			if ( ! hash_equals( PandaTranslate_Repository::revision( $known ), $revision ) ) return new WP_Error( 'pandatranslate_conflict', 'Read the current translation before saving; the revision is missing or outdated.', array( 'status' => 409 ) );
			$protected = PandaTranslate_Inline_Protector::protect( $known['source_html'] );
			$restored = PandaTranslate_Inline_Protector::restore( (string) ( $args['translated_text'] ?? '' ), $protected['tokens'] );
			if ( is_wp_error( $restored ) ) return $restored;
			if ( '' === trim( wp_strip_all_tags( $restored ) ) ) return new WP_Error( 'pandatranslate_empty', 'A reviewed or published translation cannot be empty.', array( 'status' => 400 ) );
			$saved = $repository->save_revision( $known, $restored, $status );
			return is_wp_error( $saved ) ? $saved : array( 'saved' => true, 'verified' => true, 'translation' => self::present( $saved ) );
		}
		if ( 'pandatranslate_google_translate' === $name ) {
			if ( true !== ( $args['confirm'] ?? false ) ) return new WP_Error( 'confirmation_required', __( 'Set confirm to true to start the paid Google request.', 'pandatranslate' ) );
			return PandaTranslate_Plugin::auto_translate_known( sanitize_text_field( $args['unit_key'] ?? '' ), sanitize_key( $args['language'] ?? '' ), sanitize_key( $args['api'] ?? '' ) );
		}
		return new WP_Error( 'unknown_tool', __( 'Unknown PandaTranslate tool.', 'pandatranslate' ) );
	}

	private static function target_allowed( $language ) {
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] === $language && ! empty( $row['active'] ) && $language !== PandaTranslate_Plugin::get_settings()['source_language'] ) return true;
		return false;
	}
	private static function present( array $row ) {
		$source = PandaTranslate_Inline_Protector::protect( $row['source_html'] );
		$row['protected_source_text'] = $source['text'];
		$row['protected_translation_text'] = null;
		$translated = PandaTranslate_Inline_Protector::protect( $row['translated_html'] ); $available = $source['tokens']; $replacement = array();
		foreach ( $translated['tokens'] as $token => $tag ) { $match = array_search( $tag, $available, true ); if ( false === $match ) { $available = array( 'unmatched' => true ); break; } $replacement[$token] = $match; unset( $available[$match] ); }
		if ( ! $available ) $row['protected_translation_text'] = strtr( $translated['text'], $replacement );
		$row['revision'] = PandaTranslate_Repository::revision( $row );
		$row['scope'] = in_array( $row['provider'], array( 'translatepress-import', 'manual-dictionary' ), true ) ? 'source_dictionary' : 'unit';
		$row['scope_note'] = 'source_dictionary' === $row['scope'] ? 'Publishing edits a shared dictionary source; identical source text can change on multiple pages. Page-specific manual translations take precedence.' : 'This stored page unit only.';
		unset( $row['id'], $row['last_error'] );
		return $row;
	}
	private static function seo_get( array $args ) {
		$id = absint( $args['post_id'] ?? 0 ); $language = sanitize_key( $args['language'] ?? '' );
		if ( ! self::target_allowed( $language ) ) return new WP_Error( 'pandatranslate_language', 'Select an active target language.', array( 'status' => 400 ) );
		if ( ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'pandatranslate_forbidden', 'Post access denied.', array( 'status' => 403 ) );
		$post = get_post( $id ); if ( ! $post ) return new WP_Error( 'pandatranslate_not_found', 'Post not found.', array( 'status' => 404 ) );
		$values = array(); foreach ( array( 'slug', 'seo_title', 'seo_description', 'og_title', 'og_description' ) as $field ) $values[$field] = (string) get_post_meta( $id, '_pandatranslate_' . $field . '_' . $language, true );
		return array( 'post_id' => $id, 'language' => $language, 'overrides' => $values, 'source' => array( 'slug' => $post->post_name, 'seo_title' => get_post_meta( $id, '_yoast_wpseo_title', true ) ?: get_post_meta( $id, 'rank_math_title', true ) ?: $post->post_title, 'seo_description' => get_post_meta( $id, '_yoast_wpseo_metadesc', true ) ?: get_post_meta( $id, 'rank_math_description', true ) ?: $post->post_excerpt ) );
	}

	public static function audit_entries() { return array_reverse( (array) get_option( self::AUDIT_OPTION, array() ) ); }
	private static function audit( $tool, $success ) { $log = (array) get_option( self::AUDIT_OPTION, array() ); $log[] = array( 'time' => current_time( 'mysql', true ), 'user_id' => get_current_user_id(), 'tool' => sanitize_key( $tool ), 'success' => (bool) $success ); update_option( self::AUDIT_OPTION, array_slice( $log, -200 ), false ); }
	private static function result( $id, $result ) { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ) ); }
	private static function error( $id, $code, $message ) { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) ) ); }
}
