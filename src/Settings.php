<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Settings {
	// region METHODS

	/**
	 * Registers the plugin settings.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		// add_action( 'admin_init', [ $this, 'register_settings' ] );
		// add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	// endregion

	// region HOOKS

	/*
	public function register_settings(): void {

	}*/

	// endregion
}
