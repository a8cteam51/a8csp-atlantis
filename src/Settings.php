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

	/**
	 * Prefixes of the options, and the transients stored as options, that Atlantis keeps.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @var string[]
	 */
	private const PROTECTED_OPTION_PREFIXES = array(
		'a8csp_atlantis_',
		'a8csp_module_',
		'plugin_autoupdate_filter_',
		'_transient_a8csp_atlantis_',
		'_transient_timeout_a8csp_atlantis_',
		'_transient_wpcpmsp_',
		'_transient_timeout_wpcpmsp_',
	);

	/**
	 * Options Atlantis keeps under a name no prefix above covers.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @var string[]
	 */
	private const PROTECTED_OPTIONS = array( 'plugin_update_delays' );

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

		// The modules screen has its own gate (an Automattician on a managed site), but core's
		// options.php saves the settings and applies its own, which defaults to manage_options.
		add_filter( 'option_page_capability_' . self::MODULES_OPTION_GROUP, array( $this, 'filter_modules_option_page_capability' ) );
		add_filter( 'user_has_cap', array( $this, 'filter_grant_manage_modules_capability' ), 10, 4 );

		// That only covers this group. options.php saves other groups too, and its All Settings
		// screen writes whatever option names the request lists, checking only manage_options.
		add_action( 'load-options.php', array( $this, 'maybe_protect_options' ) );
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
		if ( ! a8csp_atlantis_current_user_can_manage() ) {
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
	 * Grants the module capability to administrators on an Automattic domain, or to every
	 * administrator on a site that is not managed by the team.
	 *
	 * Reads `manage_options` out of the capability map rather than calling `current_user_can()`,
	 * which would re-enter this filter.
	 *
	 * @since   1.3.1
	 * @version 1.5.0
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

		if ( ! a8csp_atlantis_is_managed_site() || a8csp_atlantis_user_has_automattic_email( $user ) ) {
			$allcaps[ self::MANAGE_MODULES_CAP ] = true;
		}

		return $allcaps;
	}

	/**
	 * Keeps Atlantis's own options out of an options.php save by anyone who may not manage Atlantis.
	 *
	 * On a managed site, that stops an administrator who is not an Automattician from changing
	 * through core's screens what the Atlantis screens refuse to let them change, including whether
	 * the site counts as managed at all. It makes the admin screens' gate consistent; it is not a
	 * security boundary, since an administrator who can install plugins can change any option.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @return  void
	 */
	public function maybe_protect_options(): void {
		// Only a save posts an option page; options.php verifies its nonce itself.
		if ( ! isset( $_POST['option_page'] ) || current_user_can( self::MANAGE_MODULES_CAP ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		add_filter( 'pre_update_option', array( $this, 'filter_keep_protected_option' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Hands back the stored value of an Atlantis option, so that saving it changes nothing.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @param   mixed  $value     The value being saved.
	 * @param   string $option    The option name.
	 * @param   mixed  $old_value The stored value.
	 *
	 * @return  mixed
	 */
	public function filter_keep_protected_option( $value, string $option, $old_value ) {
		if ( ! self::is_protected_option( $option ) ) {
			return $value;
		}

		if ( $value !== $old_value ) {
			add_settings_error(
				'general',
				'a8csp_atlantis_protected_option',
				__( 'Atlantis settings can only be changed by someone who can manage Atlantis, so they were left as they were.', 'a8csp-atlantis' )
			);
		}

		return $old_value;
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether an option is one Atlantis keeps.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @param   string $option The option name.
	 *
	 * @return  bool
	 */
	public static function is_protected_option( string $option ): bool {
		if ( \in_array( $option, self::PROTECTED_OPTIONS, true ) ) {
			return true;
		}

		foreach ( self::PROTECTED_OPTION_PREFIXES as $prefix ) {
			if ( \str_starts_with( $option, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	// endregion
}
