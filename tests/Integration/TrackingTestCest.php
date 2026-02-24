<?php
/**
 * Integration tests for Tracking module integrations.
 */

declare(strict_types=1);

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
}
