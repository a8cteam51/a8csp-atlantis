<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Colophon;

use A8C\SpecialProjects\Atlantis\Modules\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Colophon Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Colophon extends Module {

	public function get_name(): string {
		return 'Colophon';
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
		error_log( 'Colophon initialized' );
		
		// TODO: Initialize module components and/or direct hooks.
	}
}
