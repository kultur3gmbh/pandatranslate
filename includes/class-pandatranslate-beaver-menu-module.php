<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'FLBuilderModule' ) ) return;
class PandaTranslate_Beaver_Menu_Module extends FLBuilderModule {
	public function __construct() { parent::__construct( array( 'name' => __( 'Panda Translate language menu', 'pandatranslate' ), 'description' => 'Panda Translate', 'category' => 'Panda Translate', 'dir' => PANDATRANSLATE_DIR . 'beaver-menu/', 'url' => PANDATRANSLATE_URL . 'beaver-menu/', 'enabled' => true ) ); }
}
