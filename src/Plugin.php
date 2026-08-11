<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\CLI\Message_Command;
use A8C\SpecialProjects\Atlantis\CLI\Module_Command;
use A8C\SpecialProjects\Atlantis\REST\Force_Update_Check_Controller;
use A8C\SpecialProjects\Atlantis\REST\Status_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Plugin {
	// region FIELDS AND CONSTANTS

	/**
	 * The encryption component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var Encryption
	 */
	public Encryption $encryption;

	/**
	 * The modules component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var Modules
	 */
	public Modules $modules;

	/**
	 * The settings component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var Settings
	 */
	public Settings $settings;

	/**
	 * The status REST controller.
	 *
	 * @since   1.2.0
	 * @version 1.2.0
	 *
	 * @var Status_Controller
	 */
	public Status_Controller $status_controller;

	/**
	 * The force-update-check REST controller.
	 *
	 * @since   1.3.0
	 * @version 1.3.0
	 *
	 * @var Force_Update_Check_Controller
	 */
	public Force_Update_Check_Controller $force_update_check_controller;

	// endregion

	// region MAGIC METHODS

	/**
	 * Plugin constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function __construct() {
		/* Empty on purpose. */
	}

	/**
	 * Prevent cloning.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	private function __clone() {
		/* Empty on purpose. */
	}

	/**
	 * Prevent unserializing.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function __wakeup() {
		/* Empty on purpose. */
	}

	// endregion

	// region METHODS

	/**
	 * Returns the singleton instance of the plugin.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  Plugin
	 */
	public static function get_instance(): self {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Returns true if all the plugin's dependencies are met.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  true|\WP_Error
	 */
	public function is_active(): bool|\WP_Error {
		return true;
	}

	/**
	 * Initializes the plugin components.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function initialize(): void {
		$this->register_core_compat_filters();

		$this->encryption = new Encryption();
		$this->encryption->initialize();

		$this->modules = new Modules();
		$this->modules->initialize();

		$this->settings = new Settings();
		$this->settings->initialize();

		$this->status_controller = new Status_Controller();
		$this->status_controller->initialize();

		$this->force_update_check_controller = new Force_Update_Check_Controller();
		$this->force_update_check_controller->initialize();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'atlantis module', Module_Command::class );
			\WP_CLI::add_command( 'atlantis message', Message_Command::class );
		}
	}

	/**
	 * Registers temporary WordPress-core compatibility filters.
	 *
	 * A home for always-on mitigations that must roll out fleet-wide during an
	 * incident, independent of module settings. Add filters here when needed and
	 * remove them once the corresponding upstream fix has shipped.
	 *
	 * Currently empty — no mitigations are active. (The previous
	 * `wp_img_tag_add_auto_sizes` disable was removed once the upstream
	 * Gutenberg fix shipped.)
	 *
	 * @since   1.2.2
	 * @version 1.2.4
	 *
	 * @return  void
	 */
	private function register_core_compat_filters(): void {
		// Intentionally empty. Register temporary core-compatibility filters here
		// during an incident, then remove them once the upstream fix is released.
	}

	// endregion

	// region HOOKS

	/**
	 * Initializes the plugin components if all prerequisites are met.
	 *
	 * @since   1.0.0
	 * @version 1.2.3
	 *
	 * @return  void
	 */
	public function maybe_initialize(): void {
		$is_active = $this->is_active();
		if ( is_wp_error( $is_active ) ) {
			a8csp_atlantis_output_requirements_error( $is_active );
			return;
		}

		try {
			$this->initialize();
		} catch ( \Throwable $e ) {
			// A plugin/core update replaces files one at a time, so the autoloader
			// can briefly fail to resolve a class mid-copy (or against stale
			// opcache right after). Skip init for this request rather than taking
			// down the whole site; the next request initializes normally once the
			// files have settled.
			error_log( 'A8CSP Atlantis: initialization skipped this request — ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	// endregion
}
