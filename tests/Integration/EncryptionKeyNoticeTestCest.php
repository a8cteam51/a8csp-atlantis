<?php
/**
 * Integration tests for who can see the generated encryption key.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Encryption;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Encryption key notice visibility tests.
 */
class EncryptionKeyNoticeTestCest {
	/**
	 * A user who could not act on the key must never be shown it.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function subscriber_is_not_shown_the_generated_key( IntegrationTester $i ): void {
		$user_id = $this->create_user( 'subscriber' );

		try {
			wp_set_current_user( $user_id );

			$output = $this->render_key_notice();

			Assert::assertSame( '', trim( $output ), 'A subscriber must see nothing at all.' );
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * An administrator still needs the key, since they are the one who has to paste it into
	 * wp-config.php.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function administrator_is_still_shown_the_generated_key( IntegrationTester $i ): void {
		$user_id = $this->create_user( 'administrator' );

		try {
			wp_set_current_user( $user_id );

			$output = $this->render_key_notice();

			Assert::assertStringContainsString( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', $output );
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * Runs the key insertion and returns what it prints to the current user.
	 *
	 * @return string
	 */
	private function render_key_notice(): string {
		if ( defined( 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) ) {
			Assert::markTestSkipped( 'A key is already defined in this process, so the notice cannot be reached.' );
		}

		$previous_flag = get_option( 'a8csp_atlantis_inserted_encryption_key', 'no' );
		delete_option( 'a8csp_atlantis_inserted_encryption_key' );

		$no_direct_filesystem = static function (): string {
			return 'ftpext';
		};
		add_filter( 'filesystem_method', $no_direct_filesystem );

		remove_all_actions( 'admin_notices' );

		try {
			( new Encryption() )->maybe_auto_insert_encryption_key();

			Assert::assertFalse(
				a8csp_atlantis_has_encryption_key(),
				'Test precondition: no key should have been inserted.'
			);

			ob_start();
			do_action( 'admin_notices' );
			return (string) ob_get_clean();
		} finally {
			remove_filter( 'filesystem_method', $no_direct_filesystem );
			remove_all_actions( 'admin_notices' );
			update_option( 'a8csp_atlantis_inserted_encryption_key', $previous_flag );
		}
	}

	/**
	 * Creates a user with the given role.
	 *
	 * @param string $role The role to assign.
	 *
	 * @return int
	 */
	private function create_user( string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'notice_' . $role . '_' . wp_rand( 1000, 9999 ),
				'user_pass'  => wp_generate_password(),
				'user_email' => $role . wp_rand( 1000, 9999 ) . '@example.com',
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
