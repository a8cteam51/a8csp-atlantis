<?php
/**
 * Integration tests for encryption key state handling.
 *
 * These run in their own suite so that A8CSP_ATLANTIS_ENCRYPTION_KEY is never
 * defined. Nothing in this file may define it.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Encryption;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Encryption key state tests.
 */
class EncryptionKeyStateCest {
	/**
	 * Guards the premise of this whole suite: without this, every other test here
	 * would pass for the wrong reason.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function encryption_key_constant_is_absent_in_this_suite( IntegrationTester $i ): void {
		Assert::assertFalse(
			defined( 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ),
			'This suite is only meaningful while the encryption key constant is undefined.'
		);
		Assert::assertFalse( a8csp_atlantis_has_encryption_key() );
	}

	/**
	 * With no key defined, encryption must report an error rather than returning
	 * something that would be written to the database.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function encrypting_without_a_key_returns_an_error( IntegrationTester $i ): void {
		$encrypted = a8csp_atlantis_encrypt_data( 'Important notice.' );

		Assert::assertTrue( is_wp_error( $encrypted ) );
		Assert::assertSame( 'encrypt-key-error', $encrypted->get_error_code() );
	}

	/**
	 * When the plugin has previously written a key but the constant is gone, it must
	 * warn rather than silently returning and leaving the site with unreadable content.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function lost_key_warns_instead_of_silently_latching( IntegrationTester $i ): void {
		update_option( 'a8csp_atlantis_inserted_encryption_key', 'yes' );

		$before = $this->count_admin_notice_callbacks();

		( new Encryption() )->maybe_auto_insert_encryption_key();

		Assert::assertGreaterThan(
			$before,
			$this->count_admin_notice_callbacks(),
			'A missing key combined with the inserted-key flag must raise an admin notice, not return silently.'
		);
	}

	/**
	 * Counts the callbacks currently registered on admin_notices.
	 *
	 * @return int
	 */
	private function count_admin_notice_callbacks(): int {
		global $wp_filter;

		if ( ! isset( $wp_filter['admin_notices'] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $wp_filter['admin_notices']->callbacks as $priority_callbacks ) {
			$count += count( $priority_callbacks );
		}

		return $count;
	}
}
