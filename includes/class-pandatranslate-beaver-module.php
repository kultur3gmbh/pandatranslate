<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'FLBuilderModule' ) ) return;
class PandaTranslate_Beaver_Module extends FLBuilderModule {
	public function __construct() { parent::__construct( array( 'name' => 'PandaTranslate Sprachumschalter', 'description' => 'PandaTranslate language switcher', 'category' => 'PandaTranslate', 'dir' => PANDATRANSLATE_DIR . 'beaver/', 'url' => PANDATRANSLATE_URL . 'beaver/', 'enabled' => true ) ); }
}
