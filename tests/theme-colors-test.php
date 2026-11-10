<?php
define( 'ABSPATH', __DIR__ );
function wp_get_global_settings() { return array( 'color' => array( 'palette' => array( 'theme' => array( array( 'color' => '#123456' ) ), 'custom' => array( array( 'color' => '#ABCDEF' ) ) ) ) ); }
function get_theme_support( $name ) { return array( array( array( 'color' => '#654321' ) ) ); }
function get_theme_mod( $name, $default ) { return 'background_color' === $name ? 'ffffff' : ''; }
function get_option( $name, $default = false ) { return array( 'fusion_options' => array( 'color1' => '#112233', 'global_colors_palette' => '{"colors":[{"color":"#445566"}]}' ), 'elementor_active_kit' => 10, 'et_divi' => array( 'accent_color' => '#778899' ) )[$name] ?? $default; }
function get_post_meta( $id, $name, $single ) { return array( 'system_colors' => array( array( 'color' => '#aabbcc' ) ), 'custom_colors' => array( array( 'color' => '#bbccdd' ) ) ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_hex_color( $text ) { return preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $text ) ? $text : null; }
function apply_filters( $hook, $value ) { return $value; }
require dirname(__DIR__) . '/includes/class-pandatranslate-theme-colors.php';
$palette = PandaTranslate_Theme_Colors::palette();
foreach ( array( '#123456', '#abcdef', '#654321', '#ffffff', '#112233', '#445566', '#778899', '#aabbcc', '#bbccdd' ) as $expected ) if ( ! in_array( $expected, $palette, true ) ) throw new RuntimeException( 'Missing theme color: ' . $expected );
echo "WordPress, Avada, Elementor and Divi theme palettes: ok\n";
