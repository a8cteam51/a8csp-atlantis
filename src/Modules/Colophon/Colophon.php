<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Colophon;

use A8C\SpecialProjects\Atlantis\Modules\Module;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Colophon Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Colophon extends Module {

	/**
	 * Gets the module name.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string  The module name.
	 */
	public function get_name(): string {
		return 'Colophon';
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
		return 'Having set up footer links in many partner sites, this aims to simplify the deployment of each, by using a more consistent api.';
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
		add_action( 'team51_credits', 'team51_credits', 10, 1 );
		add_action(
			'init',
			function () {
				add_shortcode( 'team51-credits', 'team51_credits_shortcode' );
			}
		);
		add_action(
			'init',
			function () {
				add_shortcode( 'team51-current-year', 'team51_current_year_shortcode' );
			}
		);
	}
}
