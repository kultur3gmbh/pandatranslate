<?php
defined( 'ABSPATH' ) || exit;

/** Parse literal gettext calls; never execute source files or inspect customer/order data. */
final class PandaTranslate_Static_Inventory {
	public static function boot() { add_action( 'rest_api_init', function () { register_rest_route( 'pandatranslate/v1', '/texts/collect', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'batch' ), 'permission_callback' => function () { return current_user_can( 'manage_options' ); } ) ); } ); }
	public static function literals( $php ) {
		$tokens = token_get_all( $php ); $found = array(); $functions = array( '__' => array( 0, 1 ), '_e' => array( 0, 1 ), 'esc_html__' => array( 0, 1 ), 'esc_attr__' => array( 0, 1 ), 'esc_html_e' => array( 0, 1 ), 'esc_attr_e' => array( 0, 1 ), '_x' => array( 0, 2, 1 ), '_ex' => array( 0, 2, 1 ), 'esc_html_x' => array( 0, 2, 1 ), 'esc_attr_x' => array( 0, 2, 1 ) );
		for ( $i = 0, $count = count( $tokens ); $i < $count; $i++ ) {
			if ( ! is_array( $tokens[$i] ) || T_STRING !== $tokens[$i][0] || ! isset( $functions[$tokens[$i][1]] ) ) continue;
			$spec = $functions[$tokens[$i][1]]; $j = $i + 1; while ( $j < $count && is_array( $tokens[$j] ) && T_WHITESPACE === $tokens[$j][0] ) $j++;
			if ( '(' !== ( $tokens[$j] ?? null ) ) continue; $depth = 1; $args = array(); $argument = ''; $literal = true;
			for ( $j++; $j < $count; $j++ ) {
				$token = $tokens[$j];
				if ( '(' === $token ) $depth++; if ( ')' === $token ) $depth--;
				if ( 0 === $depth || ( 1 === $depth && ',' === $token ) ) { $args[] = $literal && $argument ? self::decode( $argument ) : null; $argument = ''; $literal = true; if ( 0 === $depth ) break; continue; }
				if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
				if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] && '' === $argument ) $argument = $token[1]; else $literal = false;
			}
			$text = $args[$spec[0]] ?? null; $domain = $args[$spec[1]] ?? ( count( $args ) <= $spec[1] ? 'default' : null ); $context = isset( $spec[2] ) ? ( $args[$spec[2]] ?? null ) : '';
			if ( is_string( $text ) && trim( $text ) && strlen( $text ) <= 4000 && is_string( $domain ) && is_string( $context ) && ! in_array( $domain, array( 'pandatranslate', 'pandatranslate-multilingual' ), true ) ) $found[] = array( 'text' => $text, 'domain' => $domain, 'context' => $context );
		}
		return $found;
	}
	private static function decode( $literal ) { $quote = $literal[0]; $inside = substr( $literal, 1, -1 ); return "'" === $quote ? str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $inside ) : stripcslashes( $inside ); }
	private static function files() {
		$roots = array( ABSPATH . WPINC, get_stylesheet_directory(), get_template_directory() );
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) { $file = WP_PLUGIN_DIR . '/' . $plugin; $roots[] = false === strpos( $plugin, '/' ) ? $file : dirname( $file ); }
		$files = array(); foreach ( array_unique( $roots ) as $root ) {
			if ( is_file( $root ) ) { $files[] = $root; continue; } if ( ! is_dir( $root ) ) continue;
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) if ( $file->isFile() && ! $file->isLink() && 'php' === $file->getExtension() && $file->getSize() <= 512000 && ! preg_match( '#/(?:vendor|node_modules|tests|\.git)/#', $file->getPathname() ) ) $files[] = $file->getPathname();
		}
		sort( $files ); return array_values( array_unique( $files ) );
	}
	public static function store( $entry, $language, $native_locale = false ) {
		$unit = array( 'key' => hash( 'sha256', 'gettext|' . $entry['domain'] . '|' . $entry['context'] . '|' . $entry['text'] ), 'source_hash' => hash( 'sha256', $entry['text'] ), 'source_html' => $entry['text'], 'source_kind' => 'gettext', 'source_domain' => $entry['domain'] );
		$repo = new PandaTranslate_Repository(); if ( $repo->find( $unit['key'], $language ) ) return false;
		$translated = $entry['context'] ? translate_with_gettext_context( $entry['text'], $entry['context'], $entry['domain'] ) : translate( $entry['text'], $entry['domain'] ); $native = $native_locale && $translated !== $entry['text'] && PandaTranslate_Strings::same_placeholders( $entry['text'], $translated );
		$saved = $repo->save( $unit, $language, $native ? $translated : '', $native ? 'published' : 'untranslated', $native ? 'native-locale' : '' ); return is_wp_error( $saved ) ? $saved : true;
	}
	public static function batch( $request ) {
		// A frozen, user-bound file list prevents source-directory changes from skipping files on resume.
		$job_id = sanitize_key( $request['job'] ?? '' ); $job = $job_id ? get_transient( 'pandatranslate_static_' . $job_id ) : false;
		if ( ! $job_id ) { $job_id = wp_generate_uuid4(); $job = array( 'owner' => get_current_user_id(), 'files' => self::files(), 'cursor' => 0, 'created' => 0, 'errors' => 0 ); }
		if ( ! $job || $job['owner'] !== get_current_user_id() ) return new WP_Error( 'pandatranslate_inventory_expired', __( 'The text collection session expired. Restarting preserves existing translations.', 'pandatranslate' ), array( 'status' => 409 ) );
		$files = array_slice( $job['files'], $job['cursor'], 3 );
		foreach ( $files as $file ) {
			$source = is_readable( $file ) ? file_get_contents( $file ) : false;
			if ( false === $source ) { $job['errors']++; $job['cursor']++; continue; }
			$entries = self::literals( $source );
			foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( ! empty( $row['active'] ) && $row['code'] !== PandaTranslate_Plugin::get_settings()['source_language'] ) {
				$switched = switch_to_locale( $row['locale'] );
				try { foreach ( $entries as $entry ) { $saved = self::store( $entry, $row['code'], $switched || get_locale() === $row['locale'] ); if ( is_wp_error( $saved ) ) $job['errors']++; elseif ( $saved ) $job['created']++; } } finally { if ( $switched ) restore_previous_locale(); }
			}
			$job['cursor']++;
		}
		$done = $job['cursor'] >= count( $job['files'] );
		if ( $done && empty( $job['templates_collected'] ) ) { $job['created'] += (int) apply_filters( 'pandatranslate_static_templates_collect', 0 ); $job['templates_collected'] = true; }
		set_transient( 'pandatranslate_static_' . $job_id, $job, DAY_IN_SECONDS );
		return rest_ensure_response( array( 'job' => $job_id, 'processed' => $job['cursor'], 'total' => count( $job['files'] ), 'created' => $job['created'], 'errors' => $job['errors'], 'done' => $done ) );
	}
}
