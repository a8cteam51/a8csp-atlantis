<?php
/**
 * Integration tests for the Automattician trust boundary.
 *
 * `a8csp_atlantis_is_automattician()` trusts an administrator whose own email address ends in an
 * Automattic domain. These tests establish how hard it actually is for an administrator to give
 * themselves such an address.
 */

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Automattician boundary tests.
 */
class AutomatticianBoundaryTestCest {
	/**
	 * The address a non-Automattic administrator starts with.
	 *
	 * @var string
	 */
	private const ORIGINAL_EMAIL = 'outsider@example.com';

	/**
	 * The address they attempt to move to.
	 *
	 * @var string
	 */
	private const TARGET_EMAIL = 'outsider@a8c.com';

	/**
	 * Baseline: an administrator on a non-Automattic domain is not trusted.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function administrator_on_another_domain_is_not_an_automattician( IntegrationTester $i ): void {
		$user_id = $this->create_outsider_admin();

		try {
			wp_set_current_user( $user_id );

			Assert::assertFalse( a8csp_atlantis_is_automattician() );
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * Submitting a new address through the profile form does not change the account. Core parks
	 * it in `_new_email` until whoever owns the new mailbox clicks the confirmation link, so an
	 * administrator cannot simply hand themselves an address they do not control.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function changing_own_email_through_the_profile_form_requires_confirmation( IntegrationTester $i ): void {
		$user_id = $this->create_outsider_admin();

		try {
			wp_set_current_user( $user_id );

			$this->submit_profile_email_change( $user_id, self::TARGET_EMAIL );

			$user = get_userdata( $user_id );
			Assert::assertSame(
				self::ORIGINAL_EMAIL,
				$user->user_email,
				'The account email must not change until the new address is confirmed.'
			);

			$pending = get_user_meta( $user_id, '_new_email', true );
			Assert::assertIsArray( $pending, 'The requested address should be parked pending confirmation.' );
			Assert::assertSame( self::TARGET_EMAIL, $pending['newemail'] );

			Assert::assertFalse(
				a8csp_atlantis_is_automattician(),
				'An unconfirmed address must not cross the trust boundary.'
			);
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * The confirmation flow is a property of the profile form, not of the user account. Anything
	 * calling `wp_update_user()` sets the address outright — WP-CLI, another plugin, the plugin
	 * or theme editor. So the boundary holds only against someone confined to the profile screen.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function updating_the_user_directly_bypasses_confirmation_entirely( IntegrationTester $i ): void {
		$user_id = $this->create_outsider_admin();

		try {
			wp_update_user(
				array(
					'ID'         => $user_id,
					'user_email' => self::TARGET_EMAIL,
				)
			);

			wp_set_current_user( $user_id );

			$user = get_userdata( $user_id );
			Assert::assertSame( self::TARGET_EMAIL, $user->user_email, 'wp_update_user() applies the address immediately.' );

			Assert::assertTrue(
				a8csp_atlantis_is_automattician(),
				'The check passes on an address that was never verified.'
			);
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * Runs core's profile-form email handler the way `profile.php` does.
	 *
	 * @param int    $user_id   The user submitting the form.
	 * @param string $new_email The address being requested.
	 *
	 * @return void
	 */
	private function submit_profile_email_change( int $user_id, string $new_email ): void {
		global $errors;

		$previous_errors = $errors;
		$errors          = null;

		$_POST['user_id'] = $user_id;
		$_POST['email']   = $new_email;

		try {
			send_confirmation_on_profile_email();
		} finally {
			unset( $_POST['user_id'], $_POST['email'] );
			$errors = $previous_errors;
		}
	}

	/**
	 * Creates an administrator with a non-Automattic address.
	 *
	 * @return int
	 */
	private function create_outsider_admin(): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'outsider_admin_' . wp_rand( 1000, 9999 ),
				'user_pass'  => wp_generate_password(),
				'user_email' => self::ORIGINAL_EMAIL,
				'role'       => 'administrator',
			)
		);

		Assert::assertIsInt( $user_id, 'Test precondition: the administrator could not be created.' );

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
