<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking;

use A8C\SpecialProjects\Atlantis\Modules\Module;
use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Tracking extends Module {

	/**
	 * Gets the module name.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string  The module name.
	 */
	public function get_name(): string {
		return 'Tracking';
	}

	/**
	 * Gets the module description.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string  The module description.
	 */
	public function get_description(): string {
		return 'Tracking module';
	}

	/**
	 * Checks if the module should be disabled.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  false|\WP_Error  False if the module should be enabled, WP_Error if it should be disabled.
	 */
	public function is_disabled(): false|\WP_Error {
		return false;
	}

	/**
	 * Initializes the module components.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		error_log( 'Tracking initialized' );
		
		// TODO: Initialize module components and/or direct hooks.
	}
}
