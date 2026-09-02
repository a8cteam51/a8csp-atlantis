<?php
/**
 * Uninstall routine for the A8CSP Atlantis plugin.
 *
 * Fired when the plugin is deleted through the WordPress admin
 * (Plugins -> Installed Plugins -> Delete). Removes everything the plugin
 * created: the Messages custom table and every option it owns.
 *
 * The A8CSP_ATLANTIS_ENCRYPTION_KEY define is also stripped from wp-config.php
 * on a best-effort basis. Whether uninstall should rewrite wp-config.php at all
 * is the open question on the PR for https://github.com/a8cteam51/a8csp-atlantis/issues/80
 *
 * @package A8C\SpecialProjects\Atlantis
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the custom table and every option the plugin created.
 *
 * @return void
 */
function a8csp_atlantis_uninstall_cleanup(): void {
	global $wpdb;

	// Drop the custom table created by the Messages module.
	$table_name = $wpdb->prefix . 'a8csp_atlantis_messages';
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Remove every option the plugin may have created on this site.
	// Two prefixes cover Atlantis' own options and the per-module settings keys.
	$option_names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'a8csp_atlantis_' ) . '%',
			$wpdb->esc_like( 'a8csp_module_' ) . '%'
		)
	);

	foreach ( (array) $option_names as $option_name ) {
		delete_option( $option_name );
	}

	// The Autoupdates module's delay option (also used by its predecessor plugin).
	delete_option( 'plugin_update_delays' );
}

/**
 * Strips the A8CSP_ATLANTIS_ENCRYPTION_KEY define from wp-config.php.
 *
 * Best effort only: it touches a file only when that file exists, is writable
 * and actually contains the define, so a site that never auto-inserted (or
 * manually pasted) the key is never rewritten.
 *
 * @return void
 */
function a8csp_atlantis_uninstall_remove_encryption_key_define(): void {
	$wp_config_candidates = array(
		ABSPATH . 'wp-config.php',
		dirname( ABSPATH ) . '/wp-config.php', // Typical "one level up" install.
	);

	foreach ( $wp_config_candidates as $wp_config_path ) {
		if ( ! is_file( $wp_config_path ) || ! is_writable( $wp_config_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			continue;
		}

		$wp_config_contents = file_get_contents( $wp_config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $wp_config_contents || false === strpos( $wp_config_contents, 'A8CSP_ATLANTIS_ENCRYPTION_KEY' ) ) {
			continue;
		}

		$cleaned_contents = preg_replace(
			'~^[ \t]*define\(\s*[\'"]A8CSP_ATLANTIS_ENCRYPTION_KEY[\'"]\s*,.*\);\s*$~m',
			'',
			$wp_config_contents
		);

		if ( null !== $cleaned_contents && $cleaned_contents !== $wp_config_contents ) {
			file_put_contents( $wp_config_path, $cleaned_contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		break;
	}
}

a8csp_atlantis_uninstall_cleanup();
a8csp_atlantis_uninstall_remove_encryption_key_define();
