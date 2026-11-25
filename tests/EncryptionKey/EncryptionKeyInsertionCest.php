<?php
/**
 * Integration tests for writing the encryption key into wp-config.php.
 *
 * Every test writes to a throwaway copy of a config file, never the real wp-config.php, and
 * nothing here may define A8CSP_ATLANTIS_ENCRYPTION_KEY.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Encryption;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Encryption key insertion tests.
 */
class EncryptionKeyInsertionCest {
	/**
	 * A config file shaped like the one core ships.
	 */
	private const CONFIG = "<?php\ndefine( 'DB_NAME', 'wordpress' );\n\n/* That's all, stop editing! Happy publishing. */\nrequire_once ABSPATH . 'wp-settings.php';\n";

	/**
	 * Path of the throwaway config file.
	 *
	 * @var string
	 */
	private string $config_path = '';

	/**
	 * The administrator created for the test, if any.
	 *
	 * @var int
	 */
	private int $user_id = 0;

	/**
	 * Filter callback forcing direct file access.
	 *
	 * @var \Closure
	 */
	private \Closure $direct_filesystem_method;

	/**
	 * Starts each test with no inserted-key flag and a fresh config file.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		delete_option( 'a8csp_atlantis_inserted_encryption_key' );
		// Direct file access is the only method the key is ever written with.
		$this->direct_filesystem_method = static fn() => 'direct';
		add_filter( 'filesystem_method', $this->direct_filesystem_method );

		$this->config_path = get_temp_dir() . 'a8csp-atlantis-wp-config-' . wp_generate_password( 8, false ) . '.php';
		file_put_contents( $this->config_path, self::CONFIG ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Removes everything the test created.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		delete_option( 'a8csp_atlantis_inserted_encryption_key' );
		remove_filter( 'filesystem_method', $this->direct_filesystem_method );
		wp_delete_file( $this->config_path );

		wp_set_current_user( 0 );
		if ( 0 !== $this->user_id ) {
			if ( ! function_exists( 'wp_delete_user' ) ) {
				/* @phpstan-ignore requireOnce.fileNotFound */
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			wp_delete_user( $this->user_id );
			$this->user_id = 0;
		}
	}

	/**
	 * The ordinary case still works: an administrator's request writes the key once and
	 * records it.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function administrator_request_inserts_the_key( IntegrationTester $i ): void {
		$this->log_in_administrator();

		$this->encryption()->maybe_auto_insert_encryption_key();

		$contents = $this->read_config();
		Assert::assertSame( 1, substr_count( $contents, "define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY'" ) );
		Assert::assertLessThan( strpos( $contents, "/* That's all, stop editing!" ), strpos( $contents, 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) );
		Assert::assertSame( 'yes', get_option( 'a8csp_atlantis_inserted_encryption_key' ) );
	}

	/**
	 * An anonymous request must never rewrite wp-config.php.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function anonymous_request_leaves_the_file_alone( IntegrationTester $i ): void {
		wp_set_current_user( 0 );

		$this->encryption()->maybe_auto_insert_encryption_key();

		Assert::assertSame( self::CONFIG, $this->read_config() );
		Assert::assertFalse( get_option( 'a8csp_atlantis_inserted_encryption_key' ) );
	}

	/**
	 * A file read while something else was rewriting it comes back empty. Writing that back
	 * with the key added would leave the site without a configuration.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function empty_read_is_never_written_back( IntegrationTester $i ): void {
		$this->log_in_administrator();
		file_put_contents( $this->config_path, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->encryption()->maybe_auto_insert_encryption_key();

		Assert::assertSame( '', $this->read_config() );
		Assert::assertFalse(
			get_option( 'a8csp_atlantis_inserted_encryption_key' ),
			'The key was not written, so it must not be recorded as inserted.'
		);
	}

	/**
	 * While another request holds the lock, this one must leave the file to it.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function held_lock_leaves_the_file_alone( IntegrationTester $i ): void {
		$this->log_in_administrator();

		if ( ! class_exists( 'WP_Upgrader' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		Assert::assertTrue( WP_Upgrader::create_lock( 'a8csp_atlantis_insert_encryption_key', MINUTE_IN_SECONDS ) );

		try {
			$this->encryption()->maybe_auto_insert_encryption_key();
		} finally {
			WP_Upgrader::release_lock( 'a8csp_atlantis_insert_encryption_key' );
		}

		Assert::assertSame( self::CONFIG, $this->read_config() );
		Assert::assertFalse( get_option( 'a8csp_atlantis_inserted_encryption_key' ) );
	}

	/**
	 * The lock is released once the key is written, so it never blocks a later attempt.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function lock_is_released_after_inserting( IntegrationTester $i ): void {
		$this->log_in_administrator();

		$this->encryption()->maybe_auto_insert_encryption_key();

		Assert::assertFalse( get_option( 'a8csp_atlantis_insert_encryption_key.lock' ) );
	}

	/**
	 * Returns an Encryption instance that writes to the throwaway config file.
	 *
	 * @return Encryption
	 */
	private function encryption(): Encryption {
		return new class( $this->config_path ) extends Encryption {
			/**
			 * Constructor.
			 *
			 * @param string $path Path of the config file to write.
			 */
			public function __construct( private string $path ) {}

			/**
			 * {@inheritDoc}
			 */
			protected function get_wp_config_path(): ?string {
				return $this->path;
			}
		};
	}

	/**
	 * Creates an administrator and makes them the current user.
	 *
	 * @return void
	 */
	private function log_in_administrator(): void {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'key_admin_' . wp_rand( 1000, 9999 ),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'key-admin@example.com',
				'role'       => 'administrator',
			)
		);
		Assert::assertIsInt( $user_id, 'Test precondition: the administrator could not be created.' );

		$this->user_id = $user_id;
		wp_set_current_user( $user_id );
	}

	/**
	 * Returns the throwaway config file's contents.
	 *
	 * @return string
	 */
	private function read_config(): string {
		clearstatcache();

		return (string) file_get_contents( $this->config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
