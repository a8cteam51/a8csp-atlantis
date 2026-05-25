<?php
/**
 * Integration tests for AbstractModule::set_enabled().
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\Autoupdates\AutoUpdatePluginsFilter;
use A8C\SpecialProjects\Atlantis\Modules\Messages\Messages;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Tests for the shared module enable/disable writer that the WP-CLI commands
 * (and any future REST endpoint) rely on.
 */
class ModuleEnableToggleCest {
	/**
	 * Toggling a non-mandatory module persists the new enabled flag.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function set_enabled_persists_the_enabled_flag( IntegrationTester $i ): void {
		$module      = new AutoUpdatePluginsFilter();
		$option_name = a8csp_atlantis_generate_module_settings_key( $module->get_name() );

		$this->restore_option(
			$option_name,
			function () use ( $module, $option_name ): void {
				update_option( $option_name, array( 'enabled' => '1' ) );

				Assert::assertTrue( $module->set_enabled( false ) );
				Assert::assertSame( '0', get_option( $option_name )['enabled'] );
				Assert::assertFalse( $module->is_active() );

				Assert::assertTrue( $module->set_enabled( true ) );
				Assert::assertSame( '1', get_option( $option_name )['enabled'] );
				Assert::assertTrue( $module->is_active() );
			}
		);
	}

	/**
	 * Sub-settings stored alongside `enabled` must survive a toggle.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function set_enabled_preserves_sibling_subsettings( IntegrationTester $i ): void {
		$module      = new AutoUpdatePluginsFilter();
		$option_name = a8csp_atlantis_generate_module_settings_key( $module->get_name() );

		$this->restore_option(
			$option_name,
			function () use ( $module, $option_name ): void {
				update_option(
					$option_name,
					array(
						'enabled'        => '1',
						'rollout_window' => '48',
						'time_window'    => 'weekend',
					)
				);

				Assert::assertTrue( $module->set_enabled( false ) );

				$stored = get_option( $option_name );
				Assert::assertSame( '0', $stored['enabled'] );
				Assert::assertSame( '48', $stored['rollout_window'] );
				Assert::assertSame( 'weekend', $stored['time_window'] );
			}
		);
	}

	/**
	 * Mandatory modules must refuse to be disabled and the stored flag must
	 * stay unchanged.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function set_enabled_refuses_to_disable_mandatory_modules( IntegrationTester $i ): void {
		$module      = new Messages();
		$option_name = a8csp_atlantis_generate_module_settings_key( $module->get_name() );

		$this->restore_option(
			$option_name,
			function () use ( $module, $option_name ): void {
				update_option( $option_name, array( 'enabled' => '1' ) );

				$result = $module->set_enabled( false );

				Assert::assertInstanceOf( WP_Error::class, $result );
				Assert::assertSame( 'a8csp_atlantis_mandatory_module', $result->get_error_code() );
				Assert::assertSame( '1', get_option( $option_name )['enabled'] );
				Assert::assertTrue( $module->is_active() );
			}
		);
	}

	/**
	 * Mandatory modules may still be "set enabled" — it's a no-op success.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function set_enabled_true_on_mandatory_module_is_a_success( IntegrationTester $i ): void {
		$module      = new Messages();
		$option_name = a8csp_atlantis_generate_module_settings_key( $module->get_name() );

		$this->restore_option(
			$option_name,
			function () use ( $module ): void {
				Assert::assertTrue( $module->set_enabled( true ) );
				Assert::assertTrue( $module->is_active() );
			}
		);
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
