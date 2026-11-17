<?php
defined( 'ABSPATH' ) || exit;

/** Adapter registry for future translation-tool migrations; only verified sources are advertised. */
final class PandaTranslate_Migration_Sources {
	public static function available() {
		$sources = apply_filters( 'pandatranslate_migration_sources', array(
			'translatepress' => array( 'label' => 'TranslatePress', 'detected' => ! empty( PandaTranslate_Migration::tables() ), 'render' => array( 'PandaTranslate_Migration', 'render' ) ),
		) );
		return array_filter( (array) $sources, function ( $source ) { return is_array( $source ) && ! empty( $source['detected'] ) && ! empty( $source['label'] ) && is_callable( $source['render'] ?? null ); } );
	}
	public static function render() {
		foreach ( self::available() as $source ) { echo '<section><h2>' . esc_html( $source['label'] ) . '</h2>'; call_user_func( $source['render'] ); echo '</section>'; }
	}
}
