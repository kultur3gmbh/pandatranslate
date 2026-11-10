<?php
/**
 * Plugin Name: PandaTranslate
 * Description: Visual website translation with language paths, menus and builder elements.
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Update URI: https://github.com/kultur3gmbh/pandatranslate
 * Version: 0.5.2
 * Author: Panda
 * Text Domain: pandatranslate
 */

defined( 'ABSPATH' ) || exit;

define( 'PANDATRANSLATE_FILE', __FILE__ );
define( 'PANDATRANSLATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PANDATRANSLATE_URL', plugin_dir_url( __FILE__ ) );
define( 'PANDATRANSLATE_VERSION', '0.5.2' );

require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-inline-protector.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-unit-extractor.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-repository.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-translation-memory.php';
require_once PANDATRANSLATE_DIR . 'includes/interface-pandatranslate-provider.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-google-provider.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-providers.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-translation-jobs.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-site-progress.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-mcp.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-features.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-source-language.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-glossary-admin.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-theme-colors.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-migration.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-migration-sources.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-edition.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-routing.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-frontend.php';
require_once PANDATRANSLATE_DIR . 'includes/class-pandatranslate-plugin.php';

register_activation_hook( __FILE__, function () { PandaTranslate_Repository::install(); PandaTranslate_Routing::boot(); flush_rewrite_rules(); } );
add_action( 'init', function () { load_plugin_textdomain( 'pandatranslate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); }, 1 );
add_action( 'plugins_loaded', array( 'PandaTranslate_Plugin', 'boot' ) );
add_action( 'plugins_loaded', array( 'PandaTranslate_Migration', 'boot' ) );
add_action( 'plugins_loaded', array( 'PandaTranslate_Edition', 'register_updater' ), 30 );
add_action( 'plugins_loaded', array( 'PandaTranslate_Routing', 'boot' ) );
add_action( 'plugins_loaded', array( 'PandaTranslate_Frontend', 'boot' ) );

PandaTranslate_Edition::boot();
add_action( 'init', function () { if ( ! defined( 'CONTACTPANDA_VERSION' ) ) load_textdomain( 'contactpanda', PANDATRANSLATE_DIR . 'languages/contactpanda-' . determine_locale() . '.mo' ); }, 2 );
