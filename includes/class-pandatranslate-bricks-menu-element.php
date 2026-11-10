<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( '\Bricks\Element' ) ) return;
class PandaTranslate_Bricks_Menu_Element extends \Bricks\Element {
	public $category = 'general'; public $name = 'pandatranslate-menu'; public $icon = 'ti-world';
	public function get_label() { return __( 'PandaTranslate language menu', 'pandatranslate' ); }
	public function render() { echo '<div ' . $this->render_attributes( '_root' ) . '>' . do_shortcode( '[pandatranslate_menu]' ) . '</div>'; }
}
