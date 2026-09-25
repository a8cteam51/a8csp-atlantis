<?php
/**
 * Integration tests for how message notifications are emitted into the block editor.
 *
 * The block editor branch builds an inline script, so anything interpolated into it is
 * executable unless it is encoded.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Message;
use A8C\SpecialProjects\Atlantis\Modules\Messages\Notifications;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Exposes the block editor branch and lets a test supply its own messages.
 */
class NotificationsTestDouble extends Notifications {
	/**
	 * Messages to emit.
	 *
	 * @var Message[]
	 */
	public array $messages = array();

	/**
	 * Whether to take the block editor branch or the classic notice one.
	 *
	 * @var bool
	 */
	public bool $block_editor = true;

	/**
	 * {@inheritDoc}
	 */
	protected function is_block_editor(): bool {
		return $this->block_editor;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_active_messages(): array {
		return $this->messages;
	}
}

/**
 * Message notification escaping tests.
 */
class MessageNotificationEscapingTestCest {
	/**
	 * A `type` that closes the JavaScript string literal and appends its own statement.
	 *
	 * @var string
	 */
	private const MALICIOUS_TYPE = 'error", {}); alert("xss"); //';

	/**
	 * A `type` that closes the notice's class attribute and opens a script tag.
	 *
	 * @var string
	 */
	private const MALICIOUS_CLASS_TYPE = 'error"><script>alert("xss")</script><div class="';

	/**
	 * A message `type` must not be able to break out of the string literal it is placed in.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function crafted_message_type_cannot_break_out_of_the_inline_script( IntegrationTester $i ): void {
		$user_id = $this->create_automattician();

		try {
			wp_set_current_user( $user_id );
			Assert::assertTrue( a8csp_atlantis_is_automattician(), 'Test precondition: notices only render for Automatticians.' );

			$script = $this->render_notice( self::MALICIOUS_TYPE );

			Assert::assertStringNotContainsString(
				'alert("xss");',
				$script,
				'The crafted type escaped its string literal and became executable JavaScript.'
			);

			Assert::assertStringContainsString(
				'createNotice("info"',
				$script,
				'A type outside the allow-list should be emitted as "info", not passed through.'
			);
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * A stored type outside the allow-list must not reach the classic notice's class attribute, which core does not escape.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function crafted_message_type_cannot_break_out_of_the_classic_notice( IntegrationTester $i ): void {
		$user_id = $this->create_automattician();

		try {
			wp_set_current_user( $user_id );

			$markup = $this->render_classic_notice( self::MALICIOUS_CLASS_TYPE );

			Assert::assertStringNotContainsString(
				'<script>',
				$markup,
				'The crafted type escaped the class attribute and became markup.'
			);

			Assert::assertStringContainsString( 'notice-info', $markup, 'A type outside the allow-list should fall back to "info".' );
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * Allowed markup in the content must be emitted into the inline script with its angle brackets encoded, so the script cannot depend on kses to keep `</script>` out.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function message_content_angle_brackets_are_encoded_in_the_inline_script( IntegrationTester $i ): void {
		$user_id = $this->create_automattician();

		try {
			wp_set_current_user( $user_id );

			// The model decrypts content, so the test stores it the way a save would.
			if ( ! defined( 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) ) {
				define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', sodium_bin2hex( sodium_crypto_secretbox_keygen() ) );
			}

			$encrypted = a8csp_atlantis_encrypt_data( '<strong>Bold</strong>' );
			Assert::assertIsString( $encrypted, 'Test precondition: the content could not be encrypted.' );

			$script = $this->render_notice( 'info', $encrypted );

			Assert::assertStringContainsString(
				trim( (string) wp_json_encode( '<strong>Bold', JSON_HEX_TAG ), '"' ),
				$script,
				'The content should be present with its tags encoded.'
			);
			Assert::assertStringNotContainsString( '<', $script, 'No raw angle bracket may reach the inline script.' );
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * An ordinary type must still reach the editor unchanged in meaning.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function ordinary_message_type_is_still_emitted( IntegrationTester $i ): void {
		$user_id = $this->create_automattician();

		try {
			wp_set_current_user( $user_id );

			$script = $this->render_notice( 'error' );

			Assert::assertStringContainsString( 'createNotice("error"', $script );
		} finally {
			$this->cleanup( $user_id );
		}
	}

	/**
	 * Emits one message through the block editor branch and returns the inline script.
	 *
	 * @param string $type The message type to emit.
	 *
	 * @return string
	 */
	private function render_notice( string $type, string $content = '' ): string {
		if ( ! wp_script_is( 'wp-edit-post', 'registered' ) ) {
			wp_register_script( 'wp-edit-post', '', array(), '1.0.0', true );
		}

		// Start from a clean slate so a previous test's script is not read back.
		wp_scripts()->add_data( 'wp-edit-post', 'after', array() );

		$notifications           = new NotificationsTestDouble();
		$notifications->messages = array( $this->build_message( $type, $content ) );
		$notifications->output_messages();

		$data = wp_scripts()->get_data( 'wp-edit-post', 'after' );

		return is_array( $data ) ? implode( "\n", array_filter( $data, 'is_string' ) ) : (string) $data;
	}

	/**
	 * Emits one message through the classic notice branch and returns the markup it printed.
	 *
	 * @param string $type The message type to emit.
	 *
	 * @return string
	 */
	private function render_classic_notice( string $type ): string {
		$notifications               = new NotificationsTestDouble();
		$notifications->block_editor = false;
		$notifications->messages     = array( $this->build_message( $type ) );

		ob_start();
		$notifications->output_messages();

		return (string) ob_get_clean();
	}

	/**
	 * Builds a message with the given type.
	 *
	 * @param string $type    The message type.
	 * @param string $content The message content.
	 *
	 * @return Message
	 */
	private function build_message( string $type, string $content = '' ): Message {
		return new Message(
			(object) array(
				'id'         => 1,
				'title'      => 'Notice',
				'type'       => $type,
				'status'     => 'active',
				'content'    => $content,
				'locations'  => wp_json_encode( array( 'all' ) ),
				'exclusions' => wp_json_encode( array() ),
			)
		);
	}

	/**
	 * Creates an administrator on an Automattic domain.
	 *
	 * @return int
	 */
	private function create_automattician(): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'notice_user_' . wp_rand( 1000, 9999 ),
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
