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
	 * Marks the site as managed: forcing tracking on is something only a managed site does, and
	 * it is what most of these tests are about.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		update_option( 'a8csp_atlantis_managed_site', '1' );
	}

	/**
	 * Clears the managed flag.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		delete_option( 'a8csp_atlantis_managed_site' );
	}

	/**
	 * A site that is not managed keeps its own tracking choices: neither integration runs, so
	 * neither option is overridden, and nothing tags its RUM data as a Special Projects site's.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function unmanaged_site_keeps_its_own_tracking_choices( IntegrationTester $i ): void {
		update_option( 'a8csp_atlantis_managed_site', '0' );

		$hooks    = array( 'option_woocommerce_allow_tracking', 'option_sensei-settings', 'wpcomsh_bilmur_site_v', 'wpcomsh_rum_kv', 'wp_footer', 'wp_enqueue_scripts', 'wp_script_attributes' );
		$snapshot = array();
		foreach ( $hooks as $hook ) {
			$snapshot[ $hook ] = isset( $GLOBALS['wp_filter'][ $hook ] ) ? clone $GLOBALS['wp_filter'][ $hook ] : null;
			unset( $GLOBALS['wp_filter'][ $hook ] );
		}

		try {
			$woocommerce = new WooCommerce();
			$sensei      = new Sensei();

			Assert::assertFalse( $woocommerce->is_active() );
			Assert::assertFalse( $sensei->is_active() );

			$woocommerce->maybe_initialize();
			$sensei->maybe_initialize();

			Assert::assertSame( 'no', apply_filters( 'option_woocommerce_allow_tracking', 'no' ) );
			Assert::assertSame(
				array( 'sensei_usage_tracking_enabled' => false ),
				apply_filters( 'option_sensei-settings', array( 'sensei_usage_tracking_enabled' => false ) )
			);

			// Bilmur's own initialization, whichever way it is reached, must not tag the site.
			$initialize = new \ReflectionMethod( Bilmur::class, 'initialize' );
			$initialize->setAccessible( true );
			$initialize->invoke( new Bilmur() );

			Assert::assertFalse( has_filter( 'wpcomsh_rum_kv' ), 'An unmanaged site must not add the Special Projects property.' );
			Assert::assertFalse( has_filter( 'wpcomsh_bilmur_site_v' ), 'An unmanaged site must not opt into the site hash.' );
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
	 * WooCommerce's tracking checkbox is replaced by one that shows tracking is on and why, and
	 * that WooCommerce never saves. Everything else in the section is left alone.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function woocommerce_tracking_setting_is_shown_as_forced( IntegrationTester $i ): void {
		$section = array(
			array(
				'title' => 'Usage tracking',
				'type'  => 'title',
				'id'    => 'tracking_options',
			),
			array(
				'title'         => 'Enable tracking',
				'desc'          => 'Allow usage of WooCommerce to be tracked',
				'desc_tip'      => 'To opt out, leave this box unticked.',
				'id'            => 'woocommerce_allow_tracking',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
				'default'       => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'tracking_options',
			),
		);

		$filtered = ( new WooCommerce() )->filter_tracking_setting( $section );

		Assert::assertSame( $section[0], $filtered[0] );
		Assert::assertSame( $section[2], $filtered[2] );
		Assert::assertSame(
			array(
				'type'  => WooCommerce::FORCED_TRACKING_FIELD_TYPE,
				'title' => 'Enable tracking',
				'desc'  => 'Allow usage of WooCommerce to be tracked',
			),
			$filtered[1],
			'The field must have no id, so that WooCommerce never saves it over the store\'s choice.'
		);
	}

	/**
	 * The forced field says why tracking is on, cannot be changed, and posts the store's own
	 * choice back unchanged.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function woocommerce_forced_tracking_field_keeps_the_stored_choice( IntegrationTester $i ): void {
		$woocommerce = new WooCommerce();
		update_option( 'woocommerce_allow_tracking', 'no' );
		add_filter( 'option_woocommerce_allow_tracking', array( $woocommerce, 'filter_allow_tracking' ), PHP_INT_MAX );

		try {
			Assert::assertSame( 'yes', get_option( 'woocommerce_allow_tracking' ), 'Test precondition: tracking is forced on.' );
			Assert::assertSame( 'no', $woocommerce->get_stored_choice() );

			ob_start();
			$woocommerce->output_forced_tracking_field(
				array(
					'title' => 'Enable tracking',
					'desc'  => 'Allow usage of WooCommerce to be tracked',
				)
			);
			$html = (string) ob_get_clean();

			Assert::assertMatchesRegularExpression( '/<input type="checkbox"[^>]*checked="checked"[^>]*disabled="disabled"/', $html );
			Assert::assertStringContainsString( '<input type="hidden" name="woocommerce_allow_tracking" value="no" />', $html );
			Assert::assertStringContainsString( 'turned on for this store by the Atlantis plugin', $html );
			Assert::assertSame( 'yes', get_option( 'woocommerce_allow_tracking' ), 'Reading the stored choice must not lift the override.' );
		} finally {
			remove_filter( 'option_woocommerce_allow_tracking', array( $woocommerce, 'filter_allow_tracking' ), PHP_INT_MAX );
			delete_option( 'woocommerce_allow_tracking' );
		}
	}

	/**
	 * While WooCommerce saves its settings it compares the stored choice with the posted one. The
	 * override must step aside there, or an unchanged choice would read as the store opting out.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function woocommerce_tracking_override_steps_aside_while_settings_save( IntegrationTester $i ): void {
		$woocommerce = new WooCommerce();
		$seen        = null;
		$read        = static function () use ( &$seen ) {
			$seen = get_option( 'woocommerce_allow_tracking', 'no' );
		};

		update_option( 'woocommerce_allow_tracking', 'no' );
		add_filter( 'option_woocommerce_allow_tracking', array( $woocommerce, 'filter_allow_tracking' ), PHP_INT_MAX );
		add_action( 'woocommerce_settings_save_advanced', $read );

		try {
			do_action( 'woocommerce_settings_save_advanced' );

			Assert::assertSame( 'no', $seen );
			Assert::assertSame( 'yes', get_option( 'woocommerce_allow_tracking' ), 'Outside the save, tracking is still forced on.' );
		} finally {
			remove_action( 'woocommerce_settings_save_advanced', $read );
			remove_filter( 'option_woocommerce_allow_tracking', array( $woocommerce, 'filter_allow_tracking' ), PHP_INT_MAX );
			delete_option( 'woocommerce_allow_tracking' );
		}
	}

	/**
	 * A store that never saved WooCommerce's tracking setting has no row for it. Tracking must be
	 * forced on there too, or the forced field would claim something that is not so.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function woocommerce_tracking_is_forced_when_the_option_was_never_saved( IntegrationTester $i ): void {
		$hooks    = array( 'option_woocommerce_allow_tracking', 'default_option_woocommerce_allow_tracking', 'woocommerce_com_integration_settings', 'woocommerce_admin_field_' . WooCommerce::FORCED_TRACKING_FIELD_TYPE, 'woocommerce_settings_save_advanced' );
		$snapshot = array();
		foreach ( $hooks as $hook ) {
			$snapshot[ $hook ] = $GLOBALS['wp_filter'][ $hook ] ?? null;
			unset( $GLOBALS['wp_filter'][ $hook ] );
		}

		$seen = null;
		$read = static function () use ( &$seen ) {
			$seen = get_option( 'woocommerce_allow_tracking', 'no' );
		};

		try {
			delete_option( 'woocommerce_allow_tracking' );

			$woocommerce = new WooCommerce();
			$woocommerce->maybe_initialize();

			Assert::assertSame( 'yes', get_option( 'woocommerce_allow_tracking' ) );
			Assert::assertSame( 'yes', get_option( 'woocommerce_allow_tracking', 'no' ) );
			Assert::assertSame( 'no', $woocommerce->get_stored_choice(), 'The store has not chosen anything, so its choice reads as no.' );

			add_action( 'woocommerce_settings_save_advanced', $read );
			do_action( 'woocommerce_settings_save_advanced' );
			Assert::assertSame( 'no', $seen, 'The override must step aside while WooCommerce saves, as it does for a saved choice.' );
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
	 * meta tag, and on a managed site it opts into wpcomsh's own `site-v` attribute
	 * for the Atomic path via the `wpcomsh_bilmur_site_v` filter.
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

			// Atomic path: on a managed site the opt-in filter is registered whether or not wpcomsh is detected.
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
