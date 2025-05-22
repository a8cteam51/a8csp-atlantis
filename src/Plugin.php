<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

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
	 */
	public Encryption $encryption;

	/**
	 * The modules component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public Modules $modules;

	/**
	 * The settings component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public Settings $settings;

	/**
	 * The updater component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public Updater $updater;

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
		$this->encryption = new Encryption();
		$this->encryption->initialize();

		$this->modules = new Modules();
		$this->modules->initialize();

		$this->settings = new Settings();
		$this->settings->initialize();

		$this->updater = new Updater();
		$this->updater->initialize();
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
