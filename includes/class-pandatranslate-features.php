<?php
defined( 'ABSPATH' ) || exit;

final class PandaTranslate_Features {
	const GLOSSARY_OPTION = 'pandatranslate_glossary';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_menu_block' ), 20 );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_menu' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'register_wpbakery_menu' ) );
		add_action( 'fusion_builder_before_init', array( __CLASS__, 'register_avada_menu' ) );
		add_action( 'et_builder_ready', array( __CLASS__, 'register_divi_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'menu_meta_box' ) );
		add_filter( 'nav_menu_link_attributes', array( __CLASS__, 'dynamic_menu_link' ), 10, 4 );
		add_filter( 'nav_menu_item_title', array( __CLASS__, 'dynamic_menu_title' ), 10, 4 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'hide_current_menu_language' ), 10, 2 );
		add_filter( 'wp_setup_nav_menu_item', array( __CLASS__, 'prepare_menu_item' ), 20 );
		add_filter( 'wp_get_nav_menu_items', array( __CLASS__, 'hide_current_menu_language' ), 20 );
		add_filter( 'walker_nav_menu_start_el', array( __CLASS__, 'render_menu_switcher_item' ), 10, 4 );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'register_wpbakery' ) );
		add_action( 'fusion_builder_before_init', array( __CLASS__, 'register_avada' ) );
		add_action( 'et_builder_ready', array( __CLASS__, 'register_divi' ) );
		add_filter( 'bricks/builder/i18n', array( __CLASS__, 'register_bricks_label' ) );
		add_action( 'init', array( __CLASS__, 'register_bricks' ), 30 );
		add_action( 'init', array( __CLASS__, 'register_beaver' ), 30 );
	}

	public static function catalog() {
		$catalog = array(
			'de' => array( 'name' => 'Deutsch', 'locale' => 'de_DE' ), 'de-de' => array( 'name' => 'Deutsch (Deutschland)', 'locale' => 'de_DE' ), 'de-at' => array( 'name' => 'Deutsch (Österreich)', 'locale' => 'de_AT' ), 'de-ch' => array( 'name' => 'Deutsch (Schweiz)', 'locale' => 'de_CH' ), 'de-de-formal' => array( 'name' => 'Deutsch (Deutschland, formell)', 'locale' => 'de_DE', 'formality' => 'formal' ),
			'en' => array( 'name' => 'English', 'locale' => 'en_US' ), 'en-us' => array( 'name' => 'English (United States)', 'locale' => 'en_US' ), 'en-gb' => array( 'name' => 'English (United Kingdom)', 'locale' => 'en_GB' ), 'fr' => array( 'name' => 'Français', 'locale' => 'fr_FR' ),
			'it' => array( 'name' => 'Italiano', 'locale' => 'it_IT' ), 'es' => array( 'name' => 'Español', 'locale' => 'es_ES' ), 'pt' => array( 'name' => 'Português', 'locale' => 'pt_PT' ),
			'nl' => array( 'name' => 'Nederlands', 'locale' => 'nl_NL' ), 'pl' => array( 'name' => 'Polski', 'locale' => 'pl_PL' ), 'cs' => array( 'name' => 'Čeština', 'locale' => 'cs_CZ' ),
			'sk' => array( 'name' => 'Slovenčina', 'locale' => 'sk_SK' ), 'sl' => array( 'name' => 'Slovenščina', 'locale' => 'sl_SI' ), 'hu' => array( 'name' => 'Magyar', 'locale' => 'hu_HU' ),
			'hr' => array( 'name' => 'Hrvatski', 'locale' => 'hr_HR' ), 'ro' => array( 'name' => 'Română', 'locale' => 'ro_RO' ), 'bg' => array( 'name' => 'Български', 'locale' => 'bg_BG' ),
			'el' => array( 'name' => 'Ελληνικά', 'locale' => 'el_GR' ), 'tr' => array( 'name' => 'Türkçe', 'locale' => 'tr_TR' ), 'uk' => array( 'name' => 'Українська', 'locale' => 'uk_UA' ),
			'ru' => array( 'name' => 'Русский', 'locale' => 'ru_RU' ), 'ar' => array( 'name' => 'العربية', 'locale' => 'ar' ), 'he' => array( 'name' => 'עברית', 'locale' => 'he_IL' ),
			'zh' => array( 'name' => '中文', 'locale' => 'zh_CN' ), 'zh-tw' => array( 'name' => '繁體中文', 'locale' => 'zh_TW' ), 'ja' => array( 'name' => '日本語', 'locale' => 'ja' ), 'ko' => array( 'name' => '한국어', 'locale' => 'ko_KR' ),
			'hi' => array( 'name' => 'हिन्दी', 'locale' => 'hi_IN' ), 'bn' => array( 'name' => 'বাংলা', 'locale' => 'bn_BD' ), 'ur' => array( 'name' => 'اردو', 'locale' => 'ur_PK' ), 'fa' => array( 'name' => 'فارسی', 'locale' => 'fa_IR' ),
			'id' => array( 'name' => 'Bahasa Indonesia', 'locale' => 'id_ID' ), 'ms' => array( 'name' => 'Bahasa Melayu', 'locale' => 'ms_MY' ), 'th' => array( 'name' => 'ไทย', 'locale' => 'th_TH' ), 'vi' => array( 'name' => 'Tiếng Việt', 'locale' => 'vi_VN' ),
			'tl' => array( 'name' => 'Filipino', 'locale' => 'fil_PH' ), 'sw' => array( 'name' => 'Kiswahili', 'locale' => 'sw_KE' ), 'af' => array( 'name' => 'Afrikaans', 'locale' => 'af_ZA' ), 'am' => array( 'name' => 'አማርኛ', 'locale' => 'am_ET' ),
			'zu' => array( 'name' => 'isiZulu', 'locale' => 'zu_ZA' ), 'xh' => array( 'name' => 'isiXhosa', 'locale' => 'xh_ZA' ), 'ha' => array( 'name' => 'Hausa', 'locale' => 'ha_NG' ), 'yo' => array( 'name' => 'Yorùbá', 'locale' => 'yo_NG' ),
			'pt-br' => array( 'name' => 'Português (Brasil)', 'locale' => 'pt_BR' ), 'es-mx' => array( 'name' => 'Español (México)', 'locale' => 'es_MX' ), 'es-ar' => array( 'name' => 'Español (Argentina)', 'locale' => 'es_AR' ), 'fr-ca' => array( 'name' => 'Français (Canada)', 'locale' => 'fr_CA' ),
			'qu' => array( 'name' => 'Runa Simi', 'locale' => 'qu_PE' ), 'mn' => array( 'name' => 'Монгол', 'locale' => 'mn_MN' ), 'ne' => array( 'name' => 'नेपाली', 'locale' => 'ne_NP' ), 'si' => array( 'name' => 'සිංහල', 'locale' => 'si_LK' ),
		);
        if ( function_exists( 'get_option' ) ) {
            $locale = get_option( 'WPLANG', 'en_US' ) ?: 'en_US'; $locale = is_string( $locale ) ? $locale : 'en_US';
            $code = strtolower( str_replace( '_', '-', $locale ) );
            if ( ! isset( $catalog[$code] ) && ! in_array( $locale, array_column( $catalog, 'locale' ), true ) && preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8}){0,2}$/', $code ) ) $catalog[$code] = array( 'name' => ( $catalog[explode( '-', $code )[0]]['name'] ?? $locale ) . ' (' . $locale . ')', 'locale' => $locale );
        }
        return $catalog;

	}

	public static function normalize_languages( $rows, $source = 'de' ) {
		$catalog = self::catalog(); $clean = array(); $seen = array();
		foreach ( (array) $rows as $row ) {
			$code = sanitize_key( $row['code'] ?? '' ); if ( ! isset( $catalog[ $code ] ) || isset( $seen[ $code ] ) ) continue; $seen[ $code ] = true;
			$clean[] = array( 'code' => $code, 'locale' => $catalog[ $code ]['locale'], 'slug' => sanitize_title( $row['slug'] ?? $code ) ?: $code, 'formality' => in_array( $row['formality'] ?? '', array( 'standard', 'formal', 'informal' ), true ) ? $row['formality'] : ( $catalog[ $code ]['formality'] ?? 'standard' ), 'flag_id' => max( 0, (int) ( $row['flag_id'] ?? 0 ) ), 'active' => empty( $row['active'] ) ? 0 : 1 );
		}
		if ( ! $clean ) $clean[] = array( 'code' => $source, 'locale' => $catalog[ $source ]['locale'] ?? $source, 'slug' => $source, 'formality' => 'standard', 'flag_id' => 0, 'active' => 1 );
		return $clean;
	}
	public static function flag( $code ) { $flags = array( 'de' => '🇩🇪', 'de-de' => '🇩🇪', 'de-de-formal' => '🇩🇪', 'de-at' => '🇦🇹', 'de-ch' => '🇨🇭', 'en' => '🇬🇧', 'en-us' => '🇺🇸', 'en-gb' => '🇬🇧', 'fr' => '🇫🇷', 'fr-ca' => '🇨🇦', 'it' => '🇮🇹', 'es' => '🇪🇸', 'es-mx' => '🇲🇽', 'es-ar' => '🇦🇷', 'pt' => '🇵🇹', 'pt-br' => '🇧🇷', 'nl' => '🇳🇱', 'pl' => '🇵🇱', 'cs' => '🇨🇿', 'sk' => '🇸🇰', 'sl' => '🇸🇮', 'hu' => '🇭🇺', 'hr' => '🇭🇷', 'ro' => '🇷🇴', 'bg' => '🇧🇬', 'el' => '🇬🇷', 'tr' => '🇹🇷', 'uk' => '🇺🇦', 'ru' => '🇷🇺', 'ar' => '🇸🇦', 'he' => '🇮🇱', 'zh' => '🇨🇳', 'zh-tw' => '🇹🇼', 'ja' => '🇯🇵', 'ko' => '🇰🇷', 'hi' => '🇮🇳', 'bn' => '🇧🇩', 'ur' => '🇵🇰', 'fa' => '🇮🇷', 'id' => '🇮🇩', 'ms' => '🇲🇾', 'th' => '🇹🇭', 'vi' => '🇻🇳', 'tl' => '🇵🇭', 'sw' => '🇰🇪', 'af' => '🇿🇦', 'am' => '🇪🇹', 'zu' => '🇿🇦', 'xh' => '🇿🇦', 'ha' => '🇳🇬', 'yo' => '🇳🇬', 'qu' => '🇵🇪', 'mn' => '🇲🇳', 'ne' => '🇳🇵', 'si' => '🇱🇰' ); return $flags[ $code ] ?? ( $flags[ strtok( $code, '-' ) ] ?? '🌐' ); }

	public static function language_name( $code, $native = true ) {
		$entry = self::catalog()[$code] ?? array( 'name' => strtoupper( $code ), 'locale' => $code );
		if ( $native ) return $entry['name'];
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		if ( class_exists( 'Locale' ) ) { $name = Locale::getDisplayName( $entry['locale'], $locale ); if ( $name && $name !== $entry['locale'] ) return $name; }
		$names = array(
			'en' => array( 'de' => 'German', 'en' => 'English', 'fr' => 'French', 'it' => 'Italian', 'es' => 'Spanish', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'pl' => 'Polish', 'cs' => 'Czech', 'sk' => 'Slovak', 'sl' => 'Slovenian', 'hu' => 'Hungarian', 'hr' => 'Croatian', 'ro' => 'Romanian', 'bg' => 'Bulgarian', 'el' => 'Greek', 'tr' => 'Turkish', 'uk' => 'Ukrainian', 'ru' => 'Russian', 'ar' => 'Arabic', 'he' => 'Hebrew', 'zh' => 'Chinese', 'ja' => 'Japanese', 'ko' => 'Korean', 'hi' => 'Hindi', 'bn' => 'Bengali', 'ur' => 'Urdu', 'fa' => 'Persian', 'id' => 'Indonesian', 'ms' => 'Malay', 'th' => 'Thai', 'vi' => 'Vietnamese', 'tl' => 'Filipino', 'sw' => 'Swahili', 'af' => 'Afrikaans', 'am' => 'Amharic', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'ha' => 'Hausa', 'yo' => 'Yoruba', 'qu' => 'Quechua', 'mn' => 'Mongolian', 'ne' => 'Nepali', 'si' => 'Sinhala' ),
			'de' => array( 'de' => 'Deutsch', 'en' => 'Englisch', 'fr' => 'Französisch', 'it' => 'Italienisch', 'es' => 'Spanisch', 'pt' => 'Portugiesisch', 'nl' => 'Niederländisch', 'pl' => 'Polnisch', 'cs' => 'Tschechisch', 'sk' => 'Slowakisch', 'sl' => 'Slowenisch', 'hu' => 'Ungarisch', 'hr' => 'Kroatisch', 'ro' => 'Rumänisch', 'bg' => 'Bulgarisch', 'el' => 'Griechisch', 'tr' => 'Türkisch', 'uk' => 'Ukrainisch', 'ru' => 'Russisch', 'ar' => 'Arabisch', 'he' => 'Hebräisch', 'zh' => 'Chinesisch', 'ja' => 'Japanisch', 'ko' => 'Koreanisch', 'hi' => 'Hindi', 'bn' => 'Bengalisch', 'ur' => 'Urdu', 'fa' => 'Persisch', 'id' => 'Indonesisch', 'ms' => 'Malaiisch', 'th' => 'Thailändisch', 'vi' => 'Vietnamesisch', 'tl' => 'Filipino', 'sw' => 'Suaheli', 'af' => 'Afrikaans', 'am' => 'Amharisch', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'ha' => 'Hausa', 'yo' => 'Yoruba', 'qu' => 'Quechua', 'mn' => 'Mongolisch', 'ne' => 'Nepalesisch', 'si' => 'Singhalesisch' ),
			'fr' => array( 'de' => 'Allemand', 'en' => 'Anglais', 'fr' => 'Français', 'it' => 'Italien', 'es' => 'Espagnol', 'pt' => 'Portugais', 'zh' => 'Chinois', 'ja' => 'Japonais', 'ko' => 'Coréen' ),
			'it' => array( 'de' => 'Tedesco', 'en' => 'Inglese', 'fr' => 'Francese', 'it' => 'Italiano', 'es' => 'Spagnolo', 'pt' => 'Portoghese', 'zh' => 'Cinese', 'ja' => 'Giapponese', 'ko' => 'Coreano' ),
		);
		$base = explode( '-', $code )[0]; $display = substr( $locale, 0, 2 );
		return $names[$display][$base] ?? $names['en'][$base] ?? $entry['name'];
	}
	public static function language_content( $code, $settings ) {
		$mode = $settings['switcher_label_mode']; $name = self::language_name( $code, ! empty( $settings['switcher_native_names'] ) );
		$text = in_array( $mode, array( 'flag_name', 'name', 'native' ), true ) ? $name : ( in_array( $mode, array( 'flag_code', 'code' ), true ) ? strtoupper( $code ) : '' );
		$image = ''; foreach ( PandaTranslate_Plugin::language_rows() as $row ) if ( $row['code'] === $code && ! empty( $row['flag_id'] ) ) $image = wp_get_attachment_image_url( $row['flag_id'], 'thumbnail' );
		$flag = in_array( $mode, array( 'flags', 'flag_name', 'flag_code', 'native' ), true ) ? '<span class="pandatranslate-flag is-' . esc_attr( $settings['switcher_flag_shape'] ) . '" aria-hidden="true">' . ( $image ? '<img src="' . esc_url( $image ) . '" alt="">' : esc_html( self::flag( $code ) ) ) . '</span>' : '';
		$text = $text ? '<span class="pandatranslate-language-name">' . esc_html( $text ) . '</span>' : '';
		return '<span class="pandatranslate-language-content">' . ( 'after' === $settings['switcher_flag_position'] ? $text . $flag : $flag . $text ) . '</span>';
	}
	public static function glossary() { return array_values( (array) get_option( self::GLOSSARY_OPTION, array() ) ); }
	public static function sanitize_glossary( $rows ) {
		$clean = array(); $seen = array();
		foreach ( (array) $rows as $row ) { $term = sanitize_text_field( $row['term'] ?? '' ); if ( '' === $term || isset( $seen[ strtolower( $term ) ] ) ) continue; $seen[ strtolower( $term ) ] = true; $translations = array(); foreach ( (array) ( $row['translations'] ?? array() ) as $language => $translation ) $translations[ sanitize_key( $language ) ] = sanitize_text_field( $translation ); $clean[] = array( 'id' => sanitize_key( $row['id'] ?? wp_generate_uuid4() ), 'term' => $term, 'translations' => $translations, 'protect' => empty( $row['protect'] ) ? 0 : 1, 'case_sensitive' => empty( $row['case_sensitive'] ) ? 0 : 1, 'whole_word' => empty( $row['whole_word'] ) ? 0 : 1, 'active' => empty( $row['active'] ) ? 0 : 1 ); }
		return $clean;
	}

	public static function protect_glossary( $text, $target_language ) {
		$tokens = array(); $index = 0;
		foreach ( self::glossary() as $entry ) {
			if ( empty( $entry['active'] ) || empty( $entry['term'] ) ) continue;
			$replacement = ! empty( $entry['protect'] ) ? $entry['term'] : ( $entry['translations'][ $target_language ] ?? '' ); if ( '' === $replacement ) continue;
			$pattern = '/' . ( ! empty( $entry['whole_word'] ) ? '\b' : '' ) . preg_quote( $entry['term'], '/' ) . ( ! empty( $entry['whole_word'] ) ? '\b' : '' ) . '/' . ( empty( $entry['case_sensitive'] ) ? 'iu' : 'u' );
			$text = preg_replace_callback( $pattern, function () use ( &$tokens, &$index, $replacement ) { $token = '[[PG:' . $index++ . ']]'; $tokens[ $token ] = $replacement; return $token; }, $text );
		}
		return array( 'text' => $text, 'tokens' => $tokens );
	}
	public static function restore_glossary( $text, array $tokens ) { foreach ( $tokens as $token => $value ) { if ( 1 !== substr_count( $text, $token ) ) return new WP_Error( 'pandatranslate_glossary_token', __( 'A glossary placeholder was changed by the provider.', 'pandatranslate' ) ); } return strtr( $text, $tokens ); }

	public static function menu_meta_box() { add_meta_box( 'add-pandatranslate-menu', __( 'PandaTranslate', 'pandatranslate' ), array( __CLASS__, 'render_menu_meta_box' ), 'nav-menus', 'side', 'default' ); }
	public static function render_menu_meta_box() {
		$rows = PandaTranslate_Plugin::language_rows(); $index = -91000;
		echo '<div id="pandatranslate-menu-list" class="posttypediv"><div class="tabs-panel tabs-panel-active"><ul class="categorychecklist form-no-clear">';
		self::menu_option( $index--, __( 'Current language', 'pandatranslate' ), '#pandatranslate:current' );
		self::menu_option( $index--, __( 'Language switcher', 'pandatranslate' ), '#pandatranslate:switcher' );
		foreach ( $rows as $row ) if ( ! empty( $row['active'] ) ) { $name = self::catalog()[ $row['code'] ]['name'] ?? strtoupper( $row['code'] ); self::menu_option( $index--, $name, '#pandatranslate:' . $row['code'] ); }
		echo '</ul></div><p class="button-controls"><span class="list-controls"><label><input type="checkbox" data-pandatranslate-select-all> ' . esc_html__( 'Select all', 'pandatranslate' ) . '</label></span><span class="add-to-menu"><button type="submit" class="button submit-add-to-menu right contactpanda-large-action" data-contactpanda-action="add" data-contactpanda-action-mode="labelled" name="add-custom-menu-item" id="submit-pandatranslate-menu-list">' . '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add to menu', 'pandatranslate' ) . '</button></span><span class="spinner"></span></p></div><script>document.addEventListener("change",function(e){if(e.target.matches("[data-pandatranslate-select-all]"))document.querySelectorAll("#pandatranslate-menu-list .menu-item-checkbox").forEach(function(c){c.checked=e.target.checked;});});</script>';
	}
	private static function menu_option( $index, $title, $url ) { $code = substr( $url, strlen( '#pandatranslate:' ) ); $url = '#pandatranslate-' . $code; echo '<li><label class="menu-item-title"><input type="checkbox" class="menu-item-checkbox" name="menu-item[' . esc_attr( $index ) . '][menu-item-object-id]" value="' . esc_attr( $index ) . '"> ' . esc_html( $title ) . '</label><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-db-id]" value="0"><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-object]" value="custom"><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-parent-id]" value="0"><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-type]" value="custom"><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-title]" value="' . esc_attr( $title ) . '"><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-url]" value="' . esc_attr( $url ) . '"><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-target]" value=""><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-classes]" value="pandatranslate-menu-link pandatranslate-language--' . esc_attr( $code ) . '"><input type="hidden" name="menu-item[' . esc_attr( $index ) . '][menu-item-xfn]" value=""></li>'; }
	private static function current_language() { $s = PandaTranslate_Plugin::get_settings(); return isset( $_GET['pt_lang'] ) ? sanitize_key( wp_unslash( $_GET['pt_lang'] ) ) : ( sanitize_key( get_query_var( 'pt_lang' ) ) ?: $s['source_language'] ); }
	private static function menu_marker( $item ) {
		if ( isset( $item->pandatranslate_marker ) ) return $item->pandatranslate_marker;
		$url = rawurldecode( $item->url ?? '' ); $position = strpos( $url, '#pandatranslate:' );
		if ( false !== $position ) return substr( $url, $position );
		$position = strpos( $url, '#pandatranslate-' ); if ( false !== $position ) return '#pandatranslate:' . substr( $url, $position + strlen( '#pandatranslate-' ) );
		$classes = (array) ( $item->classes ?? array() );
		foreach ( $classes as $class ) if ( 0 === strpos( $class, 'pandatranslate-language--' ) ) return '#pandatranslate:' . substr( $class, strlen( 'pandatranslate-language--' ) );
		// Older releases saved plugin-owned items whose fragment URL was stripped during sanitization.
		if ( ! in_array( 'pandatranslate-menu-link', $classes, true ) ) return '';
		$title = trim( strip_tags( $item->title ?? '' ) );
		if ( in_array( $title, array( 'Current language', 'Aktuelle Sprache', __( 'Current language', 'pandatranslate' ) ), true ) ) return '#pandatranslate:current';
		if ( in_array( $title, array( 'Language switcher', 'Sprachumschalter', __( 'Language switcher', 'pandatranslate' ) ), true ) ) return '#pandatranslate:switcher';
		foreach ( self::catalog() as $code => $entry ) if ( $title === $entry['name'] ) return '#pandatranslate:' . $code;
		return '';
	}
	public static function prepare_menu_item( $item ) {
		if ( is_admin() ) return $item;
		$marker = self::menu_marker( $item ); if ( ! $marker ) return $item;
		$item->pandatranslate_marker = $marker; $code = substr( $marker, strlen( '#pandatranslate:' ) );
		if ( 'switcher' === $code ) $item->classes = array_unique( array_merge( (array) ( $item->classes ?? array() ), array( 'menu-item-has-children' ) ) );
		if ( 'switcher' !== $code ) { if ( 'current' === $code ) $code = self::current_language(); $item->url = PandaTranslate_Plugin::language_url( $code ); $item->title = self::language_content( $code, PandaTranslate_Plugin::switcher_settings( 'menu' ) ); }
		return $item;
	}
	public static function hide_current_menu_language( $items ) {
		if ( is_admin() ) return $items;
		$current = self::current_language(); $removed = array(); $parents = array();
		$visible = array_column( PandaTranslate_Plugin::visible_language_rows(), 'code' );
		foreach ( $items as $item ) if ( ! empty( $item->menu_item_parent ) ) $parents[$item->menu_item_parent] = true;
		foreach ( $items as $key => $item ) {
			$marker = self::menu_marker( $item ); if ( ! $marker ) continue; $item->pandatranslate_marker = $marker;
			$language = substr( $marker, strlen( '#pandatranslate:' ) );
			if ( ( isset( self::catalog()[$language] ) && ! in_array( $language, $visible, true ) ) || ( 'switcher' === $language && count( $visible ) < 2 ) ) { $removed[$item->ID] = $item->menu_item_parent; unset( $items[$key] ); continue; }
			// A current-language parent is the dropdown heading, not an option in its own list.
			if ( '#pandatranslate:current' === $marker && isset( $parents[$item->ID] ) ) { $item->url = PandaTranslate_Plugin::language_url( $current ); $item->title = self::language_content( $current, PandaTranslate_Plugin::switcher_settings( 'menu' ) ); continue; }
			if ( in_array( $marker, array( '#pandatranslate:current', '#pandatranslate:' . $current ), true ) ) { $removed[ $item->ID ] = $item->menu_item_parent; unset( $items[$key] ); continue; }
			$code = substr( $marker, strlen( '#pandatranslate:' ) );
			if ( isset( self::catalog()[$code] ) ) $item->url = PandaTranslate_Plugin::language_url( $code );
		}
		foreach ( $items as $item ) { $seen = array(); while ( isset( $removed[$item->menu_item_parent] ) && ! isset( $seen[$item->menu_item_parent] ) ) { $seen[$item->menu_item_parent] = true; $item->menu_item_parent = $removed[$item->menu_item_parent]; } }
		return array_values( $items );
	}
	public static function dynamic_menu_link( $atts, $item = null ) {
		$url = $item ? self::menu_marker( $item ) : ( $atts['href'] ?? '' );
		if ( 0 !== strpos( $url, '#pandatranslate:' ) ) return $atts;
		$code = substr( $url, strlen( '#pandatranslate:' ) ); if ( 'switcher' === $code ) return $atts;
		if ( 'current' === $code ) $code = self::current_language();
		$atts['href'] = PandaTranslate_Plugin::language_url( $code ); $atts['hreflang'] = $code;
		$atts['aria-label'] = self::catalog()[$code]['name'] ?? strtoupper( $code );
		$atts['class'] = trim( ( $atts['class'] ?? '' ) . ' pandatranslate-language-link' );
		return $atts;
	}
	public static function dynamic_menu_title( $title, $item ) {
		$url = self::menu_marker( $item );
		if ( is_admin() || ! $url ) return $title;
		$code = substr( $url, strlen( '#pandatranslate:' ) ); if ( 'switcher' === $code ) return $title;
		if ( 'current' === $code ) $code = self::current_language();
		return self::language_content( $code, PandaTranslate_Plugin::switcher_settings( 'menu' ) );
	}
	public static function render_menu_switcher_item( $output, $item ) { if ( '#pandatranslate:switcher' !== self::menu_marker( $item ) ) return $output; $s = PandaTranslate_Plugin::switcher_settings( 'menu' ); return do_shortcode( '[pandatranslate_switcher menu="1" inherit_menu="1" display="' . esc_attr( $s['switcher_label_mode'] ) . '" hide_current="1"]' ); }

	public static function register_block() { wp_register_script( 'pandatranslate-block', PANDATRANSLATE_URL . 'assets/block.js', array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-server-side-render' ), PANDATRANSLATE_VERSION, true ); wp_localize_script( 'pandatranslate-block', 'PandaTranslateBlocks', array( 'menuTitle' => __( 'PandaTranslate language menu', 'pandatranslate' ) ) ); register_block_type( 'pandatranslate/switcher', array( 'editor_script' => 'pandatranslate-block', 'render_callback' => function () { return do_shortcode( '[pandatranslate_switcher]' ); } ) ); }
	public static function register_menu_block() { register_block_type( 'pandatranslate/menu', array( 'editor_script' => 'pandatranslate-block', 'render_callback' => function () { return do_shortcode( '[pandatranslate_menu]' ); } ) ); }
	public static function register_elementor_menu( $manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;
		$manager->register( new class extends \Elementor\Widget_Base {
			public function get_name() { return 'pandatranslate-menu'; }
			public function get_title() { return __( 'PandaTranslate language menu', 'pandatranslate' ); }
			public function get_icon() { return 'eicon-globe'; }
			public function get_categories() { return array( 'general' ); }
			protected function render() { echo do_shortcode( '[pandatranslate_menu]' ); }
		} );
	}
	public static function register_wpbakery_menu() { if ( function_exists( 'vc_map' ) ) vc_map( array( 'name' => __( 'PandaTranslate language menu', 'pandatranslate' ), 'base' => 'pandatranslate_menu', 'category' => 'PandaTranslate', 'params' => array() ) ); }
	public static function register_avada_menu() { if ( function_exists( 'fusion_builder_map' ) ) fusion_builder_map( array( 'name' => __( 'PandaTranslate language menu', 'pandatranslate' ), 'shortcode' => 'pandatranslate_menu', 'icon' => 'fusiona-globe', 'params' => array() ) ); }
	public static function register_divi_menu() { if ( ! class_exists( 'ET_Builder_Module' ) ) return; new class extends ET_Builder_Module { public $slug = 'pandatranslate_menu_module'; public $vb_support = 'on'; public function init() { $this->name = __( 'PandaTranslate language menu', 'pandatranslate' ); } public function render( $attrs, $content = null, $render_slug = '' ) { return do_shortcode( '[pandatranslate_menu]' ); } }; }
	public static function register_elementor( $manager ) { if ( ! class_exists( '\Elementor\Widget_Base' ) ) return; $manager->register( new class extends \Elementor\Widget_Base { public function get_name(){return 'pandatranslate-switcher';} public function get_title(){return 'PandaTranslate Sprachumschalter';} public function get_icon(){return 'eicon-globe';} public function get_categories(){return array('general');} protected function render(){echo do_shortcode('[pandatranslate_switcher]');} } ); }
	public static function register_wpbakery() { if ( function_exists( 'vc_map' ) ) vc_map( array( 'name' => 'PandaTranslate Sprachumschalter', 'base' => 'pandatranslate_switcher', 'category' => 'PandaTranslate', 'params' => array() ) ); }
	public static function register_avada() { if ( function_exists( 'fusion_builder_map' ) ) fusion_builder_map( array( 'name' => 'PandaTranslate Sprachumschalter', 'shortcode' => 'pandatranslate_switcher', 'params' => array() ) ); }
	public static function register_divi() { if ( ! class_exists( 'ET_Builder_Module' ) ) return; new class extends ET_Builder_Module { public $slug='pandatranslate_switcher_module'; public $vb_support='on'; public function init(){ $this->name='PandaTranslate Sprachumschalter'; } public function render($attrs,$content=null,$render_slug=''){return do_shortcode('[pandatranslate_switcher]');} }; }
	public static function register_bricks_label( $i18n ) { $i18n['pandatranslateSwitcher'] = 'PandaTranslate Sprachumschalter'; return $i18n; }
	public static function register_bricks() { if ( class_exists( '\Bricks\Elements' ) ) { \Bricks\Elements::register_element( PANDATRANSLATE_DIR . 'includes/class-pandatranslate-bricks-element.php' ); \Bricks\Elements::register_element( PANDATRANSLATE_DIR . 'includes/class-pandatranslate-bricks-menu-element.php' ); } }
	public static function register_beaver() { if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) { require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-beaver-module.php'; require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-beaver-menu-module.php'; FLBuilder::register_module( 'PandaTranslate_Beaver_Module', array() ); FLBuilder::register_module( 'PandaTranslate_Beaver_Menu_Module', array() ); } }
}
