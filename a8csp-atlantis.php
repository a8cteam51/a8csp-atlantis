<?php
/**
 * The A8CSP Atlantis bootstrap file.
 *
 * @since       1.0.0
 * @version     1.0.5
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
 * Version:                 1.0.5
 * Requires at least:       6.8
 * Tested up to:            6.8.1
 * Requires PHP:            8.3
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

// Load the rest of the bootstrap functions.
require_once A8CSP_ATLANTIS_DIR_PATH . '/functions-bootstrap.php';
register_activation_hook( __FILE__, 'a8csp_atlantis_maybe_disable_autoupdates_module_on_activation' );

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

		$latest_release_info = wp_remote_get( 'https://api.github.com/repos/a8cteam51/a8csp-atlantis/releases/latest' );
		if ( is_wp_error( $latest_release_info ) || 200 !== wp_remote_retrieve_response_code( $latest_release_info ) ) {
			return $update;
		}

		$latest_release_info    = json_decode( wp_remote_retrieve_body( $latest_release_info ), true );
		$latest_release_version = ltrim( $latest_release_info['tag_name'], 'v' );
		if ( version_compare( $plugin_data['Version'], $latest_release_version, '<' ) ) {
			$update = array(
				'slug'    => $plugin_data['TextDomain'],
				'version' => $latest_release_version,
				'url'     => $latest_release_info['html_url'],
				'package' => $latest_release_info['assets'][0]['browser_download_url'],
			);
		} else {
			$update = false;
		}

		return $update;
	},
	10,
	3
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
	add_action( 'plugins_loaded', array( a8csp_atlantis_get_plugin_instance(), 'maybe_initialize' ) );
}
