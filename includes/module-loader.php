<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function atlantis_load_modules() {
	$enabled = get_option( 'atlantis_enabled_modules', [] );

	$modules = [
		'autoupdate-filter' => ATLANTIS_DIR . 'modules/autoupdate-filter/autoupdate-filter.php',
		'tracking'          => ATLANTIS_DIR . 'modules/tracking/tracking.php',
		'colophon'          => ATLANTIS_DIR . 'modules/colophon/colophon.php',
	];

	foreach ( $modules as $key => $file ) {
		if ( ! empty( $enabled[ $key ] ) && file_exists( $file ) ) {
			require_once $file;
		}
	}
}
add_action( 'plugins_loaded', 'atlantis_load_modules' );
