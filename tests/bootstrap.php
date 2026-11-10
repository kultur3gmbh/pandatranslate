<?php
class WP_Error {
	private $code; private $message;
	public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text ) { return $text; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_kses( $html ) { return $html; }
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/class-pandatranslate-inline-protector.php';
require_once dirname( __DIR__ ) . '/includes/class-pandatranslate-unit-extractor.php';
