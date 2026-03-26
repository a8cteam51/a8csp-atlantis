<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking\Integrations;

use A8C\SpecialProjects\Atlantis\Modules\Tracking\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Bilmur RUM Integration class.
 *
 * @since   1.0.0
 * @version 1.1.0
 */
class Bilmur extends AbstractIntegration {
	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.1.0
	 */
	public function is_active(): bool {
		if ( ! defined( 'WPCOMSP_BILMUR_TRACKING' ) || ! WPCOMSP_BILMUR_TRACKING ) {
			return false;
		}

		if ( ! defined( 'WPCOMSP_BILMUR_PROVIDER' ) || ! WPCOMSP_BILMUR_PROVIDER || ! defined( 'WPCOMSP_BILMUR_SERVICE' ) || ! WPCOMSP_BILMUR_SERVICE ) {
			return false;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.1.0
	 */
	protected function initialize(): void {
		add_action(
			'wp_enqueue_scripts',
			static function () {
				// Request a new version of bilmur every week.
				// This keeps bilmur up-to-date independently of CDN caching times.
				$weekly_cachebust = 'm=' . gmdate( 'YW' );

				// Allow for manually forcing a new version of bilmur with a plugin update.
				// Just increment this number if that's the case.
				$manual_version = '1';

				wp_enqueue_script(
					'bilmur',
					'https://s0.wp.com/wp-content/js/bilmur.min.js?' . $weekly_cachebust,
					array(),
					$manual_version,
					array(
						'strategy'  => 'defer',
						'in_footer' => true,
					)
				);
			}
		);

		// WP Rocket compatibility: prevent "Delay JavaScript Execution" from
		// blocking the bilmur beacon. The `nowprocket` attribute tells WP Rocket
		// to skip delaying this script.
		$active_plugins = get_option( 'active_plugins' );
		if ( is_array( $active_plugins ) && in_array( 'wp-rocket/wp-rocket.php', $active_plugins, true ) ) {
			add_filter(
				'wp_script_attributes',
				static function ( array $attributes ): array {
					if ( isset( $attributes['id'] ) && 'bilmur-js' === $attributes['id'] ) {
						$attributes['nowprocket'] = true;
					}
					return $attributes;
				}
			);
		}

		// Output bilmur config as a <meta> tag for the script to pick up.
		add_action(
			'wp_footer',
			static function () {
				$custom_properties = defined( 'WPCOMSP_BILMUR_CUSTOM_PROPERTIES' ) ? WPCOMSP_BILMUR_CUSTOM_PROPERTIES : array();

				$custom_properties['woo_active'] = class_exists( 'WooCommerce' ) ? '1' : '0';

				?>
				<meta
					id="bilmur"
					property="bilmur:data"
					content=""
					data-provider="<?php echo esc_attr( WPCOMSP_BILMUR_PROVIDER ); ?>"
					data-service="<?php echo esc_attr( WPCOMSP_BILMUR_SERVICE ); ?>"
					data-custom-props="<?php echo esc_attr( (string) wp_json_encode( $custom_properties ) ); ?>"
					data-site-tz="<?php echo esc_attr( self::get_timezone_string() ); ?>"
				>
				<?php
			}
		);
	}

	/**
	 * Returns a standardized timezone string.
	 *
	 * `wp_timezone_string()` sometimes returns offsets (e.g. "-07:00"), which are
	 * a non-standard representation that only works in PHP. This method returns a
	 * standardized timezone string instead, of the form "Etc/GMT+7" for integer
	 * hour offsets, or a matching "<Area>/<City>" form for fractional hour offsets.
	 *
	 * @since   1.1.0
	 * @version 1.1.0
	 *
	 * @return string
	 */
	private static function get_timezone_string(): string {
		$wp_tz = wp_timezone_string();

		if ( '' === $wp_tz ) {
			return 'UTC';
		}

		// Handle "+/-HH:MM" offset format.
		if ( 1 === preg_match( '/^([+-])?(\d{1,2}):(\d{2})$/', $wp_tz, $matches ) ) {
			$sign    = '-' === $matches[1] ? -1 : 1;
			$hours   = intval( $matches[2], 10 );
			$minutes = intval( $matches[3], 10 );

			// For fractional hour offsets, find a matching "<Area>/<City>" timezone.
			if ( $minutes > 0 ) {
				$offset  = $sign * ( $hours * 3600 + $minutes * 60 );
				$city_tz = timezone_name_from_abbr( '', $offset, 0 );

				if ( false !== $city_tz && '' !== $city_tz ) {
					return $city_tz;
				}
			}

			// For integer hour offsets, use "Etc/GMT(+|-)N".
			// The sign is flipped to match how the Etc area is specced.
			// This codepath is also the fallback when no city matches a fractional offset.
			return 'Etc/GMT' . ( -1 === $sign ? '+' : '-' ) . $hours;
		}

		// Handle legacy "UTC±N" offsets.
		if ( 1 === preg_match( '/^UTC([+-])(\d{1,2})$/i', $wp_tz, $matches ) ) {
			$sign  = '-' === $matches[1] ? -1 : 1;
			$hours = intval( $matches[2], 10 );

			return 'Etc/GMT' . ( -1 === $sign ? '+' : '-' ) . $hours;
		}

		// For named timezones, return as-is.
		return $wp_tz;
	}
}
