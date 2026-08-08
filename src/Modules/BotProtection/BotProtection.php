<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\BotProtection;

use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * WP Cloud Bot Protection module.
 *
 * WP Cloud sites ship a `wpcloud-bot-protection` mu-plugin (the external name
 * for "blackbox") that gates the login and password-reset forms. Its loader
 * runs late on `plugins_loaded` and resolves enablement through the
 * `wpcloud_bot_protection_enable` filter, which overrides every other tier
 * (the `WPC_BOT_PROTECTION_ENABLED` constant, the platform define, and the
 * client-level percentage rollout).
 *
 * This module is Team 51's single control plane for that filter across the
 * fleet. It is mandatory (always loaded) and exposes one setting — the
 * enforcement `state`:
 *
 * - `inherit` (default): register nothing, leaving WP Cloud's own tiers to
 *   decide. Ships as a no-op so rolling this module out changes no behavior.
 * - `on`: force-enable via `__return_true`.
 * - `off`: force-disable via `__return_false` — a hard override for a site
 *   where protection is causing problems, even if a client-level rollout
 *   would otherwise enable it.
 *
 * The filter is registered at `PHP_INT_MAX` priority so our verdict is the
 * final say. Registration happens during `plugins_loaded` (priority 10, via
 * the plugin bootstrap), which is before the mu-plugin loader evaluates the
 * filter at `PHP_INT_MAX` priority, so the timing is guaranteed.
 *
 * @since   1.4.0
 * @version 1.4.0
 */
class BotProtection extends AbstractModule {
	// region FIELDS AND CONSTANTS

	/**
	 * The module's human-readable name. Drives the settings option key.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @var string
	 */
	private const NAME = 'Bot Protection';

	/**
	 * Enforcement state: defer to WP Cloud's own enablement tiers.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @var string
	 */
	public const STATE_INHERIT = 'inherit';

	/**
	 * Enforcement state: force bot protection on.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @var string
	 */
	public const STATE_ON = 'on';

	/**
	 * Enforcement state: force bot protection off.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @var string
	 */
	public const STATE_OFF = 'off';

	/**
	 * The WP Cloud filter that gates bot protection enablement.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @var string
	 */
	private const FILTER = 'wpcloud_bot_protection_enable';

	/**
	 * The WP Cloud mu-plugin loader function, used to detect the mu-plugin.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @var string
	 */
	private const LOADER_FUNCTION = 'wpcloud_bot_protection_loader';

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 */
	public function get_name(): string {
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 */
	public function get_description(): string {
		return __( 'Controls WP Cloud Bot Protection (login and password-reset gating) on WP Cloud sites. Use the enforcement control to force it on or off; "Inherit" leaves the WP Cloud default untouched.', 'a8csp-atlantis' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * This module is always loaded so its enforcement state is authoritative;
	 * the single control is the `state` setting (defaulting to `inherit`, a
	 * no-op), not an enable/disable toggle.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 */
	public function is_mandatory(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 */
	protected function initialize(): void {
		switch ( self::get_configured_state() ) {
			case self::STATE_ON:
				add_filter( self::FILTER, '__return_true', PHP_INT_MAX );
				break;

			case self::STATE_OFF:
				add_filter( self::FILTER, '__return_false', PHP_INT_MAX );
				break;

			case self::STATE_INHERIT:
			default:
				// Register nothing — WP Cloud's own enablement tiers decide.
				break;
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Renders a single "Enforcement" dropdown as the module's only control.
	 * The base Enabled checkbox is intentionally omitted — the module is
	 * mandatory, so the dropdown (inherit/on/off) is the one meaningful setting.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 */
	public function register_settings(): void {
		$option_name = a8csp_atlantis_generate_module_settings_key( $this->get_name() );
		register_setting( 'a8csp_modules_group', $option_name );

		add_settings_section(
			"{$option_name}_section",
			$this->get_name(),
			function (): void {
				echo wp_kses_post( wpautop( $this->get_description() ) );
			},
			'a8csp-atlantis-modules'
		);

		add_settings_field(
			"{$option_name}_state",
			__( 'Enforcement', 'a8csp-atlantis' ),
			function ( array $args ): void {
				$settings = a8csp_atlantis_get_module_settings( $this->get_name() );
				$state    = isset( $settings['state'] ) ? (string) $settings['state'] : self::STATE_INHERIT;

				$choices = array(
					self::STATE_INHERIT => __( 'Inherit — leave the WP Cloud default untouched', 'a8csp-atlantis' ),
					self::STATE_ON      => __( 'On — force bot protection enabled', 'a8csp-atlantis' ),
					self::STATE_OFF     => __( 'Off — force bot protection disabled', 'a8csp-atlantis' ),
				);

				echo '<select name="' . esc_attr( $args['option_name'] ) . '[state]">';
				foreach ( $choices as $value => $label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $value ),
						selected( $state, $value, false ),
						esc_html( $label )
					);
				}
				echo '</select>';

				if ( ! self::is_wp_cloud() ) {
					echo '<p class="description">' . esc_html__( 'This site is not a WP Cloud site, so this setting has no effect here.', 'a8csp-atlantis' ) . '</p>';
				}
			},
			'a8csp-atlantis-modules',
			"{$option_name}_section",
			array( 'option_name' => $option_name )
		);
	}

	// endregion

	// region METHODS

	/**
	 * Returns the configured enforcement state, sanitized to a known value.
	 *
	 * Exposed statically so the REST status controller (and, later, the
	 * companion Team 51 CLI command) can report the site's state without a
	 * module instance.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @return  string One of the STATE_* constants.
	 */
	public static function get_configured_state(): string {
		$settings = a8csp_atlantis_get_module_settings( self::NAME );
		$state    = isset( $settings['state'] ) ? (string) $settings['state'] : self::STATE_INHERIT;

		return in_array( $state, array( self::STATE_INHERIT, self::STATE_ON, self::STATE_OFF ), true )
			? $state
			: self::STATE_INHERIT;
	}

	/**
	 * Whether this is a WP Cloud site (the credentials the mu-plugin requires).
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @return  bool
	 */
	public static function is_wp_cloud(): bool {
		return defined( 'ATOMIC_SITE_ID' ) && '' !== (string) constant( 'ATOMIC_SITE_ID' )
			&& defined( 'ATOMIC_SITE_API_KEY' ) && '' !== (string) constant( 'ATOMIC_SITE_API_KEY' );
	}

	/**
	 * Whether the WP Cloud bot protection mu-plugin is present on this site.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @return  bool
	 */
	public static function is_mu_plugin_present(): bool {
		return function_exists( self::LOADER_FUNCTION );
	}

	// endregion
}
