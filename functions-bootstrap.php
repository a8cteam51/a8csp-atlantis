<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's metadata.
 *
 * @template PluginMetaKey of key-of<PluginMetaData>
 *
 * @param   PluginMetaKey|null $property Optional. The property to return. Default all.
 *
 * @return  ($property is null ? PluginMetaData : ($property is PluginMetaKey ? PluginMetaData[PluginMetaKey] : null))
 */
function a8csp_atlantis_get_plugin_metadata( $property = null ) {
	static $plugin_data = null;

	$can_translate = 0 < did_action( 'init' );
	$translate_key = 0 < did_action( 'plugins_loaded' ) ? 'full' : ( $can_translate ? 'translated' : 'raw' );

	if ( ! isset( $plugin_data[ $translate_key ] ) ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file                   = trailingslashit( WP_PLUGIN_DIR ) . constant( 'A8CSP_ATLANTIS_BASENAME' );
		$plugin_data[ $translate_key ] = get_plugin_data( $plugin_file, false, $can_translate );
	}

	$metadata = $plugin_data[ $translate_key ];
	if ( null === $property ) {
		return $metadata;
	}

	if ( is_string( $property ) && isset( $metadata[ $property ] ) ) {
		return $metadata[ $property ];
	}

	return null;
}

/**
 * Returns the plugin's slug.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string
 */
function a8csp_atlantis_get_plugin_slug() {
	$text_domain = a8csp_atlantis_get_plugin_metadata( 'TextDomain' );
	return sanitize_key( $text_domain );
}

/**
 * Returns the plugin's name.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string
 */
function a8csp_atlantis_get_plugin_name() {
	return a8csp_atlantis_get_plugin_metadata( 'Name' );
}

/**
 * Returns the plugin's version.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string
 */
function a8csp_atlantis_get_plugin_version() {
	return a8csp_atlantis_get_plugin_metadata( 'Version' );
}

/**
 * Checks compatibility with the current WordPress version.
 *
 * @param   string $min_wp_version The minimum WP version required to run.
 *
 * @return  bool
 */
function a8csp_atlantis_is_wp_version_compatible( $min_wp_version ) {
	if ( ! function_exists( 'is_wp_version_compatible' ) ) {
		return false;
	}

	return is_wp_version_compatible( $min_wp_version );
}

/**
 * Checks compatibility with the current PHP version.
 *
 * @param   string $min_php_version The minimum PHP version required to run.
 *
 * @return  bool
 */
function a8csp_atlantis_is_php_version_compatible( $min_php_version ) {
	if ( ! function_exists( 'is_php_version_compatible' ) ) {
		return false;
	}

	return is_php_version_compatible( $min_php_version );
}

/**
 * Validates the plugin requirements.
 *
 * @return  true|WP_Error
 */
function a8csp_atlantis_validate_requirements() {
	$plugin_metadata = a8csp_atlantis_get_plugin_metadata();
	if ( ! isset( $plugin_metadata['RequiresPHP'] ) || '' === $plugin_metadata['RequiresPHP'] ) {
		$plugin_metadata['RequiresPHP'] = '8.2';
	}
	if ( ! isset( $plugin_metadata['RequiresWP'] ) || '' === $plugin_metadata['RequiresWP'] ) {
		$plugin_metadata['RequiresWP'] = '6.8';
	}

	$is_php_compatible = a8csp_atlantis_is_php_version_compatible( $plugin_metadata['RequiresPHP'] );
	$is_wp_compatible  = a8csp_atlantis_is_wp_version_compatible( $plugin_metadata['RequiresWP'] );

	$wp_error = new WP_Error();
	if ( ! $is_wp_compatible ) {
		$wp_error->add( 'plugin_wp_incompatible', '', array( 'requires_wp' => $plugin_metadata['RequiresWP'] ) );
	}
	if ( ! $is_php_compatible ) {
		$wp_error->add( 'plugin_php_incompatible', '', array( 'requires_php' => $plugin_metadata['RequiresPHP'] ) );
	}

	return $wp_error->has_errors() ? $wp_error : true;
}

