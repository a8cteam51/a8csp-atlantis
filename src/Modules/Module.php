<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for all modules.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
abstract class Module {
	/**
	 * Initializes the module if it is active.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function maybe_initialize(): void {
		$module_meets_requirements = $this->module_requirements_check();
		if ( is_wp_error( $module_meets_requirements ) ) {
			a8csp_atlantis_output_requirements_error( $module_meets_requirements );
			return;
		}

		$this->initialize();
	}

	/**
	 * Checks module-specific requirements and returns true if they all pass.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  true|\WP_Error
	 */
	abstract protected function module_requirements_check(): bool|\WP_Error;

} 