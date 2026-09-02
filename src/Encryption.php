<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

defined( 'ABSPATH' ) || exit;

/**
 * Encryption class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Encryption {
	// region FIELDS AND CONSTANTS

	/**
	 * Transient that throttles the missing-key log entry.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @var string
	 */
	private const MISSING_KEY_LOGGED_TRANSIENT = 'a8csp_atlantis_missing_key_logged';

	// endregion

	// region METHODS

	/**
	 * Initializes the encryption module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'init', array( $this, 'maybe_auto_insert_encryption_key' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Checks if the Atlantis encryption key is defined.
	 * If not, it generates a new one and tries to insert it into wp-config.php.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @phpstan-ignore-next-line
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @phpstan-ignore-next-line
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 *
	 * @return  void
	 */
	public function maybe_auto_insert_encryption_key(): void {
		if ( a8csp_atlantis_has_encryption_key() ) {
			return;
		}

		// A key was inserted previously but the constant is gone, so wp-config.php was most
		// likely regenerated. A new key cannot recover content encrypted under the old one,
		// so alert an operator rather than silently latching.
		if ( 'yes' === get_option( 'a8csp_atlantis_inserted_encryption_key', 'no' ) ) {
			$this->add_missing_key_notice();
			return;
		}

		$encryption_key = a8csp_atlantis_generate_random_encryption_key();
		if ( is_wp_error( $encryption_key ) ) {
			add_action(
				'admin_notices',
				static function () use ( $encryption_key ) {
					$error = '<pre style="max-height: 50px; overflow: scroll;">' . $encryption_key->get_error_message() . '</pre>';
					$error = wp_sprintf(
						/* translators: 1: Plugin name, 2: Plugin version */
						__( '<strong>%1$s (version %2$s)</strong> cannot auto-generate an encryption key.', 'a8csp-atlantis' ),
						a8csp_atlantis_get_plugin_metadata( 'Name' ),
						a8csp_atlantis_get_plugin_metadata( 'Version' )
					) . $error;

					wp_admin_notice( $error, array( 'type' => 'error' ) );
				}
			);
			return;
		}

		$wp_filesystem = $this->get_wp_filesystem();

		$wp_config_path     = $this->get_wp_config_path();
		$wp_config_contents = \is_string( $wp_config_path ) ? $wp_filesystem?->get_contents( $wp_config_path ) : null;

		$success = false;
		if ( \is_string( $wp_config_path ) && \is_string( $wp_config_contents ) ) {
			$to_insert = "define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', '" . \addcslashes( $encryption_key, "\\'" ) . "' );\r\n";
			if ( \str_contains( $wp_config_contents, "/* That's all, stop editing!" ) ) {
				$wp_config_contents = \str_replace( "/* That's all, stop editing!", $to_insert . "/* That's all, stop editing!", $wp_config_contents );
			} else {
				$wp_config_contents = \preg_replace( '/<\?php/', "<?php\r\n" . $to_insert, $wp_config_contents, 1 );
			}

			if ( \is_string( $wp_config_contents ) && true === $wp_filesystem?->put_contents( $wp_config_path, $wp_config_contents, FS_CHMOD_FILE ) ) {
				$success = true;

				update_option( 'a8csp_atlantis_inserted_encryption_key', 'yes' );
				if ( function_exists( 'opcache_invalidate' ) ) {
					// Invalidate the opcode cache to ensure the new key is used immediately.
					opcache_invalidate( $wp_config_path, true );
				}
			}
		}

		if ( ! $success ) {
			add_action(
				'admin_notices',
				static function () use ( $encryption_key ) {
					$error = '<p>' . \wp_sprintf(
						/* translators: 1: Plugin name, 2: Plugin version */
						__( '<strong>%1$s (version %2$s)</strong> cannot auto-insert an encryption key. Please add the following line to your wp-config.php file:', 'a8csp-atlantis' ),
						a8csp_atlantis_get_plugin_metadata( 'Name' ),
						a8csp_atlantis_get_plugin_metadata( 'Version' )
					) . '</p>';
					$error .= '<p style="overflow: scroll">' . "<code>define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', '" . $encryption_key . "' );</code></p>";

					wp_admin_notice(
						$error,
						array(
							'type'           => 'error',
							'paragraph_wrap' => false,
						)
					);
				}
			);
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Warns that a previously inserted encryption key is no longer defined.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @return  void
	 */
	private function add_missing_key_notice(): void {
		$notice = \wp_sprintf(
			/* translators: 1: Plugin name, 2: Plugin version */
			__( '<strong>%1$s (version %2$s)</strong> inserted an encryption key previously, but it is no longer defined in wp-config.php. Stored message content cannot be read, and new messages cannot be saved, until the original key is restored.', 'a8csp-atlantis' ),
			a8csp_atlantis_get_plugin_metadata( 'Name' ),
			a8csp_atlantis_get_plugin_metadata( 'Version' )
		);

		// This runs on `init`, so it fires on every front-end, admin, AJAX, REST and cron
		// request, and a lost-key site stays lost until an operator acts. Without throttling
		// the log grows by one identical line per request.
		if ( false === get_transient( self::MISSING_KEY_LOGGED_TRANSIENT ) ) {
			error_log( wp_strip_all_tags( $notice ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			/**
			 * Filters how long to wait before logging the missing encryption key again.
			 *
			 * @since 1.3.1
			 *
			 * @param int $interval Seconds between log entries. Default 6 hours.
			 */
			$interval = (int) apply_filters( 'a8csp_atlantis_missing_key_log_interval', 6 * HOUR_IN_SECONDS );

			set_transient( self::MISSING_KEY_LOGGED_TRANSIENT, 1, $interval );
		}

		add_action(
			'admin_notices',
			static function () use ( $notice ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}

				wp_admin_notice(
					'<p>' . $notice . '</p>',
					array(
						'type'           => 'error',
						'paragraph_wrap' => false,
					)
				);
			}
		);
	}

	/**
	 * Returns the path to the wp-config.php file.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string|null
	 */
	private function get_wp_config_path(): ?string {
		$wp_filesystem = $this->get_wp_filesystem();
		if ( \is_null( $wp_filesystem ) ) {
			return null;
		}

		$candidates = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php', // typical “one level up” install
		);

		foreach ( $candidates as $local ) {
			$remote = str_replace( ABSPATH, $wp_filesystem->abspath(), $local );
			if ( $wp_filesystem->exists( $remote ) && $wp_filesystem->is_writable( $remote ) ) {
				return $remote;
			}
		}

		return null;
	}

	/**
	 * Returns the WP_Filesystem instance.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  \WP_Filesystem_Base|null
	 */
	private function get_wp_filesystem(): ?\WP_Filesystem_Base {
		global $wp_filesystem;

		if ( ! \function_exists( 'WP_Filesystem' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( 'direct' !== get_filesystem_method() ) {
			return null;
		}

		WP_Filesystem();
		return $wp_filesystem;
	}

	// endregion
}
