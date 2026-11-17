<?php
defined( 'ABSPATH' ) || exit;

interface PandaTranslate_Provider {
	public function id();
	public function translate( $protected_text, $source_language, $target_language, array $context = array() );
}
