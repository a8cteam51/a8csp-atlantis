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
	private const EPOCH_OPTION = 'a8csp_atlantis_last_force_check_epoch';

	/**
	 * Clears the epoch marker before each test so cases don't leak state.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( IntegrationTester $i ): void {
		delete_site_option( self::EPOCH_OPTION );
	}

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
	 * With a directive served, the first poll seeds the epoch (and does not act on a pre-existing pulse).
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function seeds_on_first_poll( IntegrationTester $i ): void {
		$stub = $this->stub_directive( 123 );
		( new ForceUpdateCheck() )->run_directive_check();
		$this->remove_stub( $stub );

		Assert::assertSame( 123, (int) get_site_option( self::EPOCH_OPTION ) );
	}

	/**
	 * A directive whose epoch is not newer than the stored one is ignored.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function ignores_not_newer_epoch( IntegrationTester $i ): void {
		update_site_option( self::EPOCH_OPTION, 200 );

		$stub = $this->stub_directive( 123 );
		( new ForceUpdateCheck() )->run_directive_check();
		$this->remove_stub( $stub );

		Assert::assertSame( 200, (int) get_site_option( self::EPOCH_OPTION ) );
	}

	/**
	 * A newer epoch advances the high-water mark (and triggers the refresh).
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function advances_on_newer_epoch( IntegrationTester $i ): void {
		update_site_option( self::EPOCH_OPTION, 100 );

		$stub = $this->stub_directive( 123 );
		( new ForceUpdateCheck() )->run_directive_check();
		$this->remove_stub( $stub );

		Assert::assertSame( 123, (int) get_site_option( self::EPOCH_OPTION ) );
	}

	/**
	 * The kill-switch filter short-circuits before any polling — even with a directive served.
	 *
	 * The served epoch (123) must NOT be recorded, which proves the guard runs before the fetch: if the
	 * `apply_filters()` guard were removed, the stub below would seed the option and this test would fail.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function kill_switch_filter_short_circuits( IntegrationTester $i ): void {
		$stub = $this->stub_directive( 123 );
		add_filter( 'a8csp_atlantis_force_update_check_enabled', '__return_false' );

		( new ForceUpdateCheck() )->run_directive_check();

		remove_filter( 'a8csp_atlantis_force_update_check_enabled', '__return_false' );
		$this->remove_stub( $stub );

		Assert::assertFalse( get_site_option( self::EPOCH_OPTION, false ) );
	}

	/**
	 * Registers a `pre_http_request` stub that serves `{ "epoch": N }` for the directive URL and a
	 * benign empty 200 for everything else (so a triggered `wp_update_plugins()` stays offline).
	 *
	 * @param int $epoch The epoch to serve.
	 *
	 * @return callable The registered filter callback, for removal.
	 */
	private function stub_directive( int $epoch ): callable {
		$stub = static function ( $preempt, $args, $url ) use ( $epoch ) {
			$body = str_contains( (string) $url, 'sites/batch/plugin-refresh' )
				? (string) wp_json_encode( array( 'epoch' => $epoch ) )
				: '';

			return array(
				'headers'  => array(),
				'body'     => $body,
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $stub, 10, 3 );

		return $stub;
	}

	/**
	 * Removes a `pre_http_request` stub.
	 *
	 * @param callable $stub The filter callback to remove.
	 *
	 * @return void
	 */
	private function remove_stub( callable $stub ): void {
		remove_filter( 'pre_http_request', $stub, 10 );
	}
}
