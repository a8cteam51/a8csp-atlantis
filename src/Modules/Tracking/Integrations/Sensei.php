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
class Sensei extends AbstractIntegration {
	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function is_active(): bool {
		return ! defined( 'WPCOMSP_SENSEI_TRACKING' ) || WPCOMSP_SENSEI_TRACKING;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function initialize(): void {
		add_filter(
			'option_sensei-settings',
			static function ( $values ) {
				if ( is_array( $values ) ) {
					$values['sensei_usage_tracking_enabled'] = true;
				}

				return $values;
			},
			PHP_INT_MAX
		);
	}
}
