<?php
require __DIR__ . '/routing-test.php';
require dirname( __DIR__ ) . '/includes/class-pandatranslate-unit-extractor.php';
require dirname( __DIR__ ) . '/includes/class-pandatranslate-translation-memory.php';
require dirname( __DIR__ ) . '/includes/class-pandatranslate-frontend.php';
function is_admin() { return false; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function __( $text ) { return $text; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_kses_post( $text ) { return $text; }
function get_query_var( $key ) { return 'en'; }
function current_user_can() { return true; }
function apply_filters( $hook, $value ) { return $value; }
final class Page_Repository {
	public $row = null; public $writes = array(); public $corrected = false;
	public function published_imports( $language, $hashes ) { $rows = array( array( 'source_html'=>'Hallo Welt', 'translated_html'=>'Hello world' ) ); if ( $this->corrected ) $rows[] = array( 'source_html'=>'Hallo Welt', 'translated_html'=>'Corrected dictionary', 'provider'=>'manual-dictionary' ); return $rows; }
	public function find( $key, $language, $hash = null ) { return $this->row; }
	public function save( ...$args ) { $this->writes[] = $args; return true; }
}
$repository = new Page_Repository();
( new ReflectionProperty( 'PandaTranslate_Plugin', 'repository' ) )->setValue( $plugin, $repository );
( new ReflectionProperty( 'PandaTranslate_Plugin', 'extractor' ) )->setValue( $plugin, new PandaTranslate_Unit_Extractor() );
$home = 'https://example.com';
$html = '<!doctype html><html lang="de-DE"><head><title>Same page</title></head><body class="builder-home"><a id="logo" href="/">Logo</a><p>Hallo Welt</p></body></html>';
$result = $plugin->transform_page( $html );
check( true, false !== strpos( $result, 'Hello world' ) );
check( true, false !== strpos( $result, 'class="builder-home"' ) );
check( 0, count( $repository->writes ) );
check( true, false !== strpos( $result, 'lang="en"' ) );
check( true, false !== strpos( $result, 'href="https://example.com/en/"' ) );
$repository->row = array( 'status'=>'published', 'translated_html'=>'Manual translation' );
check( true, false !== strpos( $plugin->transform_page( $html ), 'Manual translation' ) );
$repository->row = array( 'status'=>'reviewed', 'translated_html'=>'Unpublished manual draft' );
$result = $plugin->transform_page( $html );
check( false, false !== strpos( $result, 'Unpublished manual draft' ) );
check( false, false !== strpos( $result, 'Hello world' ) );
$repository->row = null; $_GET['pandatranslate'] = 'edit';
$plugin->transform_page( $html );
check( 1, count( $repository->writes ) );
check( 'Hello world', $repository->writes[0][2] );
check( 'published', $repository->writes[0][3] );
check( 'translatepress-import', $repository->writes[0][4] );
unset($_GET['pandatranslate']); $repository->corrected = true;
$repository->row = array( 'status'=>'published', 'translated_html'=>'Stale imported copy', 'provider'=>'translatepress-import' );
check( true, false !== strpos( $plugin->transform_page( $html ), 'Corrected dictionary' ) );
$repository->row = array( 'status'=>'published', 'translated_html'=>'Page-specific manual text', 'provider'=>'manual' );
check( true, false !== strpos( $plugin->transform_page( $html ), 'Page-specific manual text' ) );
echo "page integration: migrated output, builder layout, no visitor writes, manual override, unpublished draft protection and editor reuse: ok\n";
