<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking;

use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Tracking extends AbstractModule {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_name(): string {
		return 'Tracking';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_description(): string {
		return __( 'Automatically opts-in the site for usage tracking with Automattic-owned plugins.', 'a8csp-atlantis' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function is_disabled(): bool|\WP_Error {
		if ( \function_exists( 'wp_get_environment_type' ) && 'production' !== wp_get_environment_type() ) {
			if ( did_action( 'init' ) || doing_action( 'init' ) ) {
				/* translators: %s: Current environment type */
				return new \WP_Error( 'not-production', wp_sprintf( __( 'Production environment is required. Current environment: %s', 'a8csp-atlantis' ), wp_get_environment_type() ) );
			}

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function initialize(): void {
		include __DIR__ . '/includes/bilmur.php';
		include __DIR__ . '/includes/sensei.php';
		include __DIR__ . '/includes/woocommerce.php';
	}

	// endregion
}
