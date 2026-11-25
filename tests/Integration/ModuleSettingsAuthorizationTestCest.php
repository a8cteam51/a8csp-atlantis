<?php
/**
 * Integration tests for who may write module settings.
 *
 * On a managed site the modules admin screen is gated on being an Automattician, but the settings
 * are saved by core's `options.php`, which applies its own gate. These tests exercise that gate
 * the way core does.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Settings;
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
	 * options.php also saves other groups, and its All Settings screen writes any option the
	 * request names. Someone who may not manage Atlantis must not change Atlantis's options that
	 * way, while their other settings still save.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function options_php_cannot_change_atlantis_options_without_the_capability( IntegrationTester $i ): void {
		$user_id = $this->create_administrator( 'outsider@example.com' );
		$tagline = get_option( 'blogdescription' );

		try {
			wp_set_current_user( $user_id );
			$this->save_through_options_php(
				function (): void {
					update_option( 'a8csp_atlantis_managed_site', '0' );
					update_option( 'a8csp_atlantis_autoupdate_settings_url', 'https://elsewhere.test/settings' );
					update_option( 'a8csp_module_autoupdates', array( 'enabled' => '0' ) );
					update_option( 'blogdescription', 'Changed through options.php' );
				}
			);

			Assert::assertSame( '1', get_option( 'a8csp_atlantis_managed_site' ), 'The site must still count as managed.' );
			Assert::assertFalse( get_option( 'a8csp_atlantis_autoupdate_settings_url' ), 'No settings endpoint may be set.' );
			Assert::assertNotSame( array( 'enabled' => '0' ), get_option( 'a8csp_module_autoupdates' ), 'The module may not be switched off.' );
			Assert::assertSame( 'Changed through options.php', get_option( 'blogdescription' ), 'Settings that are not Atlantis\'s must still save.' );
			Assert::assertContains( 'a8csp_atlantis_protected_option', wp_list_pluck( get_settings_errors( 'general' ), 'code' ), 'The administrator must be told why.' );
		} finally {
			update_option( 'blogdescription', $tagline );
			delete_option( 'a8csp_atlantis_autoupdate_settings_url' );
			$this->cleanup( $user_id );
		}
	}

	/**
	 * Someone who may manage Atlantis can still change its options through options.php.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function options_php_still_saves_atlantis_options_with_the_capability( IntegrationTester $i ): void {
		$user_id = $this->create_administrator( 'insider@a8c.com' );

		try {
			wp_set_current_user( $user_id );
			$this->save_through_options_php(
				function (): void {
					update_option( 'a8csp_atlantis_autoupdate_settings_url', 'https://elsewhere.test/settings' );
				}
			);

			Assert::assertSame( 'https://elsewhere.test/settings', get_option( 'a8csp_atlantis_autoupdate_settings_url' ) );
		} finally {
			delete_option( 'a8csp_atlantis_autoupdate_settings_url' );
			$this->cleanup( $user_id );
		}
	}

	/**
	 * The options covered are Atlantis's own, and nothing else.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function protected_options_are_atlantis_options_only( IntegrationTester $i ): void {
		foreach ( array( 'a8csp_atlantis_managed_site', 'a8csp_module_autoupdates', 'plugin_autoupdate_filter_disabled_plugins', 'plugin_update_delays', '_transient_wpcpmsp_auto_update_settings', '_transient_timeout_wpcpmsp_auto_update_settings', '_transient_a8csp_atlantis_github_latest_release' ) as $option ) {
			Assert::assertTrue( Settings::is_protected_option( $option ), "$option is Atlantis's." );
		}

		foreach ( array( 'blogname', 'active_plugins', 'woocommerce_allow_tracking', 'a8csp_other_plugin' ) as $option ) {
			Assert::assertFalse( Settings::is_protected_option( $option ), "$option is not Atlantis's." );
		}
	}

	/**
	 * Runs a callback as options.php would save, then undoes what that set up.
	 *
	 * @param callable $save The writes the save makes.
	 *
	 * @return void
	 */
	private function save_through_options_php( callable $save ): void {
		global $wp_settings_errors;

		$settings              = new Settings();
		$_POST['option_page']  = 'options';
		$wp_settings_errors    = array();

		try {
			$settings->maybe_protect_options();
			$save();
		} finally {
			remove_filter( 'pre_update_option', array( $settings, 'filter_keep_protected_option' ), PHP_INT_MAX );
			unset( $_POST['option_page'] );
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
