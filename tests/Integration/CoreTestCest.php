<?php
/**
 * Integration tests for core Atlantis plugin behavior.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules;
use A8C\SpecialProjects\Atlantis\Settings;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Core plugin integration tests.
 */
class CoreTestCest {
	/**
	 * Ensure Automattician detection matches allowed email domains.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function automattician_detection_uses_allowed_domains( IntegrationTester $i ): void {
		$allowed_user_id = $this->ensure_admin_user( 'a8c-admin', 'admin@automattic.com' );
		Assert::assertGreaterThan( 0, $allowed_user_id );
		wp_set_current_user( $allowed_user_id );
		Assert::assertTrue( a8csp_atlantis_is_automattician() );

		$blocked_user_id = $this->ensure_admin_user( 'site-admin', 'admin@example.com' );
		Assert::assertGreaterThan( 0, $blocked_user_id );
		wp_set_current_user( $blocked_user_id );
		Assert::assertFalse( a8csp_atlantis_is_automattician() );
	}

	/**
	 * Ensure module settings key helpers return stable values.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function module_settings_helpers_return_expected_keys( IntegrationTester $i ): void {
		Assert::assertSame( 'a8csp_module_autoupdates', a8csp_atlantis_generate_module_settings_key( 'Autoupdates' ) );

		update_option( 'a8csp_module_tracking', array( 'enabled' => '1' ) );
		$settings = a8csp_atlantis_get_module_settings( 'Tracking' );

		Assert::assertIsArray( $settings );
		Assert::assertSame( '1', $settings['enabled'] );
	}

	/**
	 * Ensure Settings and Modules classes expose expected menu entries.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function settings_and_modules_register_admin_menus( IntegrationTester $i ): void {
		$user_id = $this->ensure_admin_user( 'menu-admin', 'menu@automattic.com' );
		Assert::assertGreaterThan( 0, $user_id );
		wp_set_current_user( $user_id );

		$settings = new Settings();
		$settings->register_admin_menu();

		global $menu, $submenu;
		Assert::assertIsArray( $menu );
		Assert::assertIsArray( $submenu );
		Assert::assertNotFalse( $this->find_menu_by_slug( $menu, 'a8csp-atlantis' ) );

		$modules = new Modules();
		$modules->register_admin_menu();
		Assert::assertNotEmpty( $submenu['a8csp-atlantis'] ?? array() );
	}

	/**
	 * Find a top-level menu item by slug.
	 *
	 * @param array<int, array<mixed>> $menu Admin menu array.
	 * @param string                   $slug Menu slug.
	 *
	 * @return array<mixed>|false
	 */
	private function find_menu_by_slug( array $menu, string $slug ) {
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return $item;
			}
		}

		return false;
	}

	/**
	 * Create (or fetch) an admin user for tests.
	 *
	 * @param string $login User login.
	 * @param string $email User email.
	 *
	 * @return int
	 */
	private function ensure_admin_user( string $login, string $email ): int {
		$existing = get_user_by( 'login', $login );
		if ( $existing instanceof WP_User ) {
			$existing->set_role( 'administrator' );
			return $existing->ID;
		}

		$user_id = wp_create_user( $login, 'password', $email );
		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		$user = new WP_User( $user_id );
		$user->set_role( 'administrator' );

		return $user_id;
	}
}
