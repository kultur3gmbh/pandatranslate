<?php
class WP_Error { private $code; private $message; public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; } public function get_error_code() { return $this->code; } public function get_error_message() { return $this->message; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function apply_filters( $name, $value ) { return $value; }
function do_action() {}
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_transient() { return false; }
function set_transient() { return true; }
function absint( $value ) { return abs( (int) $value ); }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_post( $url, $args ) {
	if ( false !== strpos( $url, 'oauth2.googleapis.com' ) ) return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'access_token' => 'test-token', 'expires_in' => 3600 ) ) );
	if ( false !== strpos( $url, '/language/translate/v2?key=test-api-key' ) ) {
		$body = json_decode( $args['body'], true ); assert( 'text' === $body['format'] );
		return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'data' => array( 'translations' => array( array( 'translatedText' => 'Hallo v2 [[PT:0:OPEN]]Welt[[PT:1:CLOSE]]' ) ) ) ) ) );
	}
	assert( false !== strpos( $url, 'translation.googleapis.com/v3/projects/test-project/locations/global:translateText' ) );
	assert( 'Bearer test-token' === $args['headers']['Authorization'] );
	$body = json_decode( $args['body'], true );
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'translations' => array( array( 'translatedText' => 'Hallo [[PT:0:OPEN]]Welt[[PT:1:CLOSE]]' ) ) ) ) );
}
define( 'ABSPATH', __DIR__ );
$key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
openssl_pkey_export( $key, $private_key );
$credential_file = tempnam( sys_get_temp_dir(), 'pt-google-' );
file_put_contents( $credential_file, json_encode( array( 'client_email' => 'translator@test-project.iam.gserviceaccount.com', 'private_key' => $private_key, 'token_uri' => 'https://oauth2.googleapis.com/token' ) ) );
define( 'PANDATRANSLATE_GOOGLE_CREDENTIALS_FILE', $credential_file );
define( 'PANDATRANSLATE_GOOGLE_API_KEY', 'test-api-key' );
require dirname( __DIR__ ) . '/includes/interface-pandatranslate-provider.php';
require dirname( __DIR__ ) . '/includes/class-pandatranslate-google-provider.php';
$provider = new PandaTranslate_Google_Provider( 'test-project', 'global' );
assert( true === $provider->configured() );
$translation = $provider->translate( 'Hello [[PT:0:OPEN]]world[[PT:1:CLOSE]]', 'en', 'de' );
assert( 'Hallo [[PT:0:OPEN]]Welt[[PT:1:CLOSE]]' === $translation );
$provider_v2 = new PandaTranslate_Google_Provider( '', 'global', 'v2' );
assert( true === $provider_v2->configured() );
$translation_v2 = $provider_v2->translate( 'Hello [[PT:0:OPEN]]world[[PT:1:CLOSE]]', 'en', 'de' );
assert( 'Hallo v2 [[PT:0:OPEN]]Welt[[PT:1:CLOSE]]' === $translation_v2 );
class PandaTranslate_Plugin { public static $api_settings=array('google_project_id'=>'test-project','google_location'=>'global','google_api_version'=>'v3','auto_provider'=>'google-v2','google_v2_enabled'=>1,'google_v3_enabled'=>1);public static function get_settings(){return self::$api_settings;} }
require dirname(__DIR__).'/includes/class-pandatranslate-providers.php';
assert(count(PandaTranslate_Providers::choices())===2);
assert(PandaTranslate_Providers::default_id()==='google-v2');
assert(strpos(PandaTranslate_Providers::get('google-v2')->translate('Hello','en','de'),'v2')!==false);
assert(strpos(PandaTranslate_Providers::get('google-v3')->translate('Hello','en','de'),'v2')===false);
PandaTranslate_Plugin::$api_settings['google_v3_enabled']=0;
assert(array_keys(PandaTranslate_Providers::choices())===array('google-v2'));
assert(is_wp_error(PandaTranslate_Providers::get('google-v3')));
unlink( $credential_file );
echo "google providers v2/v3 mock: ok\n";
