<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function atlantis_load_modules() {
	$enabled = get_option( 'atlantis_enabled_modules', [] );

	$modules = [
		'autoupdate-filter' => A8CSP_ATLANTIS_DIR_PATH . 'modules/autoupdate-filter/autoupdate-filter.php',
		'tracking'          => A8CSP_ATLANTIS_DIR_PATH . 'modules/tracking/tracking.php',
		'colophon'          => A8CSP_ATLANTIS_DIR_PATH . 'modules/colophon/colophon.php',
	];

	foreach ( $modules as $key => $file ) {
		if ( ! empty( $enabled[ $key ] ) && file_exists( $file ) ) {
			require_once $file;
		}
	}
}
add_action( 'plugins_loaded', 'atlantis_load_modules' );
