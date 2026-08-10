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
	 * All valid enforcement states.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @var array<int, string>
	 */
	public const VALID_STATES = array( self::STATE_INHERIT, self::STATE_ON, self::STATE_OFF );

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
		$state = self::get_configured_state();

		// Non-production safety: staging/dev sites frequently run login
		// automation (CI/e2e suites, uptime and synthetic-login monitors,
		// scripted provisioning) that bot protection would challenge or block,
		// so force it off there by default. An explicit `on` still wins, so a
		// site can be opted in deliberately (e.g. to test bot protection).
		if ( self::STATE_ON !== $state && ! self::is_production() ) {
			add_filter( self::FILTER, '__return_false', PHP_INT_MAX );
			return;
		}

		switch ( $state ) {
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
		register_setting(
			'a8csp_modules_group',
			$option_name,
			array( 'sanitize_callback' => array( self::class, 'sanitize_settings' ) )
		);

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
				$state    = isset( $settings['state'] ) && is_string( $settings['state'] ) ? $settings['state'] : self::STATE_INHERIT;
				$field_id = (string) ( $args['label_for'] ?? $args['option_name'] . '_state' );

				$choices = array(
					self::STATE_INHERIT => __( 'Inherit — leave the WP Cloud default untouched', 'a8csp-atlantis' ),
					self::STATE_ON      => __( 'On — force bot protection enabled', 'a8csp-atlantis' ),
					self::STATE_OFF     => __( 'Off — force bot protection disabled', 'a8csp-atlantis' ),
				);

				echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $args['option_name'] ) . '[state]">';
				foreach ( $choices as $value => $label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $value ),
						selected( $state, $value, false ),
						esc_html( $label )
					);
				}
				echo '</select>';

				// The dropdown is a no-op unless a listener is actually hooked to
				// the filter: that requires both WP Cloud credentials and the
				// mu-plugin. Name whichever precondition is missing so an operator
				// reacting to a lockout is not misled into thinking Off took effect.
				$notice = '';
				if ( ! self::is_wp_cloud() ) {
					$notice = __( 'This site is not a WP Cloud site, so this setting has no effect here.', 'a8csp-atlantis' );
				} elseif ( ! self::is_mu_plugin_present() ) {
					$notice = __( 'The WP Cloud bot protection mu-plugin is not present on this site, so this setting has no effect here.', 'a8csp-atlantis' );
				} elseif ( self::STATE_ON !== $state && ! self::is_production() ) {
					$notice = __( 'This is a non-production environment, so bot protection is forced off here unless Enforcement is set to On.', 'a8csp-atlantis' );
				}

				if ( '' !== $notice ) {
					echo '<p class="description">' . esc_html( $notice ) . '</p>';
				}
			},
			'a8csp-atlantis-modules',
			"{$option_name}_section",
			array(
				'option_name' => $option_name,
				'label_for'   => "{$option_name}_state",
			)
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
		$state    = isset( $settings['state'] ) && is_string( $settings['state'] ) ? $settings['state'] : self::STATE_INHERIT;

		return in_array( $state, self::VALID_STATES, true ) ? $state : self::STATE_INHERIT;
	}

	/**
	 * Sanitizes the module settings on save.
	 *
	 * Registered as the option's `sanitize_callback`. Whitelists `state` against
	 * the STATE_* constants (falling back to `inherit`) and carries the stored
	 * `enabled` flag forward — this module's form intentionally omits the base
	 * Enabled checkbox, which would otherwise drop that key on every save.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @param   mixed $input The raw option value submitted by the settings form.
	 *
	 * @return  array<string, string>
	 */
	public static function sanitize_settings( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		$state = isset( $input['state'] ) && is_string( $input['state'] ) ? $input['state'] : self::STATE_INHERIT;
		if ( ! in_array( $state, self::VALID_STATES, true ) ) {
			$state = self::STATE_INHERIT;
		}

		$stored  = a8csp_atlantis_get_module_settings( self::NAME );
		$enabled = isset( $stored['enabled'] ) && is_string( $stored['enabled'] ) ? $stored['enabled'] : '1';

		return array(
			'enabled' => $enabled,
			'state'   => $state,
		);
	}

	/**
	 * Persists the enforcement state, preserving any other stored sub-settings.
	 *
	 * The programmatic write path, used by the `wp atlantis bot-protection set`
	 * command. (The settings form writes through the option's sanitize_callback,
	 * self::sanitize_settings(), instead.) Validates $state, keeps the stored
	 * `enabled` flag, and reports a genuine persistence failure.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @param   string $state One of the STATE_* constants.
	 *
	 * @return  true|\WP_Error True on success, WP_Error if the state is invalid
	 *                         or could not be persisted.
	 */
	public static function set_state( string $state ): true|\WP_Error {
		if ( ! in_array( $state, self::VALID_STATES, true ) ) {
			return new \WP_Error(
				'a8csp_atlantis_invalid_bot_protection_state',
				wp_sprintf(
					/* translators: 1: provided value, 2: comma-separated list of valid values */
					__( 'Invalid enforcement state "%1$s". Valid values: %2$s.', 'a8csp-atlantis' ),
					$state,
					implode( ', ', self::VALID_STATES )
				)
			);
		}

		$settings_key      = a8csp_atlantis_generate_module_settings_key( self::NAME );
		$current           = a8csp_atlantis_get_module_settings( self::NAME );
		$settings          = $current;
		$settings['state'] = $state;
		if ( ! isset( $settings['enabled'] ) ) {
			$settings['enabled'] = '1';
		}

		if ( $settings === $current ) {
			return true; // Already persisted; nothing to write.
		}

		if ( ! update_option( $settings_key, $settings ) ) {
			return new \WP_Error(
				'a8csp_atlantis_bot_protection_save_failed',
				__( 'Failed to persist the bot protection enforcement state.', 'a8csp-atlantis' )
			);
		}

		return true;
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

	/**
	 * Whether the current environment should be treated as production.
	 *
	 * On non-production environments bot protection is forced off unless the
	 * state is explicitly `on` (see initialize()), since staging/dev sites
	 * commonly run login automation that protection would break.
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @return  bool
	 */
	public static function is_production(): bool {
		$is_production = ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type();

		/**
		 * Filters whether Bot Protection treats the current site as production.
		 *
		 * Return false to have protection forced off here (unless the state is
		 * `on`); return true to opt a non-production site back into the normal
		 * enablement tiers.
		 *
		 * @since 1.4.0
		 *
		 * @param bool $is_production Whether the environment type is `production`.
		 */
		return (bool) apply_filters( 'a8csp_atlantis_bot_protection_is_production', $is_production );
	}

	// endregion
}
