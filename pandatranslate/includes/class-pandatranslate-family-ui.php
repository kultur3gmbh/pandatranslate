<?php
defined( 'ABSPATH' ) || exit;

/** Complete canonical family assets, included by the license-free base plugin. */
final class PandaTranslate_Family_UI {
	public static function admin() {
		wp_enqueue_style( 'dashicons' );
		$provided = wp_script_is( 'contactpanda-admin-ui', 'registered' );
		if ( ! wp_script_is( 'contactpanda-admin-ui', 'registered' ) ) wp_register_script( 'contactpanda-admin-ui', PANDATRANSLATE_URL . 'assets/contactpanda-admin-ui.js', array( 'wp-api-fetch' ), PANDATRANSLATE_VERSION, true );
		wp_enqueue_script( 'contactpanda-admin-ui' );
        if ( ! $provided ) wp_localize_script( 'contactpanda-admin-ui', 'CONTACTPANDA_ADMIN_UI', array( 'i18n' => array(
            'chooseColor' => __( 'Select color', 'pandatranslate' ), 'colorValue' => __( 'Color value', 'pandatranslate' ), 'themeColors' => __( 'Theme and template colors', 'pandatranslate' ), 'invalidColor' => __( 'Enter a valid HEX, RGB, or RGBA color.', 'pandatranslate' ),
            'chooseImage' => __( 'Select image', 'pandatranslate' ), 'useImage' => __( 'Use image', 'pandatranslate' ), 'dropImage' => __( 'Click or drop an image here', 'pandatranslate' ), 'changeImage' => __( 'Change image', 'pandatranslate' ), 'editImage' => __( 'Edit image', 'pandatranslate' ), 'removeImage' => __( 'Remove image', 'pandatranslate' ), 'selectedImage' => __( 'Selected image', 'pandatranslate' ), 'imageFormats' => __( 'JPG, PNG, WebP or SVG', 'pandatranslate' ), 'externalImage' => __( 'Link external image', 'pandatranslate' ), 'externalImagePrompt' => __( 'Enter the external image URL:', 'pandatranslate' ), 'uploading' => __( 'Uploading image…', 'pandatranslate' ), 'uploadFailed' => __( 'The image could not be uploaded.', 'pandatranslate' )
        ) ) );

		if ( ! wp_style_is( 'contactpanda', 'enqueued' ) ) wp_enqueue_style( 'pandatranslate-family-library', PANDATRANSLATE_URL . 'assets/contactpanda-admin.css', array(), PANDATRANSLATE_VERSION );
		foreach ( array( 'panda-editor-ui', 'panda-transfer-ui' ) as $name ) {
			if ( ! wp_script_is( $name, 'registered' ) ) wp_register_script( $name, PANDATRANSLATE_URL . 'assets/' . $name . '.js', array( 'contactpanda-admin-ui' ), PANDATRANSLATE_VERSION, true );
			if ( ! wp_style_is( $name, 'registered' ) ) wp_register_style( $name, PANDATRANSLATE_URL . 'assets/' . $name . '.css', array(), PANDATRANSLATE_VERSION );
		}
		require_once __DIR__ . '/class-panda-number-format.php';
		if ( ! wp_script_is( 'panda-number-format', 'registered' ) ) wp_register_script( 'panda-number-format', PANDATRANSLATE_URL . 'assets/panda-number-format.js', array(), PANDATRANSLATE_VERSION, true );
		wp_localize_script( 'panda-number-format', 'PANDA_NUMBER_FORMAT', Panda_Number_Format::settings() );
		if ( ! wp_style_is( 'panda-import-review', 'registered' ) ) wp_register_style( 'panda-import-review', PANDATRANSLATE_URL . 'assets/panda-import-review.css', array(), PANDATRANSLATE_VERSION );
	}
}
