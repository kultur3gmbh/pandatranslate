<?php
defined( 'ABSPATH' ) || exit;

/** Explicit static template adapters. Never inspect rendered mail, recipients, orders or arbitrary options. */
final class PandaTranslate_Stored_Templates {
	private static $collecting = false;
	private static $language = '';
	private static $registration = array( 'submission_message', 'customer_submission_message', 'confirmation_subject', 'confirmation_message', 'verified_message', 'customer_verified_message', 'newsletter_subject', 'newsletter_message', 'newsletter_success', 'newsletter_verified', 'newsletter_already_registered', 'newsletter_privacy_text', 'newsletter_consent_text', 'newsletter_submit_label', 'newsletter_error' );
	public static function boot() {
		add_filter( 'pandatranslate_static_templates_collect', array( __CLASS__, 'collect' ), 20 );
		add_filter( 'option_contactpanda_registration_settings', array( __CLASS__, 'registration' ), 99 );
		add_filter( 'contactpanda_system_email_settings', array( __CLASS__, 'email_frame' ), 99 );
		add_action( 'admin_post_pandatranslate_collect_templates', array( __CLASS__, 'collect_action' ) );
	}
	public static function collect_action() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permission required to start this translation run.', 'pandatranslate' ) );
		check_admin_referer( 'pandatranslate_collect_templates' );
		apply_filters( 'pandatranslate_static_templates_collect', 0 );
		wp_safe_redirect( admin_url( 'admin.php?page=pandatranslate-texts' ) ); exit;
	}
	public static function collect_button() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pandatranslate_collect_templates">';
		wp_nonce_field( 'pandatranslate_collect_templates' );
		echo '<button class="contactpanda-large-action" data-contactpanda-action="search" data-contactpanda-action-mode="labelled">' . esc_html__( 'Collect stored templates', 'pandatranslate' ) . '</button></form>';
	}
	/** A mail integration must establish the recipient's saved language before reading or expanding templates. */
	public static function with_language( $language, callable $callback ) {
		$previous = self::$language; $previous_mail = PandaTranslate_Strings::$email_language;
		self::$language = self::valid_language( $language ) ?: PandaTranslate_Plugin::get_settings()['source_language']; PandaTranslate_Strings::$email_language = self::$language;
		$switched = false;
		foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] === self::$language ) {
			$locale = $row['locale']; if ( 'formal' === ( $row['formality'] ?? '' ) && ! preg_match( '/_formal$/', $locale ) ) $locale .= '_formal';
			$switched = switch_to_locale( $locale ); break;
		}
		try { return $callback(); } finally { self::$language = $previous; PandaTranslate_Strings::$email_language = $previous_mail; if ( $switched ) restore_previous_locale(); }
	}
	private static function valid_language( $language ) {
		if ( ! is_string( $language ) || '' === $language ) return '';
		$source = PandaTranslate_Plugin::get_settings()['source_language'];
		return $language === $source || PandaTranslate_Edition::allows_language( $language ) ? $language : '';
	}
	private static function language() {
		if ( self::$collecting ) return '';
		$language = self::$language ?: PandaTranslate_Strings::$email_language;
		if ( ! $language && ! is_admin() && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) $language = PandaTranslate_Frontend::current_language();
		return self::valid_language( $language );
	}
	public static function unit( $source, $id, $domain, $html = false ) {
		$context = sanitize_text_field( $domain . '|' . $id ); $context = function_exists( 'mb_substr' ) ? mb_substr( $context, 0, 100, 'UTF-8' ) : substr( $context, 0, 100 );
		return array( 'key' => hash( 'sha256', wp_json_encode( array( 'stored-template-v1', $domain, $id, $html ? 'html' : 'text', $source ) ) ), 'source_hash' => hash( 'sha256', $source ), 'source_html' => $source, 'source_kind' => $html ? 'stored-template-html' : 'stored-template-text', 'source_domain' => $context );
	}
	/** Published exact-source lookup only. No capture, option writes, API calls or personalized values here. */
	public static function translate( $source, $id, $domain, $html = false, $language = '' ) {
		if ( ! is_string( $source ) || ! trim( $source ) ) return $source;
		$language = $language ? self::valid_language( $language ) : self::language();
		if ( ! $language || $language === PandaTranslate_Plugin::get_settings()['source_language'] ) return $source;
		$unit = self::unit( $source, $id, $domain, $html );
		$row = ( new PandaTranslate_Repository() )->find( $unit['key'], $language, $unit['source_hash'] );
		if ( ! $row || 'published' !== $row['status'] || '' === trim( $row['translated_html'] ) ) return $source;
		$protected = PandaTranslate_Template_Protector::prepare_row( $unit );
		// Published entries may predate this adapter. Recheck placeholder identity before output.
		$target = PandaTranslate_Template_Protector::prepare_row( array_merge( $unit, array( 'source_html' => $row['translated_html'] ) ) );
		$a = array_values( $protected['tokens'] ); $b = array_values( $target['tokens'] ); sort( $a ); sort( $b );
		return $a === $b && PandaTranslate_Strings::same_placeholders( $source, $row['translated_html'] ) ? $row['translated_html'] : $source;
	}
	public static function store( $source, $id, $domain, $html, $language ) {
		if ( ! is_string( $source ) || strlen( $source ) > 64000 || ! PandaTranslate_Template_Protector::has_text( $source ) || ! PandaTranslate_Edition::allows_language( $language ) ) return false;
		$unit = self::unit( $source, $id, $domain, $html ); $repo = new PandaTranslate_Repository();
		if ( $repo->find( $unit['key'], $language ) ) return false;
		$saved = $repo->save( $unit, $language, '', 'untranslated' ); return is_wp_error( $saved ) ? $saved : true;
	}
	public static function registration( $settings ) {
		if ( ! is_array( $settings ) || ! self::language() ) return $settings;
		foreach ( self::$registration as $key ) if ( isset( $settings[$key] ) ) $settings[$key] = self::translate( $settings[$key], $key, 'contactpanda-registration', false !== strpos( $key, 'message' ) );
		return $settings;
	}
	public static function email_frame( $settings ) {
		if ( ! is_array( $settings ) || ! self::language() ) return $settings;
		return self::frame_fields( $settings, function ( $text, $id, $html ) { return self::translate( $text, $id, 'contactpanda-email-frame', $html ); } );
	}
	private static function frame_fields( array $settings, callable $callback ) {
		foreach ( array( 'header_text', 'signature', 'footer_text' ) as $key ) if ( isset( $settings[$key] ) ) $settings[$key] = $callback( $settings[$key], $key, true );
		foreach ( array( 'header_blocks', 'footer_blocks' ) as $group ) if ( isset( $settings[$group] ) && is_array( $settings[$group] ) ) $settings[$group] = self::frame_blocks( $settings[$group], $group, $callback );
		return $settings;
	}
	private static function frame_blocks( array $blocks, $path, callable $callback, $depth = 0 ) {
		foreach ( array_slice( $blocks, 0, 12, true ) as $index => $block ) {
			if ( ! is_array( $block ) ) continue;
			$id = $path . '.' . $index; $type = $block['type'] ?? '';
			if ( 'text' === $type && isset( $block['content'] ) ) $blocks[$index]['content'] = $callback( $block['content'], $id . '.content', true );
			if ( 'image' === $type && isset( $block['alt'] ) ) $blocks[$index]['alt'] = $callback( $block['alt'], $id . '.alt', false );
			if ( 'columns' === $type && 0 === $depth ) foreach ( array_slice( (array) ( $block['columns'] ?? array() ), 0, 3, true ) as $column => $children ) if ( is_array( $children ) ) $blocks[$index]['columns'][$column] = self::frame_blocks( $children, $id . '.columns.' . $column, $callback, $depth + 1 );
		}
		return $blocks;
	}
	public static function collect( $count ) {
		self::$collecting = true;
		try {
			$definitions = array(); $registration = (array) get_option( 'contactpanda_registration_settings', array() );
			foreach ( self::$registration as $key ) if ( isset( $registration[$key] ) ) $definitions[] = array( 'source' => $registration[$key], 'id' => $key, 'domain' => 'contactpanda-registration', 'html' => false !== strpos( $key, 'message' ) );
			$frame = (array) get_option( 'contactpanda_system_email_template', array() );
			self::frame_fields( $frame, function ( $text, $id, $html ) use ( &$definitions ) { $definitions[] = array( 'source' => $text, 'id' => $id, 'domain' => 'contactpanda-email-frame', 'html' => $html ); return $text; } );
			// Integrations register explicit static definitions, not arbitrary options or rendered customer mail.
			$definitions = (array) apply_filters( 'pandatranslate_stored_templates', $definitions );
			foreach ( array_slice( $definitions, 0, 1000 ) as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['source'], $entry['id'], $entry['domain'] ) || ! is_string( $entry['id'] ) || ! is_string( $entry['domain'] ) ) continue;
				foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( ! empty( $row['active'] ) && true === self::store( $entry['source'], $entry['id'], $entry['domain'], ! empty( $entry['html'] ), $row['code'] ) ) $count++;
			}
		} finally { self::$collecting = false; }
		return $count;
	}
}
