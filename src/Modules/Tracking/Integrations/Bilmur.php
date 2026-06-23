<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking\Integrations;

use A8C\SpecialProjects\Atlantis\Modules\Tracking\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Bilmur RUM Integration class.
 *
 * @since   1.0.0
 * @version 1.2.0
 */
class Bilmur extends AbstractIntegration {
	/**
	 * Custom properties to inject into wpcomsh's Bilmur RUM data on Atomic sites.
	 *
	 * @since   1.2.0
	 * @version 1.2.0
	 *
	 * @var array<string, string>
	 */
	private static array $wpcomsh_custom_properties = array(
		'wpcomsp' => '1',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.2.0
	 */
	public function is_active(): bool {
		// On Atomic sites where wpcomsh handles Bilmur, always activate to register the filter.
		if ( self::is_wpcomsh_bilmur_active() ) {
			return true;
		}

		// On non-wpcomsh sites (Pressable), require explicit opt-in.
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
	 * @version 1.2.0
	 */
	protected function initialize(): void {
		// Always register the wpcomsh filter for Atomic compatibility (harmless on non-Atomic sites).
		add_filter( 'wpcomsh_rum_kv', array( self::class, 'filter_wpcomsh_rum_kv' ), 10, 2 );

		if ( ! self::is_wpcomsh_bilmur_active() ) {
			$this->initialize_bilmur_output();
		}
	}

	/**
	 * Check if wpcomsh is handling Bilmur output.
	 *
	 * Wpcomsh hooks its Bilmur output at wp_footer. If this function exists
	 * and is hooked, we should not output our own script/meta tag.
	 *
	 * @since   1.2.0
	 * @version 1.2.0
	 *
	 * @return bool
	 */
	private static function is_wpcomsh_bilmur_active(): bool {
		return function_exists( 'wpcomsh_footer_rum_js' )
			&& false !== has_action( 'wp_footer', 'wpcomsh_footer_rum_js' );
	}

	/**
	 * Filter wpcomsh RUM key-value pairs to add custom properties on Atomic sites.
	 *
	 * This filter is provided by wpcomsh and allows us to inject custom properties
	 * into the Bilmur data without duplicating the script or meta tag.
	 *
	 * Note: this filter feeds the `data-custom-props` JSON blob only. Values
	 * that must surface as top-level `data-*` attributes on the meta tag
	 * (e.g. `site-v`) cannot be injected here and must be handled wpcomsh-side.
	 *
	 * @since   1.2.0
	 * @version 1.3.0
	 *
	 * @param array<string, string> $kv      The existing key-value pairs.
	 * @param string                $service The bilmur service name.
	 *
	 * @return array<string, string> The modified key-value pairs.
	 */
	public static function filter_wpcomsh_rum_kv( array $kv, string $service ): array {
		return array_merge( $kv, self::$wpcomsh_custom_properties );
	}

	/**
	 * Initialize Bilmur script and meta tag output for non-wpcomsh sites.
	 *
	 * @since   1.2.0
	 * @version 1.2.0
	 */
	private function initialize_bilmur_output(): void {
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
				$custom_properties = defined( 'WPCOMSP_BILMUR_CUSTOM_PROPERTIES' ) && is_array( WPCOMSP_BILMUR_CUSTOM_PROPERTIES ) ? WPCOMSP_BILMUR_CUSTOM_PROPERTIES : array();

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
					data-site-v="<?php echo esc_attr( self::get_site_hash() ); ?>"
				>
				<?php
			}
		);
	}

	/**
	 * Returns an MD5 hash of the site's host (e.g. md5( "example.com" )).
	 *
	 * Emitted as the `site-v` Bilmur property so a single site can be
	 * identified across page views without exposing the full URL. Rendered as
	 * a top-level `data-site-v` attribute on the meta tag, alongside
	 * `data-provider` and `data-service`. Only applied on the non-wpcomsh
	 * code path; on Atomic, wpcomsh is responsible for emitting `data-site-v`
	 * on its own meta tag (the `wpcomsh_rum_kv` filter only feeds custom
	 * props and cannot set top-level data attributes).
	 *
	 * @since   1.3.0
	 * @version 1.3.0
	 *
	 * @return string
	 */
	private static function get_site_hash(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return md5( is_string( $host ) ? $host : '' );
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
