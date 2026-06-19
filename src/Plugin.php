<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\CLI\Message_Command;
use A8C\SpecialProjects\Atlantis\CLI\Module_Command;
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
	private function __wakeup(): void {
		throw new \RuntimeException( 'Cannot unserialize a singleton.' );
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
		$this->encryption = new Encryption();
		$this->encryption->initialize();

		$this->modules = new Modules();
		$this->modules->initialize();

		$this->settings = new Settings();
		$this->settings->initialize();

		$this->status_controller = new Status_Controller();
		$this->status_controller->initialize();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'atlantis module', Module_Command::class );
			\WP_CLI::add_command( 'atlantis message', Message_Command::class );
		}
	}

	// endregion

	// region HOOKS

	/**
	 * Initializes the plugin components if all prerequisites are met.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function maybe_initialize(): void {
		$is_active = $this->is_active();
		if ( is_wp_error( $is_active ) ) {
			a8csp_atlantis_output_requirements_error( $is_active );
			return;
		}

		$this->initialize();
	}

	// endregion
}
