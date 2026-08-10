<?php
/**
 * Integration tests for the Bot Protection module.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\BotProtection\BotProtection;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Bot Protection module integration tests.
 */
class BotProtectionTestCest {
	/**
	 * The WP Cloud filter the module drives.
	 *
	 * @var string
	 */
	private const FILTER = 'wpcloud_bot_protection_enable';

	/**
	 * Module metadata remains stable and the module is mandatory.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function module_metadata_is_stable( IntegrationTester $i ): void {
		$module = new BotProtection();

		Assert::assertSame( 'Bot Protection', $module->get_name() );
		Assert::assertTrue( $module->is_mandatory() );
	}

	/**
	 * get_configured_state() falls back to inherit for unset, unknown, or
	 * non-string stored values.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function get_configured_state_falls_back_to_inherit( IntegrationTester $i ): void {
		$option_name = a8csp_atlantis_generate_module_settings_key( 'Bot Protection' );

		$this->restore_option(
			$option_name,
			function () use ( $option_name ): void {
				delete_option( $option_name );
				Assert::assertSame( BotProtection::STATE_INHERIT, BotProtection::get_configured_state() );

				update_option( $option_name, array( 'state' => 'bogus' ) );
				Assert::assertSame( BotProtection::STATE_INHERIT, BotProtection::get_configured_state() );

				// A malformed (non-string) value must not trigger a string cast.
				update_option( $option_name, array( 'state' => array( 'not', 'a', 'string' ) ) );
				Assert::assertSame( BotProtection::STATE_INHERIT, BotProtection::get_configured_state() );
			}
		);
	}

	/**
	 * The `on` state forces the filter to return true.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function on_state_forces_filter_true( IntegrationTester $i ): void {
		$this->with_state(
			BotProtection::STATE_ON,
			static function (): void {
				Assert::assertTrue( apply_filters( self::FILTER, false ) );
			}
		);
	}

	/**
	 * The `off` state forces the filter to return false.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function off_state_forces_filter_false( IntegrationTester $i ): void {
		$this->with_state(
			BotProtection::STATE_OFF,
			static function (): void {
				Assert::assertFalse( apply_filters( self::FILTER, true ) );
			}
		);
	}

	/**
	 * The `inherit` state registers no filter and leaves the value untouched.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function inherit_state_registers_no_filter( IntegrationTester $i ): void {
		$this->with_state(
			BotProtection::STATE_INHERIT,
			static function (): void {
				Assert::assertSame( 'sentinel', apply_filters( self::FILTER, 'sentinel' ) );
			}
		);
	}

	/**
	 * On non-production environments, protection is forced off unless the state
	 * is explicitly `on`.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function non_production_forces_off_unless_on( IntegrationTester $i ): void {
		// `inherit` on non-production forces off instead of deferring.
		$this->with_state(
			BotProtection::STATE_INHERIT,
			static function (): void {
				Assert::assertFalse( apply_filters( self::FILTER, true ) );
			},
			false
		);

		// `off` on non-production stays off.
		$this->with_state(
			BotProtection::STATE_OFF,
			static function (): void {
				Assert::assertFalse( apply_filters( self::FILTER, true ) );
			},
			false
		);

		// Explicit `on` still wins on non-production (deliberate opt-in).
		$this->with_state(
			BotProtection::STATE_ON,
			static function (): void {
				Assert::assertTrue( apply_filters( self::FILTER, false ) );
			},
			false
		);
	}

	/**
	 * set_state() persists a valid state and rejects an invalid one without
	 * clobbering the stored value.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function set_state_validates_and_persists( IntegrationTester $i ): void {
		$option_name = a8csp_atlantis_generate_module_settings_key( 'Bot Protection' );

		$this->restore_option(
			$option_name,
			static function (): void {
				Assert::assertTrue( BotProtection::set_state( BotProtection::STATE_ON ) );
				Assert::assertSame( BotProtection::STATE_ON, BotProtection::get_configured_state() );

				$result = BotProtection::set_state( 'nonsense' );
				Assert::assertInstanceOf( WP_Error::class, $result );
				Assert::assertSame( 'a8csp_atlantis_invalid_bot_protection_state', $result->get_error_code() );

				// The rejected write left the prior value intact.
				Assert::assertSame( BotProtection::STATE_ON, BotProtection::get_configured_state() );
			}
		);
	}

	/**
	 * The mandatory module refuses to be disabled.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function mandatory_module_refuses_disable( IntegrationTester $i ): void {
		$option_name = a8csp_atlantis_generate_module_settings_key( 'Bot Protection' );

		$this->restore_option(
			$option_name,
			static function (): void {
				$result = ( new BotProtection() )->set_enabled( false );

				Assert::assertInstanceOf( WP_Error::class, $result );
				Assert::assertSame( 'a8csp_atlantis_mandatory_module', $result->get_error_code() );
			}
		);
	}

	/**
	 * Initializes a fresh module at the given state, runs the assertions, then
	 * removes any filter it registered and restores the option.
	 *
	 * @param string   $state      One of the STATE_* constants.
	 * @param callable $assertions Assertions to run while the module is active.
	 *
	 * @return void
	 */
	private function with_state( string $state, callable $assertions, bool $is_production = true ): void {
		$option_name = a8csp_atlantis_generate_module_settings_key( 'Bot Protection' );
		$prod_filter = static fn(): bool => $is_production;
		add_filter( 'a8csp_atlantis_bot_protection_is_production', $prod_filter );

		try {
			$this->restore_option(
				$option_name,
				function () use ( $state, $assertions, $option_name ): void {
					update_option(
						$option_name,
						array(
							'enabled' => '1',
							'state'   => $state,
						)
					);

					( new BotProtection() )->maybe_initialize();

					try {
						$assertions();
					} finally {
						remove_filter( self::FILTER, '__return_true', PHP_INT_MAX );
						remove_filter( self::FILTER, '__return_false', PHP_INT_MAX );
					}
				}
			);
		} finally {
			remove_filter( 'a8csp_atlantis_bot_protection_is_production', $prod_filter );
		}
	}

	/**
	 * Saves the option, runs the callback, and restores the original value.
	 *
	 * @param string   $option_name Option name to back up.
	 * @param callable $callback    Callback to run while the option is mutated.
	 *
	 * @return void
	 */
	private function restore_option( string $option_name, callable $callback ): void {
		$original = get_option( $option_name, null );
		try {
			$callback();
		} finally {
			if ( null === $original ) {
				delete_option( $option_name );
			} else {
				update_option( $option_name, $original );
			}
		}
	}
}
