<?php
/**
 * Uninstall routine for the A8CSP Atlantis plugin.
 *
 * Fired when the plugin is deleted through the WordPress admin
 * (Plugins -> Installed Plugins -> Delete). Removes everything the plugin
 * created: the Messages custom table and every option it owns.
 *
 * The A8CSP_ATLANTIS_ENCRYPTION_KEY define in wp-config.php is intentionally
 * not touched: rewriting wp-config.php during uninstall risks truncating the
 * site's bootstrap file, and a leftover define is inert. Remove it manually
 * if desired.
 *
 * @package A8C\SpecialProjects\Atlantis
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the plugin's data from the current site.
 *
 * Runs for the requesting site on single-site installs, and once per site
 * (via switch_to_blog) on multisite. $wpdb is re-resolved inside so the
 * switched blog's prefix and options table are used.
 *
 * @return void
 */
function a8csp_atlantis_uninstall_cleanup_single_site(): void {
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
 * Removes the custom table and every option the plugin created.
 *
 * On multisite WordPress includes uninstall.php once for the whole network
 * and never switches blog context around it, so every site is cleaned here.
 *
 * @return void
 */
function a8csp_atlantis_uninstall_cleanup(): void {
	// Network-level option written by the Autoupdates module's per-plugin filter.
	delete_site_option( 'plugin_autoupdate_filter_disabled_plugins' );

	if ( ! is_multisite() ) {
		a8csp_atlantis_uninstall_cleanup_single_site();
		return;
	}

	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		a8csp_atlantis_uninstall_cleanup_single_site();
		restore_current_blog();
	}
}

a8csp_atlantis_uninstall_cleanup();
