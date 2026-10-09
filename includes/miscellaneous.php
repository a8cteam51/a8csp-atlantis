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

/**
 * Checks whether this site is managed by the WordPress Special Projects team.
 *
 * Team-specific behaviour — forced usage tracking, the Special Projects RUM tag, the
 * Automattician-only admin screens, referral attribution and team-worded notices — only applies
 * to managed sites. Everywhere else Atlantis behaves like a plain utility plugin.
 *
 * The `A8CSP_ATLANTIS_MANAGED_SITE` constant wins over the stored option when defined.
 *
 * @since   1.5.0
 * @version 1.5.0
 *
 * @return  bool
 */
function a8csp_atlantis_is_managed_site(): bool {
	if ( defined( 'A8CSP_ATLANTIS_MANAGED_SITE' ) ) {
		return (bool) constant( 'A8CSP_ATLANTIS_MANAGED_SITE' );
	}

	return '1' === (string) get_option( A8CSP_ATLANTIS_MANAGED_SITE_OPTION, '0' );
}

/**
 * Records whether this site is managed, on the first request that finds no answer stored.
 *
 * A site that already ran Atlantis before the flag existed keeps the behaviour it had, so it is
 * recorded as managed. A fresh install is recorded as unmanaged, and whoever provisions a managed
 * site sets the option before or after installing. Either way the answer is written once and
 * never revisited here.
 *
 * The Messages module is mandatory and stores its settings on the first request it sees, so its
 * option row is what says Atlantis has run here before. The Autoupdates row cannot be used: the
 * activation hook may write it before the plugin has ever loaded.
 *
 * @since   1.5.0
 * @version 1.5.0
 *
 * @return  void
 */
function a8csp_atlantis_maybe_record_managed_site(): void {
	if ( null !== get_option( A8CSP_ATLANTIS_MANAGED_SITE_OPTION, null ) ) {
		return;
	}

	$has_run_before = null !== get_option( a8csp_atlantis_generate_module_settings_key( 'Messages' ), null );

	add_option( A8CSP_ATLANTIS_MANAGED_SITE_OPTION, $has_run_before ? '1' : '0' );
}

/**
 * Checks whether the current user may use the Atlantis admin screens.
 *
 * On a managed site that is an Automattician; anywhere else it is any administrator, since the
 * site's own administrators are the only people there to run it.
 *
 * @since   1.5.0
 * @version 1.5.0
 *
 * @return  bool
 */
function a8csp_atlantis_current_user_can_manage(): bool {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	return ! a8csp_atlantis_is_managed_site() || a8csp_atlantis_user_has_automattic_email( wp_get_current_user() );
}
