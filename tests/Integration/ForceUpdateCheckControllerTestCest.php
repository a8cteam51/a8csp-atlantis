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
	 * Handling the request clears the `update_plugins` transient and reports success.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function create_item_clears_update_plugins_transient( IntegrationTester $i ): void {
		set_site_transient( 'update_plugins', (object) array( 'sentinel' => true ) );

		// Keep the wp_update_plugins() re-check offline.
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

			$transient = get_site_transient( 'update_plugins' );
			Assert::assertFalse( is_object( $transient ) && isset( $transient->sentinel ) );
			Assert::assertSame( array( 'refreshed' => true ), $response->get_data() );
		} finally {
			remove_filter( 'pre_http_request', $stub, 10 );
		}
	}
}
