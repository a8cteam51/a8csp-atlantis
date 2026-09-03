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

	return a8csp_atlantis_user_has_automattic_email( wp_get_current_user() );
}

/**
 * Checks whether a user's email address is on an Automattic domain.
 *
 * Split out so capability filters can reuse it without calling `current_user_can()`, which
 * would re-enter `user_has_cap` and recurse.
 *
 * @since   1.3.1
 * @version 1.3.1
 *
 * @param   WP_User $user The user to check.
 *
 * @return  bool
 */
function a8csp_atlantis_user_has_automattic_email( WP_User $user ): bool {
	if ( 0 === $user->ID || false === is_email( $user->user_email ) ) {
		return false;
	}

	$allowed_domains = array( 'a8c.com', 'automattic.com', 'wordpress.com' );
	$email           = strtolower( trim( $user->user_email ) );
	$email_domain    = strrchr( $email, '@' );
	if ( false === $email_domain ) {
		return false;
	}
	$email_domain = ltrim( $email_domain, '@' );

	return in_array( $email_domain, $allowed_domains, true );
}
