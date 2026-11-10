<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Glossary_Admin {
	public static function routes() {
		register_rest_route( 'pandatranslate/v1', '/glossary', array( 'methods' => 'POST,DELETE', 'callback' => array( __CLASS__, 'save' ), 'permission_callback' => function () { return current_user_can( 'manage_options' ); } ) );
	}
	public static function save( WP_REST_Request $request ) {
		$entries = PandaTranslate_Features::glossary(); $id = sanitize_key( $request['id'] ?? '' );
		$index = null; foreach ( $entries as $key => $entry ) if ( $entry['id'] === $id ) $index = $key;
		if ( 'DELETE' === $request->get_method() ) {
			if ( null === $index ) return new WP_Error( 'pandatranslate_glossary_missing', __( 'Glossary entry not found.', 'pandatranslate' ), array( 'status' => 404 ) );
			unset( $entries[$index] );
		} else {
			if ( $id && null === $index ) return new WP_Error( 'pandatranslate_glossary_missing', __( 'Glossary entry not found.', 'pandatranslate' ), array( 'status' => 404 ) );
			$input = (array) $request->get_json_params(); $input['id'] = $id ?: wp_generate_uuid4();
			$sanitized = PandaTranslate_Features::sanitize_glossary( array( $input ) );
			if ( ! $sanitized ) return new WP_Error( 'pandatranslate_glossary_term', __( 'Enter a source term.', 'pandatranslate' ), array( 'status' => 400 ) );
			foreach ( $entries as $key => $entry ) if ( $key !== $index && 0 === strcasecmp( $entry['term'], $sanitized[0]['term'] ) ) return new WP_Error( 'pandatranslate_glossary_duplicate', __( 'This source term already exists.', 'pandatranslate' ), array( 'status' => 409 ) );
			if ( null === $index ) $entries[] = $sanitized[0]; else $entries[$index] = $sanitized[0];
		}
		$entries = array_values( $entries ); update_option( PandaTranslate_Features::GLOSSARY_OPTION, $entries, false );
		return rest_ensure_response( array( 'entries' => $entries ) );
	}
	public static function render() {
		$targets = array_slice( PandaTranslate_Plugin::language_rows(), 1 );
		echo '<h2>' . esc_html__( 'Glossary', 'pandatranslate' ) . '</h2><p>' . esc_html__( 'Protect product terms or define an exact replacement for each language.', 'pandatranslate' ) . '</p>';
		echo '<p><button type="button" class="contactpanda-large-action" data-contactpanda-action="add" data-contactpanda-action-mode="labelled" data-glossary-new><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__( 'Create glossary entry', 'pandatranslate' ) . '</button></p>';
		echo '<table class="widefat striped pandatranslate-glossary-list"><thead><tr><th>' . esc_html__( 'Source term', 'pandatranslate' ) . '</th><th>' . esc_html__( 'Translations', 'pandatranslate' ) . '</th><th>' . esc_html__( 'Active', 'pandatranslate' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'pandatranslate' ) . '</span></th></tr></thead><tbody data-glossary-list></tbody></table><p data-glossary-message role="status"></p>';
		echo '<script type="application/json" data-glossary-data>' . wp_json_encode( PandaTranslate_Features::glossary(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
		echo '<template data-glossary-modal-template><div class="contactpanda-consent-modal" data-glossary-modal role="dialog" aria-modal="true" aria-labelledby="pandatranslate-glossary-title" hidden><section class="contactpanda-modal-panel"><header class="pandatranslate-modal-header"><h2 id="pandatranslate-glossary-title"></h2><button type="button" class="contactpanda-list-action" data-glossary-close aria-label="' . esc_attr__( 'Close', 'pandatranslate' ) . '"><span class="dashicons dashicons-no-alt"></span></button></header><div class="pandatranslate-glossary-fields"><label>' . esc_html__( 'Source term', 'pandatranslate' ) . '<input type="text" data-glossary-field="term" required></label>';
		foreach ( $targets as $target ) echo '<label>' . esc_html( PandaTranslate_Features::language_name( $target['code'] ) ) . '<input type="text" data-glossary-language="' . esc_attr( $target['code'] ) . '"></label>';
		foreach ( array( 'protect' => __( 'Do not translate', 'pandatranslate' ), 'case_sensitive' => __( 'Case-sensitive', 'pandatranslate' ), 'whole_word' => __( 'Whole words only', 'pandatranslate' ), 'active' => __( 'Active', 'pandatranslate' ) ) as $key => $label ) echo '<label class="pandatranslate-check"><input type="checkbox" data-glossary-field="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '</div><p data-glossary-error role="alert"></p><div class="contactpanda-modal-actions"><button type="button" class="contactpanda-large-action" data-contactpanda-action="save" data-contactpanda-action-mode="labelled" data-glossary-save><span class="dashicons dashicons-saved"></span>' . esc_html__( 'Save entry', 'pandatranslate' ) . '</button><button type="button" class="contactpanda-large-action" data-contactpanda-action="edit" data-contactpanda-action-mode="labelled" data-glossary-edit><span class="dashicons dashicons-edit"></span>' . esc_html__( 'Edit entry', 'pandatranslate' ) . '</button></div></section></div></template>';
	}
}
