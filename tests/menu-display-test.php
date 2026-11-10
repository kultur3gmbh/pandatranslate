<?php
define( 'ABSPATH', __DIR__ );
function is_admin() { return false; }
function __( $text ) { return $text; }
function get_query_var( $key ) { return 'fr'; }
function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_url( $text ) { return $text; }
function wp_get_attachment_image_url( $id, $size ) { return '/custom-flag.png'; }
function do_shortcode( $text ) { return $text; }
function sanitize_key( $text ) { return strtolower( $text ); }
class PandaTranslate_Plugin {
	public static function get_settings() { return array( 'source_language' => 'de', 'switcher_label_mode' => 'native', 'switcher_menu_label_mode' => 'flags', 'switcher_flag_position' => 'hidden', 'switcher_flag_shape' => 'rectangle' ); }
	public static function switcher_settings( $context ) { $s = self::get_settings(); $s['switcher_label_mode'] = 'flags'; return $s; }
	public static function language_rows() { return array( array( 'code' => 'fr', 'flag_id' => 42 ) ); }
	public static function visible_language_rows() { return array( array('code'=>'de'),array('code'=>'en'),array('code'=>'fr') ); }
	public static function language_url( $code ) { return '/page/?pt_lang=' . $code; }
}
require dirname(__DIR__) . '/includes/class-pandatranslate-features.php';
$item = (object) array( 'url' => '#pandatranslate:fr' );
$atts = PandaTranslate_Features::dynamic_menu_link( array( 'href' => '/rewritten-placeholder' ), $item );
if ( isset( $atts['style'] ) ) throw new RuntimeException( 'Existing theme menus must retain their own styling.' );
if ( '/page/?pt_lang=fr' !== $atts['href'] || 'Français' !== $atts['aria-label'] ) throw new RuntimeException( 'Language link was not resolved.' );
$title = PandaTranslate_Features::dynamic_menu_title( 'Français', $item );
if ( false !== strpos( $title, 'Français' ) || false === strpos( $title, '/custom-flag.png' ) ) throw new RuntimeException( 'Menu flags-only mode failed.' );
$atts = PandaTranslate_Features::dynamic_menu_link( array(), (object) array( 'url' => '#pandatranslate:current' ) );
if ( '/page/?pt_lang=fr' !== $atts['href'] ) throw new RuntimeException( 'Current language must use the route language.' );
if ( false === strpos( PandaTranslate_Features::render_menu_switcher_item( '', (object) array( 'url' => '#pandatranslate:switcher' ) ), 'display="flags"' ) ) throw new RuntimeException( 'Menu switcher did not use menu display mode.' );
echo "menu links, flags-only mode and custom flags: ok\n";
$items = array(
	(object) array( 'ID' => 1, 'url' => '#pandatranslate:fr', 'menu_item_parent' => 0 ),
	(object) array( 'ID' => 2, 'url' => '#pandatranslate:en', 'menu_item_parent' => 1 ),
	(object) array( 'ID' => 3, 'url' => '/contact/', 'menu_item_parent' => 0 ),
	(object) array( 'ID' => 4, 'url' => '#pandatranslate:current', 'menu_item_parent' => 0 ),
);
$visible = PandaTranslate_Features::hide_current_menu_language( $items );
if ( array( 2, 3 ) !== array_column( $visible, 'ID' ) || 0 !== $visible[0]->menu_item_parent ) throw new RuntimeException( 'Active language must be hidden without losing child links.' );
if ( '/page/?pt_lang=en' !== $visible[0]->url ) throw new RuntimeException( 'Custom walkers must receive an actual language URL.' );
echo "active menu language hidden, remaining links preserved: ok\n";
$legacy = (object) array( 'ID' => 4452, 'menu_item_parent' => 0, 'url' => '', 'title' => 'English', 'classes' => array( 'pandatranslate-menu-link' ) );
$legacy = PandaTranslate_Features::prepare_menu_item( $legacy );
if ( '/page/?pt_lang=en' !== $legacy->url || false === strpos( $legacy->title, 'pandatranslate-flag' ) ) throw new RuntimeException( 'Legacy menu entry without href was not repaired.' );
$safe = PandaTranslate_Features::prepare_menu_item( (object) array( 'url' => '#pandatranslate-en', 'title' => 'Custom label', 'classes' => array() ) );
if ( '/page/?pt_lang=en' !== $safe->url ) throw new RuntimeException( 'Safe menu marker failed.' );
$by_class = PandaTranslate_Features::prepare_menu_item( (object) array( 'url' => '', 'title' => 'Custom label', 'classes' => array( 'pandatranslate-language--en' ) ) );
if ( '/page/?pt_lang=en' !== $by_class->url ) throw new RuntimeException( 'Persistent language class failed.' );
$ordinary = (object) array( 'url' => '', 'title' => 'English', 'classes' => array() );
if ( '' !== PandaTranslate_Features::prepare_menu_item( $ordinary )->url ) throw new RuntimeException( 'Unrelated menu item must not be changed.' );
echo "live-site regression: missing href recovery, safe markers and custom-walker titles: ok\n";
$dropdown = PandaTranslate_Features::hide_current_menu_language( array(
	(object) array( 'ID' => 20, 'url' => '#pandatranslate:current', 'menu_item_parent' => 0 ),
	(object) array( 'ID' => 21, 'url' => '#pandatranslate:fr', 'menu_item_parent' => 20 ),
	(object) array( 'ID' => 22, 'url' => '#pandatranslate:en', 'menu_item_parent' => 20 ),
) );
if ( array( 20, 22 ) !== array_column( $dropdown, 'ID' ) || 20 !== $dropdown[1]->menu_item_parent ) throw new RuntimeException( 'Dropdown heading must remain while the active option is removed.' );
echo "theme dropdown parent preserved; active language option removed: ok\n";
