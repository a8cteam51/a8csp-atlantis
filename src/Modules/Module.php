<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for all modules.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
abstract class Module {
	abstract public function get_name(): string;

	abstract public function get_description(): string;

	public function is_disabled(): false|WP_Error {
		return false;
	}

	public function is_active(): bool {
		$settings = get_option( 'atlantis_enabled_modules', array() );
		return isset( $settings[ sanitize_title( $this->get_name() ) ] );
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
			// TODO: Output error!
			return;
		}
		
		$this->initialize();
	}
} 