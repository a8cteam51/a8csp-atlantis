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
	 * Auto-update context gating returns false on a front-end request and true during cron.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function autoupdate_context_gating_distinguishes_request_types( IntegrationTester $i ): void {
		$module = new AutoUpdatePluginsFilter();
		$method = new ReflectionMethod( $module, 'is_autoupdate_context' );
		$method->setAccessible( true );

		// Front-end request: none of the three contexts apply.
		Assert::assertFalse( is_admin(), 'Test precondition: not an admin request.' );
		Assert::assertFalse( wp_doing_cron(), 'Test precondition: not a cron request.' );
		Assert::assertFalse( defined( 'WP_CLI' ) && WP_CLI, 'Test precondition: not a WP-CLI request.' );
		Assert::assertFalse( $method->invoke( $module ), 'A front-end request is not an autoupdate context.' );

		// Cron request: wp_doing_cron() is filterable, so simulate it without loading admin includes.
		add_filter( 'wp_doing_cron', '__return_true' );
		try {
			Assert::assertTrue( $method->invoke( $module ), 'A cron request is an autoupdate context.' );
		} finally {
			remove_filter( 'wp_doing_cron', '__return_true' );
		}
	}

	/**
	 * On a front-end request, initialize() short-circuits before fetching OpsOasis settings.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function front_end_initialize_makes_no_remote_request( IntegrationTester $i ): void {
		Assert::assertFalse( is_admin(), 'Test precondition: not an admin request.' );
		delete_transient( 'wpcpmsp_auto_update_settings' );

		$http_calls = 0;
		$count_http = static function ( $pre ) use ( &$http_calls ) {
			++$http_calls;
			return new WP_Error( 'unexpected_http', 'initialize() must not call OpsOasis on a front-end request.' );
		};
		add_filter( 'pre_http_request', $count_http, 10, 3 );

		try {
			$module = new AutoUpdatePluginsFilter();
			$method = new ReflectionMethod( $module, 'initialize' );
			$method->setAccessible( true );
			$method->invoke( $module );

			Assert::assertSame( 0, $http_calls, 'No remote request should be made on a front-end request.' );
			Assert::assertFalse( get_transient( 'wpcpmsp_auto_update_settings' ), 'No settings transient should be written on a front-end request.' );
		} finally {
			remove_filter( 'pre_http_request', $count_http, 10 );
			delete_transient( 'wpcpmsp_auto_update_settings' );
		}
	}

	/**
	 * The settings endpoint must be overridable so a site can be pointed elsewhere.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function settings_endpoint_is_overridable( IntegrationTester $i ): void {
		$this->reset_settings_storage();

		$requested_url = null;
		$capture       = static function ( $pre, $args, $url ) use ( &$requested_url ) {
			$requested_url = $url;
			return new WP_Error( 'http_request_failed', 'Captured.' );
		};
		$override = static function () {
			return 'https://opsoasis.test/wp-json/wpcomsp/autoupdate-plugin/v1/settings/';
		};

		add_filter( 'a8csp_atlantis_autoupdate_settings_url', $override );
		add_filter( 'pre_http_request', $capture, 10, 3 );

		try {
			$this->fetch_settings( new AutoUpdatePluginsFilter() );

			Assert::assertSame(
				'https://opsoasis.test/wp-json/wpcomsp/autoupdate-plugin/v1/settings/',
				$requested_url,
				'The settings request must use the filtered endpoint.'
			);
		} finally {
			remove_filter( 'pre_http_request', $capture, 10 );
			remove_filter( 'a8csp_atlantis_autoupdate_settings_url', $override );
			$this->reset_settings_storage();
		}
	}

	/**
	 * A successful fetch must be recorded so it can be reused while the endpoint is unreachable.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function successful_fetch_records_last_known_good_settings( IntegrationTester $i ): void {
		$this->reset_settings_storage();

		$respond = $this->http_response( array( 'disabled_plugins' => array( 'akismet/akismet.php' ) ) );
		add_filter( 'pre_http_request', $respond, 10, 3 );

		try {
			$settings = $this->fetch_settings( new AutoUpdatePluginsFilter() );

			Assert::assertIsObject( $settings );
			Assert::assertSame( array( 'akismet/akismet.php' ), (array) $settings->disabled_plugins );

			$last_good = get_option( 'a8csp_atlantis_autoupdate_last_good_settings' );
			Assert::assertIsArray( $last_good, 'A successful fetch must be recorded as the last known good settings.' );
			Assert::assertArrayHasKey( 'settings', $last_good );
			Assert::assertArrayHasKey( 'timestamp', $last_good );
			Assert::assertGreaterThan( 0, $last_good['timestamp'] );
		} finally {
			remove_filter( 'pre_http_request', $respond, 10 );
			$this->reset_settings_storage();
		}
	}

	/**
	 * A brief outage must reuse the last known good payload rather than stopping every update.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function failed_fetch_falls_back_to_last_known_good_within_grace( IntegrationTester $i ): void {
		$this->reset_settings_storage();
		$this->store_last_known_good(
			array( 'disabled_plugins' => array( 'akismet/akismet.php' ) ),
			time() - HOUR_IN_SECONDS
		);

		$fail = static function () {
			return new WP_Error( 'http_request_failed', 'Simulated OpsOasis outage' );
		};
		add_filter( 'pre_http_request', $fail, 10, 3 );

		try {
			$settings = $this->fetch_settings( new AutoUpdatePluginsFilter() );

			Assert::assertIsObject( $settings, 'A recent last known good payload must be used instead of failing closed.' );
			Assert::assertTrue( empty( $settings->disable_all ), 'A brief outage must not disable every autoupdate.' );
			Assert::assertSame(
				array( 'akismet/akismet.php' ),
				(array) $settings->disabled_plugins,
				'Deliberate blocks must still be honoured while serving stale settings.'
			);
		} finally {
			remove_filter( 'pre_http_request', $fail, 10 );
			$this->reset_settings_storage();
		}
	}

	/**
	 * A prolonged outage must still end in the conservative state.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function failed_fetch_falls_closed_once_the_grace_period_expires( IntegrationTester $i ): void {
		$this->reset_settings_storage();
		$this->store_last_known_good(
			array( 'disabled_plugins' => array() ),
			time() - ( 25 * HOUR_IN_SECONDS )
		);

		$fail = static function () {
			return new WP_Error( 'http_request_failed', 'Simulated OpsOasis outage' );
		};
		add_filter( 'pre_http_request', $fail, 10, 3 );

		try {
			$settings = $this->fetch_settings( new AutoUpdatePluginsFilter() );

			Assert::assertNull( $settings, 'An expired last known good payload must surface an exception to the caller.' );

			$cached = get_transient( 'wpcpmsp_auto_update_settings' );
			Assert::assertIsObject( $cached );
			Assert::assertTrue( ! empty( $cached->disable_all ), 'The fail-safe must still be negative-cached.' );
		} finally {
			remove_filter( 'pre_http_request', $fail, 10 );
			$this->reset_settings_storage();
		}
	}

	/**
	 * The grace window must be adjustable without a code change.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function grace_period_is_filterable( IntegrationTester $i ): void {
		$this->reset_settings_storage();
		$this->store_last_known_good( array( 'disabled_plugins' => array() ), time() );

		$no_grace = static function () {
			return 0;
		};
		$fail = static function () {
			return new WP_Error( 'http_request_failed', 'Simulated OpsOasis outage' );
		};

		add_filter( 'a8csp_atlantis_autoupdate_settings_grace', $no_grace );
		add_filter( 'pre_http_request', $fail, 10, 3 );

		try {
			$settings = $this->fetch_settings( new AutoUpdatePluginsFilter() );

			Assert::assertNull( $settings, 'A zero grace window must fail closed immediately.' );
		} finally {
			remove_filter( 'pre_http_request', $fail, 10 );
			remove_filter( 'a8csp_atlantis_autoupdate_settings_grace', $no_grace );
			$this->reset_settings_storage();
		}
	}

	/**
	 * The fail-closed state must be readable from storage, because the REST status request
	 * is not an autoupdate context and so never populates the module's settings property.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function settings_state_is_readable_without_initializing_the_module( IntegrationTester $i ): void {
		$this->reset_settings_storage();

		$state = AutoUpdatePluginsFilter::get_settings_state();

		Assert::assertTrue( $state['fail_closed'], 'With nothing stored, the site is fail-closed.' );
		Assert::assertNull( $state['last_success'] );
		Assert::assertNull( $state['seconds_since_success'] );

		$this->store_last_known_good( array( 'disabled_plugins' => array() ), time() - HOUR_IN_SECONDS );

		$state = AutoUpdatePluginsFilter::get_settings_state();

		Assert::assertFalse( $state['fail_closed'], 'A last known good payload inside the grace window is not fail-closed.' );
		Assert::assertIsInt( $state['last_success'] );
		Assert::assertGreaterThanOrEqual( HOUR_IN_SECONDS, $state['seconds_since_success'] );

		$this->store_last_known_good( array( 'disabled_plugins' => array() ), time() - ( 25 * HOUR_IN_SECONDS ) );

		$state = AutoUpdatePluginsFilter::get_settings_state();

		Assert::assertTrue( $state['fail_closed'], 'A last known good payload older than the grace window is fail-closed.' );

		$this->reset_settings_storage();
	}

	/**
	 * Invokes the private settings fetch, returning null when it throws.
	 *
	 * @param AutoUpdatePluginsFilter $module Module instance.
	 *
	 * @return \stdClass|null
	 */
	private function fetch_settings( AutoUpdatePluginsFilter $module ): ?\stdClass {
		$method = new ReflectionMethod( $module, 'get_auto_update_settings' );
		$method->setAccessible( true );

		try {
			return $method->invoke( $module );
		} catch ( \Exception $exception ) {
			return null;
		}
	}

	/**
	 * Builds a `pre_http_request` callback returning a successful JSON response.
	 *
	 * @param array $payload Response payload.
	 *
	 * @return callable
	 */
	private function http_response( array $payload ): callable {
		return static function () use ( $payload ) {
			return array(
				'headers'  => array(),
				'body'     => (string) wp_json_encode( $payload ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
	}

	/**
	 * Seeds the last known good settings option.
	 *
	 * @param array $payload   Settings payload.
	 * @param int   $timestamp When the payload was last fetched successfully.
	 *
	 * @return void
	 */
	private function store_last_known_good( array $payload, int $timestamp ): void {
		update_option(
			'a8csp_atlantis_autoupdate_last_good_settings',
			array(
				'settings'  => (object) $payload,
				'timestamp' => $timestamp,
			)
		);
	}

	/**
	 * Clears both the settings transient and the last known good option.
	 *
	 * @return void
	 */
	private function reset_settings_storage(): void {
		delete_transient( 'wpcpmsp_auto_update_settings' );
		delete_option( 'a8csp_atlantis_autoupdate_last_good_settings' );
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
