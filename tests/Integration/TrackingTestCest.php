<?php
/**
 * Integration tests for Tracking module integrations.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\Tracking\Integrations\Bilmur;
use A8C\SpecialProjects\Atlantis\Modules\Tracking\Integrations\Sensei;
use A8C\SpecialProjects\Atlantis\Modules\Tracking\Integrations\WooCommerce;
use A8C\SpecialProjects\Atlantis\Modules\Tracking\Tracking;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Tracking module integration tests.
 */
class TrackingTestCest {
	/**
	 * Ensure module metadata remains stable.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function module_metadata_is_stable( IntegrationTester $i ): void {
		$module = new Tracking();

		Assert::assertSame( 'Tracking', $module->get_name() );
		Assert::assertStringContainsString( 'tracking', strtolower( $module->get_description() ) );
	}

	/**
	 * Ensure WooCommerce and Sensei integrations enforce tracking opt-in.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function integrations_enable_tracking( IntegrationTester $i ): void {
		$woocommerce = new WooCommerce();
		$woocommerce->maybe_initialize();
		Assert::assertSame( 'yes', apply_filters( 'option_woocommerce_allow_tracking', 'no' ) );

		$sensei = new Sensei();
		$sensei->maybe_initialize();
		$sensei_settings = apply_filters( 'option_sensei-settings', array( 'sensei_usage_tracking_enabled' => false ) );
		Assert::assertIsArray( $sensei_settings );
		Assert::assertTrue( (bool) $sensei_settings['sensei_usage_tracking_enabled'] );
	}

	/**
	 * Ensure integration activation defaults stay enabled when constants are undefined.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function integration_activation_defaults_are_enabled( IntegrationTester $i ): void {
		$woocommerce = new WooCommerce();
		$sensei      = new Sensei();

		Assert::assertTrue( $woocommerce->is_active() );
		Assert::assertTrue( $sensei->is_active() );
	}

	/**
	 * The Bilmur `site-v` value is the MD5 of the site's host, so it identifies a
	 * single site without exposing the full URL.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function bilmur_site_v_is_md5_of_home_host( IntegrationTester $i ): void {
		$method = new \ReflectionMethod( Bilmur::class, 'get_site_hash' );
		$method->setAccessible( true );
		$hash = (string) $method->invoke( null );

		$expected = md5( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		Assert::assertSame( $expected, $hash );
		Assert::assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $hash );
	}

	/**
	 * On the non-wpcomsh path Bilmur emits `data-site-v` (the hashed host) on its
	 * meta tag, and it always opts into wpcomsh's own `site-v` attribute for the
	 * Atomic path via the `wpcomsh_bilmur_site_v` filter.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function bilmur_emits_site_v_and_opts_into_wpcomsh( IntegrationTester $i ): void {
		// Bilmur's non-wpcomsh output path is gated behind these opt-in constants.
		foreach (
			array(
				'WPCOMSP_BILMUR_TRACKING' => true,
				'WPCOMSP_BILMUR_PROVIDER' => 'test-provider',
				'WPCOMSP_BILMUR_SERVICE'  => 'test-service',
			) as $name => $value
		) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		// Snapshot every hook registry initialize() mutates so we can restore it
		// afterwards and never leak Bilmur's output callbacks into other tests.
		$hooks    = array( 'wp_footer', 'wp_enqueue_scripts', 'wp_script_attributes', 'wpcomsh_bilmur_site_v', 'wpcomsh_rum_kv' );
		$snapshot = array();
		foreach ( $hooks as $hook ) {
			$snapshot[ $hook ] = isset( $GLOBALS['wp_filter'][ $hook ] ) ? clone $GLOBALS['wp_filter'][ $hook ] : null;
		}

		try {
			$bilmur = new Bilmur();
			Assert::assertTrue( $bilmur->is_active(), 'Bilmur should be active once the opt-in constants are defined.' );

			$bilmur->maybe_initialize();

			// Atomic path: the opt-in filter is registered unconditionally.
			Assert::assertNotFalse(
				has_filter( 'wpcomsh_bilmur_site_v', '__return_true' ),
				'Bilmur should opt into the wpcomsh site-v attribute.'
			);

			// Non-wpcomsh path: the meta tag carries data-site-v = md5( host ).
			$expected = md5( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

			ob_start();
			do_action( 'wp_footer' );
			$footer = (string) ob_get_clean();

			Assert::assertStringContainsString( 'id="bilmur"', $footer, 'The Bilmur meta tag should be rendered.' );
			Assert::assertStringContainsString( 'data-site-v="' . $expected . '"', $footer );
		} finally {
			foreach ( $snapshot as $hook => $value ) {
				if ( null === $value ) {
					unset( $GLOBALS['wp_filter'][ $hook ] );
				} else {
					$GLOBALS['wp_filter'][ $hook ] = $value;
				}
			}
		}
	}
}
