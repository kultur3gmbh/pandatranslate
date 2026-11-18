<?php
/**
 * Plugin Name: Panda Translate
 * Description: Visual website translation with language paths, menus and builder elements.
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Version: 0.7.5
 * Author: Kultur3 GmbH
 * Plugin URI: https://pandacosmo.com/
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pandatranslate
 */

defined( 'ABSPATH' ) || exit;

define( 'PANDATRANSLATE_FILE', __FILE__ );
define( 'PANDATRANSLATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PANDATRANSLATE_URL', plugin_dir_url( __FILE__ ) );
define( 'PANDATRANSLATE_VERSION', '0.7.5' );

// Resolve feature classes only when their callbacks are invoked.
spl_autoload_register( static function ( $class ) {
	static $map = array(
		'PandaTranslate_Beaver_Menu_Module' => 'class-pandatranslate-beaver-menu-module.php',
		'PandaTranslate_Beaver_Module' => 'class-pandatranslate-beaver-module.php',
		'PandaTranslate_Bricks_Element' => 'class-pandatranslate-bricks-element.php',
		'PandaTranslate_Bricks_Menu_Element' => 'class-pandatranslate-bricks-menu-element.php',
		'PandaTranslate_DeepL_Provider' => 'class-pandatranslate-deepl-provider.php',
		'PandaTranslate_Edition' => 'class-pandatranslate-edition.php',
		'PandaTranslate_Family_UI' => 'class-pandatranslate-family-ui.php',
		'PandaTranslate_Features' => 'class-pandatranslate-features.php',
		'PandaTranslate_Frontend' => 'class-pandatranslate-frontend.php',
		'PandaTranslate_Glossary_Admin' => 'class-pandatranslate-glossary-admin.php',
		'PandaTranslate_Google_Provider' => 'class-pandatranslate-google-provider.php',
		'PandaTranslate_HTML' => 'class-pandatranslate-html.php',
		'PandaTranslate_Inline_Protector' => 'class-pandatranslate-inline-protector.php',
		'PandaTranslate_Language_Content' => 'class-pandatranslate-language-content.php',
		'PandaTranslate_MCP' => 'class-pandatranslate-mcp.php',
		'PandaTranslate_Media' => 'class-pandatranslate-media.php',
		'PandaTranslate_Migration_Sources' => 'class-pandatranslate-migration-sources.php',
		'PandaTranslate_Migration' => 'class-pandatranslate-migration.php',
		'PandaTranslate_Plugin' => 'class-pandatranslate-plugin.php',
		'PandaTranslate_Provider_Markup' => 'class-pandatranslate-provider-markup.php',
		'PandaTranslate_Providers' => 'class-pandatranslate-providers.php',
		'PandaTranslate_Repository' => 'class-pandatranslate-repository.php',
		'PandaTranslate_Routing' => 'class-pandatranslate-routing.php',
		'PandaTranslate_Site_Progress' => 'class-pandatranslate-site-progress.php',
		'PandaTranslate_Source_Language' => 'class-pandatranslate-source-language.php',
		'PandaTranslate_Static_Inventory' => 'class-pandatranslate-static-inventory.php',
		'PandaTranslate_Strings' => 'class-pandatranslate-strings.php',
		'PandaTranslate_Text_Admin' => 'class-pandatranslate-text-admin.php',
		'PandaTranslate_Theme_Colors' => 'class-pandatranslate-theme-colors.php',
		'PandaTranslate_Translation_Jobs' => 'class-pandatranslate-translation-jobs.php',
		'PandaTranslate_Translation_Memory' => 'class-pandatranslate-translation-memory.php',
		'PandaTranslate_Unit_Extractor' => 'class-pandatranslate-unit-extractor.php',
		'PandaTranslate_Provider' => 'interface-pandatranslate-provider.php',
	);
	if ( isset( $map[ $class ] ) ) require_once PANDATRANSLATE_DIR . 'includes/' . $map[ $class ];
} );

register_activation_hook( __FILE__, function () { PandaTranslate_Repository::install(); PandaTranslate_Routing::boot(); flush_rewrite_rules(); } );
add_action( 'init', static function () { if ( (int) get_option( 'pandatranslate_schema', 0 ) !== PandaTranslate_Repository::SCHEMA_VERSION ) PandaTranslate_Repository::install(); }, 5 );
// WordPress.org language packs load automatically; the manual-distribution bootstrap registers its bundled fallback.
add_action( 'plugins_loaded', array( 'PandaTranslate_Plugin', 'boot' ) );
add_action( 'plugins_loaded', array( 'PandaTranslate_Migration', 'boot' ) );
add_action( 'plugins_loaded', array( 'PandaTranslate_Edition', 'register_updater' ), 30 );
add_action( 'plugins_loaded', array( 'PandaTranslate_Routing', 'boot' ) );
add_action( 'plugins_loaded', array( 'PandaTranslate_Frontend', 'boot' ) );
PandaTranslate_Language_Content::boot();

PandaTranslate_Edition::boot();
add_action( 'init', function () { if ( ! defined( 'CONTACTPANDA_VERSION' ) ) load_textdomain( 'contactpanda', PANDATRANSLATE_DIR . 'languages/contactpanda-' . determine_locale() . '.mo' ); }, 2 );

add_action( 'plugins_loaded', array( 'PandaTranslate_Strings', 'boot' ), 20 );

PandaTranslate_Text_Admin::boot();

PandaTranslate_Static_Inventory::boot();
