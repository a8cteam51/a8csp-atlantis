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
	 * Returns the key used to store the module's settings in the database.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	public function get_settings_key(): string {
		return sanitize_title( $this->get_name() );
	}

	/**
	 * Returns whether the module is disabled due to environmental constraints.
	 * The reason for the module being disabled should be returned as a WP_Error object.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  false|\WP_Error
	 */
	public function is_disabled(): false|\WP_Error {
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
		$settings = get_option( "a8csp_module_{$this->get_settings_key()}", array() );
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		if ( ! $this->is_active() ) {
			return;
		}

		$is_disabled = $this->is_disabled();
		if ( is_wp_error( $is_disabled ) ) {
			add_action(
				'admin_notices',
				function () use ( $is_disabled ) {
					wp_admin_notice(
						wp_sprintf(
							'<strong>%s</strong>: %s',
							esc_html( $this->get_name() ),
							esc_html( $is_disabled->get_error_message() )
						),
						array( 'type' => 'error' )
					);
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
	 * Registers the default settings for the module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_settings(): void {
		$option_name = "a8csp_module_{$this->get_settings_key()}";
		register_setting( 'a8csp_modules_group', $option_name );

		add_settings_section(
			"{$this->get_settings_key()}_section",
			$this->get_name(),
			fn() => '<p>' . printf( esc_html( $this->get_description() ) ) . '</p>',
			'a8csp-atlantis-modules'
		);

		add_settings_field(
			"{$this->get_settings_key()}_enabled",
			__( 'Enabled', 'a8csp-atlantis' ),
			function ( array $args ): void {
				$value   = get_option( $args['option_name'] );
				$enabled = isset( $value['enabled'] ) && $value['enabled'];

				printf(
					'<input type="checkbox" name="%s[enabled]" value="1" %s />',
					esc_attr( $args['option_name'] ),
					checked( $enabled, true, false )
				);
			},
			'a8csp-atlantis-modules',
			"{$this->get_settings_key()}_section",
			array( 'option_name' => $option_name )
		);
	}

	// endregion
}
