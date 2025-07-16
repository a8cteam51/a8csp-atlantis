<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for all modules.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
abstract class AbstractModule {
	/**
	 * Returns the name of the module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	abstract public function get_name(): string;

	/**
	 * Returns the short description of the module.
	 * Useful, e.g., for outputting on a settings page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	abstract public function get_description(): string;

	/**
	 * Returns whether the module is always enabled or whether it can be disabled by the user.
	 *
	 * @return  bool
	 */
	public function is_mandatory(): bool {
		return false;
	}

	/**
	 * Returns whether the module is disabled due to environmental constraints.
	 *
	 * Before the init hook, this method should return true if the module is disabled.
	 * After the init hook, it should return a WP_Error object with a message explaining why the module is disabled.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool|\WP_Error
	 */
	public function is_disabled(): bool|\WP_Error {
		return false;
	}

	/**
	 * Returns whether the module is active.
	 * By default, that means that the module is enabled in the settings.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	public function is_active(): bool {
		if ( $this->is_mandatory() ) {
			return true;
		}

		$settings = a8csp_atlantis_get_module_settings( $this->get_name() );
		return (bool) ( $settings['enabled'] ?? false );
	}

	/**
	 * Initializes the module if it is active.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function maybe_initialize(): void {
		add_action( 'init', array( $this, 'maybe_set_default_settings' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		if ( true !== $this->is_active() ) {
			return;
		}

		if ( false !== $this->is_disabled() ) {
			add_action(
				'admin_notices',
				function () {
					$error = $this->is_disabled();
					if ( ! is_wp_error( $error ) ) {
						return;
					}

					$environment_error = wp_sprintf(
						/* translators: 1: Plugin name, 2: Module name, 3: Error message */
						__( '<strong>%1$s %2$s Module:</strong> %3$s', 'a8csp-atlantis' ),
						a8csp_atlantis_get_plugin_metadata( 'Name' ),
						esc_html( $this->get_name() ),
						esc_html( $error->get_error_message() )
					);

					wp_admin_notice( $environment_error, array( 'type' => 'error' ) );
				}
			);
			return;
		}

		$this->initialize();
	}

	/**
	 * Initializes the module components.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	abstract protected function initialize(): void;

	// region HOOKS

	/**
	 * Sets the default settings for the module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function maybe_set_default_settings(): void {
		$settings_key = a8csp_atlantis_generate_module_settings_key( $this->get_name() );

		$settings = get_option( $settings_key, null );
		if ( is_null( $settings ) ) {
			update_option( $settings_key, array( 'enabled' => '1' ) );
		} elseif ( $this->is_mandatory() ) {
			$is_enabled = isset( $settings['enabled'] ) && '1' === $settings['enabled'];
			if ( ! $is_enabled ) {
				$settings = a8csp_atlantis_get_module_settings( $this->get_name() );
				update_option( $settings_key, array( 'enabled' => '1' ) + $settings );
			}
		}
	}

	/**
	 * Registers the default settings for the module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_settings(): void {
		$option_name = a8csp_atlantis_generate_module_settings_key( $this->get_name() );
		register_setting( 'a8csp_modules_group', $option_name );

		add_settings_section(
			"{$option_name}_section",
			$this->get_name(),
			function (): void {
				echo wp_kses_post( wpautop( $this->get_description() ) );

				$disabled = $this->is_disabled();
				if ( is_wp_error( $disabled ) ) {
					$warning_intro   = __( 'Warning!', 'a8csp-atlantis' );
					$warning_message = __( 'This module cannot run due to the following reason', 'a8csp-atlantis' );
					echo wp_kses_post( wpautop( wp_sprintf( '<strong><span style="color: red;">%s</span> %s</strong>: %s', $warning_intro, $warning_message, $disabled->get_error_message() ) ) );
				}
			},
			'a8csp-atlantis-modules'
		);

		add_settings_field(
			"{$option_name}_enabled",
			__( 'Enabled', 'a8csp-atlantis' ),
			function ( array $args ): void {
				$value    = get_option( $args['option_name'] );
				$enabled  = isset( $value['enabled'] ) && '1' === $value['enabled'];
				$disabled = $this->is_mandatory() || ( false !== $this->is_disabled() && false === $enabled );

				printf(
					'<input type="checkbox" name="%s[enabled]" value="1" %s %s />',
					esc_attr( $args['option_name'] ),
					checked( $enabled, true, false ),
					disabled( $disabled, true, false )
				);
			},
			'a8csp-atlantis-modules',
			"{$option_name}_section",
			array( 'option_name' => $option_name )
		);
	}

	// endregion
}
