<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Autoupdates;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI handlers for per-plugin autoupdate filter controls.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class PluginFilterAdminUI {

	/**
	 * Customize automatic update setting HTML for plugins page in wp-admin.
	 *
	 * @param string $html        HTML for automatic update settings.
	 * @param string $plugin_file Path to plugin file.
	 * @param array<string, mixed> $plugin_data Plugin data for the current row.
	 *
	 * @return string Customized HTML for automatic update settings.
	 */
	public function filter_custom_setting_html( $html, $plugin_file, $plugin_data = array() ): string {
		$toggle_link_html = $this->get_plugin_filter_toggle_link_html( $plugin_file );

		if ( PluginFilterRules::is_filter_disabled_for_plugin_file( $plugin_file ) ) {
			return $html . $toggle_link_html;
		}

		// Check if updates are explicitly blocked for this plugin.
		if ( function_exists( 'disable_autoupdate_specific_plugins' ) ) {
			$plugin_slug = dirname( $plugin_file );
			if ( is_array( $plugin_data ) && isset( $plugin_data['TextDomain'] ) && is_string( $plugin_data['TextDomain'] ) && '' !== $plugin_data['TextDomain'] ) {
				$plugin_slug = sanitize_key( $plugin_data['TextDomain'] );
			}

			$plugin_obj       = new \stdClass();
			$plugin_obj->slug = $plugin_slug;

			if ( ! PluginFilterRules::is_plugin_allowed_to_autoupdate( $plugin_obj ) ) {
				return esc_html__( 'Autoupdates have been explicitly deactivated for this plugin.', 'a8csp-atlantis' ) . $toggle_link_html;
			}
		}

		$managed_message = sprintf(
			/* translators: %s: plugin name in bold for the admin plugins screen. */
			__( 'Automatic updates managed by <strong>%s</strong>', 'a8csp-atlantis' ),
			'Plugin Autoupdate Filter'
		);

		return wp_kses_post( $managed_message ) . $toggle_link_html;
	}

	/**
	 * Handle requests that toggle this module's filters for a specific plugin.
	 *
	 * @return void
	 */
	public function maybe_handle_plugin_filter_toggle_request(): void {
		$toggle_request = $this->get_valid_plugin_filter_toggle_request();
		if ( ! is_array( $toggle_request ) ) {
			return;
		}

		$plugin_file = $toggle_request['plugin_file'];
		$action      = $toggle_request['action'];

		if ( ! $this->is_installed_plugin_file( $plugin_file ) ) {
			return;
		}

		$this->update_disabled_plugins_for_action( $plugin_file, $action );
		$this->redirect_after_plugin_filter_toggle( $plugin_file, $action );
	}

	/**
	 * Show an admin notice after toggling plugin filter behavior.
	 *
	 * @return void
	 */
	public function output_plugin_filter_toggle_admin_notice(): void {
		if ( ! isset( $_GET['paf_filter_toggled'], $_GET['paf_filter_plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		$action      = sanitize_key( wp_unslash( $_GET['paf_filter_toggled'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plugin_file = plugin_basename( sanitize_text_field( wp_unslash( $_GET['paf_filter_plugin'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $action, array( 'enable', 'disable' ), true ) || '' === $plugin_file ) {
			return;
		}

		if ( 'disable' === $action ) {
			$message = sprintf(
				/* translators: %s: plugin file basename. */
				__( 'Plugin Autoupdate Filter rules are now disabled for <code>%s</code>. WordPress plugin auto-update controls now apply.', 'a8csp-atlantis' ),
				esc_html( $plugin_file )
			);

			echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
			return;
		}

		$message = sprintf(
			/* translators: %s: plugin file basename. */
			__( 'Plugin Autoupdate Filter rules are now enabled for <code>%s</code>.', 'a8csp-atlantis' ),
			esc_html( $plugin_file )
		);

		echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Get the HTML for the in-column link that toggles this module's filters.
	 *
	 * @param string $plugin_file Path to plugin file.
	 *
	 * @return string
	 */
	private function get_plugin_filter_toggle_link_html( string $plugin_file ): string {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return '';
		}

		// Do not show this for the standalone plugin-autoupdate-filter plugin in the auto-update column.
		if ( 'plugin-autoupdate-filter/plugin-autoupdate-filter.php' === $plugin_file ) {
			return '';
		}

		$is_disabled = PluginFilterRules::is_filter_disabled_for_plugin_file( $plugin_file );
		$action      = $is_disabled ? 'enable' : 'disable';
		$label       = $is_disabled
			? esc_html__( 'Enable PAF updates', 'a8csp-atlantis' )
			: esc_html__( 'Disable PAF updates', 'a8csp-atlantis' );
		$url         = wp_nonce_url(
			add_query_arg(
				array(
					'paf_toggle_filter_plugin' => $plugin_file,
					'paf_filter_action'        => $action,
				),
				admin_url( 'plugins.php' )
			),
			'paf_toggle_filter_' . $plugin_file
		);

		return '<br><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Parse and validate request data for plugin filter toggle.
	 *
	 * @return array{plugin_file: string, action: string}|null
	 */
	private function get_valid_plugin_filter_toggle_request(): ?array {
		if ( ! isset( $_GET['paf_toggle_filter_plugin'], $_GET['paf_filter_action'], $_GET['_wpnonce'] ) || ! current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		$plugin_file = plugin_basename( sanitize_text_field( wp_unslash( $_GET['paf_toggle_filter_plugin'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action      = sanitize_key( wp_unslash( $_GET['paf_filter_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce       = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $action, array( 'enable', 'disable' ), true ) ) {
			return null;
		}

		if ( false === wp_verify_nonce( $nonce, 'paf_toggle_filter_' . $plugin_file ) ) {
			return null;
		}

		return array(
			'plugin_file' => $plugin_file,
			'action'      => $action,
		);
	}

	/**
	 * Check if a plugin file exists among installed plugins.
	 *
	 * @param string $plugin_file Plugin basename.
	 *
	 * @return bool
	 */
	private function is_installed_plugin_file( string $plugin_file ): bool {
		if ( ! function_exists( 'get_plugins' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();
		return isset( $installed_plugins[ $plugin_file ] );
	}

	/**
	 * Update stored per-plugin filter toggle state.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param string $action      Toggle action.
	 *
	 * @return void
	 */
	private function update_disabled_plugins_for_action( string $plugin_file, string $action ): void {
		$disabled_plugins = PluginFilterRules::get_filter_disabled_plugins();

		if ( 'disable' === $action && ! in_array( $plugin_file, $disabled_plugins, true ) ) {
			$disabled_plugins[] = $plugin_file;
		}

		if ( 'enable' === $action ) {
			$disabled_plugins = array_values(
				array_filter(
					$disabled_plugins,
					function ( $disabled_plugin_file ) use ( $plugin_file ) {
						return $disabled_plugin_file !== $plugin_file;
					}
				)
			);
		}

		update_site_option( PluginFilterRules::DISABLED_PLUGIN_FILTERS_OPTION, $disabled_plugins );
	}

	/**
	 * Redirect user after per-plugin filter toggle.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param string $action      Toggle action.
	 *
	 * @SuppressWarnings("PHPMD.ExitExpression")
	 *
	 * @return void
	 */
	private function redirect_after_plugin_filter_toggle( string $plugin_file, string $action ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'paf_filter_toggled' => $action,
					'paf_filter_plugin'  => $plugin_file,
				),
				admin_url( 'plugins.php' )
			)
		);
		exit;
	}
}
