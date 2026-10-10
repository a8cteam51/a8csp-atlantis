<?php
/**
 * Integration tests for who may write module settings.
 *
 * On a managed site the modules admin screen is gated on being an Automattician, but the settings
 * are saved by core's `options.php`, which applies its own gate. These tests exercise that gate
 * the way core does.
 */

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Module settings authorization tests.
 */
class ModuleSettingsAuthorizationTestCest {
	/**
	 * The settings group the modules screen registers into.
	 *
	 * @var string
	 */
	private const OPTION_GROUP = 'a8csp_modules_group';

	/**
	 * Marks the site as managed, which is where the Automattician gate applies.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		update_option( 'a8csp_atlantis_managed_site', '1' );
	}

	/**
	 * Clears the managed flag.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		delete_option( 'a8csp_atlantis_managed_site' );
	}

	/**
	 * A site the team does not manage has nobody but its own administrators to run it, so any of
	 * them may write module settings — but still nobody below administrator.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function any_administrator_can_write_module_settings_on_an_unmanaged_site( IntegrationTester $i ): void {
		update_option( 'a8csp_atlantis_managed_site', '0' );

		$admin_id  = $this->create_administrator( 'outsider@example.com' );
		$editor_id = $this->create_administrator( 'editor@example.com', 'editor' );

		try {
			wp_set_current_user( $admin_id );
			Assert::assertFalse( a8csp_atlantis_is_automattician(), 'Test precondition: this administrator is not an Automattician.' );
			Assert::assertTrue( a8csp_atlantis_current_user_can_manage() );
			Assert::assertTrue( current_user_can( $this->options_page_capability() ) );

			wp_set_current_user( $editor_id );
			Assert::assertFalse( a8csp_atlantis_current_user_can_manage() );
			Assert::assertFalse( current_user_can( $this->options_page_capability() ) );
		} finally {
			$this->cleanup( $admin_id );
			$this->cleanup( $editor_id );
		}
	}

	/**
	 * An administrator who is not an Automattician must not be able to write module settings
	 * through `options.php`, because that is the same thing the modules screen refuses to let
	 * them do.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function non_automattician_administrator_cannot_write_module_settings( IntegrationTester $i ): void {
		$user_id = $this->create_administrator( 'outsider@example.com' );

		try {
			wp_set_current_user( $user_id );

			Assert::assertFalse(
				a8csp_atlantis_is_automattician(),
				'Test precondition: this administrator is not an Automattician.'
			);

			Assert::assertFalse( a8csp_atlantis_current_user_can_manage(), 'The admin screens must refuse them too.' );

			Assert::assertFalse(
				current_user_can( $this->options_page_capability() ),
				'options.php must not accept module settings from a non-Automattician administrator.'
			);
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * The people the screen is built for must still be able to save.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function automattician_administrator_can_still_write_module_settings( IntegrationTester $i ): void {
		$user_id = $this->create_administrator( 'insider@a8c.com' );

		try {
			wp_set_current_user( $user_id );

			Assert::assertTrue(
				a8csp_atlantis_is_automattician(),
				'Test precondition: this administrator is an Automattician.'
			);

			Assert::assertTrue(
				current_user_can( $this->options_page_capability() ),
				'An Automattician must still be able to save module settings.'
			);
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * A user below administrator was never able to save, and must stay that way.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function editor_cannot_write_module_settings( IntegrationTester $i ): void {
		$user_id = $this->create_administrator( 'editor@a8c.com', 'editor' );

		try {
			wp_set_current_user( $user_id );

			Assert::assertFalse( current_user_can( $this->options_page_capability() ) );
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * Resolves the capability `options.php` requires for this settings group, exactly as core
	 * does before accepting a save.
	 *
	 * @return string
	 */
	private function options_page_capability(): string {
		/** @see wp-admin/options.php */
		return (string) apply_filters( 'option_page_capability_' . self::OPTION_GROUP, 'manage_options' );
	}

	/**
	 * Creates a user with the given email and role.
	 *
	 * @param string $email The user's email address.
	 * @param string $role  The role to assign.
	 *
	 * @return int
	 */
	private function create_administrator( string $email, string $role = 'administrator' ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'settings_user_' . wp_rand( 1000, 9999 ),
				'user_pass'  => wp_generate_password(),
				'user_email' => $email,
				'role'       => $role,
			)
		);

		Assert::assertIsInt( $user_id, 'Test precondition: the user could not be created.' );

		return $user_id;
	}

	/**
	 * Removes the test user and resets the current user.
	 *
	 * @param int $user_id The user to remove.
	 *
	 * @return void
	 */
	private function cleanup( int $user_id ): void {
		wp_set_current_user( 0 );

		if ( ! function_exists( 'wp_delete_user' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		wp_delete_user( $user_id );
	}
}
