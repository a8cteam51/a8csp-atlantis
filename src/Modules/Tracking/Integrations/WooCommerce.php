<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking\Integrations;

use A8C\SpecialProjects\Atlantis\Modules\Tracking\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Integration class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class WooCommerce extends AbstractIntegration {
	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function is_active(): bool {
		return defined( 'WPCOMSP_WC_TRACKING' ) && WPCOMSP_WC_TRACKING;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function initialize(): void {
		add_filter( 'option_woocommerce_allow_tracking', static fn() => 'yes', PHP_INT_MAX );
	}
}
