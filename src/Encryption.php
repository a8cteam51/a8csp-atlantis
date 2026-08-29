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
		if ( a8csp_atlantis_has_encryption_key() || 'yes' === $this->get_inserted_encryption_key_flag() ) {
			return;
		}

		// On multisite, wp-config.php is a single file shared by the entire
		// network. Only the main site writes to it, so subsites don't each
		// append their own define() into the shared file (which produces
		// "Constant ... already defined" warnings). The flag above is stored
		// network-wide on multisite, so once the main site inserts the key
		// every subsite short-circuits at the check above.
		if ( is_multisite() && ! is_main_site() ) {
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
			// Idempotency guard: if the file already contains our define, don't
			// append another. This covers the window where a prior request wrote
			// the line but the constant isn't defined yet in the current runtime
			// (stale include/opcache), or a race across concurrent requests. Just
			// record the flag and bail rather than writing a duplicate.
			if ( \str_contains( $wp_config_contents, 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) ) {
				$this->mark_encryption_key_inserted();
				return;
			}

			// Wrap the define in a defined() guard so that even if a duplicate
			// line ever ends up in the file (e.g. a platform-managed config
			// re-merge), PHP won't emit an "already defined" warning.
			$to_insert = "if ( ! defined( 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) ) { define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', '" . \addcslashes( $encryption_key, "\\'" ) . "' ); }\r\n";

			// Insert before the FIRST "stop editing" marker only. Some configs
			// (notably multisite installs) carry more than one such marker; a
			// naive str_replace() would write the define before every marker and
			// create the exact duplicate-define warnings this method guards
			// against. substr_replace() with a zero-length span inserts at the
			// first match without touching the rest.
			$marker    = "/* That's all, stop editing!";
			$marker_at = \strpos( $wp_config_contents, $marker );
			if ( false !== $marker_at ) {
				$wp_config_contents = \substr_replace( $wp_config_contents, $to_insert, $marker_at, 0 );
			} else {
				$wp_config_contents = \preg_replace( '/<\?php/', "<?php\r\n" . $to_insert, $wp_config_contents, 1 );
			}

			if ( \is_string( $wp_config_contents ) && true === $wp_filesystem?->put_contents( $wp_config_path, $wp_config_contents, FS_CHMOD_FILE ) ) {
				$success = true;

				$this->mark_encryption_key_inserted();
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
					$error .= '<p style="overflow: scroll">' . "<code>if ( ! defined( 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) ) { define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', '" . $encryption_key . "' ); }</code></p>";

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
	 * Returns whether the encryption key has already been inserted into
	 * wp-config.php.
	 *
	 * On multisite the flag is stored network-wide because wp-config.php is a
	 * single file shared by every site in the network — tracking it per-site
	 * would let each subsite conclude it still needs to insert the key.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @return  string
	 */
	private function get_inserted_encryption_key_flag(): string {
		return is_multisite()
			? (string) get_site_option( 'a8csp_atlantis_inserted_encryption_key', 'no' )
			: (string) get_option( 'a8csp_atlantis_inserted_encryption_key', 'no' );
	}

	/**
	 * Records that the encryption key has been inserted into wp-config.php.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @return  void
	 */
	private function mark_encryption_key_inserted(): void {
		if ( is_multisite() ) {
			update_site_option( 'a8csp_atlantis_inserted_encryption_key', 'yes' );
		} else {
			update_option( 'a8csp_atlantis_inserted_encryption_key', 'yes' );
		}
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
