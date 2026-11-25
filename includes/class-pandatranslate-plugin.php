<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Plugin {
	const OPTION = 'pandatranslate_settings';
	const PROVIDER_LOG_OPTION = 'pandatranslate_provider_log';
	private static $instance;
	private $repository;
	private $extractor;
	private $scan_mode = false;
	private $scan_job = array();

	public static function boot() {
		if ( ! self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		$this->repository = new PandaTranslate_Repository();
		$this->extractor  = new PandaTranslate_Unit_Extractor();
		add_filter( 'pandatranslate_deepl_api_key', function ( $key ) { if ( $key ) return $key; $s = self::get_settings(); $stored = $s['deepl_key_encrypted'] ?? ''; if ( ! $stored ) return ''; $value = $this->decrypt_secret( $stored ); return is_wp_error( $value ) ? '' : $value; } );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_footer', array( $this, 'floating_switcher' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 90 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'order_admin_submenus' ), PHP_INT_MAX );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( $this, 'order_admin_menu' ), PHP_INT_MAX );
		add_filter( 'wp_php_error_message', array( $this, 'editor_error_message' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ), 99 );
		add_filter( 'plugin_action_links_' . plugin_basename( PANDATRANSLATE_FILE ), array( $this, 'plugin_action_links' ) );
		add_filter( 'wp_nav_menu_items', array( $this, 'menu_switcher' ), 20, 2 );
		add_shortcode( 'pandatranslate_switcher', array( $this, 'switcher_shortcode' ) );
		add_shortcode( 'pandatranslate_menu', array( $this, 'menu_shortcode' ) );
		add_filter( 'pandatranslate_builder_adapters', array( $this, 'default_adapters' ) );
		add_filter( 'pandatranslate_providers', array( $this, 'default_providers' ) );
		add_filter( 'pandatranslate_google_api_key', array( $this, 'stored_google_api_key' ) );
		add_action( 'pandatranslate_provider_event', array( $this, 'log_provider_event' ), 10, 6 );
		add_action( 'save_post', array( $this, 'queue_new_content' ), 20, 3 );
		add_action( 'pandatranslate_process_post', array( $this, 'process_post' ), 10, 4 );
		add_action( 'admin_post_pandatranslate_translate_site', array( $this, 'queue_site' ) );
		add_action( 'wp_ajax_pandatranslate_cancel_translation', array( $this, 'cancel_translation' ) );
		add_action( 'wp_ajax_pandatranslate_start_language_job', array( $this, 'start_language_job' ) );
		add_action( 'wp_ajax_pandatranslate_language_batch', array( $this, 'language_translation_batch' ) );
		add_action( 'wp_ajax_pandatranslate_site_progress', array( $this, 'site_progress' ) );
		add_action( 'wp_ajax_pandatranslate_translation_overview', array( $this, 'translation_overview' ) );
		add_action( 'pandatranslate_process_language_batch', array( 'PandaTranslate_Site_Progress', 'process' ), 10, 2 );
		add_action( 'pandatranslate_collect_site_batch', array( 'PandaTranslate_Site_Progress', 'collect' ), 10, 2 );
		PandaTranslate_MCP::boot();
		PandaTranslate_Features::boot();
	}

	public static function defaults() {
		$source = class_exists( 'PandaTranslate_Source_Language' ) ? PandaTranslate_Source_Language::wordpress_code() : 'de'; $target = in_array( $source, array( 'en', 'en-us' ), true ) ? 'de' : 'en';
		return array(
			'source_language' => $source,
			'url_mode' => 'path',
			'target_languages' => $target,
			'languages' => array(),
			'default_target_language' => $target,
			'provider' => 'manual',
			'google_project_id' => '',
			'google_location' => 'global',
			'google_api_version' => 'v3',
			'language_suggestion' => 0,
			'language_detection_source' => 'country_browser',
			'deepl_enabled' => 0, 'deepl_endpoint' => 'free', 'deepl_key_encrypted' => '', 'deepl_key_last4' => '',
			'google_v2_enabled' => 0,
			'google_v3_enabled' => 0,
			'google_tests' => array(),
			'auto_provider' => '',
			'google_api_key_encrypted' => '',
			'google_api_key_last4' => '',
			'google_key_status' => 'untested',
			'google_key_tested_at' => '',
			'google_key_last_error' => '',
			'auto_enabled' => 0,
			'auto_post_types' => array( 'post', 'page' ),
			'auto_publish' => 1,
			'show_admin_bar' => 1,
			'switcher_menu_locations' => array(),
			'switcher_floating' => 0,
			'switcher_position' => 'bottom-right',
			'switcher_show_names' => 1,
			'switcher_label_mode' => 'flag_code',
			'switcher_native_names' => 1,
			'switcher_display_schema' => 2,
			'switcher_menu_label_mode' => 'inherit',
			'menu_switcher_container_background' => 'rgba(255,255,255,0)',
			'switcher_border_color_hover' => '#dcdcde',
			'switcher_type' => 'dropdown', 'switcher_flag_position' => 'before', 'switcher_flag_shape' => 'rectangle', 'switcher_width' => 'auto', 'switcher_padding_top' => '8px', 'switcher_padding_right' => '10px', 'switcher_padding_bottom' => '8px', 'switcher_padding_left' => '10px',
			'switcher_background' => '#ffffff', 'switcher_background_hover' => '#f0f0f1', 'switcher_text' => '#1d2327', 'switcher_text_hover' => '#1d2327', 'switcher_border_color' => '#dcdcde', 'switcher_border_width' => '1px', 'switcher_border_radius' => '6px', 'switcher_animations' => 1,
		);
	}

	public static function get_settings() {
		static $previous = null, $cached = array();
		$stored = (array) get_option( self::OPTION, array() );
		$input = array( $stored, get_option( 'WPLANG', 'en_US' ) );
		if ( $previous === $input ) return $cached;
		$defaults = self::defaults();
		if ( ! isset( $stored['switcher_border_color_hover'] ) && isset( $stored['switcher_border_color'] ) ) $stored['switcher_border_color_hover'] = $stored['switcher_border_color'];
		if ( ! isset( $stored['menu_switcher_border_color_hover'] ) && isset( $stored['menu_switcher_border_color'] ) ) $stored['menu_switcher_border_color_hover'] = $stored['menu_switcher_border_color'];
		if ( ! isset( $stored['switcher_label_mode'] ) && isset( $stored['switcher_show_names'] ) ) $stored['switcher_label_mode'] = ! empty( $stored['switcher_show_names'] ) ? 'native' : 'code';
		$settings = wp_parse_args( $stored, $defaults );
		if ( ! isset( $stored['language_detection_source'] ) && ! empty( $stored['language_suggestion'] ) ) $settings['language_detection_source'] = 'browser';
		foreach ( array( 'v2', 'v3' ) as $edition ) if ( ! array_key_exists( 'google_' . $edition . '_enabled', $stored ) ) $settings['google_' . $edition . '_enabled'] = 'google-cloud-translation' === $settings['provider'] && $edition === $settings['google_api_version'] ? 1 : 0;
		foreach ( $defaults as $key => $value ) if ( 0 === strpos( $key, 'switcher_' ) && ! isset( $settings['menu_' . $key] ) ) $settings['menu_' . $key] = $settings[$key];
		if ( ! isset( $stored['menu_switcher_label_mode'] ) && 'inherit' !== $settings['switcher_menu_label_mode'] ) $settings['menu_switcher_label_mode'] = $settings['switcher_menu_label_mode'];
		if ( empty( $stored['switcher_display_schema'] ) ) foreach ( array( 'switcher', 'menu_switcher' ) as $prefix ) { $mode = $settings[$prefix . '_label_mode']; $hidden = 'hidden' === $settings[$prefix . '_flag_position']; if ( 'native' === $mode ) $settings[$prefix . '_label_mode'] = $hidden ? 'name' : 'flag_name'; elseif ( 'code' === $mode ) $settings[$prefix . '_label_mode'] = $hidden ? 'code' : 'flag_code'; }
		foreach ( array( 'switcher', 'menu_switcher' ) as $prefix ) if ( 'native' === $settings[$prefix . '_label_mode'] ) $settings[$prefix . '_label_mode'] = 'flag_name';
		$previous = $input; $cached = $settings;
		return $settings;
	}
	public static function switcher_settings( $context ) { $s = self::get_settings(); if ( in_array( $context, array( 'menu', 'theme-menu' ), true ) ) foreach ( self::defaults() as $key => $value ) if ( 0 === strpos( $key, 'switcher_' ) ) $s[$key] = $s['menu_' . $key]; return $s; }
	private function settings() { return self::get_settings(); }

	public static function google_provider() { $s = self::get_settings(); return new PandaTranslate_Google_Provider( $s['google_project_id'], $s['google_location'], $s['google_api_version'] ); }
	public static function configured_language_rows() {
		static $previous = null, $normalized = array();
		$s = self::get_settings();
		$input = array( $s['languages'], $s['source_language'], $s['target_languages'] );
		// Cache only normalization, never entitlement or effective activation. Option changes
		// are visible immediately, including during imports and source-language adoption.
		if ( $previous === $input ) return $normalized;
		$rows = $s['languages'];
		if ( empty( $rows ) ) {
			$codes = array_values( array_unique( array_merge( array( $s['source_language'] ), preg_split( '/\s*,\s*/', $s['target_languages'], -1, PREG_SPLIT_NO_EMPTY ) ) ) );
			$rows = array_map( function ( $code ) { return array( 'code' => $code, 'slug' => $code, 'active' => 1 ); }, $codes );
		}
		$normalized = PandaTranslate_Features::normalize_languages( $rows, $s['source_language'] );
		$previous = $input;
		return $normalized;
	}
	public static function language_rows() { $rows = self::configured_language_rows(); return class_exists( 'PandaTranslate_Edition' ) ? PandaTranslate_Edition::effective_languages( $rows ) : $rows; }
	public static function language_url( $code ) { return apply_filters( 'pandatranslate_language_url', PandaTranslate_Routing::url( $code ), $code ); }
	public static function visible_language_rows() {
		static $counts = null;
		if ( null === $counts ) $counts = ( new PandaTranslate_Repository() )->public_language_counts();
		$source = self::get_settings()['source_language'];
		return array_values( array_filter( self::language_rows(), function ( $row ) use ( $counts, $source ) { return ! empty( $row['active'] ) && ( $row['code'] === $source || ! empty( $counts[$row['code']] ) ); } ) );
	}

	public function register_settings() {
		register_setting( 'pandatranslate', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_settings' ), 'default' => self::defaults() ) );
	}

	public function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$output = $this->settings();
		$section = sanitize_key( $input['_section'] ?? 'general' );
		if ( 'google' === $section ) {
			$scope = $input['_api_provider'] ?? '';
			if ( ! in_array( $scope, array( '', 'deepl', 'google' ), true ) ) return $output;
			if ( 'google' !== $scope ) {
				$output['deepl_enabled'] = empty( $input['deepl_enabled'] ) ? 0 : 1;
				$old_deepl_plan = $output['deepl_endpoint'];
				$output['deepl_endpoint'] = 'pro' === ( $input['deepl_endpoint'] ?? '' ) ? 'pro' : 'free';
				if ( $old_deepl_plan !== $output['deepl_endpoint'] ) unset( $output['deepl_test'] );
				if ( ! empty( $input['deepl_key_remove'] ) ) { unset( $output['deepl_test'] ); $output['deepl_key_encrypted'] = ''; $output['deepl_key_last4'] = ''; }
				elseif ( ! empty( $input['deepl_key'] ) ) { $key = trim( (string) $input['deepl_key'] ); $encrypted = $this->encrypt_secret( $key ); if ( is_wp_error( $encrypted ) ) add_settings_error( self::OPTION, 'deepl_key', $encrypted->get_error_message(), 'error' ); else { unset( $output['deepl_test'] ); $output['deepl_key_encrypted'] = $encrypted; $output['deepl_key_last4'] = substr( $key, -4 ); } }
			}
			if ( 'deepl' !== $scope ) {
				foreach ( array( 'v2', 'v3' ) as $edition ) $output['google_' . $edition . '_enabled'] = empty( $input['google_' . $edition . '_enabled'] ) ? 0 : 1;
				$output['provider'] = $output['google_v2_enabled'] || $output['google_v3_enabled'] ? 'google-cloud-translation' : 'manual';
				$output['google_api_version'] = 'v2' === ( $input['google_api_version'] ?? '' ) ? 'v2' : 'v3';
				$output['google_project_id'] = sanitize_text_field( $input['google_project_id'] ?? '' );
				$output['google_location'] = sanitize_key( $input['google_location'] ?? 'global' ) ?: 'global';
				if ( ! empty( $input['google_api_key_remove'] ) ) {
					$output['google_api_key_encrypted'] = ''; $output['google_api_key_last4'] = ''; $output['google_key_status'] = 'untested'; unset( $output['google_tests']['v2'] ); $output['google_key_tested_at'] = ''; $output['google_key_last_error'] = '';
				} elseif ( isset( $input['google_api_key'] ) && '' !== trim( (string) $input['google_api_key'] ) ) {
					$encrypted = $this->encrypt_secret( trim( (string) $input['google_api_key'] ) );
					if ( is_wp_error( $encrypted ) ) add_settings_error( self::OPTION, 'google_api_key', $encrypted->get_error_message(), 'error' );
					else { $output['google_api_key_encrypted'] = $encrypted; $output['google_api_key_last4'] = substr( trim( (string) $input['google_api_key'] ), -4 ); $output['google_key_status'] = 'untested'; unset( $output['google_tests']['v2'] ); $output['google_key_tested_at'] = ''; $output['google_key_last_error'] = ''; }
				}
			}
			return $output;
		}
		if ( 'preferences' === $section ) {
			$mode = (string) ( $input['language_suggestion'] ?? '0' );
			$output['language_suggestion'] = in_array( $mode, array( '0', '1', '2' ), true ) ? (int) $mode : 0;
			$source = $input['language_detection_source'] ?? $output['language_detection_source'];
			$output['language_detection_source'] = in_array( $source, array( 'browser', 'country', 'country_browser' ), true ) ? $source : 'country_browser';
			return $output;
		}
		if ( 'switcher' === $section ) {
			$output['url_mode'] = 'query' === ( $input['url_mode'] ?? '' ) ? 'query' : 'path';
			$registered = array_keys( get_registered_nav_menus() );
			$locations = array_map( 'sanitize_key', (array) ( $input['switcher_menu_locations'] ?? array() ) );
			$output['switcher_menu_locations'] = array_values( array_intersect( $locations, $registered ) );
			$output['switcher_floating'] = empty( $input['switcher_floating'] ) ? 0 : 1;
			$output['switcher_position'] = in_array( $input['switcher_position'] ?? '', array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ), true ) ? $input['switcher_position'] : 'bottom-right';
			$output['switcher_display_schema'] = 2;
			if ( 'native' === ( $input['switcher_label_mode'] ?? '' ) ) $input['switcher_label_mode'] = 'flag_name';
			$output['switcher_label_mode'] = in_array( $input['switcher_label_mode'] ?? '', array( 'flag_name', 'flag_code', 'name', 'code', 'flags' ), true ) ? $input['switcher_label_mode'] : 'flag_code';
			$output['switcher_native_names'] = empty( $input['switcher_native_names'] ) ? 0 : 1;
			$output['menu_switcher_native_names'] = empty( $input['menu_switcher_native_names'] ) ? 0 : 1;
			$output['switcher_show_names'] = in_array( $output['switcher_label_mode'], array( 'flag_name', 'name' ), true ) ? 1 : 0;
			$output['switcher_menu_label_mode'] = in_array( $input['switcher_menu_label_mode'] ?? '', array( 'native', 'code', 'flags', 'inherit' ), true ) ? $input['switcher_menu_label_mode'] : 'inherit';
			$output['switcher_type'] = 'inline' === ( $input['switcher_type'] ?? '' ) ? 'inline' : 'dropdown';
			$output['switcher_flag_position'] = in_array( $input['switcher_flag_position'] ?? '', array( 'before', 'after', 'hidden' ), true ) ? $input['switcher_flag_position'] : 'before';
			$output['switcher_flag_shape'] = in_array( $input['switcher_flag_shape'] ?? '', array( 'rectangle', 'square', 'round' ), true ) ? $input['switcher_flag_shape'] : 'rectangle';
			foreach ( array( 'switcher_width', 'switcher_padding_top', 'switcher_padding_right', 'switcher_padding_bottom', 'switcher_padding_left', 'switcher_border_width', 'switcher_border_radius' ) as $measure ) $output[ $measure ] = preg_match( '/^(?:auto|\d+(?:\.\d+)?(?:px|%|em|rem|vw|vh))$/', trim( $input[ $measure ] ?? '' ) ) ? trim( $input[ $measure ] ) : self::defaults()[ $measure ];
			foreach ( array( 'switcher_background', 'switcher_background_hover', 'switcher_text', 'switcher_text_hover', 'switcher_border_color', 'switcher_border_color_hover' ) as $color ) { $value = trim( sanitize_text_field( $input[ $color ] ?? '' ) ); $output[ $color ] = preg_match( '/^(?:#[0-9a-f]{3,8}|rgba?\([^\)]+\))$/i', $value ) ? $value : self::defaults()[ $color ]; }
			$output['switcher_animations'] = empty( $input['switcher_animations'] ) ? 0 : 1;
			foreach ( array( 'label_mode' => array( 'flag_name', 'flag_code', 'name', 'code', 'flags' ), 'type' => array( 'dropdown', 'inline' ), 'flag_position' => array( 'before', 'after', 'hidden' ), 'flag_shape' => array( 'rectangle', 'square', 'round' ) ) as $field => $values ) { $key = 'menu_switcher_' . $field; $output[$key] = in_array( $input[$key] ?? '', $values, true ) ? $input[$key] : $output[$key]; }
			foreach ( array( 'width', 'padding_top', 'padding_right', 'padding_bottom', 'padding_left', 'border_width', 'border_radius' ) as $field ) { $key = 'menu_switcher_' . $field; $value = trim( (string) ( $input[$key] ?? '' ) ); if ( preg_match( '/^(?:auto|\d+(?:\.\d+)?(?:px|%|em|rem|vw|vh))$/', $value ) ) $output[$key] = $value; }
			foreach ( array( 'container_background', 'background', 'background_hover', 'text', 'text_hover', 'border_color', 'border_color_hover' ) as $field ) { $key = 'menu_switcher_' . $field; $value = trim( sanitize_text_field( $input[$key] ?? '' ) ); if ( preg_match( '/^(?:#[0-9a-f]{3,8}|rgba?\([^\)]+\))$/i', $value ) ) $output[$key] = $value; }
			$output['menu_switcher_animations'] = empty( $input['menu_switcher_animations'] ) ? 0 : 1;
			return $output;
		}
		if ( 'glossary' === $section ) { update_option( PandaTranslate_Features::GLOSSARY_OPTION, PandaTranslate_Features::sanitize_glossary( $input['glossary'] ?? array() ), false ); return $output; }
		if ( 'automation' === $section ) { $api_id = sanitize_key( $input['auto_provider'] ?? '' ); $output['auto_provider'] = isset( PandaTranslate_Providers::choices()[$api_id] ) ? $api_id : $output['auto_provider']; $public = array_keys( get_post_types( array( 'public' => true ), 'names' ) ); $output['auto_enabled'] = empty( $input['auto_enabled'] ) ? 0 : 1; $output['auto_post_types'] = array_values( array_intersect( $public, array_map( 'sanitize_key', (array) ( $input['auto_post_types'] ?? array() ) ) ) ); $output['auto_publish'] = empty( $input['auto_publish'] ) ? 0 : 1; return $output; }
		if ( ! empty( $input['languages'] ) ) {
			
			$chosen_source = $output['source_language']; if ( ! empty( $input['adopt_wordpress_source'] ) && class_exists( 'PandaTranslate_Source_Language' ) ) $chosen_source = PandaTranslate_Source_Language::wordpress_code( self::configured_language_rows() ); $input_rows = $input['languages'];
            if ( ! in_array( $chosen_source, array_column( $input_rows, 'code' ), true ) ) $input_rows[] = array( 'code' => $chosen_source, 'active' => 1 );
            $rows = PandaTranslate_Features::normalize_languages( $input_rows, $chosen_source );
            if ( count( $rows ) > 2 && class_exists( 'ContactPanda_License', false ) && ! ContactPanda_License::allows( 'pandatranslate-multilingual', 'write' ) && array_diff( array_column( $rows, 'code' ), array_column( self::configured_language_rows(), 'code' ) ) ) { add_settings_error( self::OPTION, 'panda_license_language', ContactPanda_License::operation_error()->get_error_message() ); return $output; }
            foreach ( $rows as &$source_row ) if ( $source_row['code'] === $chosen_source ) $source_row['active'] = 1; unset( $source_row );
			foreach ( $rows as $i => $row ) if ( $row['code'] === $chosen_source && 0 !== $i ) { unset( $rows[ $i ] ); array_unshift( $rows, $row ); $rows = array_values( $rows ); break; }
			$output['languages'] = $rows; $output['source_language'] = $rows[0]['code']; $targets = array(); foreach ( array_slice( $rows, 1 ) as $row ) if ( ! empty( $row['active'] ) ) $targets[] = $row['code']; $output['target_languages'] = implode( ',', $targets ); $output['default_target_language'] = $targets[0] ?? $rows[0]['code']; $output['show_admin_bar'] = empty( $input['show_admin_bar'] ) ? 0 : 1; return $output;
		}
		$languages = preg_split( '/[\s,;]+/', (string) ( $input['target_languages'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
		$languages = array_values( array_unique( array_filter( array_map( function ( $language ) { return $this->sanitize_language( $language, '' ); }, $languages ) ) ) );
		if ( ! $languages ) $languages = array( $defaults['default_target_language'] );
		$output['target_languages'] = implode( ',', $languages );
		$output['default_target_language'] = $this->sanitize_language( $input['default_target_language'] ?? '', $languages[0] );
		if ( ! in_array( $output['default_target_language'], $languages, true ) ) $output['default_target_language'] = $languages[0];
		$output['show_admin_bar'] = empty( $input['show_admin_bar'] ) ? 0 : 1;
		return $output;
	}

	private function sanitize_language( $language, $fallback = '' ) {
		$language = strtolower( str_replace( '_', '-', sanitize_text_field( (string) $language ) ) );
		return preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8}){0,3}$/', $language ) ? $language : $fallback;
	}

	public function stored_google_api_key( $fallback ) {
		$s = $this->settings();
		if ( empty( $s['google_api_key_encrypted'] ) ) return $fallback;
		$decrypted = $this->decrypt_secret( $s['google_api_key_encrypted'] );
		return is_wp_error( $decrypted ) ? $fallback : $decrypted;
	}

	private function encryption_key() { return hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . '|' . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' ), true ); }

	private function encrypt_secret( $secret ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) return new WP_Error( 'pandatranslate_encryption', __( 'WordPress security keys and OpenSSL are required to store the API key safely.', 'pandatranslate' ) );
		$iv = random_bytes( 12 ); $tag = ''; $ciphertext = openssl_encrypt( $secret, 'aes-256-gcm', $this->encryption_key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $ciphertext ) return new WP_Error( 'pandatranslate_encryption', __( 'The API key could not be encrypted.', 'pandatranslate' ) );
		return base64_encode( wp_json_encode( array( 'v' => 1, 'iv' => base64_encode( $iv ), 'tag' => base64_encode( $tag ), 'data' => base64_encode( $ciphertext ) ) ) );
	}

	private function decrypt_secret( $payload ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) return new WP_Error( 'pandatranslate_decryption', __( 'OpenSSL is required to read the stored API key.', 'pandatranslate' ) );
		$data = json_decode( base64_decode( (string) $payload, true ), true );
		if ( ! is_array( $data ) || empty( $data['iv'] ) || empty( $data['tag'] ) || empty( $data['data'] ) ) return new WP_Error( 'pandatranslate_decryption', __( 'The stored API key is invalid.', 'pandatranslate' ) );
		$secret = openssl_decrypt( base64_decode( $data['data'], true ), 'aes-256-gcm', $this->encryption_key(), OPENSSL_RAW_DATA, base64_decode( $data['iv'], true ), base64_decode( $data['tag'], true ) );
		return false === $secret ? new WP_Error( 'pandatranslate_decryption', __( 'The stored API key could not be decrypted.', 'pandatranslate' ) ) : $secret;
	}

	public function admin_menu() {
        add_menu_page( 'Panda Translate', 'Panda Translate', 'manage_options', 'pandatranslate', array( $this, 'settings_page' ), PANDATRANSLATE_URL . 'assets/images/panda-admin-icon.png', 58 );
        foreach ( array( 'pandatranslate' => __( 'Dashboard', 'pandatranslate' ), 'pandatranslate-translation' => __( 'Translation', 'pandatranslate' ), 'pandatranslate-glossary' => __( 'Glossary', 'pandatranslate' ), 'pandatranslate-settings' => __( 'Settings', 'pandatranslate' ) ) as $slug => $label ) add_submenu_page( 'pandatranslate', $label, $label, 'manage_options', $slug, array( $this, 'settings_page' ) );
    }

	public function order_admin_submenus() {
		global $submenu;
		$items = $submenu['pandatranslate'] ?? array();
		if ( ! $items ) return;

		$order = array( 'pandatranslate', 'pandatranslate-translation', 'pandatranslate-glossary', 'pandatranslate-url-bases', 'pandatranslate-texts', '', 'pandatranslate-settings' );
		$groups = array_fill_keys( $order, array() );
		foreach ( $items as $item ) {
			$slug = isset( $groups[ $item[2] ] ) ? $item[2] : '';
			$groups[ $slug ][] = $item;
		}
		$submenu['pandatranslate'] = array_merge( ...array_values( $groups ) );
	}

	public function order_admin_menu( $menu ) {
		$family = array_intersect( array( 'panda-seasons-dashboard', 'pandatranslate', 'pandamercury' ), $menu );
		$anchor = 'rank-math';
		if ( ! in_array( $anchor, $menu, true ) ) {
			$anchors = array_intersect( array( 'separator-woocommerce', 'woocommerce', 'separator2' ), $menu );
			$anchor = reset( $anchors );
		}
		if ( $family && $anchor ) {
			$menu = array_values( array_diff( $menu, $family ) );
			$position = array_search( $anchor, $menu, true ) + ( 'rank-math' === $anchor ? 1 : 0 );
			array_splice( $menu, $position, 0, $family );
		}

		if ( in_array( 'woocommerce', $menu, true ) && in_array( 'pandawoo', $menu, true ) ) {
			$menu = array_values( array_diff( $menu, array( 'pandawoo' ) ) );
			$woo_items = array_intersect( $menu, array( 'woocommerce', 'edit.php?post_type=product', 'wc-reports', 'woocommerce-analytics', 'wc-admin&path=/analytics/overview', 'woocommerce-marketing', 'woocommerce-marketplace' ) );
			array_splice( $menu, array_key_last( $woo_items ) + 1, 0, array( 'pandawoo' ) );
		}
		return $menu;
	}

	public function admin_assets( $hook ) {
		if ( 'nav-menus.php' === $hook ) { wp_enqueue_style( 'pandatranslate-family-actions', PANDATRANSLATE_URL . 'assets/contactpanda-actions.css', array(), PANDATRANSLATE_VERSION ); return; }
		if ( false === strpos( $hook, 'pandatranslate' ) ) return;
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_media();
		PandaTranslate_Family_UI::admin();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'pandatranslate-admin', PANDATRANSLATE_URL . 'assets/admin.css', array(), PANDATRANSLATE_VERSION );
		wp_enqueue_style( 'pandatranslate-admin-features', PANDATRANSLATE_URL . 'assets/admin-features.css', array( 'pandatranslate-admin' ), PANDATRANSLATE_VERSION );
		wp_enqueue_style( 'pandatranslate-admin-fixes', PANDATRANSLATE_URL . 'assets/admin-fixes.css', array( 'pandatranslate-admin-features' ), PANDATRANSLATE_VERSION );
		wp_enqueue_style( 'pandatranslate-admin-053', PANDATRANSLATE_URL . 'assets/admin-053.css', array( 'pandatranslate-admin-050' ), PANDATRANSLATE_VERSION );
		wp_enqueue_style( 'pandatranslate-admin-050', PANDATRANSLATE_URL . 'assets/admin-050.css', array( 'pandatranslate-admin-045' ), PANDATRANSLATE_VERSION );
		wp_enqueue_style( 'pandatranslate-admin-045', PANDATRANSLATE_URL . 'assets/admin-045.css', array( 'pandatranslate-admin-fixes' ), PANDATRANSLATE_VERSION );
		wp_enqueue_script( 'pandatranslate-glossary', PANDATRANSLATE_URL . 'assets/glossary.js', array( 'pandatranslate-admin' ), PANDATRANSLATE_VERSION, true );
		wp_enqueue_script( 'pandatranslate-admin', PANDATRANSLATE_URL . 'assets/admin.js', array( 'contactpanda-admin-ui' ), PANDATRANSLATE_VERSION, true );
		$this->enqueue_switcher_assets();
		wp_enqueue_script( 'pandatranslate-switcher-preview', PANDATRANSLATE_URL . 'assets/switcher-preview.js', array( 'pandatranslate-admin', 'pandatranslate-switcher' ), PANDATRANSLATE_VERSION, true );
		wp_localize_script( 'pandatranslate-admin', 'PandaTranslateAdmin', array( 'glossaryUrl' => rest_url( 'pandatranslate/v1/glossary' ), 'defaultApi' => PandaTranslate_Providers::default_id(), 'noApi' => __( 'No translation API configured', 'pandatranslate' ), 'requestFailed' => __( 'Request failed.', 'pandatranslate' ), 'flagMediaTitle' => __( 'Select flag image', 'pandatranslate' ), 'flagMediaButton' => __( 'Use flag image', 'pandatranslate' ), 'testing' => __( 'Testing…', 'pandatranslate' ), 'connected' => __( 'Connection successful', 'pandatranslate' ), 'testFailed' => __( 'Connection test failed.', 'pandatranslate' ), 'batchSummary' => /* translators: %1$d: translated items, %2$d: failed items. */
		__( '%1$d translated · %2$d failed', 'pandatranslate' ), 'cancelLabel' => __( 'Cancel translation', 'pandatranslate' ), 'cancelling' => __( 'Stopping after the current request…', 'pandatranslate' ), 'cancelled' => __( 'Translation cancelled. Saved translations are retained.', 'pandatranslate' ), 'cancelFailed' => __( 'Cancellation could not be confirmed.', 'pandatranslate' ), 'runLabel' => /* translators: %s: selected translation provider label. */
		__( 'Translate entire website with %s', 'pandatranslate' ), 'glossary' => array( 'view' => __( 'View entry', 'pandatranslate' ), 'edit' => __( 'Edit entry', 'pandatranslate' ), 'remove' => __( 'Delete entry', 'pandatranslate' ), 'create' => __( 'Create glossary entry', 'pandatranslate' ), 'empty' => __( 'No glossary entries.', 'pandatranslate' ), 'active' => __( 'Active', 'pandatranslate' ), 'inactive' => __( 'Inactive', 'pandatranslate' ), 'failed' => __( 'The entry could not be saved.', 'pandatranslate' ), 'confirmDelete' => __( 'Delete this glossary entry?', 'pandatranslate' ) ), 'deeplTestUrl' => rest_url( 'pandatranslate/v1/deepl/test' ), 'testUrl' => esc_url_raw( rest_url( 'pandatranslate/v1/google/test' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'selectLanguage' => __( 'Select language', 'pandatranslate' ), 'option' => self::OPTION, 'catalog' => PandaTranslate_Features::catalog(), 'migration' => array( 'running' => __( 'Importing TranslatePress translations…', 'pandatranslate' ), 'complete' => __( 'Migration completed.', 'pandatranslate' ), 'failed' => __( 'Migration interrupted. Restarting is safe.', 'pandatranslate' ) ) ) );
	}

	public function plugin_action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=pandatranslate-settings' ) ) . '">' . esc_html__( 'Settings', 'pandatranslate' ) . '</a>' );
		return $links;
	}

	public function admin_bar_menu( WP_Admin_Bar $bar ) {
		if ( ! ( current_user_can( 'edit_posts' ) || current_user_can( 'pandatranslate_translate' ) ) || ! is_admin_bar_showing() ) return;
		$settings = $this->settings();
		if ( empty( $settings['show_admin_bar'] ) ) return;
		$base = is_admin() ? home_url( '/' ) : remove_query_arg( array( 'pandatranslate', 'pt_lang', '_pt_editor_nonce' ) );
		$language = ( is_admin() ? '' : $this->target_language() ) ?: $settings['default_target_language'];
		$url = wp_nonce_url( add_query_arg( array( 'pandatranslate' => 'edit', 'pt_lang' => $language ), $base ), 'pandatranslate_editor', '_pt_editor_nonce' );
		$bar->add_node( array( 'id' => 'pandatranslate', 'title' => '<span class="ab-icon dashicons-translation" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Translate page', 'pandatranslate' ) . '</span>', 'href' => esc_url( $url ), 'meta' => array( 'class' => 'pandatranslate-admin-bar' ) ) );
		$bar->add_node( array( 'parent' => 'pandatranslate', 'id' => 'pandatranslate-settings', 'title' => esc_html__( 'Settings', 'pandatranslate' ), 'href' => admin_url( 'admin.php?page=pandatranslate-settings' ) ) );
	}

	/** Explain where an editor-only fatal occurred, without exposing server paths or error contents. */
	public function editor_error_message( $message, $error ) {
		if ( ! current_user_can( 'manage_options' ) || ! self::editing_request() || empty( $error['file'] ) ) return $message;
		return $message . '<p><code>' . esc_html( basename( $error['file'] ) . ':' . absint( $error['line'] ?? 0 ) ) . '</code></p>';
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = $this->settings();
		if ( ! empty( $s['google_api_key_encrypted'] ) && empty( $s['google_api_key_last4'] ) ) { $legacy_key = $this->decrypt_secret( $s['google_api_key_encrypted'] ); if ( ! is_wp_error( $legacy_key ) && '' !== $legacy_key ) { $s['google_api_key_last4'] = substr( $legacy_key, -4 ); update_option( self::OPTION, $s, false ); } }
		$has_translatepress = class_exists( 'PandaTranslate_Migration_Sources' ) ? ! empty( PandaTranslate_Migration_Sources::available() ) : ! empty( PandaTranslate_Migration::tables() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value.
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? 'pandatranslate' ) );
		$defaults = array( 'pandatranslate' => 'general', 'pandatranslate-translation' => 'automation', 'pandatranslate-glossary' => 'glossary', 'pandatranslate-settings' => 'preferences' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( $defaults[$page] ?? 'general' );
		$allowed_tabs = array( 'general', 'preferences', 'switcher', 'google', 'automation', 'glossary', 'mcp', 'advanced', 'woocommerce' ); if ( $has_translatepress ) $allowed_tabs[] = 'migration';
		if ( 'mcp' === $tab ) $tab = 'advanced';
		if ( 'license' === $tab || ( 'general' === $tab && 'pandatranslate-settings' === $page ) ) $tab = 'preferences';
		if ( ! in_array( $tab, $allowed_tabs, true ) ) $tab = 'general';
		$editor_url = wp_nonce_url( add_query_arg( array( 'pandatranslate' => 'edit', 'pt_lang' => $s['default_target_language'] ), home_url( '/' ) ), 'pandatranslate_editor', '_pt_editor_nonce' );
		$system_checks = $this->system_checks();
		$critical_issues = array_filter( $system_checks, function ( $check ) { return 'error' === $check['status']; } );
		?>
		<div class="wrap contactpanda-wrap pandatranslate-admin">
			<header class="pandatranslate-admin-header"><img class="pandatranslate-mascot" src="<?php echo esc_url( defined( 'PANDATRANSLATE_MULTILINGUAL_URL' ) ? PANDATRANSLATE_MULTILINGUAL_URL . 'assets/mascot.png' : PANDATRANSLATE_URL . 'assets/mascots/free.png' ); ?>" alt=""><div><h1><?php esc_html_e( 'Panda Translate', 'pandatranslate' ); ?></h1><p><?php esc_html_e( 'Configure languages and open the visual translation editor.', 'pandatranslate' ); ?></p></div><a class="contactpanda-large-action pandatranslate-action" data-contactpanda-action-mode="labelled" data-contactpanda-action="edit" href="<?php echo esc_url( $editor_url ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Open translation editor', 'pandatranslate' ); ?></a></header>
			<?php settings_errors(); ?>
			<?php if ( $critical_issues ) : ?><div class="notice notice-error pandatranslate-system-warning"><p><strong><?php esc_html_e( 'Panda Translate needs attention.', 'pandatranslate' ); ?></strong> <?php echo esc_html( implode( ' ', wp_list_pluck( $critical_issues, 'message' ) ) ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=pandatranslate-settings&tab=advanced' ) ); ?>"><?php esc_html_e( 'Open system status', 'pandatranslate' ); ?></a></p></div><?php endif; ?>
            <?php if ( ! in_array( $tab, array( 'general', 'automation', 'glossary' ), true ) ) : ?>
            <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Panda Translate settings', 'pandatranslate' ); ?>"><?php $navigation_tabs = array( 'preferences' => __( 'General', 'pandatranslate' ), 'switcher' => __( 'Language switcher', 'pandatranslate' ), 'google' => __( 'Translation APIs', 'pandatranslate' ), 'woocommerce' => 'WooCommerce' ); if ( $has_translatepress ) $navigation_tabs['migration'] = __( 'Migration', 'pandatranslate' ); $navigation_tabs['advanced'] = __( 'System status', 'pandatranslate' ); foreach ( $navigation_tabs as $tab_key => $tab_label ) : ?><a class="nav-tab <?php echo $tab_key === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=pandatranslate-settings&tab=' . $tab_key ) ); ?>"><?php echo esc_html( $tab_label ); ?></a><?php endforeach; ?></nav>
            <?php endif; ?>
            <?php if ( class_exists( 'PandaTranslate_Edition' ) ) PandaTranslate_Edition::promotion(); ?>

			<div class="pandatranslate-grid">
				<?php if ( 'general' === $tab ) $this->render_dashboard_overview( $s ); ?>
				<?php if ( in_array( $tab, array( 'preferences', 'woocommerce', 'google' ), true ) ) : ?><div class="pandatranslate-card"><?php else : ?><form method="post" action="<?php echo esc_url( 'migration' === $tab ? admin_url( 'admin-post.php' ) : 'options.php' ); ?>" class="pandatranslate-card"><?php endif; ?>
					<?php if ( 'migration' === $tab ) : ?><input type="hidden" name="action" value="pandatranslate_import_translatepress"><?php wp_nonce_field( 'pandatranslate_import_translatepress' ); ?><?php elseif ( ! in_array( $tab, array( 'preferences', 'woocommerce', 'google' ), true ) ) : settings_fields( 'pandatranslate' ); endif; ?>
					<?php if ( 'google' !== $tab ) : ?><input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_section]" value="<?php echo esc_attr( $tab ); ?>"><?php endif; ?>
					<?php if ( 'preferences' === $tab ) : ?>
					<?php $this->render_general_preferences( $s ); ?>
					<?php elseif ( 'general' === $tab ) : ?>
					<h2><?php esc_html_e( 'Language settings', 'pandatranslate' ); ?></h2>
					<?php $this->render_language_settings(); ?>
					<h2><?php esc_html_e( 'Editor', 'pandatranslate' ); ?></h2>
					<label class="pandatranslate-check"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_admin_bar]" value="1" <?php checked( $s['show_admin_bar'], 1 ); ?>><span><?php esc_html_e( 'Show “Translate page” in the upper admin bar', 'pandatranslate' ); ?></span></label>
					<?php elseif ( 'switcher' === $tab ) : ?>
					<label><span><?php esc_html_e( 'Language URL format', 'pandatranslate' ); ?></span><select name="<?php echo esc_attr( self::OPTION ); ?>[url_mode]"><option value="path" <?php selected( $s['url_mode'], 'path' ); ?>><?php esc_html_e( 'Language paths (/en/…, /fr/…)', 'pandatranslate' ); ?></option><option value="query" <?php selected( $s['url_mode'], 'query' ); ?>><?php esc_html_e( 'URL parameters (?pt_lang=en)', 'pandatranslate' ); ?></option></select></label>
					<h2><?php esc_html_e( 'Language switcher', 'pandatranslate' ); ?></h2>
					<p><?php esc_html_e( 'Use the same switcher in navigation menus, as a shortcode, or as a floating control.', 'pandatranslate' ); ?></p>
					<div class="pandatranslate-switcher-columns"><section class="pandatranslate-switcher-panel"><h3><?php esc_html_e( 'Navigation menus', 'pandatranslate' ); ?></h3>
					<?php $this->render_switcher_display( $s, 'menu_switcher' ); ?>
					<?php $menus = get_registered_nav_menus(); if ( $menus ) : foreach ( $menus as $location => $description ) : ?><label class="pandatranslate-check"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[switcher_menu_locations][]" value="<?php echo esc_attr( $location ); ?>" <?php checked( in_array( $location, (array) $s['switcher_menu_locations'], true ) ); ?>><span><?php echo esc_html( $description ); ?> <code><?php echo esc_html( $location ); ?></code></span></label><?php endforeach; else : ?><p><?php esc_html_e( 'The active theme has not registered a menu location.', 'pandatranslate' ); ?></p><?php endif; ?>
					<?php $this->render_switcher_design( self::switcher_settings( 'menu' ), 'menu_switcher' ); ?>
					<h3><?php esc_html_e( 'Shortcode', 'pandatranslate' ); ?></h3><input class="large-text code" readonly value="[pandatranslate_switcher menu=&quot;1&quot;]">
					</section><section class="pandatranslate-switcher-panel"><h3><?php esc_html_e( 'Floating menu', 'pandatranslate' ); ?></h3>
					<label class="pandatranslate-check"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[switcher_floating]" value="1" <?php checked( $s['switcher_floating'], 1 ); ?>><span><?php esc_html_e( 'Show the floating language menu', 'pandatranslate' ); ?></span></label>
					<label><span><?php esc_html_e( 'Position', 'pandatranslate' ); ?></span><select name="<?php echo esc_attr( self::OPTION ); ?>[switcher_position]"><?php foreach ( array( 'bottom-right' => __( 'Bottom right', 'pandatranslate' ), 'bottom-left' => __( 'Bottom left', 'pandatranslate' ), 'top-right' => __( 'Top right', 'pandatranslate' ), 'top-left' => __( 'Top left', 'pandatranslate' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['switcher_position'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
					<?php $this->render_switcher_display( $s, 'switcher' ); ?>
					<?php $this->render_switcher_design( $s ); ?>
					</section></div>
					<?php elseif ( 'google' === $tab ) : ?>
					<?php $this->render_api_settings( $s ); ?>
					<?php elseif ( 'automation' === $tab ) : ?>
					<h2><?php esc_html_e( 'Automatic translation', 'pandatranslate' ); ?></h2><p><?php esc_html_e( 'Automatically collect and translate newly published or updated content with the configured translation API.', 'pandatranslate' ); ?></p>
					<label class="pandatranslate-check"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_enabled]" value="1" <?php checked( $s['auto_enabled'], 1 ); ?>><span><?php esc_html_e( 'Automatically translate new and updated content', 'pandatranslate' ); ?></span></label>
					<h3 id="pandatranslate-content-types-heading"><?php esc_html_e( 'Content types', 'pandatranslate' ); ?></h3>
					<div class="pandatranslate-content-types" role="group" aria-labelledby="pandatranslate-content-types-heading">
						<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) : ?>
							<label class="pandatranslate-check">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, (array) $s['auto_post_types'], true ) ); ?>>
								<span><?php echo esc_html( $post_type->labels->name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="pandatranslate-content-publishing">
						<label class="pandatranslate-check"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_publish]" value="1" aria-describedby="pandatranslate-content-publishing-help" <?php checked( $s['auto_publish'], 1 ); ?>><span><?php esc_html_e( 'Publish automatic translations immediately', 'pandatranslate' ); ?></span></label>
						<p id="pandatranslate-content-publishing-help" class="description"><?php esc_html_e( 'Turn off to save new automatic translations as drafts for review.', 'pandatranslate' ); ?></p>
					</div>
					<?php $this->render_api_selector( self::OPTION . '[auto_provider]', 'pandatranslate-run-api' ); $this->render_translation_progress(); ?>
					<section class="pandatranslate-site-actions"><h3><?php esc_html_e( 'Translate existing website', 'pandatranslate' ); ?></h3><p><?php esc_html_e( 'Queues all published content of the selected types for the selected translation API.', 'pandatranslate' ); ?></p>
					<p class="pandatranslate-run-provider"><span><?php esc_html_e( 'Translation API', 'pandatranslate' ); ?></span><strong data-api-run-label><?php echo esc_html( PandaTranslate_Providers::label( PandaTranslate_Providers::default_id() ) ); ?></strong></p><div class="pandatranslate-site-action-grid">
					<?php wp_nonce_field( 'pandatranslate_translate_site', 'pandatranslate_site_nonce' ); ?>
					<button type="submit" class="contactpanda-large-action" data-contactpanda-action="start" data-contactpanda-action-mode="labelled" data-translate-site name="action" value="pandatranslate_translate_site" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" <?php disabled( ! PandaTranslate_Providers::choices() ); ?>><span class="dashicons dashicons-controls-play"></span><span><?php esc_html_e( 'Translate entire website', 'pandatranslate' ); ?></span></button>
					<?php $this->render_all_languages_action(); ?></div>
					<div data-site-job data-nonce="<?php echo esc_attr( wp_create_nonce( 'pandatranslate_language_batch' ) ); ?>"><?php PandaTranslate_Site_Progress::render(); ?></div>
					<?php /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value. */ if ( isset( $_GET['queued'] ) ) : ?><p class="is-ok"><?php echo esc_html( sprintf( /* translators: %d: number of queued content items. */
		__( '%d content items were queued.', 'pandatranslate' ), absint( $_GET['queued'] ) ) ); ?></p><?php /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value. */ endif; ?>
					</section>
					<?php elseif ( 'glossary' === $tab ) : ?>
					<?php $this->render_glossary(); ?>
					<?php elseif ( 'migration' === $tab ) : ?>
					<?php /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value. */ if ( isset( $_GET['imported'] ) ) : ?><div class="notice notice-success inline"><p><?php echo esc_html( sprintf( /* translators: %1$d: imported translations, %2$d: skipped entries. */
		__( '%1$d translations imported; %2$d existing or invalid entries skipped.', 'pandatranslate' ), absint( $_GET['imported'] ), absint( $_GET['skipped'] ?? 0 ) ) ); ?></p></div><?php /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value. */ endif; ?><?php PandaTranslate_Migration_Sources::render(); ?>
					<?php elseif ( 'woocommerce' === $tab ) : ?>
                    <?php if ( has_action( 'pandatranslate_settings_' . $tab ) ) do_action( 'pandatranslate_settings_' . $tab ); else { echo '<h2>' . esc_html( 'license' === $tab ? __( 'License', 'pandatranslate' ) : 'WooCommerce' ) . '</h2><p>' . esc_html__( 'Available with Panda Translate Multilingual. Free requires no paid license.', 'pandatranslate' ) . '</p>'; } ?>
                    <?php else : ?>
					<h2><?php esc_html_e( 'System status', 'pandatranslate' ); ?></h2>
					<p><?php esc_html_e( 'Technical checks for translation, background processing, URLs and SEO integrations.', 'pandatranslate' ); ?></p>
					<?php foreach ( $system_checks as $check ) $this->status_row( $check['label'], $check['value'], $check['status'] ); ?>
					<div class="pandatranslate-note"><strong><?php esc_html_e( 'Troubleshooting', 'pandatranslate' ); ?></strong><p><?php esc_html_e( 'If the editor opens but no content can be selected, check the PHP DOM extension first. URL rules can be refreshed by saving WordPress permalinks once.', 'pandatranslate' ); ?></p></div>
					<?php PandaTranslate_Edition::render_update_status(); $this->render_mcp(); ?>
					<?php endif; ?>
					<?php if ( ! in_array( $tab, array( 'mcp', 'migration', 'advanced', 'glossary', 'preferences', 'woocommerce', 'google' ), true ) ) : ?><div class="pandatranslate-settings-actions"><?php if ( 'general' === $tab ) : ?><a class="contactpanda-large-action" data-contactpanda-action="start" data-contactpanda-action-mode="labelled" href="<?php echo esc_url( admin_url( 'admin.php?page=pandatranslate-translation#pandatranslate-translation-progress' ) ); ?>"><?php esc_html_e( 'Automatic translation', 'pandatranslate' ); ?></a><?php endif; ?><button type="submit" class="contactpanda-large-action pandatranslate-action" data-contactpanda-action-mode="labelled" data-contactpanda-action="save"><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e( 'Save settings', 'pandatranslate' ); ?></button></div><?php endif; ?>
				</<?php echo in_array( $tab, array( 'preferences', 'woocommerce', 'google' ), true ) ? 'div' : 'form'; ?>>
			</div>
		</div>
		<?php
	}

	private function render_general_preferences( $s ) {
		echo '<h2>' . esc_html__( 'General', 'pandatranslate' ) . '</h2>';
		if ( has_action( 'pandatranslate_settings_license' ) ) do_action( 'pandatranslate_settings_license' );
		else echo '<section><h3>' . esc_html__( 'License status', 'pandatranslate' ) . '</h3><p>' . esc_html__( 'Panda Translate — no license required.', 'pandatranslate' ) . '</p></section>';
		echo '<section class="pandatranslate-general-detection"><h3>' . esc_html__( 'Browser language detection', 'pandatranslate' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'pandatranslate' );
		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[_section]" value="preferences"><label><span>' . esc_html__( 'Visitor language behavior', 'pandatranslate' ) . '</span><select name="' . esc_attr( self::OPTION ) . '[language_suggestion]">';
		echo '<option value="0" ' . selected( empty( $s['language_suggestion'] ), true, false ) . '>' . esc_html__( 'Manual switching only', 'pandatranslate' ) . '</option>';
		echo '<option value="1" ' . selected( (int) $s['language_suggestion'], 1, false ) . '>' . esc_html__( 'Suggest the detected language', 'pandatranslate' ) . '</option><option value="2" ' . selected( (int) $s['language_suggestion'], 2, false ) . '>' . esc_html__( 'Automatically switch to the detected language', 'pandatranslate' ) . '</option></select></label>';
		echo '<label><span>' . esc_html__( 'Language detection source', 'pandatranslate' ) . '</span><select name="' . esc_attr( self::OPTION ) . '[language_detection_source]">';
		foreach ( array( 'browser' => __( 'Browser language', 'pandatranslate' ), 'country' => __( 'Country of origin', 'pandatranslate' ), 'country_browser' => __( 'Country of origin, otherwise browser language', 'pandatranslate' ) ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $s['language_detection_source'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label>';
		echo '<p class="description">' . esc_html__( 'Only active languages with published translations are used. Explicit language links and a manually selected language take priority over automatic detection.', 'pandatranslate' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Manual switching remains available in every mode. Suggestions can be accepted or dismissed; automatic switching runs once per session unless a manual language choice is saved.', 'pandatranslate' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Country detection uses server GeoIP data or an existing local WooCommerce geolocation database. Without a country match, the combined mode uses the browser language. No external geolocation service is contacted by Panda Translate.', 'pandatranslate' ) . '</p>';
		if ( ! PandaTranslate_Edition::multilingual() ) echo '<p class="description">' . esc_html__( 'Language detection requires a valid Panda Translate Multilingual license. The selection is retained until activation.', 'pandatranslate' ) . '</p>';
		echo '<div class="pandatranslate-settings-actions"><button type="submit" class="contactpanda-large-action" data-contactpanda-action="save" data-contactpanda-action-mode="labelled">' . esc_html__( 'Save settings', 'pandatranslate' ) . '</button></div></form></section>';
	}

	private function render_mcp() { ?>
					<h2><?php esc_html_e( 'MCP access', 'pandatranslate' ); ?></h2><p><?php esc_html_e( 'Standalone access uses a dedicated WordPress administrator application password. Royal MCP and WordPress Abilities transports use their own authenticated connections.', 'pandatranslate' ); ?></p>
					<?php $this->status_row( 'WordPress Abilities API', function_exists( 'wp_register_ability' ) ? __( 'Available', 'pandatranslate' ) : __( 'Missing', 'pandatranslate' ), function_exists( 'wp_register_ability' ) ? 'ok' : 'neutral' ); ?>
					<p><?php esc_html_e( 'Royal MCP, WordPress Abilities transports such as miniOrange, and the standalone MCP endpoint can be used simultaneously. Each connection retains its own access policy.', 'pandatranslate' ); ?></p>
					<?php $this->status_row( 'Royal MCP', defined( 'ROYAL_MCP_VERSION' ) ? ROYAL_MCP_VERSION : __( 'Missing', 'pandatranslate' ), defined( 'ROYAL_MCP_VERSION' ) && version_compare( ROYAL_MCP_VERSION, '1.5.11', '>=' ) ? 'ok' : 'neutral' ); ?>
					<label><span><?php esc_html_e( 'Standalone endpoint', 'pandatranslate' ); ?></span><input class="large-text code" readonly value="<?php echo esc_attr( PandaTranslate_MCP::endpoint() ); ?>"></label>
					<h3>Royal MCP</h3><p><?php esc_html_e( 'With Royal MCP 1.5.11 or later active, Panda Translate tools appear on the existing Royal connection. Royal permissions and read-only restrictions also apply.', 'pandatranslate' ); ?></p>
					<label><span><?php esc_html_e( 'Royal MCP endpoint', 'pandatranslate' ); ?></span><input class="large-text code" readonly value="<?php echo esc_attr( rest_url( 'royal-mcp/v1/mcp' ) ); ?>"></label>
					<p><?php esc_html_e( 'Read a translation before editing. Saving requires its current revision and returns the verified saved translation.', 'pandatranslate' ); ?></p>
					<div class="pandatranslate-note"><strong><?php esc_html_e( 'Available tools', 'pandatranslate' ); ?></strong><p><?php foreach ( PandaTranslate_MCP::tools() as $mcp_tool ) echo '<code>' . esc_html( $mcp_tool['name'] ) . '</code><br>'; ?></p></div>
					<p><?php esc_html_e( 'When ContactPanda is active, the same tools are also registered on its central MCP endpoint.', 'pandatranslate' ); ?></p>
					<h3><?php esc_html_e( 'Recent MCP activity', 'pandatranslate' ); ?></h3><?php $audit = PandaTranslate_MCP::audit_entries(); if ( ! $audit ) : ?><p><?php esc_html_e( 'No MCP activity has been recorded yet.', 'pandatranslate' ); ?></p><?php else : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Time', 'pandatranslate' ); ?></th><th><?php esc_html_e( 'Tool', 'pandatranslate' ); ?></th><th><?php esc_html_e( 'Result', 'pandatranslate' ); ?></th></tr></thead><tbody><?php foreach ( array_slice( $audit, 0, 20 ) as $entry ) : ?><tr><td><?php echo esc_html( $entry['time'] ?? '' ); ?></td><td><code><?php echo esc_html( $entry['tool'] ?? '' ); ?></code></td><td><?php echo esc_html( ! empty( $entry['success'] ) ? __( 'Successful', 'pandatranslate' ) : __( 'Failed', 'pandatranslate' ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>

	<?php }

	private function status_row( $label, $value, $status = 'ok' ) { echo '<div class="pandatranslate-status-row"><span>' . esc_html( $label ) . '</span><strong class="' . esc_attr( 'is-' . $status ) . '">' . esc_html( $value ) . '</strong></div>'; }

	private function render_api_settings( $s ) {
		echo '<h2>' . esc_html__( 'Translation APIs', 'pandatranslate' ) . '</h2><p>' . esc_html__( 'Multiple APIs can be enabled simultaneously. The API is selected for each translation run.', 'pandatranslate' ) . '</p>';
		echo '<div class="pandatranslate-api-settings"><div class="pandatranslate-api-grid">';
		$this->open_api_provider_form( 'google', 'Google Translate' );
		foreach ( array( 'v2', 'v3' ) as $edition ) echo '<label class="pandatranslate-check"><input type="checkbox" name="' . esc_attr( self::OPTION . '[google_' . $edition . '_enabled]' ) . '" value="1" ' . checked( $s['google_' . $edition . '_enabled'], 1, false ) . '><span>' . esc_html( sprintf( /* translators: %s: translation provider label. */
		__( 'Enable %s', 'pandatranslate' ), PandaTranslate_Providers::label( 'google-' . $edition ) ) ) . '</span></label>';
		echo '<label class="pandatranslate-api-row"><span>' . esc_html__( 'API settings', 'pandatranslate' ) . '</span><select data-google-edition name="' . esc_attr( self::OPTION . '[google_api_version]' ) . '">';
		foreach ( array( 'v2', 'v3' ) as $edition ) echo '<option value="' . esc_attr( $edition ) . '" ' . selected( $s['google_api_version'], $edition, false ) . '>' . esc_html( PandaTranslate_Providers::label( 'google-' . $edition ) ) . '</option>';
		echo '</select></label>';
		foreach ( array( 'v2', 'v3' ) as $edition ) {
			echo '<section data-google-fields="' . esc_attr( $edition ) . '"' . ( $edition !== $s['google_api_version'] ? ' hidden' : '' ) . '>';
			if ( 'v2' === $edition ) {
				echo '<div class="pandatranslate-key-field">';
				if ( $s['google_api_key_encrypted'] ) echo '<div class="pandatranslate-api-row"><strong>' . esc_html__( 'Google v2 API key', 'pandatranslate' ) . '</strong><div class="pandatranslate-key-controls"><div class="pandatranslate-key-mask"><span aria-hidden="true">•••• •••• ••••</span><code>' . esc_html( $s['google_api_key_last4'] ) . '</code></div></div></div>';
				echo '<label class="pandatranslate-api-row"><span>' . esc_html( $s['google_api_key_encrypted'] ? __( 'Enter or replace API key', 'pandatranslate' ) : __( 'Google v2 API key', 'pandatranslate' ) ) . '</span><input type="password" autocomplete="new-password" name="' . esc_attr( self::OPTION . '[google_api_key]' ) . '" aria-describedby="pandatranslate-key-help"></label><small id="pandatranslate-key-help" class="pandatranslate-api-help">' . esc_html__( 'Stored encrypted. Only the last four characters are shown.', 'pandatranslate' ) . '</small></div>';
				if ( $s['google_api_key_encrypted'] ) echo '<label class="pandatranslate-check"><input type="checkbox" name="' . esc_attr( self::OPTION . '[google_api_key_remove]' ) . '" value="1"><span>' . esc_html__( 'Delete stored API key', 'pandatranslate' ) . '</span></label>';
			} else {
				echo '<label><span>' . esc_html__( 'Google Cloud project ID', 'pandatranslate' ) . '</span><input data-google-project name="' . esc_attr( self::OPTION . '[google_project_id]' ) . '" value="' . esc_attr( $s['google_project_id'] ) . '"></label><label><span>' . esc_html__( 'API location', 'pandatranslate' ) . '</span><input data-google-location name="' . esc_attr( self::OPTION . '[google_location]' ) . '" value="' . esc_attr( $s['google_location'] ) . '"></label>';
				echo '<div class="pandatranslate-note"><strong>' . esc_html__( 'Authentication', 'pandatranslate' ) . '</strong><p><code>define( \'PANDATRANSLATE_GOOGLE_CREDENTIALS_FILE\', \'/secure/path/service-account.json\' );</code></p><p>' . esc_html__( 'Place this in wp-config.php. The JSON file should be outside the public web root and readable only by the web server.', 'pandatranslate' ) . '</p></div>';
			}
			$test = $s['google_tests'][$edition] ?? ( $edition === $s['google_api_version'] ? array( 'status' => $s['google_key_status'], 'time' => $s['google_key_tested_at'], 'error' => $s['google_key_last_error'] ) : array() );
			$this->status_row( __( 'Last API test', 'pandatranslate' ), ( 'valid' === ( $test['status'] ?? '' ) ? __( 'Works', 'pandatranslate' ) : ( 'invalid' === ( $test['status'] ?? '' ) ? __( 'Failed', 'pandatranslate' ) : __( 'Not tested', 'pandatranslate' ) ) ) . ( empty( $test['time'] ) ? '' : ' · ' . $test['time'] ), 'valid' === ( $test['status'] ?? '' ) ? 'ok' : 'neutral' );
			$provider = new PandaTranslate_Google_Provider( $s['google_project_id'], $s['google_location'], $edition ); $ready = $provider->configured();
			$this->status_row( __( 'Configuration status', 'pandatranslate' ), is_wp_error( $ready ) ? $ready->get_error_message() : __( 'Ready', 'pandatranslate' ), is_wp_error( $ready ) ? 'error' : 'ok' );
			echo '<button type="button" class="contactpanda-large-action" data-contactpanda-action="test" data-contactpanda-action-mode="labelled" data-google-test="' . esc_attr( $edition ) . '"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>' . esc_html( 'v2' === $edition ? __( 'Test API key', 'pandatranslate' ) : __( 'Run connection test', 'pandatranslate' ) ) . '</button><p data-google-result role="status">' . esc_html( $test['error'] ?? '' ) . '</p></section>';
		}
		$this->close_api_provider_form();
		$this->open_api_provider_form( 'deepl', 'DeepL' );
		echo '<label class="pandatranslate-check"><input type="checkbox" name="' . esc_attr( self::OPTION . '[deepl_enabled]' ) . '" value="1" ' . checked( $s['deepl_enabled'], 1, false ) . '>' . esc_html__( 'Enable DeepL', 'pandatranslate' ) . '</label><label class="pandatranslate-api-row"><span>' . esc_html__( 'DeepL API plan', 'pandatranslate' ) . '</span><select name="' . esc_attr( self::OPTION . '[deepl_endpoint]' ) . '"><option value="free" ' . selected( $s['deepl_endpoint'], 'free', false ) . '>DeepL API Free</option><option value="pro" ' . selected( $s['deepl_endpoint'], 'pro', false ) . '>DeepL API Pro</option></select></label>';
		if ( $s['deepl_key_encrypted'] ) echo '<div class="pandatranslate-key-mask">•••• •••• <code>' . esc_html( $s['deepl_key_last4'] ) . '</code></div>';
		echo '<label class="pandatranslate-api-row"><span>' . esc_html__( 'Enter or replace DeepL API key', 'pandatranslate' ) . '</span><input type="password" autocomplete="new-password" name="' . esc_attr( self::OPTION . '[deepl_key]' ) . '"></label><label class="pandatranslate-check"><input type="checkbox" name="' . esc_attr( self::OPTION . '[deepl_key_remove]' ) . '" value="1">' . esc_html__( 'Delete stored DeepL API key', 'pandatranslate' ) . '</label>';
		$test = $s['deepl_test'] ?? array();
		echo '<p>' . esc_html__( 'Last key test', 'pandatranslate' ) . ': <strong>' . esc_html( empty( $test ) ? __( 'Not tested', 'pandatranslate' ) : ( 'valid' === $test['status'] ? __( 'Works', 'pandatranslate' ) : __( 'Failed', 'pandatranslate' ) ) . ' · ' . ( $test['time'] ?? '' ) ) . '</strong></p><button type="button" class="contactpanda-large-action" data-contactpanda-action="test" data-contactpanda-action-mode="labelled" data-deepl-test>' . esc_html__( 'Test API key', 'pandatranslate' ) . '</button><p data-deepl-result role="status">' . esc_html( $test['error'] ?? '' ) . '</p>';
		$this->close_api_provider_form();
		$usage = $this->provider_usage();
		echo '</div></div><h3>' . esc_html__( 'Translation usage', 'pandatranslate' ) . '</h3>';
		$this->status_row( __( 'Characters sent to translation APIs', 'pandatranslate' ), number_format_i18n( $usage['characters'] ) );
		$this->status_row( __( 'Words (Panda Translate count)', 'pandatranslate' ), number_format_i18n( $usage['words'] ) );
	}

	private function open_api_provider_form( $provider, $label ) {
		$id = 'pandatranslate-' . $provider . '-provider';
		echo '<form class="pandatranslate-api-provider" method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" aria-labelledby="' . esc_attr( $id ) . '">';
		settings_fields( 'pandatranslate' );
		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[_section]" value="google">';
		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[_api_provider]" value="' . esc_attr( $provider ) . '">';
		echo '<h3 id="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</h3>';
	}

	private function close_api_provider_form() {
		echo '<div class="pandatranslate-settings-actions"><button type="submit" class="contactpanda-large-action" data-contactpanda-action="save" data-contactpanda-action-mode="labelled">' . esc_html__( 'Save settings', 'pandatranslate' ) . '</button></div></form>';
	}

	private function render_api_selector( $name = '', $id = '' ) {
		$choices = PandaTranslate_Providers::choices(); $selected = PandaTranslate_Providers::default_id();
		echo '<label class="pandatranslate-api-choice"><span>' . esc_html__( 'Translation API', 'pandatranslate' ) . '</span><select data-translation-api' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ( $name ? ' name="' . esc_attr( $name ) . '"' : '' ) . ( ! $choices ? ' disabled' : '' ) . '>';
		if ( ! $choices ) echo '<option value="">' . esc_html__( 'No translation API configured', 'pandatranslate' ) . '</option>';
		foreach ( $choices as $key => $label ) echo '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, $key, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label>';
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only schema existence check against the fixed plugin-owned table, bound through wpdb; schema changes must be visible immediately.
	private function table_exists() { global $wpdb; $table = PandaTranslate_Repository::table(); return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); }

	private function system_checks() {
		$settings = $this->settings();
		$dom = class_exists( 'DOMDocument' );
		$rest = function_exists( 'rest_url' ) && did_action( 'rest_api_init' ) >= 0;
		$table = $this->table_exists();
		$cron = ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON;
		$rules = (array) get_option( 'rewrite_rules', array() );
		$rewrites = (bool) array_filter( $rules, function ( $target ) { return false !== strpos( $target, 'pt_lang=' ); } );
		$google = PandaTranslate_Providers::get();
		$google_enabled = ! empty( $settings['google_v2_enabled'] ) || ! empty( $settings['google_v3_enabled'] ) || (bool) PandaTranslate_Providers::choices();
		$seo = array();
		if ( defined( 'RANK_MATH_VERSION' ) ) $seo[] = 'Rank Math';
		if ( defined( 'WPSEO_VERSION' ) ) $seo[] = 'Yoast SEO';
		return array(
			array( 'label' => __( 'PHP DOM extension', 'pandatranslate' ), 'value' => $dom ? __( 'Available', 'pandatranslate' ) : __( 'Missing', 'pandatranslate' ), 'status' => $dom ? 'ok' : 'error', 'message' => __( 'The PHP DOM extension is missing.', 'pandatranslate' ) ),
			array( 'label' => __( 'REST API', 'pandatranslate' ), 'value' => $rest ? __( 'Available', 'pandatranslate' ) : __( 'Missing', 'pandatranslate' ), 'status' => $rest ? 'ok' : 'error', 'message' => __( 'The WordPress REST API is unavailable.', 'pandatranslate' ) ),
			array( 'label' => __( 'Translation table', 'pandatranslate' ), 'value' => $table ? __( 'Available', 'pandatranslate' ) : __( 'Missing', 'pandatranslate' ), 'status' => $table ? 'ok' : 'error', 'message' => __( 'The translation database table is missing.', 'pandatranslate' ) ),
			array( 'label' => __( 'Google Translation API', 'pandatranslate' ), 'value' => ! $google_enabled ? __( 'Not enabled', 'pandatranslate' ) : ( is_wp_error( $google ) ? __( 'Configuration incomplete', 'pandatranslate' ) : __( 'Ready', 'pandatranslate' ) ), 'status' => ! $google_enabled ? 'neutral' : ( is_wp_error( $google ) ? 'error' : 'ok' ), 'message' => $google_enabled && is_wp_error( $google ) ? __( 'The Google Translation API configuration is incomplete.', 'pandatranslate' ) : '' ),
			array( 'label' => __( 'WordPress Cron', 'pandatranslate' ), 'value' => $cron ? __( 'Available', 'pandatranslate' ) : __( 'Disabled or externally controlled', 'pandatranslate' ), 'status' => $cron ? 'ok' : 'warning', 'message' => '' ),
			array( 'label' => __( 'Language URL rules', 'pandatranslate' ), 'value' => $rewrites ? __( 'Available', 'pandatranslate' ) : __( 'Refresh recommended', 'pandatranslate' ), 'status' => $rewrites ? 'ok' : 'warning', 'message' => '' ),
			array( 'label' => __( 'SEO integration', 'pandatranslate' ), 'value' => $seo ? implode( ', ', $seo ) : __( 'WordPress metadata', 'pandatranslate' ), 'status' => 'ok', 'message' => '' ),
			array( 'label' => __( 'Plugin version', 'pandatranslate' ), 'value' => PANDATRANSLATE_VERSION, 'status' => 'neutral', 'message' => '' ),
		);
	}

	private function render_dashboard_overview( $settings ) {
		$types = array_replace( array( 'page' => null, 'post' => null, 'product' => null ), get_post_types( array( 'public' => true ), 'objects' ) );
		unset( $types['attachment'] );
		$enabled_apis = wp_list_pluck( wp_list_filter( PandaTranslate_Providers::all(), array( 'enabled' => true ) ), 'label' );
		if ( isset( $enabled_apis['deepl'] ) ) {
			$enabled_apis['deepl'] = 'pro' === $settings['deepl_endpoint'] ? 'DeepL API Pro (v2)' : 'DeepL API Free (v2)';
		}
		?>
		<section class="pandatranslate-card pandatranslate-dashboard-overview" aria-labelledby="pandatranslate-overview-title">
			<h2 id="pandatranslate-overview-title"><?php esc_html_e( 'Website overview', 'pandatranslate' ); ?></h2>
			<h3><?php esc_html_e( 'Published content', 'pandatranslate' ); ?></h3>
			<dl class="pandatranslate-dashboard-metrics">
				<?php foreach ( array_filter( $types ) as $type ) : ?>
					<div><dt><?php echo esc_html( $type->labels->name ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) ( wp_count_posts( $type->name )->publish ?? 0 ) ) ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
			<h3><?php esc_html_e( 'Translation', 'pandatranslate' ); ?></h3>
			<dl class="pandatranslate-dashboard-metrics">
				<div><dt><?php esc_html_e( 'Recorded source characters', 'pandatranslate' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $this->repository->source_character_total() ) ); ?></dd></div>
				<div>
					<dt><?php esc_html_e( 'Enabled translation APIs', 'pandatranslate' ); ?></dt>
					<dd>
						<span><?php echo esc_html( number_format_i18n( count( $enabled_apis ) ) ); ?></span>
						<?php if ( $enabled_apis ) : ?>
							<ul class="pandatranslate-dashboard-apis">
								<?php foreach ( $enabled_apis as $label ) : ?><li><?php echo esc_html( $label ); ?></li><?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</dd>
				</div>
			</dl>
		</section>
		<?php
	}

	private function render_language_settings() {
		echo '<p hidden role="status" data-language-limit-message>' . esc_html__( 'Free includes the source language and one translation language. Existing additional language data remains stored.', 'pandatranslate' ) . '</p>';
		$limited = class_exists( 'PandaTranslate_Edition' ) && ! PandaTranslate_Edition::multilingual();
		if ( $limited ) {
			echo '<p class="description pandatranslate-language-notice" role="status" data-language-activation-feedback data-message="' . esc_attr__( 'Deactivate the current translation language before activating another, or activate Panda Translate Multilingual to use multiple translation languages.', 'pandatranslate' ) . '"></p>';
		}
		$rows = self::language_rows(); $catalog = PandaTranslate_Features::catalog(); $stats = $this->repository->language_stats();
        $wp_source = class_exists( 'PandaTranslate_Source_Language' ) ? PandaTranslate_Source_Language::wordpress_code( $rows ) : $rows[0]['code'];
        if ( class_exists( 'PandaTranslate_Source_Language' ) && PandaTranslate_Source_Language::mismatch( $rows ) ) echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The stored translation source differs from the WordPress website language. Changing it affects language assignments and URLs. Existing translations are retained and are not reversed automatically.', 'pandatranslate' ) . '</p><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[adopt_wordpress_source]" value="1"> ' . esc_html__( 'Confirm adoption of the WordPress website language as the translation source.', 'pandatranslate' ) . '</label></div>';

		echo '<div class="pandatranslate-languages-scroll"><table class="pandatranslate-languages"><colgroup><col style="width:42px"><col style="width:60px"><col style="width:96px"><col><col style="width:150px"><col class="pandatranslate-formality-column"><col style="width:84px"><col style="width:98px"><col style="width:62px"><col style="width:58px"></colgroup><thead><tr>';
		foreach ( array( '', __( 'Flag', 'pandatranslate' ), __( 'Schema', 'pandatranslate' ), __( 'Language', 'pandatranslate' ), __( 'Translation status', 'pandatranslate' ), __( 'Address style', 'pandatranslate' ), __( 'URL code', 'pandatranslate' ), __( 'Flag actions', 'pandatranslate' ), __( 'Active', 'pandatranslate' ), '' ) as $heading_index => $heading ) echo '<th scope="col">' . ( '' === $heading ? '<span class="screen-reader-text">' . esc_html( 0 === $heading_index ? __( 'Default language', 'pandatranslate' ) : __( 'Delete', 'pandatranslate' ) ) . '</span>' : esc_html( $heading ) ) . '</th>';
		echo '</tr></thead><tbody data-language-rows data-language-limit="' . ( $limited ? '2' : '0' ) . '">';
		foreach ( $rows as $index => $row ) $this->render_language_row( $index, $row, $catalog, 0 === $index, $stats[ $row['code'] ] ?? array() );
		echo '</tbody></table></div><template data-language-row-template>';
		$this->render_language_row( '__INDEX__', array( 'code' => '__CODE__', 'locale' => '__LOCALE__', 'slug' => '__CODE__', 'active' => 1, 'formality' => 'standard', 'template' => true ), $catalog, false );
		echo '</template><div class="pandatranslate-language-add"><div class="pandatranslate-combobox" data-language-combobox><input type="hidden" data-language-picker><button type="button" class="pandatranslate-combobox-trigger" data-language-trigger aria-expanded="false"><span data-language-selection>' . esc_html__( 'Select language', 'pandatranslate' ) . '</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button><div class="pandatranslate-combobox-popup" data-language-popup hidden><input type="search" data-language-search placeholder="' . esc_attr__( 'Search by code or language…', 'pandatranslate' ) . '" autocomplete="off"><div class="pandatranslate-language-options" role="listbox">';
		foreach ( $catalog as $code => $language ) echo '<button type="button" role="option" data-language-option data-code="' . esc_attr( $code ) . '" data-local-name="' . esc_attr( PandaTranslate_Features::language_name( $code, false ) ) . '" data-name="' . esc_attr( $language['name'] ) . '" data-flag="' . esc_attr( PandaTranslate_Features::flag( $code ) ) . '"><span class="pandatranslate-option-flag">' . esc_html( PandaTranslate_Features::flag( $code ) ) . '</span><code>' . esc_html( $code ) . '</code><span>' . esc_html( $language['name'] ) . '</span></button>';
		echo '</div><p class="pandatranslate-no-results" data-language-empty hidden>' . esc_html__( 'No matching language found.', 'pandatranslate' ) . '</p></div></div><button type="button" class="contactpanda-large-action pandatranslate-action" data-contactpanda-action-mode="labelled" data-contactpanda-action="add" data-add-language disabled><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__( 'Add language', 'pandatranslate' ) . '</button></div>';
	}

	private function render_language_row( $index, $row, $catalog, $source, $stat = array() ) {
		$name = $catalog[ $row['code'] ]['name'] ?? strtoupper( $row['code'] ); $base = self::OPTION . '[languages][' . $index . ']'; $flag_image = ! empty( $row['flag_id'] ) ? wp_get_attachment_image_url( $row['flag_id'], 'thumbnail' ) : '';
		$template = ! empty( $row['template'] ); $code = $row['code'];
		$native = $template ? '__NATIVE__' : PandaTranslate_Features::language_name( $code, true );
		$local = $template ? '__LOCAL__' : PandaTranslate_Features::language_name( $code, false );
		$flag = $template ? '__FLAG__' : PandaTranslate_Features::flag( $code );
		echo '<tr class="pandatranslate-language-row' . ( $source ? ' is-source-language' : '' ) . '" data-language-row><td class="pandatranslate-language-order">';
		if ( $source ) echo '<span class="pandatranslate-default-dot" role="img" aria-label="' . esc_attr__( 'Default language', 'pandatranslate' ) . '"></span>';
		else echo '<button type="button" class="pandatranslate-drag-handle" data-language-drag aria-label="' . esc_attr__( 'Reorder language', 'pandatranslate' ) . '"><span class="dashicons dashicons-menu" aria-hidden="true"></span></button>';
		echo '</td><td><span class="pandatranslate-row-flag" data-flag-preview>' . ( $flag_image ? '<img src="' . esc_url( $flag_image ) . '" alt="">' : esc_html( $flag ) ) . '</span><input type="hidden" name="' . esc_attr( $base ) . '[code]" value="' . esc_attr( $code ) . '"><input type="hidden" name="' . esc_attr( $base ) . '[flag_id]" value="' . esc_attr( $row['flag_id'] ?? 0 ) . '" data-flag-id></td>';
		echo '<td><input readonly data-language-locale aria-label="' . esc_attr__( 'Schema', 'pandatranslate' ) . '" value="' . esc_attr( $row['locale'] ) . '"></td><td><span data-language-native lang="' . esc_attr( $code ) . '">' . esc_html( $native ) . '</span><span data-language-local>' . esc_html( $local ) . '</span></td><td class="pandatranslate-progress-cell">';
		$this->render_language_progress_cell( $stat, $source ); echo '</td>';
		echo '<td><select name="' . esc_attr( $base ) . '[formality]" aria-label="' . esc_attr__( 'Address style', 'pandatranslate' ) . '">';
		foreach ( array( 'standard' => __( 'Standard', 'pandatranslate' ), 'formal' => __( 'Polite', 'pandatranslate' ), 'informal' => __( 'Informal', 'pandatranslate' ) ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $row['formality'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></td><td><input name="' . esc_attr( $base ) . '[slug]" aria-label="' . esc_attr__( 'URL code', 'pandatranslate' ) . '" value="' . esc_attr( $row['slug'] ) . '"></td><td class="pandatranslate-flag-actions"><div>';
		echo '<button type="button" class="contactpanda-list-action" data-contactpanda-action="image" data-contactpanda-action-mode="icon" data-flag-image data-default-flag="' . esc_attr( $flag ) . '" title="' . esc_attr__( 'Change flag', 'pandatranslate' ) . '" aria-label="' . esc_attr__( 'Change flag', 'pandatranslate' ) . '"></button>';
		echo '<button type="button" class="contactpanda-list-action" data-contactpanda-action="reset" data-contactpanda-action-mode="icon" data-flag-reset title="' . esc_attr__( 'Reset flag', 'pandatranslate' ) . '" aria-label="' . esc_attr__( 'Reset flag', 'pandatranslate' ) . '"></button></div></td>';
		echo '<td class="pandatranslate-language-active"><input type="checkbox" name="' . esc_attr( $base ) . '[active]" aria-label="' . esc_attr__( 'Active', 'pandatranslate' ) . '" value="1" ' . checked( $row['active'], 1, false ) . ( $source ? ' disabled' : '' ) . '>' . ( $source ? '<input type="hidden" name="' . esc_attr( $base ) . '[active]" value="1">' : '' ) . '</td><td>';
		if ( ! $source ) echo '<button type="button" class="button button-small" data-contactpanda-action="delete" data-contactpanda-action-mode="icon" data-remove-language aria-label="' . esc_attr__( 'Remove language', 'pandatranslate' ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		echo '</td></tr>';
	}
	private function render_language_progress_cell( $stat, $source ) {
		if ( $source ) { echo '<span class="pandatranslate-source-label">' . esc_html__( 'Source language', 'pandatranslate' ) . '</span>'; return; }
		$total = max( 0, (int) ( $stat['total'] ?? 0 ) );
		if ( ! $total ) { echo '<div class="pandatranslate-compact-progress"><strong>0%</strong><div class="pandatranslate-stacked-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="' . esc_attr__( 'Not translated', 'pandatranslate' ) . '"><span class="is-untranslated" style="width:100%"></span></div></div>'; return; }
		$finished = max( 0, min( $total, $total - (int) ( $stat['untranslated'] ?? 0 ) ) );
		$percent = round( $finished / $total * 100 );
		$description = sprintf( /* translators: %1$d: translated units, %2$d: total units. */
		__( '%1$d of %2$d units translated', 'pandatranslate' ), $finished, $total );
		echo '<div class="pandatranslate-compact-progress" title="' . esc_attr( $description ) . '"><strong>' . esc_html( $percent ) . '%</strong><div class="pandatranslate-stacked-progress" role="progressbar" aria-label="' . esc_attr( $description ) . '" aria-valuenow="' . esc_attr( $percent ) . '" aria-valuemin="0" aria-valuemax="100">';
		if ( $finished ) echo '<span class="is-translated" style="width:' . esc_attr( $finished / $total * 100 ) . '%"></span>';
		if ( $finished < $total ) echo '<span class="is-untranslated" style="width:' . esc_attr( ( $total - $finished ) / $total * 100 ) . '%"></span>';
		echo '</div></div>';
	}

	private function render_switcher_display( $s, $prefix ) {
		echo '<label><span>' . esc_html__( 'Language display', 'pandatranslate' ) . '</span><select name="' . esc_attr( self::OPTION . '[' . $prefix . '_label_mode]' ) . '">';
		foreach ( array( 'flags' => __( 'Flag', 'pandatranslate' ), 'flag_code' => __( 'Flag + language code', 'pandatranslate' ), 'flag_name' => __( 'Flag + language name', 'pandatranslate' ), 'code' => __( 'Language code only', 'pandatranslate' ), 'name' => __( 'Language name only', 'pandatranslate' ) ) as $mode => $label ) echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $s[$prefix . '_label_mode'], $mode, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><label class="pandatranslate-check"><input type="checkbox" name="' . esc_attr( self::OPTION . '[' . $prefix . '_native_names]' ) . '" value="1" ' . checked( $s[$prefix . '_native_names'], 1, false ) . '><span>' . esc_html__( 'Language name in its own language', 'pandatranslate' ) . '</span></label>';
	}
	private function render_switcher_design( $s, $prefix = 'switcher' ) {
		ob_start();
		echo '<div data-switcher-design data-switcher-prefix="' . esc_attr( $prefix ) . '">';
		echo '<h2>' . esc_html__( 'Switcher layout and design', 'pandatranslate' ) . '</h2>';
		if ( 'menu_switcher' === $prefix ) echo '<p>' . esc_html__( 'Existing navigation menus use the theme menu styling. These design settings apply to the standalone language menu and builder elements.', 'pandatranslate' ) . '</p><label class="contactpanda-color-field" data-colors="' . esc_attr( implode( ',', PandaTranslate_Theme_Colors::palette() ) ) . '"><span>' . esc_html__( 'Menu container background', 'pandatranslate' ) . '</span><input type="color" class="pandatranslate-color" name="' . esc_attr( self::OPTION . '[menu_switcher_container_background]' ) . '" value="#ffffff" data-alpha-enabled="true" data-contactpanda-color-value="' . esc_attr( $s['menu_switcher_container_background'] ) . '"></label>';
		if ( 'switcher' === $prefix ) {
			$samples = array(); foreach ( self::language_rows() as $sample ) if ( ! empty( $sample['active'] ) ) $samples[] = array( 'code' => $sample['code'], 'native' => PandaTranslate_Features::language_name( $sample['code'], true ), 'local' => PandaTranslate_Features::language_name( $sample['code'], false ), 'flag' => PandaTranslate_Features::flag( $sample['code'] ), 'image' => empty( $sample['flag_id'] ) ? '' : wp_get_attachment_image_url( $sample['flag_id'], 'thumbnail' ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- switcher_markup escapes every dynamic URL, attribute and label before returning the complete control.
			echo '<div class="pandatranslate-preview" data-switcher-preview data-preview-languages="' . esc_attr( wp_json_encode( $samples ) ) . '">' . $this->switcher_markup( 'floating', array( 'preview' => true ) ) . '</div>';
		}
		echo '<label><span>' . esc_html__( 'Type', 'pandatranslate' ) . '</span><select name="' . esc_attr( self::OPTION ) . '[switcher_type]"><option value="dropdown" ' . selected( $s['switcher_type'], 'dropdown', false ) . '>' . esc_html__( 'Dropdown', 'pandatranslate' ) . '</option><option value="inline" ' . selected( $s['switcher_type'], 'inline', false ) . '>' . esc_html__( 'Side by side', 'pandatranslate' ) . '</option></select></label>';
		echo '<div class="pandatranslate-paired-fields">';
		echo '<label><span>' . esc_html__( 'Flag position', 'pandatranslate' ) . '</span><select name="' . esc_attr( self::OPTION ) . '[switcher_flag_position]">'; foreach ( array( 'before' => __( 'Before language', 'pandatranslate' ), 'after' => __( 'After language', 'pandatranslate' ) ) as $v => $l ) echo '<option value="' . esc_attr( $v ) . '" ' . selected( $s['switcher_flag_position'], $v, false ) . '>' . esc_html( $l ) . '</option>'; echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Flag shape', 'pandatranslate' ) . '</span><select name="' . esc_attr( self::OPTION ) . '[switcher_flag_shape]">'; foreach ( array( 'rectangle' => 'Rectangle 4:3', 'square' => 'Square 1:1', 'round' => 'Rounded' ) as $v => $l ) echo '<option value="' . esc_attr( $v ) . '" ' . selected( $s['switcher_flag_shape'], $v, false ) . '>' . esc_html( $l ) . '</option>'; echo '</select></label>';
		echo '</div>';
		echo '<label><span>' . esc_html__( 'Width (px, %, em, rem or auto)', 'pandatranslate' ) . '</span><input name="' . esc_attr( self::OPTION . '[switcher_width]' ) . '" value="' . esc_attr( $s['switcher_width'] ) . '"></label>';
		echo '<div class="pandatranslate-paired-fields">';
		foreach ( array( 'switcher_border_width' => __( 'Border width (CSS unit)', 'pandatranslate' ), 'switcher_border_radius' => __( 'Border radius (CSS unit)', 'pandatranslate' ) ) as $key => $label ) echo '<label><span>' . esc_html( $label ) . '</span><input name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '" value="' . esc_attr( $s[$key] ) . '"></label>';
		echo '</div>';
		echo '<fieldset class="pandatranslate-padding"><legend>' . esc_html__( 'Padding (CSS units)', 'pandatranslate' ) . '</legend>'; foreach ( array( 'top' => __( 'Top', 'pandatranslate' ), 'right' => __( 'Right', 'pandatranslate' ), 'bottom' => __( 'Bottom', 'pandatranslate' ), 'left' => __( 'Left', 'pandatranslate' ) ) as $side => $label ) echo '<label><span>' . esc_html( $label ) . '</span><input name="' . esc_attr( self::OPTION . '[switcher_padding_' . $side . ']' ) . '" value="' . esc_attr( $s[ 'switcher_padding_' . $side ] ) . '"></label>'; echo '</fieldset>';
		echo '<div class="pandatranslate-color-grid">';
		foreach ( array( 'switcher_text' => __( 'Text color', 'pandatranslate' ), 'switcher_background' => __( 'Background color', 'pandatranslate' ), 'switcher_text_hover' => __( 'Hover text', 'pandatranslate' ), 'switcher_background_hover' => __( 'Hover background', 'pandatranslate' ), 'switcher_border_color' => __( 'Border color', 'pandatranslate' ), 'switcher_border_color_hover' => __( 'Border color (hover)', 'pandatranslate' ) ) as $key => $label ) echo '<label class="contactpanda-color-field" data-colors="' . esc_attr( implode( ',', PandaTranslate_Theme_Colors::palette() ) ) . '"><span>' . esc_html( $label ) . '</span><input type="color" class="pandatranslate-color" name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '" value="' . esc_attr( $s[ $key ] ) . '" data-alpha-enabled="true" data-contactpanda-color-value="' . esc_attr( $s[ $key ] ) . '"></label>';
		echo '</div>';
		echo '<label class="pandatranslate-check"><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[switcher_animations]" value="1" ' . checked( $s['switcher_animations'], 1, false ) . '><span>' . esc_html__( 'Enable animations', 'pandatranslate' ) . '</span></label>';
		echo '</div>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captured settings markup is escaped at each field; only a fixed internal field prefix is replaced.
		echo str_replace( '[switcher_', '[' . $prefix . '_', ob_get_clean() );
	}

	private function render_translation_progress() {
		echo '<div id="pandatranslate-translation-progress" data-translation-overview>';
		$stats = $this->repository->language_stats();
		$categories = array( 'migrated' => __( 'Migrated', 'pandatranslate' ), 'automatic' => __( 'Automatic', 'pandatranslate' ), 'manual' => __( 'Manual', 'pandatranslate' ), 'untranslated' => __( 'Not translated', 'pandatranslate' ) );
		echo '<h3>' . esc_html__( 'Progress by language', 'pandatranslate' ) . '</h3>';
		$targets = array_filter( array_slice( self::language_rows(), 1 ), function ( $language ) { return ! empty( $language['active'] ); } );
		$providers = PandaTranslate_Providers::choices();
		if ( ! $targets ) {
			echo '<p>' . esc_html__( 'Select an active target language.', 'pandatranslate' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=pandatranslate' ) ) . '">' . esc_html__( 'Language settings', 'pandatranslate' ) . '</a></p>';
		}
		if ( ! $providers ) {
			echo '<p>' . esc_html__( 'No translation API configured', 'pandatranslate' ) . '. <a href="' . esc_url( admin_url( 'admin.php?page=pandatranslate-settings&tab=google' ) ) . '">' . esc_html__( 'Translation APIs', 'pandatranslate' ) . '</a></p>';
		}
		foreach ( $targets as $language ) {
			$stat = $stats[ $language['code'] ] ?? array( 'total' => 0, 'migrated' => 0, 'automatic' => 0, 'manual' => 0, 'untranslated' => 0 );
			$total = $stat['total']; $finished = $total - $stat['untranslated'];
			$published = $stat['published'] ?? 0;
			$code = $language['code'];
			$native = PandaTranslate_Features::language_name( $code );
			$local = PandaTranslate_Features::language_name( $code, false, get_user_locale() );
			$language_lang = str_replace( '_', '-', $language['locale'] );
			echo '<article class="pandatranslate-language-progress"><div class="pandatranslate-language-progress-header"><strong><span aria-hidden="true">' . esc_html( PandaTranslate_Features::flag( $code ) ) . '</span> <span lang="' . esc_attr( $language_lang ) . '">' . esc_html( $native ) . '</span>' . ( $local === $native ? '' : ' <span>(' . esc_html( $local ) . ')</span>' ) . '</strong>';
			$unmeasured = __( 'Not yet measured', 'pandatranslate' );
			if ( $total ) {
				echo '<span class="pandatranslate-language-progress-count"><span>' . esc_html( sprintf( /* translators: %1$d: translated units, %2$d: total units. */
					__( '%1$d of %2$d units translated', 'pandatranslate' ), $finished, $total ) ) . '</span><b>' . esc_html( round( $finished / $total * 100 ) ) . '%</b></span>';
			} else {
				echo '<span>' . esc_html( $unmeasured ) . '</span>';
			}
			echo '</div><div class="pandatranslate-stacked-progress" aria-hidden="true">';
			$legend = array();
			foreach ( $categories as $key => $name ) {
				$legend[ $key ] = $name;
				if ( ! $total ) continue;
				$percentage = $stat[ $key ] / $total * 100;
				if ( $stat[ $key ] ) echo '<span class="is-' . esc_attr( $key ) . '" style="width:' . esc_attr( $percentage ) . '%"></span>';
				$legend[ $key ] .= ': ' . number_format_i18n( $stat[ $key ] ) . ' (' . number_format_i18n( $percentage, 1 ) . '%)';
			}
			echo '</div><div class="pandatranslate-progress-meta"><ul class="pandatranslate-progress-legend">';
			foreach ( $legend as $key => $label ) echo '<li><span class="is-' . esc_attr( $key ) . '" aria-hidden="true"></span>' . esc_html( $label ) . '</li>';
			echo '<li>(' . esc_html( sprintf(
				/* translators: %1$s: count of published translations, %2$s: count of unpublished translations. */
				__( 'Published: %1$s · Unpublished: %2$s', 'pandatranslate' ),
				number_format_i18n( $published ),
				number_format_i18n( $finished - $published )
			) ) . ')</li></ul>';
			echo '<div class="pandatranslate-language-actions">';
			$actions = array(
				'missing' => array(
					'label' => __( 'Translate missing content', 'pandatranslate' ),
					'characters' => $total ? ( $stat['characters']['untranslated'] ?? 0 ) : null,
					'confirm' => __( 'Translate untranslated content with the selected API?', 'pandatranslate' ),
					'available' => true,
				),
				'all' => array(
					'label' => __( 'Retranslate all', 'pandatranslate' ),
					'characters' => $total ? array_sum( $stat['characters'] ?? array() ) : null,
					'confirm' => __( 'Replace all translations, including migrated and manual texts?', 'pandatranslate' ),
					'available' => $total > 0,
				),
				'failed' => array(
					'label' => __( 'Retry failures', 'pandatranslate' ),
					'characters' => $stat['failed_characters'] ?? 0,
					'confirm' => __( 'Retry failed translations? Existing translations will be replaced.', 'pandatranslate' ),
					'available' => ! empty( $stat['failed'] ),
				),
			);
			$nonce = wp_create_nonce( 'pandatranslate_language_batch' );
			foreach ( $actions as $mode => $action ) {
				$characters = $action['characters'];
				$scope = null === $characters ? $unmeasured : sprintf(
					/* translators: %s: formatted count of source characters. */
					_n( '%s character', '%s characters', $characters, 'pandatranslate' ),
					number_format_i18n( $characters )
				);
				echo '<button type="button" class="contactpanda-large-action pandatranslate-action" data-contactpanda-action-mode="labelled" data-contactpanda-action="' . esc_attr( 'failed' === $mode ? 'refresh' : 'start' ) . '" data-language-translate="' . esc_attr( $code ) . '" data-mode="' . esc_attr( $mode ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-confirm="' . esc_attr( $action['confirm'] ) . '"' . disabled( ! $providers || ! $action['available'], true, false ) . '><span class="pandatranslate-language-action-label">' . esc_html( $action['label'] . ' (' . $scope . ')' ) . '</span></button>';
			}
			echo '<p data-language-run-status role="status" aria-live="polite"></p></div></div></article>';
		}

		echo '</div>';
	}

	private function render_all_languages_action() {
        $targets = array(); foreach ( self::language_rows() as $row ) if ( ! empty( $row['active'] ) && $row['code'] !== self::get_settings()['source_language'] ) $targets[] = $row['code'];
        echo '<div class="pandatranslate-language-actions pandatranslate-all-languages"><button type="button" class="contactpanda-large-action" data-contactpanda-action="start" data-contactpanda-action-mode="labelled" aria-label="' . esc_attr__( 'Translate all untranslated content in all languages', 'pandatranslate' ) . '" data-language-translate-all data-languages="' . esc_attr( wp_json_encode( $targets ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'pandatranslate_language_batch' ) ) . '"' . ( ! PandaTranslate_Providers::choices() || ! $targets ? ' disabled' : '' ) . '>' . esc_html__( 'Translate missing content', 'pandatranslate' ) . '</button><small>' . esc_html__( 'All active target languages.', 'pandatranslate' ) . '</small><progress hidden aria-label="' . esc_attr__( 'Translation progress', 'pandatranslate' ) . '"></progress><p data-language-run-status role="status" aria-live="polite"></p></div>';
	}

	public function cancel_translation() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Access denied.', 'pandatranslate' ) ), 403 );
		check_ajax_referer( 'pandatranslate_language_batch', 'nonce' );
		$id = sanitize_key( wp_unslash( $_POST['run_id'] ?? '' ) );
		if ( ! PandaTranslate_Translation_Jobs::cancel( $id ) ) wp_send_json_error( array( 'message' => __( 'Cancellation could not be confirmed.', 'pandatranslate' ) ), 409 );
		if ( class_exists( 'PandaTranslate_Site_Progress' ) ) PandaTranslate_Site_Progress::cancel( $id );
		wp_send_json_success( array( 'cancelled' => true ) );
	}

	public function start_language_job() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Access denied.', 'pandatranslate' ) ), 403 );
		check_ajax_referer( 'pandatranslate_language_batch', 'nonce' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decode JSON before checking the array shape and sanitizing each language code below.
		$raw_languages = wp_unslash( $_POST['languages'] ?? '[]' );
		$languages = is_string( $raw_languages ) ? json_decode( $raw_languages, true ) : null;
		if ( ! is_array( $languages ) || count( $languages ) > 100 || array_filter( $languages, function ( $value ) { return ! is_string( $value ); } ) ) wp_send_json_error( array( 'message' => __( 'Invalid target language.', 'pandatranslate' ) ), 400 );
		$job = PandaTranslate_Site_Progress::start_languages( array_map( 'sanitize_key', $languages ), sanitize_key( wp_unslash( $_POST['api'] ?? '' ) ), sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) ), sanitize_key( wp_unslash( $_POST['run_id'] ?? '' ) ) );
		if ( is_wp_error( $job ) ) wp_send_json_error( array( 'message' => $job->get_error_message() ), 409 );
		ob_start(); PandaTranslate_Site_Progress::render(); $html = ob_get_clean();
		wp_send_json_success( array( 'id' => $job['id'], 'html' => $html, 'running' => PandaTranslate_Site_Progress::running( $job ) ) );
	}

	public function language_translation_batch() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Access denied.', 'pandatranslate' ) ), 403 );
		check_ajax_referer( 'pandatranslate_language_batch', 'nonce' );
		$result = PandaTranslate_Translation_Jobs::batch( wp_unslash( $_POST ) );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( $result );
	}

	private function render_glossary() { PandaTranslate_Glossary_Admin::render(); }

	public function default_providers( $providers ) {
		$providers['google-cloud-translation'] = PandaTranslate_Google_Provider::class;
		return $providers;
	}

	public function default_adapters( $adapters ) {
		foreach ( array( 'gutenberg', 'elementor', 'divi', 'avada', 'bricks', 'beaver-builder', 'wpbakery' ) as $builder ) $adapters[ $builder ] = 'rendered-html';
		return $adapters;
	}

	public function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_robots() || is_trackback() ) return;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound,WordPress.Security.NonceVerification.Recommended -- Authenticated internal loopback uses a cryptographic one-time transient token; validate and consume it before collecting. A visitor selector alone cannot authorize writes. DONOTCACHEPAGE is the intentional WordPress/cache-plugin interoperability constant, not a new plugin-global name.
		if ( isset( $_GET['pandatranslate_scan'] ) ) { $token = sanitize_text_field( wp_unslash( $_GET['pandatranslate_scan'] ) ); $job = get_transient( 'pandatranslate_scan_' . $token ); if ( $job && ( ! is_array( $job ) || ( $job['language'] ?? '' ) === $this->target_language() ) ) { $this->scan_mode = true; $this->scan_job = is_array( $job ) ? $job : array(); PandaTranslate_Translation_Jobs::set_run( $this->scan_job['run_id'] ?? '' ); delete_transient( 'pandatranslate_scan_' . $token ); if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true ); nocache_headers(); header( 'X-PandaTranslate-Scan: 1' ); } }
		ob_start( array( $this, 'transform_page' ) );
	}

	public function transform_page( $html ) {
		$started = microtime( true );
		try { return $this->render_page( $html ); }
		finally {
			// Standard, content-free timing metric separates our HTML work from
			// WordPress/theme generation when investigating slow translated pages.
			if ( ! headers_sent() ) { header( 'Server-Timing: pandatranslate;dur=' . round( ( microtime( true ) - $started ) * 1000, 2 ), false ); header( 'Server-Timing: ' . PandaTranslate_Strings::timing_header(), false ); }
		}
	}
	private function render_page( $html ) {
		$language = $this->target_language();
		if ( ! apply_filters( 'pandatranslate_capture_allowed', true ) ) {
			// Only already-published public header/footer text is displayed; never collect a transaction page.
			if ( class_exists( 'PandaTranslate_Frontend' ) ) return PandaTranslate_Frontend::transform( PandaTranslate_Frontend::public_chrome( $html, $language ), $language );
			return $html;
		}
		$editing  = $this->is_editing();
		if ( ! $editing && ! $this->scan_mode && '' === $language ) return class_exists( 'PandaTranslate_Frontend' ) ? PandaTranslate_Frontend::transform( $html, '' ) : $html;
		$request_path = is_singular() ? wp_parse_url( get_permalink( get_queried_object_id() ), PHP_URL_PATH ) : wp_parse_url( home_url( '/' . PandaTranslate_Routing::original_path( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) ), PHP_URL_PATH );
		$context = (string) get_queried_object_id() . '|' . (string) $request_path;
		$memory = new PandaTranslate_Translation_Memory( $html, $language, $this->repository );
		$public_rows = null;
		$public_keys = array();
		if ( $language && ! $editing && ! $this->scan_mode && method_exists( $this->repository, 'find_many' ) ) {
			$toolbar_keys = array();
			$this->extractor->transform( $html, function ( $unit ) use ( &$public_keys, &$toolbar_keys ) {
				$public_keys[$unit['key']] = true;
				if ( isset( $unit['toolbar_key'] ) ) $toolbar_keys[$unit['toolbar_key']] = true;
				return null;
			}, $context, true );
			$public_rows = $this->repository->find_many( array_keys( $public_keys + $toolbar_keys ), $language );
		}
		$result = $this->extractor->transform( $html, function ( $unit ) use ( $language, $editing, $memory, $public_rows, $public_keys ) {
			if ( ( $editing || $this->scan_mode ) && method_exists( $this->repository, 'record_context' ) ) $this->repository->record_context( $unit['key'], get_queried_object_id() );
			$row = null !== $public_rows ? ( $public_rows[$unit['key']] ?? null ) : ( $language ? $this->repository->find( $unit['key'], $language, $unit['source_hash'] ) : null );
			if ( null !== $public_rows && $row && $row['source_hash'] !== $unit['source_hash'] ) $row = null;
			if ( $language && ( ! $row || ( 'untranslated' === $row['status'] && '' === $row['translated_html'] ) ) && isset( $unit['toolbar_key'] ) && ! isset( $public_keys[$unit['toolbar_key']] ) ) {
				$legacy = null !== $public_rows ? ( $public_rows[$unit['toolbar_key']] ?? null ) : $this->repository->find( $unit['toolbar_key'], $language, $unit['source_hash'] );
				if ( $legacy && $legacy['source_hash'] === $unit['source_hash'] ) {
					if ( $editing || $this->scan_mode ) {
						if ( ! $row ) $this->repository->save( $unit, $language, $legacy['translated_html'], $legacy['status'], $legacy['provider'] ?? '', true );
						elseif ( 'untranslated' !== $legacy['status'] ) $this->repository->save_automatic_revision( $row, $legacy['translated_html'], $legacy['status'], $legacy['provider'] ?? '' );
						$row = $this->repository->find( $unit['key'], $language, $unit['source_hash'] );
					} elseif ( 'published' === $legacy['status'] ) $row = $legacy;
				}
			}
			// A corrected dictionary entry supersedes stale imported copies, never page-specific manual text.
			if ( $row && 'translatepress-import' === ( $row['provider'] ?? '' ) ) { $corrected = $memory->manual_lookup( $unit['source_html'] ); if ( null !== $corrected ) return $corrected; }
			$imported = ! $row || ( 'untranslated' === $row['status'] && '' === $row['translated_html'] ) ? $memory->lookup( $unit['source_html'] ) : null;
			if ( null !== $imported ) {
				if ( $editing || $this->scan_mode ) $this->repository->save( $unit, $language, $imported, 'published', 'translatepress-import' );
				return $imported;
			}
			if ( ( $editing || $this->scan_mode ) && $language && ! $row ) {
				$this->repository->save( $unit, $language, '', 'untranslated' );
			}
			if ( $this->scan_mode && ! empty( $this->scan_job['collect_only'] ) ) return null;
			if ( $this->scan_mode && $language ) { if ( $row && '' !== $row['translated_html'] && 'untranslated' !== $row['status'] ) return $row['translated_html']; $translated = self::auto_translate_known( $unit['key'], $language, $this->scan_job['api'] ?? '' ); if ( ! is_wp_error( $translated ) ) return $translated['translated_html']; }
			if ( $row && ( $editing || 'published' === $row['status'] ) ) return $row['translated_html'];
			if ( $row && 'untranslated' !== $row['status'] ) return $unit['source_html'];
			return null;
		}, $context );
		$result = $memory->transform( $result );
		if ( class_exists( 'PandaTranslate_Media' ) ) $result = PandaTranslate_Media::transform( $result, $language, $editing && current_user_can( 'upload_files' ) && current_user_can( 'edit_post', get_queried_object_id() ), get_queried_object_id() );
		return class_exists( 'PandaTranslate_Frontend' ) ? PandaTranslate_Frontend::transform( $result, $language ) : $result;
	}

	public function assets() {
		$s = $this->settings();
		$this->enqueue_switcher_assets();
		if ( ! $this->is_editing() ) return;
		wp_enqueue_media();
		PandaTranslate_Family_UI::admin();
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'pandatranslate-editor', PANDATRANSLATE_URL . 'assets/editor.css', array(), PANDATRANSLATE_VERSION );
		wp_enqueue_style( 'pandatranslate-editor-fixes', PANDATRANSLATE_URL . 'assets/editor-fixes.css', array( 'pandatranslate-editor' ), PANDATRANSLATE_VERSION );
		wp_enqueue_style( 'pandatranslate-family-actions', PANDATRANSLATE_URL . 'assets/contactpanda-actions.css', array( 'pandatranslate-editor-fixes' ), PANDATRANSLATE_VERSION );
		wp_enqueue_script( 'pandatranslate-editor-request', PANDATRANSLATE_URL . 'assets/editor-request.js', array(), PANDATRANSLATE_VERSION, true );
		wp_enqueue_script( 'pandatranslate-editor', PANDATRANSLATE_URL . 'assets/editor.js', array( 'contactpanda-admin-ui', 'pandatranslate-editor-request' ), PANDATRANSLATE_VERSION, true );
		wp_enqueue_script( 'pandatranslate-media-editor', PANDATRANSLATE_URL . 'assets/media-editor.js', array( 'pandatranslate-editor', 'contactpanda-admin-ui' ), PANDATRANSLATE_VERSION, true );
		wp_localize_script( 'pandatranslate-media-editor', 'PandaTranslateMedia', array( 'url' => rest_url( 'pandatranslate/v1/media' ), 'labels' => array( 'title' => __( 'Image translation', 'pandatranslate' ), 'source' => __( 'Original image', 'pandatranslate' ), 'alt' => __( 'Alternative text', 'pandatranslate' ), 'titleText' => __( 'Image title', 'pandatranslate' ), 'caption' => __( 'Caption', 'pandatranslate' ), 'save' => __( 'Save image translation', 'pandatranslate' ), 'remove' => __( 'Remove image mapping', 'pandatranslate' ), 'saved' => __( 'Saved.', 'pandatranslate' ), 'failed' => __( 'The image mapping could not be saved.', 'pandatranslate' ) ) ) );
		wp_localize_script( 'pandatranslate-editor', 'PandaTranslateEditor', array(
			'dynamicUrl' => rest_url( 'pandatranslate/v1/dynamic' ), 'postId' => get_queried_object_id(), 'captureAllowed' => apply_filters( 'pandatranslate_capture_allowed', true ),
			'restUrl' => esc_url_raw( rest_url( 'pandatranslate/v1/unit' ) ),
			'nonceUrl' => esc_url_raw( admin_url( 'admin-ajax.php?action=rest-nonce' ) ),
			'autoUrl' => esc_url_raw( rest_url( 'pandatranslate/v1/unit/auto' ) ),
			'publicUrl' => esc_url_raw( self::language_url( $this->target_language() ) ),
			'canPublish' => current_user_can( 'publish_posts' ) || current_user_can( 'manage_options' ),
			'apis' => current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) ? PandaTranslate_Providers::choices() : array(),
			'defaultApi' => PandaTranslate_Providers::default_id(),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'language' => $this->target_language() ?: $s['default_target_language'],
			'statuses' => array( 'untranslated', 'automatic', 'reviewed', 'published' ),
			'i18n' => array(
				'requestNetworkError' => __( 'The connection was interrupted. Reload the editor and check the saved translation before retrying.', 'pandatranslate' ),
				'requestSessionError' => __( 'The login session could not be renewed. Sign in and reload the editor.', 'pandatranslate' ),
				'requestOriginError' => __( 'The editor and API addresses use different domains. Check the WordPress website addresses.', 'pandatranslate' ),
				'requestResponseError' => __( 'The website returned an unexpected API response. Check the connection and server logs.', 'pandatranslate' ),
				'saving' => __( 'Saving…', 'pandatranslate' ), 'saved' => __( 'Saved.', 'pandatranslate' ), 'translating' => __( 'Translating…', 'pandatranslate' ), 'close' => __( 'Close', 'pandatranslate' ), 'statusLabels' => array( 'untranslated' => __( 'Not translated', 'pandatranslate' ), 'automatic' => __( 'Automatically translated', 'pandatranslate' ), 'reviewed' => __( 'Reviewed', 'pandatranslate' ), 'published' => __( 'Published', 'pandatranslate' ) ), 'title' => 'Panda Translate', 'previewNotice' => __( 'This preview includes drafts. Only published translations appear on the public page.', 'pandatranslate' ), 'publicPage' => __( 'View public page', 'pandatranslate' ), 'source' => __( 'Source', 'pandatranslate' ), 'translation' => __( 'Translation', 'pandatranslate' ), 'save' => __( 'Save draft', 'pandatranslate' ), 'publish' => __( 'Publish', 'pandatranslate' ), 'automatic' => /* translators: %s: selected translation provider label. */
		__( 'Translate with %s', 'pandatranslate' ), 'automaticPage' => /* translators: %s: selected translation provider label. */
		__( 'Translate entire page with %s', 'pandatranslate' ), 'apiLabel' => __( 'Translation API', 'pandatranslate' ), 'noApi' => __( 'No translation API configured', 'pandatranslate' ), 'select' => __( 'Select visible content to translate it.', 'pandatranslate' ), 'progressTitle' => __( 'Translating current page', 'pandatranslate' ), 'progressDone' => __( 'translated', 'pandatranslate' ), 'progressFailed' => __( 'failed', 'pandatranslate' ), 'cancelLabel' => __( 'Cancel translation', 'pandatranslate' ), 'cancelled' => __( 'Translation cancelled. Saved translations are retained.', 'pandatranslate' ), 'cancelling' => __( 'Stopping after the current request…', 'pandatranslate' ), 'repeatedErrors' => __( 'Stopped after repeated errors.', 'pandatranslate' ) ),
		) );
	}

	public function register_routes() {
		PandaTranslate_Glossary_Admin::routes();
		PandaTranslate_Media::routes();
		register_rest_route( 'pandatranslate/v1', '/unit', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'read_unit' ), 'permission_callback' => array( $this, 'can_edit' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save_unit' ), 'permission_callback' => array( $this, 'can_edit' ) ),
		) );
		register_rest_route( 'pandatranslate/v1', '/unit/auto', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'auto_translate_unit' ), 'permission_callback' => array( $this, 'can_edit' ) ) );
		register_rest_route( 'pandatranslate/v1', '/google/test', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'google_test' ), 'permission_callback' => function () { return current_user_can( 'manage_options' ); } ) );
		register_rest_route( 'pandatranslate/v1', '/deepl/test', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'deepl_test' ), 'permission_callback' => function () { return current_user_can( 'manage_options' ); } ) );
	}

	public function can_edit() { return current_user_can( 'edit_posts' ) || current_user_can( 'pandatranslate_translate' ); }

	public function read_unit( WP_REST_Request $request ) {
		$key = sanitize_text_field( $request['key'] ); $language = sanitize_key( $request['language'] );
		$row = $this->repository->find( $key, $language );
		if ( $row ) $row['revision'] = PandaTranslate_Repository::revision( $row );
		return rest_ensure_response( $row ?: array( 'status' => 'untranslated', 'translated_html' => '' ) );
	}

	public function save_unit( WP_REST_Request $request ) {
		$unit = array( 'key' => sanitize_text_field( $request['key'] ), 'source_hash' => sanitize_text_field( $request['source_hash'] ), 'source_html' => (string) $request['source_html'] );
		$language = sanitize_key( $request['language'] );
		if ( class_exists( 'PandaTranslate_Edition' ) && ! PandaTranslate_Edition::allows_language( $language ) ) return new WP_Error( 'pandatranslate_language', __( 'Select an active target language.', 'pandatranslate' ) );
		$status = sanitize_key( $request['status'] );
		if ( 'published' === $status && ! current_user_can( 'publish_posts' ) && ! current_user_can( 'manage_options' ) ) return new WP_Error( 'pandatranslate_publish_denied', __( 'Publishing permission is required.', 'pandatranslate' ), array( 'status' => 403 ) );
		if ( ! preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8}){0,3}$/', $language ) ) return new WP_Error( 'pandatranslate_language', __( 'Invalid target language.', 'pandatranslate' ), array( 'status' => 400 ) );
		$known = $this->repository->find( $unit['key'], $language );
		if ( ! $known || ! hash_equals( $known['source_hash'], $unit['source_hash'] ) ) return new WP_Error( 'pandatranslate_source_changed', __( 'The source content changed. Reload the preview before saving.', 'pandatranslate' ), array( 'status' => 409 ) );
		$unit['source_html'] = $known['source_html'];
		if ( ! hash_equals( PandaTranslate_Repository::revision( $known ), (string) $request['revision'] ) ) return new WP_Error( 'pandatranslate_conflict', __( 'The translation changed. Reload before saving.', 'pandatranslate' ), array( 'status' => 409 ) );
		$protected = PandaTranslate_Inline_Protector::protect( $unit['source_html'] );
		$restored = PandaTranslate_Inline_Protector::restore( (string) $request['translated_text'], $protected['tokens'] );
		if ( is_wp_error( $restored ) ) return $restored;
		$result = $this->repository->save_revision( $known, $restored, $status );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'saved' => true, 'translated_html' => $restored, 'status' => $status, 'revision' => PandaTranslate_Repository::revision( $result ) ) );
	}

	public function auto_translate_unit( WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) return new WP_Error( 'pandatranslate_api_denied', __( 'Translation API permission is required.', 'pandatranslate' ), array( 'status' => 403 ) );
		if ( true !== rest_sanitize_boolean( $request['confirm'] ) ) return new WP_Error( 'pandatranslate_confirmation', __( 'Confirm the paid Google request.', 'pandatranslate' ), array( 'status' => 400 ) );
		$can_publish = current_user_can( 'publish_posts' ) || current_user_can( 'manage_options' );
		$result = self::auto_translate_known( sanitize_text_field( $request['key'] ), sanitize_key( $request['language'] ), sanitize_key( $request['api'] ?? '' ), ! $can_publish );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function deepl_test( WP_REST_Request $request ) {
		$s = self::get_settings(); $key = trim( (string) $request->get_param( 'api_key' ) );
		$s['deepl_endpoint'] = 'pro' === $request->get_param( 'plan' ) ? 'pro' : 'free';
		if ( '' !== $key ) { $encrypted = $this->encrypt_secret( $key ); if ( is_wp_error( $encrypted ) ) return $encrypted; $s['deepl_key_encrypted'] = $encrypted; $s['deepl_key_last4'] = substr( $key, -4 ); }
		update_option( self::OPTION, $s, false );
		$result = ( new PandaTranslate_DeepL_Provider() )->translate( 'Connection test', 'en', 'de', array( 'test' => true ) );
		$s['deepl_test'] = array( 'status' => is_wp_error( $result ) ? 'invalid' : 'valid', 'time' => current_time( 'mysql' ), 'error' => is_wp_error( $result ) ? PandaTranslate_Translation_Jobs::safe_error( $result ) : '' );
		update_option( self::OPTION, $s, false );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'success' => true, 'translation' => $result ) );
	}

	public function google_test( WP_REST_Request $request ) {
		$s = self::get_settings();
		$edition = 'v2' === $request->get_param( 'edition' ) ? 'v2' : ( 'v3' === $request->get_param( 'edition' ) ? 'v3' : $s['google_api_version'] );
		$s['google_api_version'] = $edition;
		if ( null !== $request->get_param( 'enabled' ) ) $s['google_' . $edition . '_enabled'] = rest_sanitize_boolean( $request->get_param( 'enabled' ) ) ? 1 : 0;
		$s['provider'] = $s['google_v2_enabled'] || $s['google_v3_enabled'] ? 'google-cloud-translation' : 'manual';
		update_option( self::OPTION, $s, false );
		if ( 'v3' === $edition ) { $s['google_project_id'] = sanitize_text_field( $request->get_param( 'project_id' ) ?? $s['google_project_id'] ); $s['google_location'] = sanitize_key( $request->get_param( 'location' ) ?? $s['google_location'] ) ?: 'global'; update_option( self::OPTION, $s, false ); }
		$new_key = trim( (string) $request->get_param( 'api_key' ) ); if ( '' !== $new_key ) { $encrypted = $this->encrypt_secret( $new_key ); if ( is_wp_error( $encrypted ) ) return $encrypted; $s['google_api_key_encrypted'] = $encrypted; $s['google_api_key_last4'] = substr( $new_key, -4 ); update_option( self::OPTION, $s, false ); }
		$provider = new PandaTranslate_Google_Provider( $s['google_project_id'], $s['google_location'], $edition );
		$result = $provider->translate( 'Connection test', 'en', 'en' === self::provider_language( $s['default_target_language'] ) ? 'de' : self::provider_language( $s['default_target_language'] ), array( 'test' => true ) );
		$s['google_key_status'] = is_wp_error( $result ) ? 'invalid' : 'valid'; $s['google_key_tested_at'] = current_time( 'mysql' ); $s['google_key_last_error'] = is_wp_error( $result ) ? $result->get_error_message() : ''; update_option( self::OPTION, $s, false );
		$s['google_tests'][$edition] = array( 'status' => $s['google_key_status'], 'time' => $s['google_key_tested_at'], 'error' => $s['google_key_last_error'] ); update_option( self::OPTION, $s, false );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'success' => true, 'translation' => $result ) );
	}

	public static function auto_translate_known( $key, $language, $api_id = '', $draft_only = false ) {
		$result = self::translate_known( $key, $language, $api_id, $draft_only );
		$repository = new PandaTranslate_Repository();
		if ( method_exists( $repository, 'record_error' ) ) $repository->record_error( $key, $language, is_wp_error( $result ) ? $result->get_error_code() . ': ' . PandaTranslate_Translation_Jobs::safe_error( $result ) : '' );
		return $result;
	}

	private static function translate_known( $key, $language, $api_id, $draft_only ) {
		if ( class_exists( 'PandaTranslate_Edition' ) && ! PandaTranslate_Edition::allows_language( $language ) ) return new WP_Error( 'pandatranslate_language', __( 'Select an active target language.', 'pandatranslate' ) );
		$repository = new PandaTranslate_Repository();
		$row = $repository->find( $key, $language );
		if ( ! $row ) return new WP_Error( 'pandatranslate_unit_missing', __( 'Open the page in the visual editor before translating this unit.', 'pandatranslate' ) );
		$s = self::get_settings();
		if ( $draft_only ) {
			if ( 'published' === $row['status'] ) return new WP_Error( 'pandatranslate_publish_denied', __( 'Publishing permission is required.', 'pandatranslate' ), array( 'status' => 403 ) );
			$s['auto_publish'] = 0;
		}
		$api_id = $api_id ?: PandaTranslate_Providers::default_id(); $provider = PandaTranslate_Providers::get( $api_id ); if ( is_wp_error( $provider ) ) return $provider;
		$protected = PandaTranslate_Template_Protector::prepare_row( $row );
		$glossary = PandaTranslate_Features::protect_glossary( $protected['text'], $language );
		$provider_source = 'gettext' === ( $row['source_kind'] ?? '' ) ? '' : self::provider_language( $s['source_language'] );
		$translated = $provider->translate( $glossary['text'], $provider_source, self::provider_language( $language ), array( 'unit_key' => $key, 'requested_language' => $language, 'api' => $api_id ) );
		if ( is_wp_error( $translated ) ) return $translated;
		return self::finish_automatic_translation( $row, $translated, $protected, $glossary, $s, $language, $api_id );
	}

	private static function finish_automatic_translation( $row, $translated, $protected, $glossary, $s, $language, $api_id ) {
		if ( class_exists( 'PandaTranslate_Translation_Jobs' ) && PandaTranslate_Translation_Jobs::is_cancelled() ) return new WP_Error( 'pandatranslate_cancelled', __( 'Translation cancelled. Saved translations are retained.', 'pandatranslate' ) );
		$repository = new PandaTranslate_Repository();
		$translated = PandaTranslate_Features::restore_glossary( $translated, $glossary['tokens'] );
		if ( is_wp_error( $translated ) ) return $translated;
		$restored = PandaTranslate_Template_Protector::restore_prepared( $translated, $protected );
		if ( is_wp_error( $restored ) ) return $restored;
		if ( class_exists( 'PandaTranslate_Strings' ) && ! PandaTranslate_Strings::same_placeholders( $row['source_html'], $restored ) ) return new WP_Error( 'pandatranslate_variables', __( 'Formatting variables must remain unchanged.', 'pandatranslate' ) );
		$unit = array( 'key' => $row['unit_key'], 'source_hash' => $row['source_hash'], 'source_html' => $row['source_html'] );
		$provider_id = 'google-v2' === $api_id ? 'google-cloud-translation-v2' : ( 'google-v3' === $api_id ? 'google-cloud-translation-v3' : 'api:' . $api_id );
		$status = ! empty( $s['auto_publish'] ) ? 'published' : 'automatic';
		if ( method_exists( $repository, 'save_automatic_revision' ) ) $saved = $repository->save_automatic_revision( $row, $restored, $status, $provider_id );
		else $saved = $repository->save( $unit, $language, $restored, $status, $provider_id );
		return is_wp_error( $saved ) ? $saved : array( 'translated_html' => $restored, 'status' => $status, 'provider' => $provider_id, 'api' => $api_id, 'revision' => method_exists( $repository, 'save_automatic_revision' ) && is_array( $saved ) ? PandaTranslate_Repository::revision( $saved ) : '' );
	}

	public static function auto_translate_many( array $keys, $language, $api_id, $mode = 'missing' ) {
		if ( ! PandaTranslate_Edition::allows_language( $language ) ) return array_fill_keys( $keys, new WP_Error( 'pandatranslate_language', __( 'Invalid target language.', 'pandatranslate' ) ) );
		$provider = PandaTranslate_Providers::get( $api_id );
		if ( is_wp_error( $provider ) || ! method_exists( $provider, 'translate_many' ) ) { $out = array(); foreach ( $keys as $key ) $out[$key] = is_wp_error( $provider ) ? $provider : self::auto_translate_known( $key, $language, $api_id ); return $out; }
		$s = self::get_settings(); $repository = new PandaTranslate_Repository(); $rows = $repository->find_many( $keys, $language ); $groups = array(); $items = array(); $out = array();
		foreach ( $keys as $key ) {
			$row = $rows[$key] ?? null;
			if ( ! $row ) { $out[$key] = new WP_Error( 'pandatranslate_unit_missing', __( 'Open the page in the visual editor before translating this unit.', 'pandatranslate' ) ); continue; }
			if ( 'missing' === $mode && 'untranslated' !== $row['status'] && '' !== trim( $row['translated_html'] ) ) { $out[$key] = array( 'skipped' => true ); continue; }
			$protected = PandaTranslate_Template_Protector::prepare_row( $row ); $glossary = PandaTranslate_Features::protect_glossary( $protected['text'], $language );
			$source = 'gettext' === ( $row['source_kind'] ?? '' ) ? '' : self::provider_language( $s['source_language'] );
			$groups[$source][$key] = $glossary['text']; $items[$key] = array( $row, $protected, $glossary );
		}
		foreach ( $groups as $source => $texts ) {
			$values = $provider->translate_many( $texts, $source, self::provider_language( $language ) );
			foreach ( $texts as $key => $text ) {
				$value = is_wp_error( $values ) ? $values : ( $values[$key] ?? new WP_Error( 'pandatranslate_google_v2_response', __( 'Google v2 did not return a translation.', 'pandatranslate' ) ) );
				list( $row, $protected, $glossary ) = $items[$key];
				$out[$key] = is_wp_error( $value ) ? $value : self::finish_automatic_translation( $row, $value, $protected, $glossary, $s, $language, $api_id );
				$repository->record_error( $key, $language, is_wp_error( $out[$key] ) ? $out[$key]->get_error_code() . ': ' . PandaTranslate_Translation_Jobs::safe_error( $out[$key] ) : '' );
			}
		}
		return $out;
	}

	public static function save_known_translation( $key, $language, $translated_text, $status ) {
		$repository = new PandaTranslate_Repository(); $row = $repository->find( $key, $language );
		if ( ! $row ) return new WP_Error( 'pandatranslate_unit_missing', __( 'The translation unit does not exist.', 'pandatranslate' ) );
		$protected = PandaTranslate_Template_Protector::prepare_row( $row );
		$restored = PandaTranslate_Template_Protector::restore_prepared( $translated_text, $protected );
		if ( is_wp_error( $restored ) ) return $restored;
		$unit = array( 'key' => $row['unit_key'], 'source_hash' => $row['source_hash'], 'source_html' => $row['source_html'] );
		$provider = $restored === $row['translated_html'] ? $row['provider'] : 'manual';
		$saved = $repository->save( $unit, $language, $restored, $status, $provider );
		return is_wp_error( $saved ) ? $saved : array( 'saved' => true, 'status' => $status );
	}

	public function menu_switcher( $items, $args ) {
		$s = $this->settings();
		if ( empty( $args->theme_location ) || ! in_array( $args->theme_location, (array) $s['switcher_menu_locations'], true ) ) return $items;
		$markup = $this->switcher_markup( 'theme-menu' ); return $markup ? $items . '<li class="menu-item menu-item-has-children pandatranslate-menu-item">' . $markup . '</li>' : $items;
	}

	public function switcher_shortcode( $attributes = array() ) {
		$this->enqueue_switcher_assets();
		return $this->switcher_markup( 'shortcode', (array) $attributes );
	}
	public function menu_shortcode( $attributes = array() ) { $attributes = (array) $attributes; $attributes['menu'] = 1; return $this->switcher_shortcode( $attributes ); }

	public function floating_switcher() {
		$s = $this->settings(); if ( empty( $s['switcher_floating'] ) || is_admin() ) return;
		$markup = $this->switcher_markup( 'floating' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete switcher markup escapes dynamic values in switcher_markup; the position class is escaped here.
		if ( $markup ) echo '<div class="pandatranslate-floating is-' . esc_attr( $s['switcher_position'] ) . '">' . $markup . '</div>';
	}

	private function switcher_markup( $context, array $attributes = array() ) {
		if ( ! empty( $attributes['menu'] ) ) $context = 'menu';
		if ( ! empty( $attributes['inherit_menu'] ) ) $context = 'theme-menu';
		$s = self::switcher_settings( $context ); $languages = array(); foreach ( ( ! empty( $attributes['preview'] ) ? self::language_rows() : self::visible_language_rows() ) as $row ) if ( ! empty( $row['active'] ) ) $languages[ $row['code'] ] = $row;
		$current = $this->target_language() ?: $s['source_language']; if ( ! isset( $languages[$current] ) ) $current = $s['source_language'];
		$mode = isset( $attributes['display'] ) ? sanitize_key( $attributes['display'] ) : ( $s['switcher_label_mode'] ?? ( ! empty( $s['switcher_show_names'] ) ? 'native' : 'code' ) );
		if ( in_array( $mode, array( 'names', 'native' ), true ) ) $mode = 'flag_name'; if ( 'codes' === $mode ) $mode = 'flag_code'; if ( ! in_array( $mode, array( 'flag_name', 'flag_code', 'name', 'code', 'flags' ), true ) ) $mode = 'flag_name';
		$s['switcher_label_mode'] = $mode;
		$catalog = PandaTranslate_Features::catalog();
		$dropdown = 'dropdown' === $s['switcher_type'];
		$hide_current = $dropdown || 'menu' === $context || ! empty( $attributes['hide_current'] );
		if ( $hide_current ) unset( $languages[ $current ] );
		if ( ! $languages ) return '';
		if ( 'theme-menu' === $context ) {
			$html = '<a href="' . esc_url( self::language_url( $current ) ) . '" class="pandatranslate-theme-trigger" aria-label="' . esc_attr__( 'Language selection', 'pandatranslate' ) . '">' . PandaTranslate_Features::language_content( $current, $s ) . '</a><ul class="sub-menu">';
			foreach ( $languages as $language => $row ) if ( $language !== $current ) $html .= '<li class="menu-item"><a href="' . esc_url( self::language_url( $language ) ) . '" data-pt-language="' . esc_attr( $language ) . '" lang="' . esc_attr( $language ) . '" hreflang="' . esc_attr( $language ) . '" aria-label="' . esc_attr( PandaTranslate_Features::language_name( $language ) ) . '">' . PandaTranslate_Features::language_content( $language, $s ) . '</a></li>';
			return $html . '</ul>';
		}
		$style = '--pt-bg:' . $s['switcher_background'] . ';--pt-bg-hover:' . $s['switcher_background_hover'] . ';--pt-text:' . $s['switcher_text'] . ';--pt-text-hover:' . $s['switcher_text_hover'] . ';--pt-border:' . $s['switcher_border_color'] . ';--pt-border-hover:' . $s['switcher_border_color_hover'] . ';--pt-border-width:' . $s['switcher_border_width'] . ';--pt-radius:' . $s['switcher_border_radius'] . ';--pt-width:' . $s['switcher_width'] . ';--pt-padding:' . $s['switcher_padding_top'] . ' ' . $s['switcher_padding_right'] . ' ' . $s['switcher_padding_bottom'] . ' ' . $s['switcher_padding_left'];
		if ( 'menu' === $context ) $style .= ';--pt-container-bg:' . $s['menu_switcher_container_background'];
		$html = '<nav style="' . esc_attr( $style ) . '" class="pandatranslate-switcher is-' . esc_attr( $context ) . ' is-' . esc_attr( $s['switcher_type'] ) . ' is-label-' . esc_attr( $mode ) . ( empty( $s['switcher_animations'] ) ? ' no-animation' : '' ) . '" aria-label="' . esc_attr__( 'Language selection', 'pandatranslate' ) . '">';
		if ( $dropdown ) $html .= '<button type="button" class="pandatranslate-selection" data-pandatranslate-toggle aria-expanded="false" aria-label="' . esc_attr__( 'Language selection', 'pandatranslate' ) . '">' . ( 'menu' === $context ? '<span class="dashicons dashicons-translation" aria-hidden="true"></span>' : PandaTranslate_Features::language_content( $current, $s ) ) . '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>';
		$html .= '<ul>';
		foreach ( $languages as $language => $language_row ) {
			$content = PandaTranslate_Features::language_content( $language, $s );
			$html .= '<li' . ( $language === $current ? ' class="is-current"' : '' ) . '><a href="' . esc_url( self::language_url( $language ) ) . '" data-pt-language="' . esc_attr( $language ) . '" lang="' . esc_attr( $language ) . '" hreflang="' . esc_attr( $language ) . '" aria-label="' . esc_attr( PandaTranslate_Features::language_name( $language ) ) . '"' . ( $language === $current ? ' aria-current="page"' : '' ) . '>' . $content . '</a>' . ( $language === $current && 'dropdown' === $s['switcher_type'] ? '<button type="button" data-pandatranslate-toggle aria-expanded="false" aria-label="' . esc_attr__( 'Language selection', 'pandatranslate' ) . '"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>' : '' ) . '</li>';
		}
		return $html . '</ul></nav>';
	}
	private function enqueue_switcher_assets() { wp_enqueue_style( 'dashicons' ); wp_enqueue_style( 'pandatranslate-switcher', PANDATRANSLATE_URL . 'assets/switcher.css', array(), PANDATRANSLATE_VERSION ); wp_enqueue_style( 'pandatranslate-switcher-fixes', PANDATRANSLATE_URL . 'assets/switcher-fixes.css', array( 'pandatranslate-switcher', 'dashicons' ), PANDATRANSLATE_VERSION ); wp_enqueue_script( 'pandatranslate-switcher', PANDATRANSLATE_URL . 'assets/switcher.js', array(), PANDATRANSLATE_VERSION, true ); }

	public function log_provider_event( $provider, $result, $characters, $cost_micros, $error_code, $words = 0 ) {
		$log = (array) get_option( self::PROVIDER_LOG_OPTION, array() );
		$log[] = array( 'time' => current_time( 'mysql', true ), 'provider' => sanitize_key( $provider ), 'result' => sanitize_key( $result ), 'characters' => absint( $characters ), 'words' => absint( $words ), 'cost_micros' => absint( $cost_micros ), 'error_code' => sanitize_key( $error_code ) );
		update_option( self::PROVIDER_LOG_OPTION, array_slice( $log, -500 ), false );
	}
	private function provider_usage() { $total = array( 'characters' => 0, 'words' => 0 ); foreach ( (array) get_option( self::PROVIDER_LOG_OPTION, array() ) as $entry ) if ( 'success' === ( $entry['result'] ?? '' ) ) { $total['characters'] += absint( $entry['characters'] ?? 0 ); $total['words'] += absint( $entry['words'] ?? 0 ); } return $total; }
	private static function provider_language( $language ) { $parts = explode( '-', strtolower( (string) $language ) ); return $parts[0]; }

	public function queue_new_content( $post_id, $post, $update ) {
		$settings = $this->settings();
		// Both backend editors and frontend builders that save a WordPress post arrive here.
		if ( empty( $settings['auto_enabled'] ) || 'publish' !== $post->post_status || '' !== $post->post_password || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! in_array( $post->post_type, (array) $settings['auto_post_types'], true ) ) return;
		$api_id = PandaTranslate_Providers::default_id();
		if ( ! $api_id ) return;
		$args = array( $post_id, $api_id );
		if ( ! wp_next_scheduled( 'pandatranslate_process_post', $args ) ) wp_schedule_single_event( time() + 10, 'pandatranslate_process_post', $args );
	}
	public function queue_site() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permission required to start this translation run.', 'pandatranslate' ) );
		check_admin_referer( 'pandatranslate_translate_site', 'pandatranslate_site_nonce' );
		$api_id = sanitize_key( wp_unslash( $_POST[self::OPTION]['auto_provider'] ?? '' ) ) ?: PandaTranslate_Providers::default_id();
		$input_types = isset( $_POST[self::OPTION]['auto_post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[self::OPTION]['auto_post_types'] ) ) : null;
		$provider = PandaTranslate_Providers::get( $api_id ); if ( is_wp_error( $provider ) ) wp_die( esc_html( $provider->get_error_message() ) );
		$s = $this->settings(); $types = array_values( array_intersect( array_keys( get_post_types( array( 'public' => true ), 'names' ) ), ( $input_types ?? $s['auto_post_types'] ) ) );
        $languages = array(); foreach ( self::language_rows() as $row ) if ( ! empty( $row['active'] ) && $row['code'] !== $s['source_language'] ) $languages[] = $row['code'];
        if ( ! $languages ) wp_die( esc_html__( 'Select an active target language.', 'pandatranslate' ) );
        $queued = PandaTranslate_Site_Progress::start_query( $types, $languages, $api_id );
        if ( is_wp_error( $queued ) ) wp_die( esc_html( $queued->get_error_message() ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'pandatranslate-translation', 'queued' => $queued['total'] ), admin_url( 'admin.php' ) ) ); exit;
	}
    public function process_post( $post_id, $api_id = '', $language = '', $job_id = '' ) {
        PandaTranslate_Translation_Jobs::set_run( $job_id ); $job = $job_id ? PandaTranslate_Site_Progress::state( true ) : array();
        if ( PandaTranslate_Translation_Jobs::is_cancelled() || ( $job_id && ( ( $job['id'] ?? '' ) !== $job_id || ! empty( $job['cancelled'] ) ) ) ) return;
        $api_id = $api_id ?: PandaTranslate_Providers::default_id(); $failed = false; $ran = false; $failed_detail = '';
        if ( is_wp_error( PandaTranslate_Providers::get( $api_id ) ) ) $failed = true;
        else foreach ( self::language_rows() as $row ) {
            if ( empty( $row['active'] ) || $row['code'] === self::get_settings()['source_language'] || ( $language && $language !== $row['code'] ) ) continue;
            if ( PandaTranslate_Translation_Jobs::is_cancelled() ) return;
            $ran = true; $result = PandaTranslate_Translation_Jobs::scan( $post_id, $row['code'], $api_id, (bool) $job_id, $job_id ); $failed = $failed || is_wp_error( $result ); if ( is_wp_error( $result ) ) $failed_detail = PandaTranslate_Translation_Jobs::safe_error( $result );
            if ( ! is_wp_error( $result ) && class_exists( 'PandaTranslate_SEO' ) && ( ! class_exists( 'PandaTranslate_Edition' ) || PandaTranslate_Edition::multilingual() ) ) PandaTranslate_SEO::auto_translate( $post_id, $row['code'], $api_id );
        }
        if ( class_exists( 'PandaTranslate_Site_Progress' ) ) PandaTranslate_Site_Progress::finish( $job_id, $post_id, $language, $failed || ! $ran, $failed_detail );
    }
    public function translation_overview() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array(), 403 );
        check_ajax_referer( 'pandatranslate_language_batch', 'nonce' );
        ob_start(); $this->render_translation_progress(); wp_send_json_success( array( 'html' => ob_get_clean() ) );
    }
    public function site_progress() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array(), 403 );
        check_ajax_referer( 'pandatranslate_language_batch', 'nonce' );
        ob_start(); PandaTranslate_Site_Progress::render(); $html = ob_get_clean();
        $state = PandaTranslate_Site_Progress::state(); $running = false; foreach ( $state['languages'] ?? array() as $counts ) if ( ! in_array( $counts['phase'] ?? '', array( 'done', 'failed', 'cancelled' ), true ) ) $running = true;
        wp_send_json_success( array( 'html' => $html, 'running' => $running ) );
    }

	public static function editing_request() {
		if ( ! ( current_user_can( 'edit_posts' ) || current_user_can( 'pandatranslate_translate' ) ) ) return false;
		$nonce = sanitize_text_field( wp_unslash( $_GET['_pt_editor_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'pandatranslate_editor' ) ) return false;
		return 'edit' === sanitize_key( wp_unslash( $_GET['pandatranslate'] ?? '' ) );
	}
	private function is_editing() { return self::editing_request(); }
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing/display selector or escaped numeric notice. Values are sanitized/allowlisted; no state change is authorized by this GET value.
	private function target_language() { $requested = isset( $_GET['pt_lang'] ) ? sanitize_key( wp_unslash( $_GET['pt_lang'] ) ) : sanitize_key( get_query_var( 'pt_lang' ) ); if ( ! $requested ) return ''; foreach ( self::language_rows() as $row ) if ( ! empty( $row['active'] ) && $requested === $row['code'] ) return $requested; return ''; }
}
