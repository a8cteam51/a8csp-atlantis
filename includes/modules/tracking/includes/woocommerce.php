<?php

defined( 'ABSPATH' ) || exit;

// Allow disabling tracking via a constant.
if ( defined( 'WPCOMSP_WC_TRACKING' ) && ! WPCOMSP_WC_TRACKING ) {
	return;
}

/**
 * WooCommerce Usage Tracking Auto-opt-in.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
add_filter(
	'option_woocommerce_allow_tracking',
	static fn() => 'yes',
	PHP_INT_MAX
);
