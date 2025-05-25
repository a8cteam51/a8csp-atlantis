<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Checks whether the current user is probably an Automattician.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  bool
 */
function a8csp_atlantis_is_automattician(): bool {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	$user = wp_get_current_user();
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return false;
	}

	$allowed_domains = array( '@a8c.com', '@automattic.com', '@wordpress.com' );
	$email_domain    = strrchr( $user->user_email, '@' );

	return in_array( $email_domain, $allowed_domains, true );
}
