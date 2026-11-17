<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'FLBuilderModule' ) ) return;
class PandaTranslate_Beaver_Module extends FLBuilderModule {
	public function __construct() { parent::__construct( array( 'name' => 'Panda Translate Sprachumschalter', 'description' => 'Panda Translate language switcher', 'category' => 'Panda Translate', 'dir' => PANDATRANSLATE_DIR . 'beaver/', 'url' => PANDATRANSLATE_URL . 'beaver/', 'enabled' => true ) ); }
}
