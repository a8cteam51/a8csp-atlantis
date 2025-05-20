<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define module path constant if needed
if ( ! defined( 'ATLANTIS_TRACKING_PATH' ) ) {
	define( 'ATLANTIS_TRACKING_PATH', __DIR__ . '/' );
}

// Load plugin translations if needed
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'team51-tracking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

// Include the rest of the tracking plugin's files
foreach ( glob( __DIR__ . '/includes/*.php' ) as $filename ) {
	if ( preg_match( '#/includes/_#i', $filename ) ) {
		continue; // Ignore files prefixed with an underscore.
	}
	include $filename;
}
