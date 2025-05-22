<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for all modules.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
abstract class Module {
	abstract public function get_name(): string;

	abstract public function initialize(): void;

	abstract public function get_description(): string;

	public function get_settings_key(): string {
		return sanitize_title( $this->get_name() );
	}

	public function is_disabled(): false|\WP_Error {
		return false;
	}

	public function is_active(): bool {
		$settings = get_option( 'atlantis_enabled_modules', array() );
		return isset( $settings[ $this->get_settings_key() ] ) && $settings[ $this->get_settings_key() ];
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
					printf(
						'<div class="notice notice-error"><p><strong>%s</strong>: %s</p></div>',
						esc_html( $this->get_name() ),
						esc_html( $is_disabled->get_error_message() )
					);
				}
			);
			return;
		}

		$this->initialize();
	}
}
