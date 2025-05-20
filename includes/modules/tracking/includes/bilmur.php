<?php

defined( 'ABSPATH' ) || exit;

// Only load if the constant is defined and true.
if ( ! defined( 'WPCOMSP_BILMUR_TRACKING' ) || ! WPCOMSP_BILMUR_TRACKING ) {
	return;
}

/**
 * Bilmur RUM data collector
 */
add_action(
	'wp_enqueue_scripts',
	static function() {
		wp_enqueue_script( 'bilmur', 'https://s0.wp.com/wp-content/js/bilmur.min.js?m=202215', array(), '1.0.0', true );
	}
);

add_filter(
	'script_loader_tag',
	function( $tag, $handle ) {
		if ( 'bilmur' === $handle ) {
			if ( defined( 'WPCOMSP_BILMUR_PROVIDER' ) ) {
				$tag = str_replace( ' src', ' data-provider="' . WPCOMSP_BILMUR_PROVIDER . '" src', $tag );
			}
			if ( defined( 'WPCOMSP_BILMUR_SERVICE' ) ) {
				$tag = str_replace( ' src', ' data-service="' . WPCOMSP_BILMUR_SERVICE . '" src', $tag );
			}
			if ( defined( 'WPCOMSP_BILMUR_CUSTOM_PROPERTIES' ) ) {
				$tag = str_replace( ' src', ' data-customproperties="' . wp_json_encode( WPCOMSP_BILMUR_CUSTOM_PROPERTIES ) . '" src', $tag );
			}
		}

		return $tag;
	},
	10,
	2
);
