<?php
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
function pt_template_expect( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS $label\n"; }
$settings = array( 'source_language' => 'de', 'languages' => array( array( 'code' => 'de', 'active' => 1 ), array( 'code' => 'fr', 'active' => 1 ) ) );
$option = function () use ( &$settings ) { return $settings; };
add_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $option, 999 );
$registration = array( 'newsletter_subject' => 'Hallo {first_name}', 'newsletter_message' => '<table><tr><td>Danke {first_name}: [resa_id] {{amount}} %currency%</td></tr></table>', 'sender_email' => 'private@example.test', 'secret_key' => 'never-collect-this-value' );
$frame = array( 'signature' => '<p>Viele Grüße {first_name}</p>', 'logo_url' => 'https://example.test/logo.png', 'header_blocks' => array( array( 'type' => 'text', 'content' => '<p>Willkommen</p>' ), array( 'type' => 'image', 'url' => 'https://example.test/image.png', 'alt' => 'Logo source label' ), array( 'type' => 'columns', 'columns' => array( array( array( 'type' => 'text', 'content' => '<p>Nested frame text</p>' ) ), array() ) ) ) );
$raw_registration = function () use ( $registration ) { return $registration; }; $raw_frame = function () use ( $frame ) { return $frame; };
add_filter( 'pre_option_contactpanda_registration_settings', $raw_registration ); add_filter( 'pre_option_contactpanda_system_email_template', $raw_frame );
global $wpdb; $wpdb->query( 'START TRANSACTION' ); $repo = new PandaTranslate_Repository();
try {
 $public_before = $repo->public_language_counts();
 $created = PandaTranslate_Stored_Templates::collect( 0 ); pt_template_expect( $created === 6, 'Only allowlisted static text fields including column text and image labels are collected, never addresses, keys or image settings' );
 $source = $registration['newsletter_message']; $unit = PandaTranslate_Stored_Templates::unit( $source, 'newsletter_message', 'contactpanda-registration', true ); $row = $repo->find( $unit['key'], 'fr' );
 pt_template_expect( $row['source_kind'] === 'stored-template-html' && $row['status'] === 'untranslated', 'Full email HTML has its own private source kind and begins untranslated' );
 $translated = str_replace( 'Danke', 'Merci', $source ); $repo->save_revision( $row, $translated, 'automatic' );
 pt_template_expect( PandaTranslate_Stored_Templates::translate( $source, 'newsletter_message', 'contactpanda-registration', true, 'fr' ) === $source, 'Unpublished automatic template falls back to the current source' );
 $repo->save_revision( $repo->find( $unit['key'], 'fr' ), $translated, 'published' );
 pt_template_expect( PandaTranslate_Stored_Templates::translate( $source, 'newsletter_message', 'contactpanda-registration', true, 'fr' ) === $translated, 'Only a published exact-source template is used, with all variables retained' );
 pt_template_expect( PandaTranslate_Stored_Templates::translate( $source . ' changed', 'newsletter_message', 'contactpanda-registration', true, 'fr' ) === $source . ' changed', 'Edited source never reuses a stale published translation' );
 pt_template_expect( PandaTranslate_Stored_Templates::collect( 0 ) === 0 && $repo->find( $unit['key'], 'fr' )['translated_html'] === $translated, 'Repeated collection never overwrites published manual templates' );
 $before = PandaTranslate_Strings::$email_language; $before_locale = get_locale();
 try { PandaTranslate_Stored_Templates::with_language( 'fr', function () use ( $registration, $translated ) { $value = PandaTranslate_Stored_Templates::registration( $registration ); pt_template_expect( $value['newsletter_message'] === $translated && $value['sender_email'] === $registration['sender_email'], 'Explicit recipient language translates the raw template before customer values are substituted' ); throw new RuntimeException( 'synthetic mail failure' ); } ); } catch ( RuntimeException $error ) { if ( 'synthetic mail failure' !== $error->getMessage() ) throw $error; }
 pt_template_expect( PandaTranslate_Strings::$email_language === $before && get_locale() === $before_locale, 'Recipient language and WordPress locale are restored even when sending throws' );
 $catalog = PandaTranslate_Strings::script_catalog( 'fr' ); pt_template_expect( false === strpos( wp_json_encode( $catalog ), 'Merci' ), 'Stored email templates are absent from the public JavaScript dictionary' );
 $woo_source = 'Booking {order_number}'; PandaTranslate_Stored_Templates::store( $woo_source, 'synthetic|subject', 'woocommerce-email-template', false, 'fr' ); $woo_unit = PandaTranslate_Stored_Templates::unit( $woo_source, 'synthetic|subject', 'woocommerce-email-template', false ); $repo->save_revision( $repo->find( $woo_unit['key'], 'fr' ), 'Réservation {order_number}', 'published' );
 $woo_value = PandaTranslate_Stored_Templates::with_language( 'fr', function () use ( $woo_source ) { return PandaTranslate_WooCommerce::email_template( $woo_source, (object) array( 'id' => 'synthetic' ), '', 'subject' ); } );
 pt_template_expect( 'Réservation {order_number}' === $woo_value, 'WooCommerce loads the published raw subject in the scoped recipient language before formatting order values' );
 pt_template_expect( $repo->public_language_counts() === $public_before, 'Private template publication alone never makes a language visible in the frontend switcher' );
 $settings['languages'][1]['active'] = 0; pt_template_expect( PandaTranslate_Stored_Templates::translate( $source, 'newsletter_message', 'contactpanda-registration', true, 'fr' ) === $source, 'A deactivated target immediately falls back to source' );
} finally { $wpdb->query( 'ROLLBACK' ); remove_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $option, 999 ); remove_filter( 'pre_option_contactpanda_registration_settings', $raw_registration ); remove_filter( 'pre_option_contactpanda_system_email_template', $raw_frame ); }
