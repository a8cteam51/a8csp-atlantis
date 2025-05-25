<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking\Integrations;

use A8C\SpecialProjects\Atlantis\Modules\Tracking\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Integration class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Bilmur extends AbstractIntegration {
	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function is_active(): bool {
		return defined( 'WPCOMSP_BILMUR_TRACKING' ) && WPCOMSP_BILMUR_TRACKING;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function initialize(): void {
		add_action(
			'wp_enqueue_scripts',
			static function () {
				wp_enqueue_script( 'bilmur', 'https://s0.wp.com/wp-content/js/bilmur.min.js?m=202215', array(), '1.0.0', true );
			}
		);

		add_filter(
			'script_loader_tag',
			static function ( $tag, $handle ) {
				if ( 'bilmur' === $handle ) {
					if ( defined( 'WPCOMSP_BILMUR_PROVIDER' ) ) {
						$tag = str_replace( ' src', ' data-provider="' . WPCOMSP_BILMUR_PROVIDER . '" src', $tag );
					}
					if ( defined( 'WPCOMSP_BILMUR_SERVICE' ) ) {
						$tag = str_replace( ' src', ' data-service="' . WPCOMSP_BILMUR_SERVICE . '" src', $tag );
					}
					if ( defined( 'WPCOMSP_BILMUR_CUSTOM_PROPERTIES' ) ) {
						$tag = str_replace( ' src', ' data-customproperties="' . wp_json_encode( WPCOMSP_BILMUR_CUSTOM_PROPERTIES ) . '" src', $tag );
					}
				}

				return $tag;
			},
			10,
			2
		);
	}
}
