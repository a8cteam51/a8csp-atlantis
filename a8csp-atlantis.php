<?php
/**
 * The A8CSP Atlantis bootstrap file.
 *
 * @since       1.0.0
 * @version     1.3.0
 * @package     A8C\SpecialProjects\Plugins
 * @author      WordPress.com Special Projects
 * @license     GPL-3.0-or-later
 *
 * @noinspection    ALL
 *
 * @wordpress-plugin
 * Plugin Name:             A8CSP Atlantis
 * Plugin URI:              https://github.com/a8cteam51/a8csp-atlantis
 * Update URI:              https://github.com/a8cteam51/a8csp-atlantis
 * Description:             Centralized site management for Team51.
 * Version:                 1.3.0
 * Requires at least:       6.8
 * Tested up to:            7.0
 * Requires PHP:            8.2
 * Author:                  Automattic Special Projects
 * Author URI:              https://specialprojects.automattic.com
 * License:                 GPL v3 or later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             a8csp-atlantis
 * Domain Path:             /languages
 **/

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'A8CSP_ATLANTIS_BASENAME', plugin_basename( __FILE__ ) );
define( 'A8CSP_ATLANTIS_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'A8CSP_ATLANTIS_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY', 'a8csp_atlantis_github_latest_release' );

// Load the rest of the bootstrap functions.
require_once A8CSP_ATLANTIS_DIR_PATH . '/functions-bootstrap.php';

// Load plugin translations so they are available even for the error admin notices.
add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			a8csp_atlantis_get_plugin_metadata( 'TextDomain' ),
			false,
			dirname( A8CSP_ATLANTIS_BASENAME ) . a8csp_atlantis_get_plugin_metadata( 'DomainPath' )
		);
	}
);

// Instruct WordPress to fetch update information from GitHub.
add_action(
	'update_plugins_github.com',
	static function ( $update, array $plugin_data, string $plugin_file ) {
		if ( A8CSP_ATLANTIS_BASENAME !== $plugin_file || false !== $update ) {
			return $update;
		}

		$latest_release_info = get_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );
		if (
			is_array( $latest_release_info ) &&
			isset( $latest_release_info['tag_name'], $latest_release_info['html_url'] ) &&
			null !== a8csp_atlantis_get_release_package_url( $latest_release_info )
		) {
			$latest_release_version = ltrim( $latest_release_info['tag_name'], 'v' );
		} elseif ( false === $latest_release_info ) {
			$latest_release_info = wp_safe_remote_get( 'https://api.github.com/repos/a8cteam51/a8csp-atlantis/releases/latest' );
			if ( is_wp_error( $latest_release_info ) || 200 !== wp_remote_retrieve_response_code( $latest_release_info ) ) {
				set_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY, array(), 5 * MINUTE_IN_SECONDS );
				return $update;
			}

			$latest_release_info = json_decode( wp_remote_retrieve_body( $latest_release_info ), true );
			if (
				! is_array( $latest_release_info ) ||
				! isset( $latest_release_info['tag_name'], $latest_release_info['html_url'] ) ||
				null === a8csp_atlantis_get_release_package_url( $latest_release_info )
			) {
				set_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY, array(), 5 * MINUTE_IN_SECONDS );
				return $update;
			}

			set_transient(
				A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY,
				$latest_release_info,
				HOUR_IN_SECONDS
			);
			$latest_release_version = ltrim( $latest_release_info['tag_name'], 'v' );
		} else {
			return $update;
		}

		if ( version_compare( $plugin_data['Version'], $latest_release_version, '<' ) ) {
			$update = array(
				'slug'    => $plugin_data['TextDomain'],
				'version' => $latest_release_version,
				'url'     => $latest_release_info['html_url'],
				'package' => a8csp_atlantis_get_release_package_url( $latest_release_info ),
			);
		} else {
			$update = false;
		}

		return $update;
	},
	10,
	3
);

// Verify the downloaded package against the checksum GitHub publishes for the release. The
// update check is cached for an hour, so the asset could otherwise be replaced between the
// check and the install.
add_filter(
	'upgrader_pre_download',
	static function ( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( ! is_array( $hook_extra ) || A8CSP_ATLANTIS_BASENAME !== ( $hook_extra['plugin'] ?? '' ) ) {
			return $reply;
		}

		$latest_release_info = get_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );
		if ( ! is_array( $latest_release_info ) ) {
			return $reply;
		}

		$digest = a8csp_atlantis_get_release_package_digest( $latest_release_info );
		if ( null === $digest ) {
			// The release predates GitHub publishing digests, so there is nothing to check.
			return $reply;
		}

		$file = download_url( $package );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$verified = a8csp_atlantis_verify_package_digest( $file, $digest );
		if ( is_wp_error( $verified ) ) {
			wp_delete_file( $file );
			return $verified;
		}

		return $file;
	},
	10,
	4
);

// Load the autoloader.
if ( ! is_file( A8CSP_ATLANTIS_DIR_PATH . '/vendor/autoload.php' ) ) {
	a8csp_atlantis_output_requirements_error( new WP_Error( 'missing_autoloader' ) );
	return;
}
require_once A8CSP_ATLANTIS_DIR_PATH . '/vendor/autoload.php';

// Bootstrap the plugin (maybe)!
define( 'A8CSP_ATLANTIS_REQUIREMENTS', a8csp_atlantis_validate_requirements() );
if ( is_wp_error( A8CSP_ATLANTIS_REQUIREMENTS ) ) {
	a8csp_atlantis_output_requirements_error( A8CSP_ATLANTIS_REQUIREMENTS );
} else {
	require_once A8CSP_ATLANTIS_DIR_PATH . '/functions.php';
	register_activation_hook( __FILE__, 'a8csp_atlantis_maybe_disable_autoupdates_module_on_activation' );
	add_action( 'plugins_loaded', array( a8csp_atlantis_get_plugin_instance(), 'maybe_initialize' ) );
}