/**
 * Outputs an error that the system requirements weren't met.
 *
 * @param   WP_Error $error The error message to display.
 *
 * @return  void
 */
function a8csp_atlantis_output_requirements_error( $error ) {
	add_action(
		'admin_notices',
		static function () use ( $error ) {
			$requirements_error = wp_sprintf(
				/* translators: 1: Plugin name, 2: Plugin version */
				__( '<strong>%1$s (version %2$s)</strong> could not be initialized.', 'a8csp-atlantis' ),
				a8csp_atlantis_get_plugin_metadata( 'Name' ),
				a8csp_atlantis_get_plugin_metadata( 'Version' )
			);

			if ( $error->has_errors() ) {
				$requirements_error .= ' ' . \__( 'Your environment does not meet all the system requirements listed below:', 'a8csp-atlantis' );
				$requirements_error .= '<ul class="ul-disc">';

				foreach ( $error->get_error_codes() as $error_code ) {
					$error_data = $error->get_error_data( $error_code );
					if ( ! is_array( $error_data ) ) {
						$error_data = array();
					}

					switch ( $error_code ) {
						case 'plugin_wp_incompatible':
							$error_message = wp_sprintf(
								/* translators: 1: Current WP version, 2: Minimum WP version */
								__( 'Current <em>WordPress version (%1$s)</em> does not meet minimum required version of %2$s.', 'a8csp-atlantis' ),
								get_bloginfo( 'version' ),
								$error_data['requires_wp']
							);
							break;
						case 'plugin_php_incompatible':
							$error_message = wp_sprintf(
								/* translators: 1: Current PHP version, 2: Minimum PHP version */
								__( 'Current <em>PHP version (%1$s)</em> does not meet minimum required version of %2$s.', 'a8csp-atlantis' ),
								PHP_VERSION,
								$error_data['requires_php']
							);
							break;
						case 'missing_autoloader':
							$error_message = __( 'The autoloader file is missing. Please run <code>composer install</code> to generate it.', 'a8csp-atlantis' );
							break;
						default:
							$error_message = $error->get_error_message( $error_code );
					}

					$requirements_error .= "<li>$error_message</li>";
				}

				$requirements_error .= '</ul>';
			}

			wp_admin_notice( $requirements_error, array( 'type' => 'error' ) );
		}
	);
}

/**
 * Checks whether the legacy Plugin Autoupdate Filter plugin is active.
 *
 * @return bool
 */
