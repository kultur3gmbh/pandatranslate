<?php
defined( 'ABSPATH' ) || exit;

/** Named API instances. The registry key is carried through each translation job. */
final class PandaTranslate_Providers {
	public static function all() {
		$s = PandaTranslate_Plugin::get_settings();
		$entries = array(
			'google-v2' => array( 'label' => __( 'Google Translate v2', 'pandatranslate' ), 'provider' => new PandaTranslate_Google_Provider( $s['google_project_id'], $s['google_location'], 'v2' ), 'enabled' => (bool) $s['google_v2_enabled'] ),
			'google-v3' => array( 'label' => __( 'Google Translate v3', 'pandatranslate' ), 'provider' => new PandaTranslate_Google_Provider( $s['google_project_id'], $s['google_location'], 'v3' ), 'enabled' => (bool) $s['google_v3_enabled'] ),
		);
		if ( class_exists( 'PandaTranslate_DeepL_Provider' ) ) $entries['deepl'] = array( 'label' => 'DeepL', 'provider' => new PandaTranslate_DeepL_Provider(), 'enabled' => ! empty( $s['deepl_enabled'] ) );
		// Existing integrations can register a configured instance, class, or a labelled descriptor.
		foreach ( (array) apply_filters( 'pandatranslate_providers', array() ) as $id => $entry ) {
			if ( 'google-cloud-translation' === $id ) continue;
			$descriptor = is_array( $entry ) ? $entry : array( 'provider' => $entry );
			$provider = $descriptor['provider'] ?? null;
			try { if ( is_string( $provider ) && class_exists( $provider ) ) $provider = new $provider(); } catch ( Throwable $error ) { continue; }
			if ( ! $provider instanceof PandaTranslate_Provider || sanitize_key( $id ) !== $id ) continue;
			$entries[$id] = array( 'label' => sanitize_text_field( $descriptor['label'] ?? $provider->id() ), 'provider' => $provider, 'enabled' => $descriptor['enabled'] ?? true );
		}
		return $entries;
	}
	public static function available() {
		return array_filter( self::all(), function ( $entry ) { return $entry['enabled'] && ( ! method_exists( $entry['provider'], 'configured' ) || true === $entry['provider']->configured() ); } );
	}
	public static function default_id() {
		$s = PandaTranslate_Plugin::get_settings(); $available = self::available();
		$id = $s['auto_provider'] ?: 'google-' . $s['google_api_version'];
		return isset( $available[$id] ) ? $id : ( array_key_first( $available ) ?? '' );
	}
	public static function get( $id = '' ) {
		$id = $id ?: self::default_id(); $entries = self::all();
		if ( ! isset( $entries[$id] ) || ! $entries[$id]['enabled'] ) return new WP_Error( 'pandatranslate_api_unavailable', __( 'The selected translation API is not enabled.', 'pandatranslate' ) );
		$provider = $entries[$id]['provider'];
		$ready = method_exists( $provider, 'configured' ) ? $provider->configured() : true;
		return is_wp_error( $ready ) ? $ready : $provider;
	}
	public static function label( $id ) { return self::all()[$id]['label'] ?? $id; }
	public static function choices() { $choices = array(); foreach ( self::available() as $id => $entry ) $choices[$id] = $entry['label']; return $choices; }
}
