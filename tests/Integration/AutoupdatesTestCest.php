<?php
/**
 * Integration tests for Autoupdates module behavior.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\Autoupdates\AutoUpdatePluginsFilter;
use A8C\SpecialProjects\Atlantis\Modules\Autoupdates\PluginFilterAdminUI;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Autoupdates module integration tests.
 */
class AutoupdatesTestCest {
	/**
	 * Basic module metadata should be stable.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function module_metadata_is_stable( IntegrationTester $i ): void {
		$module = new AutoUpdatePluginsFilter();

		Assert::assertSame( 'Autoupdates', $module->get_name() );
		Assert::assertStringContainsString( 'auto-update', strtolower( $module->get_description() ) );
	}

	/**
	 * Ensure per-plugin disabled entries bypass Atlantis autoupdate overrides.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function respects_per_plugin_filter_toggle( IntegrationTester $i ): void {
		$this->set_current_user_as_admin();

		$disabled_plugin = 'akismet/akismet.php';
		update_site_option( 'plugin_autoupdate_filter_disabled_plugins', array( $disabled_plugin ) );

		$module = new AutoUpdatePluginsFilter();
		$item   = (object) array(
			'plugin'      => $disabled_plugin,
			'slug'        => 'akismet',
			'new_version' => '1.0.0',
		);

		Assert::assertTrue( $module->filter_auto_update_specific_times( true, $item ) );
		Assert::assertTrue( $module->filter_enforce_delay( true, $item ) );

		$admin_ui = new PluginFilterAdminUI();
		$html     = $admin_ui->filter_custom_setting_html( 'Current setting', $disabled_plugin );
		Assert::assertStringContainsString( 'Enable PAF updates', $html );
	}

	/**
	 * Ensure autoupdate decision helpers work for core paths.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function core_decision_filters_behave_as_expected( IntegrationTester $i ): void {
		$module = new AutoUpdatePluginsFilter();
		$this->set_module_settings(
			$module,
			(object) array(
				'disable_all'   => true,
				'canary_sites'  => array(),
			)
		);

		Assert::assertFalse( $module->filter_maybe_disable_all_autoupdates( true ) );
		Assert::assertFalse( $module->filter_maybe_disable_all_autoupdates( null ) );

		$email = array( 'to' => 'admin@example.com' );
		Assert::assertSame( 'concierge@wordpress.com', $module->filter_custom_update_emails( $email, '', array(), array() )['to'] );
		Assert::assertSame( 'concierge@wordpress.com', $module->filter_custom_debug_email( $email, 0, array() )['to'] );
	}

	/**
	 * Ensure schedule filters can be controlled through hooks.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function schedule_filters_respect_hour_and_day_filters( IntegrationTester $i ): void {
		$module = new AutoUpdatePluginsFilter();
		$item   = (object) array(
			'plugin'      => 'akismet/akismet.php',
			'slug'        => 'akismet',
			'new_version' => '1.0.0',
		);

		$holidays_filter = function () {
			return array();
		};
		$hours_filter = function () {
			return array(
				'start'      => '00',
				'end'        => '23',
				'friday_end' => '23',
			);
		};
		$days_filter = function () {
			return array();
		};

		add_filter( 'plugin_autoupdate_filter_holidays', $holidays_filter );
		add_filter( 'plugin_autoupdate_filter_hours', $hours_filter );
		add_filter( 'plugin_autoupdate_filter_days_off', $days_filter );

		Assert::assertTrue( $module->filter_auto_update_specific_times( true, $item ) );

		remove_filter( 'plugin_autoupdate_filter_holidays', $holidays_filter );
		remove_filter( 'plugin_autoupdate_filter_hours', $hours_filter );
		remove_filter( 'plugin_autoupdate_filter_days_off', $days_filter );
	}

	/**
	 * Delay cleanup should remove plugin entries once updates complete.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function delay_cleanup_clears_saved_delay_entries( IntegrationTester $i ): void {
		update_option(
			'plugin_update_delays',
			array(
				'akismet/akismet.php' => array(
					'1.0.0' => time() + HOUR_IN_SECONDS,
				),
			)
		);

		$module = new AutoUpdatePluginsFilter();
		$module->cleanup_plugin_delay_after_update_complete(
			(object) array(),
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => array( 'akismet/akismet.php' ),
			)
		);

		$delays = get_option( 'plugin_update_delays', array() );
		Assert::assertIsArray( $delays );
		Assert::assertArrayNotHasKey( 'akismet/akismet.php', $delays );
	}

	/**
	 * Set current user as admin to satisfy capability checks.
	 *
	 * @return void
	 */
	private function set_current_user_as_admin(): void {
		$admin_user = get_user_by( 'login', 'admin' );

		if ( $admin_user instanceof WP_User ) {
			wp_set_current_user( $admin_user->ID );
			return;
		}

		$user_id = wp_create_user( 'admin', 'password', 'admin@example.com' );
		if ( is_wp_error( $user_id ) ) {
			return;
		}

		$user = new WP_User( $user_id );
		$user->set_role( 'administrator' );
		wp_set_current_user( $user_id );
	}

	/**
	 * Inject settings into module without running remote initialization.
	 *
	 * @param AutoUpdatePluginsFilter $module   Module instance.
	 * @param \stdClass               $settings Settings object.
	 *
	 * @return void
	 */
	private function set_module_settings( AutoUpdatePluginsFilter $module, \stdClass $settings ): void {
		$reflection = new ReflectionClass( $module );
		$property   = $reflection->getProperty( 'settings' );
		$property->setAccessible( true );
		$property->setValue( $module, $settings );
	}
}
