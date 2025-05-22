<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Colophon;

use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Colophon Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Colophon extends AbstractModule {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_name(): string {
		return 'Colophon';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_description(): string {
		return 'Having set up footer links in many partner sites, this aims to simplify the deployment of each, by using a more consistent api.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function initialize(): void {
		add_action( 'team51_credits', 'team51_credits' );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the shortcodes.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'team51-credits', 'team51_credits_shortcode' );
		add_shortcode( 'team51-current-year', 'team51_current_year_shortcode' );
	}

	// endregion
}
