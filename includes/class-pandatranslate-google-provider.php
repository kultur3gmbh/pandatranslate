<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Google_Provider implements PandaTranslate_Provider {
	private $project_id;
	private $location;
	private $edition;

	public function __construct( $project_id = '', $location = 'global', $edition = 'v3' ) {
		$this->project_id = sanitize_text_field( (string) $project_id );
		$this->location   = sanitize_key( (string) $location ) ?: 'global';
		$this->edition    = 'v2' === $edition ? 'v2' : 'v3';
	}

	public function id() { return 'google-cloud-translation'; }
	public function location() { return $this->location; }

	public function configured() {
		if ( 'v2' === $this->edition ) return '' !== $this->api_key() ? true : new WP_Error( 'pandatranslate_google_api_key', __( 'Enter the Google v2 API key.', 'pandatranslate' ) );
		if ( '' === $this->project_id ) return new WP_Error( 'pandatranslate_google_project', __( 'Enter the Google Cloud project ID.', 'pandatranslate' ) );
		$credentials = $this->credentials();
		return is_wp_error( $credentials ) ? $credentials : true;
	}

	public function translate( $protected_text, $source_language, $target_language, array $context = array() ) {
		$configured = $this->configured();
		if ( is_wp_error( $configured ) ) return $configured;
		if ( 'v2' === $this->edition ) return $this->translate_v2( $protected_text, $source_language, $target_language );
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) return $this->failed( $token, $protected_text );
		$parent = 'projects/' . rawurlencode( $this->project_id ) . '/locations/' . rawurlencode( $this->location );
		$body = array( 'contents' => array( (string) $protected_text ), 'mimeType' => 'text/plain', 'sourceLanguageCode' => sanitize_key( $source_language ), 'targetLanguageCode' => sanitize_key( $target_language ), 'labels' => array( 'product' => 'pandatranslate' ) );
		if ( ! $source_language ) unset( $body['sourceLanguageCode'] );
		$glossary = apply_filters( 'pandatranslate_google_glossary', '', $source_language, $target_language, $context );
		if ( is_string( $glossary ) && '' !== $glossary ) $body['glossaryConfig'] = array( 'glossary' => $glossary );
		$response = wp_remote_post( 'https://translation.googleapis.com/v3/' . $parent . ':translateText', array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json; charset=utf-8', 'x-goog-user-project' => $this->project_id ), 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $response ) ) return $this->failed( $response, $protected_text );
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || empty( $data['translations'][0]['translatedText'] ) ) {
			$message = sanitize_text_field( $data['error']['message'] ?? __( 'Google did not return a translation.', 'pandatranslate' ) );
			return $this->failed( new WP_Error( 'pandatranslate_google_response', $message ), $protected_text );
		}
		$translated = html_entity_decode( (string) $data['translations'][0]['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$characters = function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $protected_text ) ) : strlen( wp_strip_all_tags( $protected_text ) );
		do_action( 'pandatranslate_provider_event', $this->id(), 'success', $characters, 0, '', $this->word_count( $protected_text ) );
		return $translated;
	}

	private function translate_v2( $protected_text, $source_language, $target_language ) {
		$body = array( 'q' => array( (string) $protected_text ), 'target' => sanitize_key( $target_language ), 'format' => 'text' ); if ( $source_language ) $body['source'] = sanitize_key( $source_language );
		$response = wp_remote_post( 'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode( $this->api_key() ), array( 'timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ), 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $response ) ) return $this->failed( $response, $protected_text );
		$status = wp_remote_retrieve_response_code( $response ); $data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || empty( $data['data']['translations'][0]['translatedText'] ) ) return $this->failed( new WP_Error( 'pandatranslate_google_v2_response', sanitize_text_field( $data['error']['message'] ?? __( 'Google v2 did not return a translation.', 'pandatranslate' ) ) ), $protected_text );
		$translated = html_entity_decode( (string) $data['data']['translations'][0]['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$characters = function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $protected_text ) ) : strlen( wp_strip_all_tags( $protected_text ) );
		do_action( 'pandatranslate_provider_event', 'google-cloud-translation-v2', 'success', $characters, 0, '', $this->word_count( $protected_text ) );
		return $translated;
	}

	private function api_key() {
		$key = defined( 'PANDATRANSLATE_GOOGLE_API_KEY' ) ? PANDATRANSLATE_GOOGLE_API_KEY : '';
		return trim( (string) apply_filters( 'pandatranslate_google_api_key', $key ) );
	}

	private function credentials() {
		$path = defined( 'PANDATRANSLATE_GOOGLE_CREDENTIALS_FILE' ) ? PANDATRANSLATE_GOOGLE_CREDENTIALS_FILE : '';
		$path = apply_filters( 'pandatranslate_google_credentials_file', $path );
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) return new WP_Error( 'pandatranslate_google_credentials', __( 'Define PANDATRANSLATE_GOOGLE_CREDENTIALS_FILE in wp-config.php with a readable service-account JSON path.', 'pandatranslate' ) );
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['client_email'] ) || empty( $data['private_key'] ) || empty( $data['token_uri'] ) ) return new WP_Error( 'pandatranslate_google_credentials_invalid', __( 'The Google service-account file is incomplete.', 'pandatranslate' ) );
		if ( 'https://oauth2.googleapis.com/token' !== $data['token_uri'] ) return new WP_Error( 'pandatranslate_google_token_uri', __( 'The service-account token endpoint is not allowed.', 'pandatranslate' ) );
		return $data;
	}

	private function access_token() {
		$credentials = $this->credentials();
		if ( is_wp_error( $credentials ) ) return $credentials;
		$cache_key = 'pandatranslate_google_' . md5( $credentials['client_email'] . '|' . $this->project_id );
		$cached = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) return $cached;
		$now = time();
		$header = $this->base64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claim = $this->base64url( wp_json_encode( array( 'iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/cloud-translation', 'aud' => $credentials['token_uri'], 'iat' => $now, 'exp' => $now + 3600 ) ) );
		$unsigned = $header . '.' . $claim;
		$signature = '';
		if ( ! function_exists( 'openssl_sign' ) || ! openssl_sign( $unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256 ) ) return new WP_Error( 'pandatranslate_google_signing', __( 'The server could not sign the Google authentication request.', 'pandatranslate' ) );
		$response = wp_remote_post( $credentials['token_uri'], array( 'timeout' => 20, 'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . $this->base64url( $signature ) ) ) );
		if ( is_wp_error( $response ) ) return $response;
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $data['access_token'] ) ) return new WP_Error( 'pandatranslate_google_oauth', sanitize_text_field( $data['error_description'] ?? __( 'Google authentication failed.', 'pandatranslate' ) ) );
		set_transient( $cache_key, $data['access_token'], max( 60, absint( $data['expires_in'] ?? 3600 ) - 120 ) );
		return $data['access_token'];
	}

	private function failed( WP_Error $error, $text ) {
		$characters = function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $text ) ) : strlen( wp_strip_all_tags( $text ) );
		do_action( 'pandatranslate_provider_event', $this->id(), 'error', $characters, 0, $error->get_error_code(), $this->word_count( $text ) );
		return $error;
	}

	private function base64url( $value ) { return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); }
	private function word_count( $text ) { return preg_match_all( '/[\p{L}\p{N}]+/u', wp_strip_all_tags( (string) $text ), $matches ); }
}
