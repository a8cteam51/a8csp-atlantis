<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Integration class for the Tracking module.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
abstract class AbstractIntegration {
	/**
	 * Returns whether the integration is active.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	abstract public function is_active(): bool;

	/**
	 * Initializes the integration if it is active.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function maybe_initialize(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->initialize();
	}

	/**
	 * Initializes the integration.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	abstract protected function initialize(): void;
}
