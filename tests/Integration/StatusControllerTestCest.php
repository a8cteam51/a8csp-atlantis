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
	 * The autoupdates entry must report whether the site is currently refusing all updates,
	 * because `enabled` stays true throughout an OpsOasis outage.
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
	 * Returns the `modules` section of the status payload.
	 *
	 * @return array
	 */
	private function get_modules_payload(): array {
		$controller = new Status_Controller();
		$response   = $controller->get_item( new WP_REST_Request( 'GET', '/a8csp-atlantis/v1/status' ) );
		$data       = $response->get_data();

		Assert::assertArrayHasKey( 'modules', $data );

		return $data['modules'];
	}
}
