<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Colophon Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Colophon extends Module {
	/**
	 * Checks module-specific requirements and returns true if they all pass.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  true|\WP_Error
	 */
	protected function module_requirements_check(): bool|\WP_Error {
		return true; // TODO: Implement module-specific requirements check.
	}

	/**
	 * Initializes the module components.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function initialize(): void {
		// TODO: Initialize module components and/or direct hooks.
	}
}
