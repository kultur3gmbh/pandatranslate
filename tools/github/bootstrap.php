<?php
defined( 'ABSPATH' ) || exit;
// Language loading is shared by both distributions in the main plugin bootstrap.
if ( ! class_exists( 'Panda_Public_Updater', false ) ) require_once __DIR__ . '/class-panda-public-updater.php';
return new Panda_Public_Updater( plugin_basename( PANDATRANSLATE_FILE ), PANDATRANSLATE_VERSION, array( 'repository' => 'kultur3gmbh/pandatranslate', 'slug' => 'pandatranslate', 'name' => 'Panda Translate', 'asset_prefix' => 'pandatranslate', 'manifest' => 'pandatranslate-update.json' ) );
