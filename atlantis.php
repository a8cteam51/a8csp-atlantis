<?php
/**
 * The atlantis bootstrap file.
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
 * Plugin Name:             atlantis
 * Plugin URI:              https://wpspecialprojects.wordpress.com
 * Description:             
 * Version:                 1.0.0
 * Requires at least:       6.7
 * Tested up to:            6.7
 * Requires PHP:            8.3
 * Author:                  WordPress.com Special Projects
 * Author URI:              https://wpspecialprojects.wordpress.com
 * License:                 GPL v3 or later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             atlantis
 * Domain Path:             /languages
 * WC requires at least:    9.5
 * WC tested up to:         9.5
 **/

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'ATLANTIS_BASENAME', plugin_basename( __FILE__ ) );
define( 'ATLANTIS_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'ATLANTIS_DIR_URL', plugin_dir_url( __FILE__ ) );

// Load the rest of the bootstrap functions.
require_once ATLANTIS_DIR_PATH . '/functions-bootstrap.php';

// Load plugin translations so they are available even for the error admin notices.
add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			atlantis_get_plugin_metadata( 'TextDomain' ),
			false,
			dirname( ATLANTIS_BASENAME ) . atlantis_get_plugin_metadata( 'DomainPath' )
		);
	}
);

// Declare compatibility with WC features.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Load the autoloader.
if ( ! is_file( ATLANTIS_DIR_PATH . '/vendor/autoload.php' ) ) {
	atlantis_output_requirements_error( new WP_Error( 'missing_autoloader' ) );
	return;
}
require_once ATLANTIS_DIR_PATH . '/vendor/autoload.php';

// Bootstrap the plugin (maybe)!
define( 'ATLANTIS_REQUIREMENTS', atlantis_validate_requirements() );
if ( is_wp_error( ATLANTIS_REQUIREMENTS ) ) {
	atlantis_output_requirements_error( ATLANTIS_REQUIREMENTS );
} else {
	require_once ATLANTIS_DIR_PATH . '/functions.php';
	add_action( 'plugins_loaded', array( atlantis_get_plugin_instance(), 'maybe_initialize' ) );
}
