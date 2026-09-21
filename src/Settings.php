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
	// region FIELDS AND CONSTANTS

	/**
	 * The settings group the module settings are registered into.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @var string
	 */
	public const MODULES_OPTION_GROUP = 'a8csp_modules_group';

	/**
	 * Capability required to save module settings.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @var string
	 */
	public const MANAGE_MODULES_CAP = 'a8csp_atlantis_manage_modules';

	// endregion

	// region METHODS

	/**
	 * Registers the plugin settings.
	 *
	 * @since   1.0.0
	 * @version 1.3.1
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'remove_default_submenu' ), 999 );

		// The modules screen is gated on being an Automattician, but core's options.php saves the
		// settings and applies its own gate, which defaults to manage_options.
		add_filter( 'option_page_capability_' . self::MODULES_OPTION_GROUP, array( $this, 'filter_modules_option_page_capability' ) );
		add_filter( 'user_has_cap', array( $this, 'filter_grant_manage_modules_capability' ), 10, 4 );
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

	/**
	 * Requires the module capability rather than `manage_options` when saving module settings.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @param   string $capability The capability core would otherwise require.
	 *
	 * @return  string
	 */
	public function filter_modules_option_page_capability( string $capability ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return self::MANAGE_MODULES_CAP;
	}

	/**
	 * Grants the module capability to administrators on an Automattic domain.
	 *
	 * Reads `manage_options` out of the capability map rather than calling `current_user_can()`,
	 * which would re-enter this filter.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @param   array<string, bool> $allcaps The user's capabilities.
	 * @param   string[]            $caps    The capabilities being checked.
	 * @param   array<mixed>        $args    Context for the check.
	 * @param   \WP_User            $user    The user being checked.
	 *
	 * @return  array<string, bool>
	 */
	public function filter_grant_manage_modules_capability( array $allcaps, array $caps, array $args, \WP_User $user ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( true !== ( $allcaps['manage_options'] ?? false ) ) {
			return $allcaps;
		}

		if ( a8csp_atlantis_user_has_automattic_email( $user ) ) {
			$allcaps[ self::MANAGE_MODULES_CAP ] = true;
		}

		return $allcaps;
	}

	// endregion
}
