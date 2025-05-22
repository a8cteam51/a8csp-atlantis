<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking;

use A8C\SpecialProjects\Atlantis\Modules\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Tracking extends Module {
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
		return 'Opts sites into tracking.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function is_disabled(): false|\WP_Error {
		$environment_types = array( 'development', 'staging', 'develop', 'local' );
		if ( \defined( 'WP_ENVIRONMENT_TYPE' ) && \in_array( WP_ENVIRONMENT_TYPE, $environment_types, true ) ) {
			return new \WP_Error(
				'tracking_disabled',
				'Tracking module is disabled in the current environment.'
			);
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
