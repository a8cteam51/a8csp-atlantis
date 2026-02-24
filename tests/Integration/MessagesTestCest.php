<?php
/**
 * Integration tests for Messages module behavior.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Message;
use A8C\SpecialProjects\Atlantis\Modules\Messages\CustomTable;
use A8C\SpecialProjects\Atlantis\Modules\Messages\Messages;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Messages module integration tests.
 */
class MessagesTestCest {
	/**
	 * Ensure module metadata and mandatory status remain stable.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function module_metadata_is_stable( IntegrationTester $i ): void {
		$module = new Messages();

		Assert::assertSame( 'Messages', $module->get_name() );
		Assert::assertStringContainsString( 'messages', strtolower( $module->get_description() ) );
		Assert::assertTrue( $module->is_mandatory() );
	}

	/**
	 * Ensure custom table exists and message helper functions support CRUD.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function message_helpers_support_crud_flow( IntegrationTester $i ): void {
		$this->ensure_encryption_key_is_defined();
		$this->ensure_messages_table_exists();

		global $wpdb;
		$table_name = CustomTable::get_table_name();

		$wpdb->query( "DELETE FROM `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$encrypted = a8csp_atlantis_encrypt_data( 'Important notice.' );
		Assert::assertIsString( $encrypted );

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'title'      => 'Test message',
				'content'    => $encrypted,
				'type'       => 'info',
				'status'     => 'active',
				'locations'  => wp_json_encode( array( 'all' ) ),
				'exclusions' => wp_json_encode( array() ),
			)
		);
		Assert::assertNotFalse( $inserted );

		$message_id = (int) $wpdb->insert_id;
		$message    = a8csp_atlantis_get_message( $message_id );
		Assert::assertInstanceOf( Message::class, $message );
		Assert::assertSame( 'Test message', $message->title );
		Assert::assertSame( 'Important notice.', $message->content );

		$active_messages = a8csp_atlantis_get_active_messages();
		Assert::assertNotEmpty( $active_messages );

		Assert::assertTrue( a8csp_atlantis_update_message_status( $message_id, 'inactive' ) );
		$updated_message = a8csp_atlantis_get_message( $message_id );
		Assert::assertInstanceOf( Message::class, $updated_message );
		Assert::assertSame( 'inactive', $updated_message->status );

		Assert::assertTrue( a8csp_atlantis_delete_message( $message_id ) );
		Assert::assertNull( a8csp_atlantis_get_message( $message_id ) );
	}

	/**
	 * Ensure Atlantis key is available for encryption/decryption in tests.
	 *
	 * @return void
	 */
	private function ensure_encryption_key_is_defined(): void {
		if ( defined( 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) ) {
			return;
		}

		define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', sodium_bin2hex( sodium_crypto_secretbox_keygen() ) );
	}

	/**
	 * Ensure custom messages table is created before assertions.
	 *
	 * @return void
	 */
	private function ensure_messages_table_exists(): void {
		if ( CustomTable::table_exists() ) {
			return;
		}

		$table = new CustomTable();
		$table->maybe_create_table();
	}
}
