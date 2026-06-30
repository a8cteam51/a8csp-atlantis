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
		$previous_disabled_plugins = get_site_option( 'plugin_autoupdate_filter_disabled_plugins', array() );
		update_site_option( 'plugin_autoupdate_filter_disabled_plugins', array( $disabled_plugin ) );

		try {
			$module = new AutoUpdatePluginsFilter();
			$item   = (object) array(
				'plugin'      => $disabled_plugin,
				'slug'        => 'akismet',
				'new_version' => '1.0.0',
			);

			Assert::assertTrue( $module->filter_auto_update_specific_times( true, $item ) );
			Assert::assertTrue( $module->filter_enforce_delay( true, $item ) );

			$admin_ui = new PluginFilterAdminUI();
			$html     = $admin_ui->filter_custom_setting_html( 'Current setting', $disabled_plugin, array() );
			Assert::assertStringContainsString( 'Enable PAF updates', $html );
		} finally {
			update_site_option( 'plugin_autoupdate_filter_disabled_plugins', $previous_disabled_plugins );
		}
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
	 * Plugin-specific centralized settings should disable matching plugin autoupdates only.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function centralized_plugin_blocks_disable_only_selected_plugins( IntegrationTester $i ): void {
		$module = new AutoUpdatePluginsFilter();
		$this->set_module_settings(
			$module,
			(object) array(
				'canary_sites'      => array(),
				'disabled_plugins'  => array(
					'akismet/akismet.php',
				),
			)
		);

		$blocked_plugin_item = (object) array(
			'plugin' => 'akismet/akismet.php',
			'slug'   => 'akismet',
		);
		$allowed_plugin_item = (object) array(
			'plugin' => 'hello-dolly/hello.php',
			'slug'   => 'hello-dolly',
		);

		Assert::assertFalse( $module->filter_maybe_disable_all_autoupdates( true, $blocked_plugin_item ) );
		Assert::assertTrue( $module->filter_maybe_disable_all_autoupdates( true, $allowed_plugin_item ) );
	}

	/**
	 * Centrally blocked plugins should not display PAF toggle actions.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function centralized_plugin_blocks_hide_plugin_column_toggle_action( IntegrationTester $i ): void {
		$this->set_current_user_as_admin();

		$settings = (object) array(
			'disabled_plugins' => array(
				'akismet/akismet.php',
			),
		);

		$admin_ui = new PluginFilterAdminUI( $settings );
		$html     = $admin_ui->filter_custom_setting_html( 'Current setting', 'akismet/akismet.php', array() );

		Assert::assertStringContainsString( 'Autoupdates have been explicitly deactivated for this plugin via global OpsOasis settings.', $html );
		Assert::assertStringNotContainsString( 'Disable PAF updates', $html );
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
	 * A failed OpsOasis fetch negative-caches the fail-safe default instead of refetching on every request.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function failed_settings_fetch_negative_caches_safe_default( IntegrationTester $i ): void {
		delete_transient( 'wpcpmsp_auto_update_settings' );

		$force_failure = static function () {
			return new WP_Error( 'http_request_failed', 'Simulated OpsOasis outage' );
		};
		add_filter( 'pre_http_request', $force_failure, 10, 3 );

		try {
			$module = new AutoUpdatePluginsFilter();
			$method = new ReflectionMethod( $module, 'get_auto_update_settings' );
			$method->setAccessible( true );

			$threw = false;
			try {
				$method->invoke( $module );
			} catch ( \Exception $exception ) {
				$threw = true;
			}

			Assert::assertTrue( $threw, 'A failed remote fetch should surface an exception to the caller.' );

			$cached = get_transient( 'wpcpmsp_auto_update_settings' );
			Assert::assertIsObject( $cached );
			Assert::assertTrue( ! empty( $cached->disable_all ) );
		} finally {
			remove_filter( 'pre_http_request', $force_failure, 10 );
			delete_transient( 'wpcpmsp_auto_update_settings' );
		}
	}

	/**
	 * A warm settings transient is returned without making a remote request.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function warm_settings_transient_skips_remote_request( IntegrationTester $i ): void {
		set_transient( 'wpcpmsp_auto_update_settings', (object) array( 'disable_all' => true ), 5 * MINUTE_IN_SECONDS );

		$http_calls = 0;
		$count_http = static function ( $pre ) use ( &$http_calls ) {
			++$http_calls;
			return new WP_Error( 'unexpected_http', 'No remote request should be made when the transient is warm.' );
		};
		add_filter( 'pre_http_request', $count_http, 10, 3 );

		try {
			$module = new AutoUpdatePluginsFilter();
			$method = new ReflectionMethod( $module, 'get_auto_update_settings' );
			$method->setAccessible( true );

			$settings = $method->invoke( $module );

			Assert::assertIsObject( $settings );
			Assert::assertTrue( ! empty( $settings->disable_all ) );
			Assert::assertSame( 0, $http_calls, 'A warm transient must not trigger a remote request.' );
		} finally {
			remove_filter( 'pre_http_request', $count_http, 10 );
			delete_transient( 'wpcpmsp_auto_update_settings' );
		}
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
