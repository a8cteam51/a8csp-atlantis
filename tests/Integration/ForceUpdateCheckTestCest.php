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
	 * Clears the state the tests touch before each case so they don't leak into one another.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( IntegrationTester $i ): void {
		delete_site_option( self::EPOCH_OPTION );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Restores the state the tests touch after each case.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void {
		delete_site_option( self::EPOCH_OPTION );
		delete_site_transient( 'update_plugins' );
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
	 * The first poll seeds the epoch and does NOT refresh (the sentinel transient survives).
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function seeds_on_first_poll_without_refreshing( IntegrationTester $i ): void {
		set_site_transient( 'update_plugins', (object) array( 'sentinel' => true ) );

		$stub = $this->stub_directive( 123 );
		try {
			( new ForceUpdateCheck() )->run_directive_check();

			Assert::assertSame( 123, (int) get_site_option( self::EPOCH_OPTION ) );
			Assert::assertTrue( $this->has_update_plugins_sentinel(), 'Seed-and-return must not clear update_plugins.' );
		} finally {
			$this->remove_stub( $stub );
		}
	}

	/**
	 * An epoch that is not newer than the stored one is ignored (no seed change, no refresh).
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function ignores_not_newer_epoch( IntegrationTester $i ): void {
		update_site_option( self::EPOCH_OPTION, 200 );
		set_site_transient( 'update_plugins', (object) array( 'sentinel' => true ) );

		$stub = $this->stub_directive( 123 );
		try {
			( new ForceUpdateCheck() )->run_directive_check();

			Assert::assertSame( 200, (int) get_site_option( self::EPOCH_OPTION ) );
			Assert::assertTrue( $this->has_update_plugins_sentinel(), 'A not-newer epoch must not clear update_plugins.' );
		} finally {
			$this->remove_stub( $stub );
		}
	}

	/**
	 * A newer epoch advances the marker AND refreshes (the sentinel transient is cleared).
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function advances_and_refreshes_on_newer_epoch( IntegrationTester $i ): void {
		update_site_option( self::EPOCH_OPTION, 100 );
		set_site_transient( 'update_plugins', (object) array( 'sentinel' => true ) );

		$stub = $this->stub_directive( 123 );
		try {
			( new ForceUpdateCheck() )->run_directive_check();

			Assert::assertSame( 123, (int) get_site_option( self::EPOCH_OPTION ) );
			Assert::assertFalse( $this->has_update_plugins_sentinel(), 'A newer epoch must clear update_plugins.' );
		} finally {
			$this->remove_stub( $stub );
		}
	}

	/**
	 * The kill-switch filter short-circuits before any polling — even with a directive served.
	 *
	 * The served epoch (123) must NOT be recorded, which proves the guard runs before the fetch: if the
	 * `apply_filters()` guard were removed, the stub would seed the option and this test would fail.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function kill_switch_filter_short_circuits( IntegrationTester $i ): void {
		$stub = $this->stub_directive( 123 );
		add_filter( 'a8csp_atlantis_force_update_check_enabled', '__return_false' );

		try {
			( new ForceUpdateCheck() )->run_directive_check();

			Assert::assertFalse( get_site_option( self::EPOCH_OPTION, false ) );
		} finally {
			remove_filter( 'a8csp_atlantis_force_update_check_enabled', '__return_false' );
			$this->remove_stub( $stub );
		}
	}

	/**
	 * Whether the `update_plugins` site transient still carries the test sentinel.
	 *
	 * @return bool
	 */
	private function has_update_plugins_sentinel(): bool {
		$transient = get_site_transient( 'update_plugins' );

		return is_object( $transient ) && isset( $transient->sentinel );
	}

	/**
	 * Registers a `pre_http_request` stub that serves `{ "epoch": N }` for the OpsOasis host and a
	 * benign empty 200 for everything else (so a triggered `wp_update_plugins()` stays offline).
	 *
	 * Matching on the host — not the route path — keeps this working if the route is renamed.
	 *
	 * @param int $epoch The epoch to serve.
	 *
	 * @return callable The registered filter callback, for removal.
	 */
	private function stub_directive( int $epoch ): callable {
		$stub = static function ( $preempt, $args, $url ) use ( $epoch ) {
			$body = str_contains( (string) $url, 'opsoasis.wpspecialprojects.com' )
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
