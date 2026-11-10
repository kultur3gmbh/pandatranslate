<?php
namespace Elementor { class Widget_Base {} }
namespace {
	define( 'ABSPATH', __DIR__ ); define( 'PANDATRANSLATE_DIR', dirname( __DIR__ ) . '/' ); define( 'PANDATRANSLATE_URL', '/plugin/' ); define( 'PANDATRANSLATE_VERSION', 'test' );
	function __( $text ) { return $text; }
	function do_shortcode( $text ) { return $text; }
	function vc_map( $args ) { $GLOBALS['vc'][$args['base']] = $args; }
	function fusion_builder_map( $args ) { $GLOBALS['avada'][$args['shortcode']] = $args; }
	function wp_register_script( ...$args ) {}
	function wp_localize_script( ...$args ) {}
	function register_block_type( $name, $args ) { $GLOBALS['blocks'][$name] = $args; }
	class ET_Builder_Module { public $name; public function __construct() { $this->init(); $GLOBALS['divi'][] = $this; } }
	require dirname( __DIR__ ) . '/includes/class-pandatranslate-features.php';
	PandaTranslate_Features::register_avada_menu(); PandaTranslate_Features::register_wpbakery_menu();
	if ( ! isset( $GLOBALS['avada']['pandatranslate_menu'], $GLOBALS['vc']['pandatranslate_menu'] ) ) throw new \RuntimeException( 'Builder element missing.' );
	PandaTranslate_Features::register_block(); PandaTranslate_Features::register_menu_block();
	if ( '[pandatranslate_menu]' !== $GLOBALS['blocks']['pandatranslate/menu']['render_callback']() ) throw new \RuntimeException( 'Menu block does not use menu settings.' );
	$manager = new class { public $widgets = array(); public function register( $widget ) { $this->widgets[] = $widget; } };
	PandaTranslate_Features::register_elementor_menu( $manager ); $widget = $manager->widgets[0];
	if ( 'pandatranslate-menu' !== $widget->get_name() ) throw new \RuntimeException( 'Elementor widget missing.' );
	$render = new \ReflectionMethod( $widget, 'render' ); if ( PHP_VERSION_ID < 80100 ) $render->setAccessible( true );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean(); if ( '[pandatranslate_menu]' !== $html ) throw new \RuntimeException( 'Elementor did not use menu shortcode.' );
	PandaTranslate_Features::register_divi_menu();
	if ( '[pandatranslate_menu]' !== $GLOBALS['divi'][0]->render( array() ) ) throw new \RuntimeException( 'Divi menu element missing.' );
	echo "Avada, WPBakery, Gutenberg, Elementor and Divi language menu registrations: ok\n";
}
