<?php
/**
 * Integration tests for the Force Update Check module.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\ForceUpdateCheck\ForceUpdateCheck;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Force Update Check module integration tests.
 */
class ForceUpdateCheckTestCest {
	/**
	 * The module is mandatory and identifies itself.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function module_is_mandatory( IntegrationTester $i ): void {
		$module = new ForceUpdateCheck();

		Assert::assertTrue( $module->is_mandatory() );
		Assert::assertSame( 'Force Update Check', $module->get_name() );
	}

	/**
	 * The custom five-minute cron schedule is registered with the correct interval.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function registers_five_minute_schedule( IntegrationTester $i ): void {
		$schedules = ( new ForceUpdateCheck() )->register_cron_schedule( array() );

		Assert::assertArrayHasKey( 'a8csp_atlantis_every_five_minutes', $schedules );
		Assert::assertSame( 5 * MINUTE_IN_SECONDS, $schedules['a8csp_atlantis_every_five_minutes']['interval'] );
	}

	/**
	 * The kill-switch filter short-circuits the directive check before any polling or state change.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function kill_switch_filter_short_circuits( IntegrationTester $i ): void {
		$option = 'a8csp_atlantis_last_force_check_epoch';
		delete_site_option( $option );

		add_filter( 'a8csp_atlantis_force_update_check_enabled', '__return_false' );
		( new ForceUpdateCheck() )->run_directive_check();
		remove_filter( 'a8csp_atlantis_force_update_check_enabled', '__return_false' );

		// Nothing should have been polled or recorded when the lever is filtered off.
		Assert::assertFalse( get_site_option( $option, false ) );
	}
}
