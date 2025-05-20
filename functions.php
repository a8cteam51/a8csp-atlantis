<?php declare( strict_types=1 );

use A8C\SpecialProjects\atlantis\Plugin;

defined( 'ABSPATH' ) || exit;

// region META

/**
 * Returns the plugin's main class instance.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  Plugin
 */
function atlantis_get_plugin_instance(): Plugin {
	return Plugin::get_instance();
}

// endregion

// region OTHERS

$atlantis_files = glob( constant( 'ATLANTIS_DIR_PATH' ) . 'includes/*.php' );
if ( false !== $atlantis_files ) {
	foreach ( $atlantis_files as $atlantis_file ) {
		if ( 1 === preg_match( '#/includes/_#i', $atlantis_file ) ) {
			continue; // Ignore files prefixed with an underscore.
		}

		require_once $atlantis_file;
	}
}

// endregion
