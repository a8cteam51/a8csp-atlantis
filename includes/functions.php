<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;


/**
 * Check if the current user is an Automattician.
 *
 * @return bool True if user is an admin with Automattic/WordPress.com email.
 */
function a8csp_atlantis_is_user_automattician(): bool {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	$user = wp_get_current_user();
	if ( ! $user || ! $user->user_email ) {
		return false;
	}

	$allowed_domains = array( 'automattic.com', 'wordpress.com' );
	$email_domain    = substr( strrchr( $user->user_email, '@' ), 1 );

	return in_array( $email_domain, $allowed_domains, true );
}
