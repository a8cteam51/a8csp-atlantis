<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Autoupdates;

use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * AutoUpdatePluginsFilter Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class AutoUpdatePluginsFilter extends AbstractModule {
	// region FIELDS AND CONSTANTS

	/**
	 * Option key that stores plugins for which this module's filters are disabled.
	 *
	 * @var string
	 */
	private const string DISABLED_PLUGIN_FILTERS_OPTION = 'plugin_autoupdate_filter_disabled_plugins';

	/**
	 * Settings fetched from OpsOasis or default ones in case of failure.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var \stdClass
	 */
	private \stdClass $settings;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_name(): string {
		return 'Autoupdates';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_description(): string {
		return __( 'Manages the auto-update schedule of core, themes, and plugins.', 'a8csp-atlantis' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function initialize(): void {
		$plugin_filter_admin_ui = new PluginFilterAdminUI();

		// get the centralized settings from opsoasis
		try {
			$this->settings = $this->get_auto_update_settings();
		} catch ( \Exception $exception ) {
			$error_message  = $exception->getMessage();
			$this->settings = (object) array( 'disable_all' => true );

			add_action(
				'admin_notices',
				function () use ( $error_message ) {
					echo '<div class="notice notice-error"><p><strong> Plugin Autoupdate Filter:</strong> Unable to get autoupdate settings (' . esc_html( $error_message ) . ').</p></div>';
				}
			);
		}

		// setup plugins and core to autoupdate _unless_ it's during specific day/time
		add_filter( 'auto_update_plugin', array( $this, 'filter_auto_update_specific_times' ), 10, 2 );
		add_filter( 'auto_update_core', array( $this, 'filter_auto_update_specific_times' ), 10, 2 );

		// enforce a delay on all plugin autoupdates, based on release date
		add_filter( 'auto_update_plugin', array( $this, 'filter_enforce_delay' ), 11, 2 );

		// Replace automatic update wording on plugin management page in admin
		add_filter( 'plugin_auto_update_setting_html', array( $plugin_filter_admin_ui, 'filter_custom_setting_html' ), 11, 3 );

		// Append text to upgrade text on plugins page for plugins explicitly set to not autoupdate
		add_action( 'admin_init', array( $this, 'output_upgrade_message_for_specific_plugins' ) );
		add_action( 'admin_init', array( $plugin_filter_admin_ui, 'maybe_handle_plugin_filter_toggle_request' ) );
		add_action( 'admin_notices', array( $plugin_filter_admin_ui, 'output_plugin_filter_toggle_admin_notice' ) );

		// Always send auto-update emails to T51 concierge email address
		add_filter( 'auto_plugin_theme_update_email', array( $this, 'filter_custom_update_emails' ), 10, 4 );
		add_filter( 'auto_core_update_email', array( $this, 'filter_custom_update_emails' ), 10, 4 );
		add_filter( 'automatic_updates_debug_email', array( $this, 'filter_custom_debug_email' ), 10, 3 );

		// re-enable core update emails which are disabled in an mu-plugin at the Atomic platform level
		add_filter( 'automatic_updates_send_debug_email', '__return_true', 11 );
		add_filter( 'auto_core_update_send_email', '__return_true', 11 );
		add_filter( 'auto_plugin_update_send_email', '__return_true', 11 );
		add_filter( 'auto_theme_update_send_email', '__return_true', 11 );

		// "Disable all autoupdates" toggle
		add_filter( 'auto_update_plugin', array( $this, 'filter_maybe_disable_all_autoupdates' ), PHP_INT_MAX );
		add_filter( 'auto_update_core', array( $this, 'filter_maybe_disable_all_autoupdates' ), PHP_INT_MAX, 2 );
		add_filter( 'auto_update_theme', array( $this, 'filter_maybe_disable_all_autoupdates' ), PHP_INT_MAX, 2 );
		add_action( 'admin_init', array( $this, 'output_auto_updates_disabled_admin_notice' ) );

		// Clean-up delay data after a plugin is updated
		add_action( 'upgrader_process_complete', array( $this, 'cleanup_plugin_delay_after_update_complete' ), 10, 2 );
	}

	// endregion

	/**
	 * Load settings from the centralized settings page
	 *
	 * @throws  \RuntimeException If the settings cannot be loaded.
	 * @throws  \JsonException    If the settings cannot be decoded.
	 *
	 * @return  \stdClass
	 */
	private function get_auto_update_settings(): \stdClass {

		// Try getting the settings from the transient first
		$transient_key = 'wpcpmsp_auto_update_settings';
		$settings      = get_transient( $transient_key );

		if ( empty( $settings ) ) {
			$response = wp_safe_remote_get(
				'https://opsoasis.wpspecialprojects.com/wp-json/wpcomsp/autoupdate-plugin/v1/settings/',
				array( 'headers' => array( 'Accept' => 'application/json' ) )
			);

			if ( is_wp_error( $response ) ) {
				throw new \RuntimeException( wp_kses_post( $response->get_error_message() ) );
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			// Check that the response code is a 2xx code.
			if ( ! \str_starts_with( (string) $response_code, '2' ) ) {
				$response_message = wp_remote_retrieve_response_message( $response );
				throw new \RuntimeException( wp_kses_post( $response_message ), absint( $response_code ) );
			}

			try {
				$decoded_body = json_decode( $response_body, false, 512, JSON_THROW_ON_ERROR );
			} catch ( \JsonException $exception ) {
				throw $exception;
			}

			// if the settings are empty, we still need to return an object
			if ( ! is_object( $decoded_body ) ) {
				$object              = new \stdClass();
				$object->placeholder = $decoded_body;
				$decoded_body        = $object;
			}

			// Save the settings in a transient for 5 minutes
			set_transient( $transient_key, $decoded_body, 5 * MINUTE_IN_SECONDS );

			$settings = $decoded_body;
		}

		return $settings;
	}

	/**
	 * If we have hit the "Disable all autoupdates" toggle switch, or if we can't get the centralized settings, don't autoupdate anything.
	 *
	 * @param bool|null $update Whether to update the plugin or not. This can be bool or null as per the docs.
	 *
	 * @return bool True to update, false to not update.
	 */
	public function filter_maybe_disable_all_autoupdates( $update ): bool {
		if ( isset( $this->settings->disable_all ) ) {
			return false;
		}

		if ( null === $update ) {
			return false;
		}

		return $update;
	}

	/**
	 * Disable plugin auto-updates based on if a delay has passed since plugin was released.
	 *
	 * @param bool   $update Whether to update the plugin or not.
	 * @param object $item   The plugin update object.
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 *
	 * @return bool True to update, false to not update.
	 */
	public function filter_enforce_delay( $update, $item ): bool {
		// protect against non-bool being returned from this function
		if ( null === $update ) {
			$update = false;
		}

		if ( $this->is_filter_disabled_for_plugin( $item ) ) {
			return $update;
		}

		// no delay if site is a canary site
		$site_url = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( isset( $this->settings->canary_sites ) && in_array( $site_url, $this->settings->canary_sites, true ) ) {
			return $update;
		}

		// otherwise apply delay logic
		$helpers = new Helpers();

		$plugin_file        = empty( $item->plugin ) ? '' : $item->plugin;
		$plugin_slug        = empty( $item->slug ) ? '' : $item->slug;
		$plugin_new_version = empty( $item->new_version ) ? '0.0.0' : $item->new_version;

		$has_delay_passed = $helpers->has_delay_passed( $plugin_slug, $plugin_new_version, $plugin_file );

		if ( false === $has_delay_passed ) {
			$option_key = 'plugin_update_delays';
			$delays     = get_option( $option_key, array() );

			if ( isset( $delays[ $plugin_file ][ $plugin_new_version ] ) && is_numeric( $delays[ $plugin_file ][ $plugin_new_version ] ) && ( ! empty( $plugin_file ) && is_plugin_active( $plugin_file ) ) ) {
				$delay_date = $delays[ $plugin_file ][ $plugin_new_version ];

				// Get the site's date and time format settings.
				$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				// Set the $gmt parameter to true for UTC time
				$formatted_date = date_i18n( $datetime_format, $delay_date, true );

				// adds message to update notice box for that plugin on the plugins page
				add_filter(
					"in_plugin_update_message-{$plugin_file}",
					function ( $plugin_data, $response ) use ( $plugin_new_version, $formatted_date ) {
						if ( ! empty( $response->package ) ) {
							echo ' For stability, autoupdates operate on a slight delay. Autoupdate to version ' . esc_html( $plugin_new_version ) . ' is currently estimated to run after ' . esc_html( $formatted_date ) . ' UTC.';
						}
					},
					10,
					2
				);
			}
			$update = false;
		}

		return $update;
	}

	/**
	 * Disable auto-updates based on time and day of the week.
	 *
	 * @param bool   $update Whether to update the plugin or not.
	 * @param object $item   The plugin update object.
	 *
	 * @return bool True to update, false to not update.
	 */
	public function filter_auto_update_specific_times( $update, $item ): bool {
		if ( $this->is_filter_disabled_for_plugin( $item ) ) {
			return (bool) $update;
		}

		if ( $this->is_within_holiday_window() ) {
			return false;
		}

		return $this->is_within_allowed_update_window();
	}

	/**
	 * Customize auto-update email recipients.
	 *
	 * @param array  $email              Array of email data.
	 * @param string $type               Type of email to send.
	 * @param array  $successful_updates Array of successful updates.
	 * @param array  $failed_updates     Array of failed updates.
	 *
	 * @return array Array of email data with modified recipient email.
	 */
	public function filter_custom_update_emails( $email, $type, $successful_updates, $failed_updates ): array {
		$email['to'] = 'concierge@wordpress.com';
		return $email;
	}

	/**
	 * Filters the recipient email address for plugin update failure notifications.
	 *
	 * @param array $email The email details, including 'to', 'subject', 'body', 'headers'.
	 * @param int   $failures The number of failures encountered while upgrading.
	 * @param mixed $update_results The results of all attempted updates.
	 *
	 * @return array $email The email details with the 'to' address modified.
	 */
	public function filter_custom_debug_email( $email, $failures, $update_results ): array {
		$email['to'] = 'concierge@wordpress.com';
		return $email;
	}

	/**
	 * Get a normalized list of plugin files for which this module's filters are disabled.
	 *
	 * @return array<int, string>
	 */
	private function get_filter_disabled_plugins(): array {
		$disabled_plugins = get_site_option( self::DISABLED_PLUGIN_FILTERS_OPTION, array() );

		if ( ! is_array( $disabled_plugins ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_map(
					'plugin_basename',
					array_filter( $disabled_plugins, 'is_string' )
				)
			)
		);
	}

	/**
	 * Determine whether this module's filters are disabled for a plugin file.
	 *
	 * @param string $plugin_file Path to plugin file.
	 *
	 * @return bool
	 */
	private function is_filter_disabled_for_plugin_file( string $plugin_file ): bool {
		return in_array( plugin_basename( $plugin_file ), $this->get_filter_disabled_plugins(), true );
	}

	/**
	 * Determine whether this module's filters are disabled for a plugin update item.
	 *
	 * @param object $item The plugin update object.
	 *
	 * @return bool
	 */
	private function is_filter_disabled_for_plugin( $item ): bool {
		$plugin_file = empty( $item->plugin ) ? '' : plugin_basename( $item->plugin );

		if ( ! empty( $plugin_file ) ) {
			return $this->is_filter_disabled_for_plugin_file( $plugin_file );
		}

		if ( empty( $item->slug ) ) {
			return false;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $installed_plugin_file ) {
			if ( dirname( $installed_plugin_file ) === $item->slug ) {
				return $this->is_filter_disabled_for_plugin_file( $installed_plugin_file );
			}
		}

		return false;
	}

	/**
	 * Evaluate external plugin-level autoupdate rules.
	 *
	 * @param \stdClass $plugin_obj Plugin-like object containing a slug property.
	 *
	 * @return bool
	 */
	private function is_plugin_allowed_to_autoupdate( \stdClass $plugin_obj ): bool {
		if ( ! function_exists( 'disable_autoupdate_specific_plugins' ) ) {
			return true;
		}

		$callback = 'disable_autoupdate_specific_plugins';
		return (bool) $callback( true, $plugin_obj );
	}

	/**
	 * Determine whether now is within a holiday no-update window.
	 *
	 * @return bool
	 */
	private function is_within_holiday_window(): bool {
		$holidays = array(
			'christmas' => array(
				'start' => gmdate( 'Y' ) . '-12-23 00:00:00',
				'end'   => gmdate( 'Y' ) . '-12-31 23:59:59',
			),
			'new_years' => array(
				'start' => gmdate( 'Y' ) . '-01-01 00:00:00',
				'end'   => gmdate( 'Y' ) . '-01-02 23:59:59',
			),
		);
		$holidays = apply_filters( 'plugin_autoupdate_filter_holidays', $holidays ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$now = gmdate( 'Y-m-d H:i:s' );
		foreach ( $holidays as $holiday ) {
			$start = $holiday['start'];
			$end   = $holiday['end'];
			if ( $start <= $now && $now <= $end ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether current time/day is inside the update window.
	 *
	 * @return bool
	 */
	private function is_within_allowed_update_window(): bool {
		$hours = array(
			'start'      => '10', // 6am Eastern
			'end'        => '23', // 7pm Eastern
			'friday_end' => '19', // 3pm Eastern on Fridays
		);
		$hours = apply_filters( 'plugin_autoupdate_filter_hours', $hours ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$days_off = array(
			'Sat',
			'Sun',
		);
		$days_off = apply_filters( 'plugin_autoupdate_filter_days_off', $days_off ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$hour = gmdate( 'H' );
		$day  = gmdate( 'D' );

		if ( $hour < $hours['start'] || $hour > $hours['end'] ) {
			return false;
		}

		if ( in_array( $day, $days_off, true ) ) {
			return false;
		}

		if ( 'Fri' === $day && $hour > $hours['friday_end'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Append text to upgrade text on plugins page for plugins explicitly set to not autoupdate
	 */
	public function output_upgrade_message_for_specific_plugins(): void {
		// check if updates are explicitly blocked for this plugin
		// don't show if we are already disabling all updates
		if ( ! function_exists( 'disable_autoupdate_specific_plugins' ) || isset( $this->settings->disable_all ) ) {
			return;
		}

		$all_plugins = get_plugins();

		foreach ( array_keys( $all_plugins ) as $plugin_file ) {
			// create a fake object to feed to disable_autoupdate_specific_plugins
			$plugin_obj        = new \stdClass();
			$slug              = dirname( $plugin_file );
			$plugin_obj->slug  = $slug;
			$plugin_can_update = $this->is_plugin_allowed_to_autoupdate( $plugin_obj );
			if ( false === $plugin_can_update ) {
				// add notice next to the "update now" link
				add_filter(
					"in_plugin_update_message-{$plugin_file}",
					function () {
						echo ' <strong style="color:red;"> Caution:</strong> Autoupdates have been explicitly deactivated for this plugin. Please contact the WordPress Special Projects team before manually updating.';
					},
					10,
					2
				);
				// add notice to the top of the screen
				global $pagenow;
				if ( 'plugins.php' === $pagenow ) {
					add_action(
						'admin_notices',
						function () use ( $slug ) {
							echo '<div class="notice notice-error"><p><strong style="color:red;"> Caution:</strong> Autoupdates have been explicitly deactivated for ', esc_html( $slug ), '. Please contact the WordPress Special Projects team before manually updating.</p></div>';
						}
					);
				}
			}
		}
	}

	/**
	 * Autoupdates disabled admin notice
	 */
	public function output_auto_updates_disabled_admin_notice(): void {
		// add notice to the top of the screen
		global $pagenow;
		if ( 'plugins.php' === $pagenow && isset( $this->settings->disable_all ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><strong style="color:red;"> Caution:</strong> All automatic updates are deactivated. Please contact the WordPress Special Projects team before manually updating plugins.</p></div>';
				}
			);
		}
	}

	/**
	 * Executes after a plugin has been updated.
	 * Cleanup plugin delay data after update is complete.
	 *
	 * @param object $upgrader_object WP_Upgrader instance.
	 * @param array  $options         Array of bulk item update data.
	 */
	public function cleanup_plugin_delay_after_update_complete( $upgrader_object, $options ) {
		// Check if this is a plugin update.
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			if ( isset( $options['plugins'] ) ) {
				$helpers = new Helpers();
				foreach ( $options['plugins'] as $plugin ) {
					$helpers->clear_plugin_delay( $plugin );
				}
			}
		}
	}
}
