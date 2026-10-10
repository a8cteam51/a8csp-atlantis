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
	 * Only on a managed site: overriding the store owner's own tracking choice is a team decision,
	 * not something a site inherits by installing the plugin.
	 *
	 * @since   1.0.0
	 * @version 1.5.0
	 */
	public function is_active(): bool {
		if ( ! a8csp_atlantis_is_managed_site() ) {
			return false;
		}

		return ! defined( 'WPCOMSP_WC_TRACKING' ) || WPCOMSP_WC_TRACKING;
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
