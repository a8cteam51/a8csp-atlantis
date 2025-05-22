<?php
/**
 * The A8CSP Atlantic bootstrap file.
 *
 * @since       1.0.0
 * @version     1.0.0
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
 * Description:             Atlantis.
 * Version:                 0.9.0
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
