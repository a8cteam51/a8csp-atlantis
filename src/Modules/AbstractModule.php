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
		$settings = get_option( 'atlantis_enabled_modules', array() );
		if ( ! isset( $settings[ $this->get_settings_key() ] ) ) {
			return false;
		}

		return (bool) $settings[ $this->get_settings_key() ];
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
}
