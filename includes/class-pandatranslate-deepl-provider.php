<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_DeepL_Provider implements PandaTranslate_Provider {
	public function id() { return 'deepl'; }
	public function configured() { return $this->key() ? true : new WP_Error( 'pandatranslate_deepl_key', __( 'Enter a DeepL API key.', 'pandatranslate' ) ); }
	private function key() { return trim( (string) apply_filters( 'pandatranslate_deepl_api_key', defined( 'PANDATRANSLATE_DEEPL_API_KEY' ) ? PANDATRANSLATE_DEEPL_API_KEY : '' ) ); }
	public function translate( $text, $source_language, $target_language, array $context = array() ) {
		$ready = $this->configured(); if ( is_wp_error( $ready ) ) return $ready;
		$settings = PandaTranslate_Plugin::get_settings(); $endpoint = 'free' === ( $settings['deepl_endpoint'] ?? 'free' ) ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
		$target_language = $context['requested_language'] ?? $target_language;
		$target = strtoupper( str_replace( '_', '-', $target_language ) ); $source = strtoupper( explode( '-', str_replace( '_', '-', $source_language ) )[0] );
		if ( ! in_array( $target, array( 'EN-US', 'EN-GB', 'PT-BR', 'PT-PT', 'ZH-HANS', 'ZH-HANT' ), true ) ) $target = explode( '-', $target )[0];
		$xml = htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		$xml = preg_replace( '/(\[\[PT:[^\]]+\]\])/', '<pt>$1</pt>', $xml );
		$body = array( 'text' => array( '<text>' . $xml . '</text>' ), 'source_lang' => $source, 'target_lang' => $target, 'tag_handling' => 'xml', 'ignore_tags' => array( 'pt' ), 'preserve_formatting' => true );
		if ( ! $source_language ) unset( $body['source_lang'] );
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] === $target_language && 'formal' === ( $row['formality'] ?? '' ) ) $body['formality'] = 'prefer_more';
		$response = wp_remote_post( $endpoint, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Authorization' => 'DeepL-Auth-Key ' . $this->key(), 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $response ) ) return new WP_Error( 'pandatranslate_deepl_connection', __( 'The DeepL API could not be reached.', 'pandatranslate' ) );
		$code = wp_remote_retrieve_response_code( $response ); $data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! isset( $data['translations'][0]['text'] ) ) {
			$message = 456 === $code ? __( 'The DeepL character quota has been reached.', 'pandatranslate' ) : ( 429 === $code ? __( 'DeepL is temporarily limiting requests. Retry later.', 'pandatranslate' ) : __( 'The DeepL API rejected the request. Check API key, plan and language.', 'pandatranslate' ) );
			do_action( 'pandatranslate_provider_event', 'deepl', 'error', 0, 0, $message, 0 ); return new WP_Error( 'pandatranslate_deepl_' . $code, $message );
		}
		$translated = html_entity_decode( preg_replace( '~</?(?:text|pt)(?:\s[^>]*)?>~', '', $data['translations'][0]['text'] ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
		$characters = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		do_action( 'pandatranslate_provider_event', 'deepl', 'success', $data['translations'][0]['billed_characters'] ?? $characters, 0, '', str_word_count( $text ) );
		return $translated;
	}
}
