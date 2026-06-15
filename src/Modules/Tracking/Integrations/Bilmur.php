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

		if ( self::is_wpcomsh_bilmur_active() ) {
			$this->register_atomic_host_guard();
		} else {
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
	 * @since   1.2.0
	 * @version 1.2.0
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
	 * The meta and script are not emitted statically. Instead, a small inline
	 * guard injects both at runtime, but only when the visitor's hostname matches
	 * the canonical site host. Scraped/copied sites therefore never load bilmur
	 * and never report to Grafana.
	 *
	 * @since   1.2.0
	 * @version 1.3.0
	 */
	private function initialize_bilmur_output(): void {
		add_action(
			'wp_footer',
			static function () {
				$expected_host = (string) wp_parse_url( (string) home_url(), PHP_URL_HOST );
				if ( '' === $expected_host ) {
					return;
				}

				$custom_properties               = defined( 'WPCOMSP_BILMUR_CUSTOM_PROPERTIES' ) && is_array( WPCOMSP_BILMUR_CUSTOM_PROPERTIES ) ? WPCOMSP_BILMUR_CUSTOM_PROPERTIES : array();
				$custom_properties['woo_active'] = class_exists( 'WooCommerce' ) ? '1' : '0';

				// Request a new version of bilmur every week.
				// This keeps bilmur up-to-date independently of CDN caching times.
				$weekly_cachebust = 'm=' . gmdate( 'YW' );

				// Allow for manually forcing a new version of bilmur with a plugin update.
				// Just increment this number if that's the case.
				$manual_version = '1';

				$config = array(
					'host'        => $expected_host,
					'provider'    => (string) WPCOMSP_BILMUR_PROVIDER,
					'service'     => (string) WPCOMSP_BILMUR_SERVICE,
					'customProps' => (string) wp_json_encode( $custom_properties ),
					'siteTz'      => self::get_timezone_string(),
					'src'         => 'https://s0.wp.com/wp-content/js/bilmur.min.js?' . $weekly_cachebust . '&ver=' . $manual_version,
				);
				?>
				<script id="bilmur-host-guard" nowprocket>
				(function () {
					var cfg = <?php echo wp_json_encode( $config ); ?>;
					if ( window.location.hostname !== cfg.host ) {
						return;
					}
					var meta = document.createElement( 'meta' );
					meta.id = 'bilmur';
					meta.setAttribute( 'property', 'bilmur:data' );
					meta.setAttribute( 'content', '' );
					meta.setAttribute( 'data-provider', cfg.provider );
					meta.setAttribute( 'data-service', cfg.service );
					meta.setAttribute( 'data-custom-props', cfg.customProps );
					meta.setAttribute( 'data-site-tz', cfg.siteTz );
					( document.head || document.documentElement ).appendChild( meta );

					var s = document.createElement( 'script' );
					s.id = 'bilmur-js';
					s.src = cfg.src;
					s.defer = true;
					document.body.appendChild( s );
				})();
				</script>
				<?php
			}
		);
	}

	/**
	 * Register a client-side host guard for Atomic sites where wpcomsh emits the
	 * bilmur tags directly. Since wpcomsh's output can't be suppressed from here,
	 * the guard removes the meta tag (and best-effort the script element) when
	 * the visitor's hostname doesn't match the canonical site host. Without the
	 * meta tag, bilmur has no provider/service to report to.
	 *
	 * @since   1.3.0
	 * @version 1.3.0
	 */
	private function register_atomic_host_guard(): void {
		add_action(
			'wp_footer',
			static function () {
				$expected_host = (string) wp_parse_url( (string) home_url(), PHP_URL_HOST );
				if ( '' === $expected_host ) {
					return;
				}
				?>
				<script id="bilmur-host-guard" nowprocket>
				(function () {
					if ( window.location.hostname === <?php echo wp_json_encode( $expected_host ); ?> ) {
						return;
					}
					var meta = document.getElementById( 'bilmur' );
					if ( meta && meta.parentNode ) {
						meta.parentNode.removeChild( meta );
					}
					var script = document.getElementById( 'bilmur-js' );
					if ( script && script.parentNode ) {
						script.parentNode.removeChild( script );
					}
				})();
				</script>
				<?php
			},
			PHP_INT_MAX
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
