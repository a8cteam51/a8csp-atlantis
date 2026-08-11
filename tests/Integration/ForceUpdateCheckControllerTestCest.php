<?php
/**
 * Integration tests for the Force Update Check REST controller.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\REST\Force_Update_Check_Controller;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Force Update Check REST controller integration tests.
 */
class ForceUpdateCheckControllerTestCest {
	/**
	 * Restores the state the tests touch after each case.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void {
		delete_site_transient( 'update_plugins' );
		wp_set_current_user( 0 );
	}

	/**
	 * The route is denied to callers without `manage_options`.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function permission_denied_without_manage_options( IntegrationTester $i ): void {
		wp_set_current_user( 0 );

		$result = ( new Force_Update_Check_Controller() )->create_item_permissions_check( new WP_REST_Request( 'POST' ) );

		Assert::assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * The route is allowed for a caller with `manage_options`, so OpsOasis is not locked out of its lever.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function permission_granted_with_manage_options( IntegrationTester $i ): void {
		$user_id = wp_create_user( 'force_check_admin', 'password', 'force_check_admin@example.com' );
		if ( is_wp_error( $user_id ) ) {
			$existing = get_user_by( 'login', 'force_check_admin' );
			$user_id  = $existing instanceof WP_User ? $existing->ID : 0;
		}
		( new WP_User( $user_id ) )->set_role( 'administrator' );
		wp_set_current_user( $user_id );

		$result = ( new Force_Update_Check_Controller() )->create_item_permissions_check( new WP_REST_Request( 'POST' ) );

		Assert::assertTrue( $result );
	}

	/**
	 * Handling the request drops the stale `update_plugins` transient, re-runs the check, and reports a
	 * fresh, honest outcome payload.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function create_item_clears_update_plugins_transient( IntegrationTester $i ): void {
		set_site_transient( 'update_plugins', (object) array( 'sentinel' => true ) );

		// Keep the wp_update_plugins() re-check offline; a 200 with an empty body makes core repopulate the
		// transient with a fresh last_checked and no update responses.
		$stub = static function () {
			return array(
				'headers'  => array(),
				'body'     => '',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $stub, 10, 3 );

		try {
			$response = ( new Force_Update_Check_Controller() )->create_item( new WP_REST_Request( 'POST' ) );

			// The stale sentinel is gone and the re-check repopulated the transient with a fresh timestamp.
			$transient = get_site_transient( 'update_plugins' );
			Assert::assertIsObject( $transient );
			Assert::assertFalse( isset( $transient->sentinel ) );
			Assert::assertNotEmpty( $transient->last_checked );

			// The payload reports the real outcome, not an unconditional success.
			$data = $response->get_data();
			Assert::assertTrue( $data['refreshed'] );
			Assert::assertSame( 0, $data['updates'] );
			Assert::assertNull( $data['woocommerce'] );
			Assert::assertSame( (int) $transient->last_checked, $data['last_checked'] );
		} finally {
			remove_filter( 'pre_http_request', $stub, 10 );
		}
	}
}
