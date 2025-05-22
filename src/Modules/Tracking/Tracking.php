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
		return 'Opts sites into tracking.';
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
		$environment_types = array( 'development', 'staging', 'develop', 'local' );
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && in_array( WP_ENVIRONMENT_TYPE, $environment_types, true ) ) {
			return new \WP_Error(
				'tracking_disabled',
				'Tracking module is disabled in the current environment.'
			);
		}
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
		$this->define_constants();
		$this->load_translations();
		$this->load_tracking_scripts();
	}

	/**
	 * Defines the constants for the module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	private function define_constants(): void {
		if ( ! defined( 'ATLANTIS_TRACKING_PATH' ) ) {
			define( 'ATLANTIS_TRACKING_PATH', __DIR__ . '/' );
		}
	}

	/**
	 * Loads the translations for the module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	private function load_translations(): void {
		add_action(
			'init',
			static function () {
				load_plugin_textdomain( 'team51-tracking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			}
		);
	}

	/**
	 * Loads the tracking scripts.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	private function load_tracking_scripts(): void {
		foreach ( glob( __DIR__ . '/includes/*.php' ) as $filename ) {
			if ( preg_match( '#/includes/_#i', $filename ) ) {
				continue; // Ignore files prefixed with an underscore.
			}
			include $filename;
		}
	}
}
