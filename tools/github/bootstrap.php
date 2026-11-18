<?php
defined( 'ABSPATH' ) || exit;
// Only manual distributions include this bootstrap and bundled translations.
add_action( 'init', static function () { load_plugin_textdomain( 'pandatranslate', false, dirname( plugin_basename( PANDATRANSLATE_FILE ) ) . '/languages' ); }, 1 );
if ( ! class_exists( 'Panda_Public_Updater', false ) ) require_once __DIR__ . '/class-panda-public-updater.php';
return new Panda_Public_Updater( plugin_basename( PANDATRANSLATE_FILE ), PANDATRANSLATE_VERSION, array( 'repository' => 'kultur3gmbh/pandatranslate', 'slug' => 'pandatranslate', 'name' => 'Panda Translate', 'asset_prefix' => 'pandatranslate', 'manifest' => 'pandatranslate-update.json' ) );
