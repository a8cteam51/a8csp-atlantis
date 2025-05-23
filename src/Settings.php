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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'remove_default_submenu' ), 999 );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the admin menu.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_admin_menu(): void {
		if ( ! a8csp_atlantis_is_automattician() ) {
			return;
		}

		add_menu_page(
			_x( 'Atlantis', 'page title', 'a8csp-atlantis' ),
			_x( 'Atlantis', 'menu title', 'a8csp-atlantis' ),
			'manage_options',
			'a8csp-atlantis',
			'__return_null',
			'dashicons-plugins-checked',
			3
		);

		do_action( 'a8csp/atlantis/admin_menu_registered' );
	}

	/**
	 * Removes the default submenu page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function remove_default_submenu(): void {
		remove_submenu_page( 'a8csp-atlantis', 'a8csp-atlantis' );
	}

	// endregion
}
