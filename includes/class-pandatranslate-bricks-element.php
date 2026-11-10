<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( '\Bricks\Element' ) ) return;
class PandaTranslate_Bricks_Element extends \Bricks\Element {
	public $category = 'general'; public $name = 'pandatranslate-switcher'; public $icon = 'ti-world';
	public function get_label() { return 'PandaTranslate Sprachumschalter'; }
	public function render() { echo '<div ' . $this->render_attributes( '_root' ) . '>' . do_shortcode( '[pandatranslate_switcher]' ) . '</div>'; }
}
