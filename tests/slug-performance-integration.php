<?php
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
require $root . '/wp-load.php';
function pt_slug_expect( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS $label\n"; }
$settings = function () { return array( 'source_language' => 'de', 'url_mode' => 'path', 'languages' => array( array( 'code' => 'de', 'active' => 1 ), array( 'code' => 'fr', 'active' => 1 ) ) ); };
add_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $settings, 999 );
$calls = 0; $observe = function ( $url ) use ( &$calls ) { $calls++; return $url; }; add_filter( 'url_to_postid', $observe );
$key = '_pandatranslate_slug_fr'; $id = wp_insert_post( array( 'post_title' => 'Slug performance fixture', 'post_name' => 'slug-perf-fixture', 'post_status' => 'publish', 'post_type' => 'page' ) );
$virtual = function ( $value, $object_id, $meta_key ) use ( $id, $key ) { return $object_id === $id && $meta_key === $key ? 'virtual-fr' : $value; };
try {
 PandaTranslate_Frontend::invalidate_slugs();
 for ( $i = 0; $i < 50; $i++ ) PandaTranslate_Frontend::localize_url( home_url( '/unmapped-fixture-' . $i . '/' ), 'fr' );
 pt_slug_expect( 0 === $calls, 'Fifty links avoid rewrite scans when the language has no translated post slugs' );
 update_post_meta( $id, $key, 'page-fr' ); $url = get_permalink( $id );
 $localized = PandaTranslate_Frontend::localize_url( $url, 'fr' );
 pt_slug_expect( $calls > 0 && false !== strpos( $localized, 'page-fr' ), 'Adding translated slug immediately restores post resolution and translated URL' );
 $request = (object) array( 'request' => 'fr/page-fr', 'query_vars' => array( 'pt_lang' => 'fr', 'pandatranslate_slug' => 'page-fr' ) );
 PandaTranslate_Routing::resolve_translated_slug( $request );
 $page_query = new WP_Query( $request->query_vars );
 pt_slug_expect( $page_query->is_page() && ! $page_query->is_single() && $id === $page_query->get_queried_object_id(), 'Translated page keeps the WordPress page template and queried page ID' );
 delete_post_meta( $id, $key ); $calls = 0;
 PandaTranslate_Frontend::localize_url( home_url( '/unmapped-fixture-again/' ), 'fr' );
 pt_slug_expect( 0 === $calls, 'Deleting last translated slug immediately restores fast path' );
 add_filter( 'get_post_metadata', $virtual, 999, 3 ); $localized = PandaTranslate_Frontend::localize_url( $url, 'fr' );
 pt_slug_expect( $calls > 0 && false !== strpos( $localized, 'virtual-fr' ), 'Virtual metadata providers retain dynamic slug behavior' );
} finally {
 remove_filter( 'get_post_metadata', $virtual, 999 ); wp_delete_post( $id, true ); remove_filter( 'url_to_postid', $observe ); remove_filter( 'pre_option_' . PandaTranslate_Plugin::OPTION, $settings, 999 ); PandaTranslate_Frontend::invalidate_slugs();
}
