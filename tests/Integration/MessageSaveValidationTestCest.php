<?php
/**
 * Integration tests for validation on the message save handler.
 *
 * The form offers a fixed set of types in a dropdown, but a dropdown constrains nothing — the
 * handler receives whatever is posted.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\Messages\CustomTable;
use A8C\SpecialProjects\Atlantis\Modules\Messages\ListTable;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Message save validation tests.
 */
class MessageSaveValidationTestCest {
	/**
	 * The types the form actually offers.
	 *
	 * @var string[]
	 */
	private const ALLOWED_TYPES = array( 'info', 'warning', 'error', 'success' );

	/**
	 * A type outside the offered set must be refused rather than written to the table.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function save_refuses_a_type_the_form_never_offered( IntegrationTester $i ): void {
		$this->ensure_save_prerequisites();
		$user_id = $this->create_automattician();

		try {
			wp_set_current_user( $user_id );

			$died = $this->capture_wp_die(
				function (): void {
					( new ListTable() )->handle_single_actions();
				},
				array( 'type' => 'error", {}); alert("xss"); //' )
			);

			Assert::assertNotNull( $died, 'Saving an unoffered type must be refused.' );
			Assert::assertStringContainsString( 'type', strtolower( (string) $died ) );
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * The accepted set must be exactly what the form offers, or the dropdown starts producing
	 * saves the handler rejects.
	 *
	 * A successful save ends in `exit`, so the accept path cannot be driven through
	 * `handle_single_actions()` from a test without killing the process. This checks the list
	 * the handler validates against instead.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function accepted_types_match_the_types_the_form_offers( IntegrationTester $i ): void {
		Assert::assertSame( self::ALLOWED_TYPES, ListTable::ALLOWED_MESSAGE_TYPES );

		$form = (string) file_get_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			\dirname( __DIR__, 2 ) . '/templates/admin/message-form.php'
		);

		foreach ( self::ALLOWED_TYPES as $type ) {
			Assert::assertStringContainsString(
				"'" . $type . "'",
				$form,
				sprintf( 'The form should still offer the "%s" type.', $type )
			);
		}
	}

	/**
	 * Runs a callable with a populated save request, returning the `wp_die()` message if one was
	 * raised, or null if the call completed.
	 *
	 * @param callable             $callable  The code to run.
	 * @param array<string, mixed> $overrides Fields to override on the request.
	 *
	 * @return string|null
	 */
	private function capture_wp_die( callable $callable, array $overrides = array() ): ?string {
		$handler = static function (): callable {
			return static function ( $message ): void {
				throw new \RuntimeException( is_string( $message ) ? $message : 'wp_die' );
			};
		};

		add_filter( 'wp_die_handler', $handler );
		$this->populate_save_request( $overrides );

		try {
			$callable();
			return null;
		} catch ( \RuntimeException $exception ) {
			return $exception->getMessage();
		} finally {
			remove_filter( 'wp_die_handler', $handler );
			$this->clear_save_request();
		}
	}

	/**
	 * Builds a valid save request, with the given fields overridden.
	 *
	 * @param array<string, mixed> $overrides Fields to override.
	 *
	 * @return void
	 */
	private function populate_save_request( array $overrides ): void {
		$defaults = array(
			'action'                        => 'a8csp_atlantis_save_message',
			'a8csp_atlantis_message_nonce'  => wp_create_nonce( 'save_message' ),
			'id'                            => 0,
			'title'                         => 'Test notice',
			'content'                       => 'Body copy.',
			'type'                          => 'info',
			'status'                        => 'active',
			'location_include'              => array( 'all' ),
		);

		foreach ( array_merge( $defaults, $overrides ) as $key => $value ) {
			$_POST[ $key ]    = $value;
			$_REQUEST[ $key ] = $value;
		}
	}

	/**
	 * Clears the save request fields.
	 *
	 * @return void
	 */
	private function clear_save_request(): void {
		foreach ( array( 'action', 'a8csp_atlantis_message_nonce', 'id', 'title', 'content', 'type', 'status', 'location_include' ) as $key ) {
			unset( $_POST[ $key ], $_REQUEST[ $key ] );
		}
	}

	/**
	 * Saving encrypts the content and writes a row, so both need to be possible.
	 *
	 * @return void
	 */
	private function ensure_save_prerequisites(): void {
		if ( ! defined( 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) ) {
			define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', sodium_bin2hex( sodium_crypto_secretbox_keygen() ) );
		}

		if ( ! CustomTable::table_exists() ) {
			( new CustomTable() )->maybe_create_table();
		}
	}

	/**
	 * Creates an administrator on an Automattic domain.
	 *
	 * @return int
	 */
	private function create_automattician(): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'save_user_' . wp_rand( 1000, 9999 ),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'insider@a8c.com',
				'role'       => 'administrator',
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
