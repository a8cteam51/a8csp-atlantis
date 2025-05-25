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
		if ( a8csp_atlantis_has_encryption_key() || 'yes' === get_option( 'a8csp_atlantis_inserted_encryption_key', 'no' ) ) {
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
		$wp_config_contents = $wp_config_path ? $wp_filesystem?->get_contents( $wp_config_path ) : null;

		$success = false;
		if ( $wp_config_path && $wp_config_contents ) {
			$to_insert = "define( 'A8CSP_ATLANTIS_ENCRYPTION_KEY', '" . \addcslashes( $encryption_key, "\\'" ) . "' );\r\n";
			if ( \str_contains( $wp_config_contents, "/* That's all, stop editing!" ) ) {
				$wp_config_contents = \str_replace( "/* That's all, stop editing!", $to_insert . "/* That's all, stop editing!", $wp_config_contents );
			} else {
				$wp_config_contents = \preg_replace( '/<\?php/', "<?php\r\n" . $to_insert, $wp_config_contents, 1 );
			}

			if ( $wp_config_contents && $wp_filesystem?->put_contents( $wp_config_path, $wp_config_contents, FS_CHMOD_FILE ) ) {
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