function a8csp_atlantis_is_legacy_autoupdate_filter_active(): bool {
	if ( ! function_exists( 'get_plugins' ) ) {
		/* @phpstan-ignore requireOnce.fileNotFound */
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		/* @phpstan-ignore requireOnce.fileNotFound */
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_file = 'plugin-autoupdate-filter/plugin-autoupdate-filter.php';
	$plugins     = get_plugins();

	if ( ! isset( $plugins[ $plugin_file ] ) ) {
		return false;
	}

	if ( is_plugin_active( $plugin_file ) ) {
		return true;
	}

	return is_multisite() && is_plugin_active_for_network( $plugin_file );
}

/**
 * On Atlantis activation, disable the Autoupdates module when legacy PAF is not active.
 *
 * @return void
 */
function a8csp_atlantis_maybe_disable_autoupdates_module_on_activation(): void {
	if ( ! function_exists( 'get_plugins' ) ) {
		/* @phpstan-ignore requireOnce.fileNotFound */
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_file = 'plugin-autoupdate-filter/plugin-autoupdate-filter.php';
	$plugins     = get_plugins();
	if ( ! isset( $plugins[ $plugin_file ] ) ) {
		return;
	}

	if ( a8csp_atlantis_is_legacy_autoupdate_filter_active() ) {
		return;
	}

	$option_key       = a8csp_atlantis_generate_module_settings_key( 'Autoupdates' );
	$current_settings = get_option( $option_key, array() );
	$module_settings  = is_array( $current_settings ) ? $current_settings : array();

	$module_settings['enabled'] = '0';
	update_option( $option_key, $module_settings );
}

/**
 * Returns the download URL of the zip attached to a GitHub release.
 *
 * Position in the asset list means nothing, so anything else attached to a release — a
 * checksum file, a changelog — could otherwise be installed as the plugin. The zip is
 * identified by type rather than by name, since the build names it after the repository.
 *
 * @since   1.3.1
 * @version 1.3.1
 *
 * @param   array<string, mixed> $release The decoded GitHub release.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 *
 * @return  string|null The download URL, or null when the release carries no zip.
 */
function a8csp_atlantis_get_release_package_url( array $release ): ?string {
	if ( ! isset( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
		return null;
	}

	foreach ( $release['assets'] as $asset ) {
		if ( ! is_array( $asset ) || ! is_string( $asset['browser_download_url'] ?? null ) || '' === $asset['browser_download_url'] ) {
			continue;
		}

		$name         = is_string( $asset['name'] ?? null ) ? strtolower( $asset['name'] ) : '';
		$content_type = is_string( $asset['content_type'] ?? null ) ? strtolower( $asset['content_type'] ) : '';

		if ( str_ends_with( $name, '.zip' ) || 'application/zip' === $content_type ) {
			return $asset['browser_download_url'];
		}
	}

	return null;
}

/**
 * Returns the SHA-256 GitHub published for the zip attached to a release.
 *
 * GitHub reports this as `sha256:<hex>` on the asset. Releases published before that field
 * existed carry none, so a null result means "nothing to check against", not "invalid".
 *
 * @since   1.3.1
 * @version 1.3.1
 *
 * @param   array<string, mixed> $release The decoded GitHub release.
 *
 * @phpstan-ignore-next-line
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @phpstan-ignore-next-line
 * @SuppressWarnings(PHPMD.NPathComplexity)
 *
 * @return  string|null The lowercase hex digest, or null when the release publishes none.
 */
function a8csp_atlantis_get_release_package_digest( array $release ): ?string {
	if ( ! isset( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
		return null;
	}

	foreach ( $release['assets'] as $asset ) {
		if ( ! is_array( $asset ) ) {
			continue;
		}

		$name         = is_string( $asset['name'] ?? null ) ? strtolower( $asset['name'] ) : '';
		$content_type = is_string( $asset['content_type'] ?? null ) ? strtolower( $asset['content_type'] ) : '';

		if ( ! str_ends_with( $name, '.zip' ) && 'application/zip' !== $content_type ) {
			continue;
		}

		$digest = $asset['digest'] ?? null;
		if ( ! is_string( $digest ) || ! str_starts_with( $digest, 'sha256:' ) ) {
			return null;
		}

		return strtolower( substr( $digest, strlen( 'sha256:' ) ) );
	}

	return null;
}

/**
 * Checks a downloaded update package against the digest GitHub published for it.
 *
 * @since   1.3.1
 * @version 1.3.1
 *
 * @param   string      $file   Path to the downloaded package.
 * @param   string|null $digest Expected lowercase hex SHA-256, or null if none was published.
 *
 * @return  true|WP_Error True when the package may be installed.
 */
function a8csp_atlantis_verify_package_digest( string $file, ?string $digest ) {
	if ( null === $digest ) {
		// Nothing published to check against, so this release cannot be verified either way.
		return true;
	}

	$actual = is_readable( $file ) ? hash_file( 'sha256', $file ) : false;
	if ( ! is_string( $actual ) ) {
		return new WP_Error(
			'a8csp_atlantis_package_unreadable',
			__( 'The downloaded update could not be read for verification.', 'a8csp-atlantis' )
		);
	}

	if ( ! hash_equals( $digest, $actual ) ) {
		return new WP_Error(
			'a8csp_atlantis_package_digest_mismatch',
			__( 'The downloaded update did not match the checksum published for the release, so it was not installed.', 'a8csp-atlantis' )
		);
	}

	return true;
}
