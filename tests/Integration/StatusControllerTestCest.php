<?php
/**
 * Integration tests for the REST status payload.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\REST\Status_Controller;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Status controller integration tests.
 */
class StatusControllerTestCest {
	/**
	 * Configures a centralized settings endpoint: without one the module never fetches and has no
	 * fail-closed state to report.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		update_option( 'a8csp_atlantis_autoupdate_settings_url', 'https://settings.test/wp-json/example/v1/settings/' );
	}

	/**
	 * Removes what the tests configured.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		delete_option( 'a8csp_atlantis_autoupdate_settings_url' );
		delete_option( 'a8csp_atlantis_managed_site' );
	}

	/**
	 * The autoupdates entry must report whether the site is currently refusing all updates,
	 * because `enabled` stays true throughout an outage of the settings endpoint.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function status_payload_reports_the_autoupdates_fail_closed_state( IntegrationTester $i ): void {
		delete_option( 'a8csp_atlantis_autoupdate_last_good_settings' );

		$modules = $this->get_modules_payload();

		Assert::assertArrayHasKey( 'autoupdates', $modules, 'The autoupdates module must appear in the status payload.' );
		Assert::assertTrue( $modules['autoupdates']['fail_closed'], 'With no successful fetch on record the site is fail-closed.' );
		Assert::assertNull( $modules['autoupdates']['last_success'] );

		update_option(
			'a8csp_atlantis_autoupdate_last_good_settings',
			array(
				'settings'  => (object) array( 'disabled_plugins' => array() ),
				'timestamp' => time() - HOUR_IN_SECONDS,
			)
		);

		$modules = $this->get_modules_payload();

		Assert::assertFalse( $modules['autoupdates']['fail_closed'], 'A recent successful fetch is not fail-closed.' );
		Assert::assertIsInt( $modules['autoupdates']['last_success'] );
		Assert::assertGreaterThanOrEqual( HOUR_IN_SECONDS, $modules['autoupdates']['seconds_since_success'] );

		// `enabled` must remain true in both states — that is precisely why `fail_closed` is needed.
		Assert::assertTrue( $modules['autoupdates']['enabled'] );

		delete_option( 'a8csp_atlantis_autoupdate_last_good_settings' );
	}

	/**
	 * A switched-off module vetoes nothing, so it must not answer the "is this site refusing
	 * updates?" question at all. It never fetches, so it has no successful fetch on record and
	 * would otherwise report itself as permanently fail-closed.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function status_payload_omits_autoupdate_state_when_the_module_is_off( IntegrationTester $i ): void {
		$option_key = a8csp_atlantis_generate_module_settings_key( 'Autoupdates' );
		$previous   = get_option( $option_key, array() );

		delete_option( 'a8csp_atlantis_autoupdate_last_good_settings' );

		try {
			update_option( $option_key, array( 'enabled' => '0' ) );

			$modules = $this->get_modules_payload();

			Assert::assertFalse( $modules['autoupdates']['enabled'], 'Test precondition: the module is switched off.' );
			Assert::assertArrayNotHasKey( 'fail_closed', $modules['autoupdates'] );
			Assert::assertArrayNotHasKey( 'last_success', $modules['autoupdates'] );
			Assert::assertArrayNotHasKey( 'seconds_since_success', $modules['autoupdates'] );

			update_option( $option_key, array( 'enabled' => '1' ) );

			$modules = $this->get_modules_payload();

			Assert::assertTrue( $modules['autoupdates']['enabled'], 'Test precondition: the module is switched on.' );
			Assert::assertArrayHasKey( 'fail_closed', $modules['autoupdates'], 'A running module must still report its state.' );
		} finally {
			update_option( $option_key, $previous );
			delete_option( 'a8csp_atlantis_autoupdate_last_good_settings' );
		}
	}

	/**
	 * Fleet tooling finds the managed sites that were never given an endpoint by these two flags,
	 * so both must be reported and neither may give the endpoint itself away.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function status_payload_reports_whether_the_site_is_managed_and_has_an_endpoint( IntegrationTester $i ): void {
		update_option( 'a8csp_atlantis_managed_site', '1' );

		$data = $this->get_payload();

		Assert::assertTrue( $data['plugin']['managed'] );
		Assert::assertTrue( $data['modules']['autoupdates']['settings_url_configured'] );
		Assert::assertStringNotContainsString( 'settings.test', (string) wp_json_encode( $data ), 'The endpoint is configuration, not status.' );

		update_option( 'a8csp_atlantis_managed_site', '0' );
		delete_option( 'a8csp_atlantis_autoupdate_settings_url' );

		$data = $this->get_payload();

		Assert::assertFalse( $data['plugin']['managed'] );
		Assert::assertFalse( $data['modules']['autoupdates']['settings_url_configured'] );
		Assert::assertFalse( $data['modules']['autoupdates']['fail_closed'], 'A site with no endpoint never fetches, so it is not fail-closed.' );
	}

	/**
	 * Returns the `modules` section of the status payload.
	 *
	 * @return array
	 */
	private function get_modules_payload(): array {
		$data = $this->get_payload();

		Assert::assertArrayHasKey( 'modules', $data );

		return $data['modules'];
	}

	/**
	 * Returns the whole status payload.
	 *
	 * @return array
	 */
	private function get_payload(): array {
		$controller = new Status_Controller();
		$response   = $controller->get_item( new WP_REST_Request( 'GET', '/a8csp-atlantis/v1/status' ) );

		return $response->get_data();
	}
}
