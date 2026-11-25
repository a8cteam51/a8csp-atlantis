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
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class AutoUpdatePluginsFilter extends AbstractModule {
	// region FIELDS AND CONSTANTS

	/**
	 * Settings fetched from the centralized endpoint, or default ones when none is configured or the fetch fails.
	 * Null until something first needs them; see {@see self::get_settings()}.
	 *
	 * @since   1.0.0
	 * @version 1.5.2
	 *
	 * @var \stdClass|null
	 */
	private ?\stdClass $settings = null;

	/**
	 * Option holding the last payload successfully fetched from the centralized endpoint.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @var string
	 */
	private const LAST_KNOWN_GOOD_OPTION = 'a8csp_atlantis_autoupdate_last_good_settings';

	/**
	 * Transient caching the settings, or the fail-safe, for five minutes.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	private const SETTINGS_TRANSIENT = 'wpcpmsp_auto_update_settings';

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
	 * Watches the settings endpoint option before anything else, and whether or not the module
	 * goes on to initialize: the option can be changed in any request and while the module is
	 * switched off, and what was cached from the previous endpoint must not be there when the
	 * module next reads its settings.
	 *
	 * @since   1.0.0
	 * @version 1.5.0
	 */
	public function maybe_initialize(): void {
		add_action( 'add_option_' . A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL_OPTION, array( self::class, 'flush_settings_cache' ), 10, 0 );
		add_action( 'delete_option_' . A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL_OPTION, array( self::class, 'flush_settings_cache' ), 10, 0 );
		add_action( 'update_option_' . A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL_OPTION, array( self::class, 'maybe_flush_settings_cache' ), 10, 2 );

		parent::maybe_initialize();
	}

	/**
	 * {@inheritDoc}
	 *
	 * The update filters are registered on every request, not only in admin, WP-Cron and WP-CLI
	 * ones. Core consults them wherever an auto-update runs, and that is not always a request
	 * that looked like one when the module loaded: with `ALTERNATE_WP_CRON`, cron runs inside an
	 * ordinary page view, after `plugins_loaded`. The centralized settings are fetched the first
	 * time a filter needs them, so a front-end request that runs no update never fetches them.
	 *
	 * @since   1.0.0
	 * @version 1.5.2
	 */
	protected function initialize(): void {
		// setup plugins and core to autoupdate _unless_ it's during specific day/time
		add_filter( 'auto_update_plugin', array( $this, 'filter_auto_update_specific_times' ), 10, 2 );
		add_filter( 'auto_update_core', array( $this, 'filter_auto_update_core' ), 10, 2 );

		// enforce a delay on all plugin autoupdates, based on release date
		add_filter( 'auto_update_plugin', array( $this, 'filter_enforce_delay' ), 11, 2 );

		// Route update emails to the recipient the centralized settings name. Each callback leaves
		// the email alone when they name none: who receives update emails and whether they are sent
		// at all is then left to core and the host.
		add_filter( 'auto_plugin_theme_update_email', array( $this, 'filter_custom_update_emails' ), 10, 4 );
		add_filter( 'auto_core_update_email', array( $this, 'filter_custom_update_emails' ), 10, 4 );
		add_filter( 'automatic_updates_debug_email', array( $this, 'filter_custom_debug_email' ), 10, 3 );
		add_filter( 'send_core_update_notification_email', array( $this, 'filter_core_update_notification_email' ), 10, 2 );

		// re-enable core update emails which are disabled in an mu-plugin at the Atomic platform level
		add_filter( 'automatic_updates_send_debug_email', array( $this, 'filter_send_update_email' ), 11 );
		add_filter( 'auto_core_update_send_email', array( $this, 'filter_send_update_email' ), 11 );
		add_filter( 'auto_plugin_update_send_email', array( $this, 'filter_send_update_email' ), 11 );
		add_filter( 'auto_theme_update_send_email', array( $this, 'filter_send_update_email' ), 11 );

		// "Disable all autoupdates" toggle
		add_filter( 'auto_update_plugin', array( $this, 'filter_maybe_disable_all_autoupdates' ), PHP_INT_MAX, 2 );
		add_filter( 'auto_update_core', array( $this, 'filter_maybe_disable_all_autoupdates' ), PHP_INT_MAX, 2 );
		add_filter( 'auto_update_theme', array( $this, 'filter_maybe_disable_all_autoupdates' ), PHP_INT_MAX, 2 );

		// Clean-up delay data after a plugin is updated
		add_action( 'upgrader_process_complete', array( $this, 'cleanup_plugin_delay_after_update_complete' ), 10, 2 );

		if ( ! is_admin() ) {
			return;
		}

		$plugin_filter_admin_ui = new PluginFilterAdminUI( $this->get_settings() );

		// Replace automatic update wording on plugin management page in admin
		add_filter( 'plugin_auto_update_setting_html', array( $plugin_filter_admin_ui, 'filter_custom_setting_html' ), 11, 3 );

		// Append text to upgrade text on plugins page for plugins explicitly set to not autoupdate
		add_action( 'admin_init', array( $this, 'output_upgrade_message_for_specific_plugins' ) );
		add_action( 'admin_init', array( $plugin_filter_admin_ui, 'maybe_handle_plugin_filter_toggle_request' ) );
		add_action( 'admin_notices', array( $plugin_filter_admin_ui, 'output_plugin_filter_toggle_admin_notice' ) );
		add_action( 'admin_init', array( $this, 'output_auto_updates_disabled_admin_notice' ) );
	}

	// endregion

	/**
	 * Returns the centralized settings, fetching them the first time they are needed.
	 *
	 * When they cannot be fetched and there is no last known good payload, every autoupdate is
	 * refused.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @return  \stdClass
	 */
	private function get_settings(): \stdClass {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

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

		return $this->settings;
	}

	/**
	 * Load settings from the centralized settings endpoint.
	 *
	 * With no endpoint configured there is nothing to ask and nothing to fail at: the module runs
	 * on its local rules alone and an empty settings object is returned.
	 *
	 * @throws  \RuntimeException If the settings cannot be loaded.
	 * @throws  \Exception        Rethrown when there is no usable last known good payload. Includes
	 *                            the \JsonException raised when the response cannot be decoded.
	 *
	 * @return  \stdClass
	 */
	private function get_auto_update_settings(): \stdClass {
		$endpoint = self::get_settings_endpoint();
		if ( '' === $endpoint ) {
			return new \stdClass();
		}

		// Try getting the settings from the transient first
		$transient_key = self::SETTINGS_TRANSIENT;
		$settings      = get_transient( $transient_key );

		if ( empty( $settings ) ) {
			try {
				$response = wp_safe_remote_get(
					$endpoint,
					array(
						'timeout' => 2,
						'headers' => array( 'Accept' => 'application/json' ),
					)
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

				$decoded_body = json_decode( $response_body, false, 512, JSON_THROW_ON_ERROR );

				// if the settings are empty, we still need to return an object
				if ( ! is_object( $decoded_body ) ) {
					$object              = new \stdClass();
					$object->placeholder = $decoded_body;
					$decoded_body        = $object;
				}

				// Save the settings in a transient for 5 minutes
				set_transient( $transient_key, $decoded_body, 5 * MINUTE_IN_SECONDS );
				self::store_last_known_good( $decoded_body );

				$settings = $decoded_body;
			} catch ( \Exception $exception ) {
				// A short outage must not imitate a deliberate fleet-wide stop, so keep serving the last
				// payload we fetched successfully. A stale `disabled_plugins` list still blocks whatever
				// somebody deliberately blocked; only a newly added block is missed.
				$last_known_good = self::get_last_known_good_settings();
				if ( $last_known_good instanceof \stdClass ) {
					set_transient( $transient_key, $last_known_good, 5 * MINUTE_IN_SECONDS );
					return $last_known_good;
				}

				// Negative-cache the fail-safe so a slow/down endpoint cannot block every uncached page load.
				set_transient( $transient_key, (object) array( 'disable_all' => true ), 5 * MINUTE_IN_SECONDS );
				throw $exception;
			}
		}

		return $settings;
	}

	/**
	 * Returns the centralized settings endpoint, or an empty string when none is configured.
	 *
	 * There is no built-in endpoint. The URL is read from the
	 * `A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL` constant when defined, otherwise from the stored
	 * option, and the result is filterable.
	 *
	 * @since   1.3.1
	 * @version 1.5.0
	 *
	 * @return  string
	 */
	public static function get_settings_endpoint(): string {
		$url = \defined( 'A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL' )
			? \constant( 'A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL' )
			: get_option( A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL_OPTION, '' );

		/**
		 * Filters the centralized autoupdate settings endpoint.
		 *
		 * @since 1.3.1
		 *
		 * @param string $url The settings endpoint URL, or an empty string when none is configured.
		 */
		$url = apply_filters( 'a8csp_atlantis_autoupdate_settings_url', \is_string( $url ) ? $url : '' );

		return self::sanitize_settings_endpoint( $url );
	}

	/**
	 * Reduces a value to an http(s) URL, or to an empty string when it is not one.
	 *
	 * The scheme has to be written out. `esc_url_raw()` puts `http://` in front of a bare word,
	 * which would turn a typo into an endpoint that never answers — and once an endpoint is
	 * configured, one that never answers stops every autoupdate.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed $url The value to sanitize.
	 *
	 * @return  string
	 */
	public static function sanitize_settings_endpoint( $url ): string {
		if ( ! \is_string( $url ) ) {
			return '';
		}

		$url = trim( $url );
		if ( 1 !== preg_match( '#^https?://[^/\s]#i', $url ) ) {
			return '';
		}

		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * Drops the cached settings and the last known good payload.
	 *
	 * For when the endpoint changes: both were fetched from the previous one, and a payload kept
	 * from an endpoint the site no longer reads should not go on deciding its updates. Hooked to
	 * the endpoint option being added or deleted, so it happens however the option is changed.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public static function flush_settings_cache(): void {
		delete_transient( self::SETTINGS_TRANSIENT );
		delete_option( self::LAST_KNOWN_GOOD_OPTION );
	}

	/**
	 * Drops the cached settings when the endpoint option is updated to a different endpoint.
	 *
	 * Only then: the last known good payload is what carries a site through an outage, so saving
	 * the endpoint it already has must not throw that payload away.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed $old_value The endpoint stored before.
	 * @param   mixed $value     The endpoint stored now.
	 *
	 * @return  void
	 */
	public static function maybe_flush_settings_cache( $old_value, $value ): void {
		$old_value = \is_string( $old_value ) ? trim( $old_value ) : '';
		$value     = \is_string( $value ) ? trim( $value ) : '';

		if ( $old_value !== $value ) {
			self::flush_settings_cache();
		}
	}

	/**
	 * Returns the address the centralized settings route update emails to, or an empty string.
	 *
	 * One address or nothing. The value is deliberately not passed through `sanitize_email()`
	 * first: that strips the comma and the space out of two addresses and leaves one string
	 * `is_email()` accepts, naming a mailbox nobody owns.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  string
	 */
	private function get_notification_recipient(): string {
		$recipient = $this->get_settings()->notification_email ?? '';
		if ( ! \is_string( $recipient ) ) {
			return '';
		}

		$recipient = trim( $recipient );

		return false === is_email( $recipient ) ? '' : $recipient;
	}

	/**
	 * Returns the sentence telling an administrator who to ask before updating by hand.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $subject What would be updated, with its leading space, or an empty string.
	 *
	 * @return  string
	 */
	private static function get_manual_update_caution( string $subject = '' ): string {
		return a8csp_atlantis_is_managed_site()
			? "Please contact the WordPress Special Projects team before manually updating$subject."
			: "Please check with whoever manages this site before manually updating$subject.";
	}

	/**
	 * Returns how long the last known good settings may be reused while the endpoint is unreachable.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @return  int
	 */
	private static function get_grace_period(): int {
		/**
		 * Filters the grace window for reusing the last known good autoupdate settings.
		 *
		 * @since 1.3.1
		 *
		 * @param int $grace Seconds since the last successful fetch. Default 24 hours.
		 */
		return (int) apply_filters( 'a8csp_atlantis_autoupdate_settings_grace', DAY_IN_SECONDS );
	}

	/**
	 * Records a successfully fetched payload so it survives object-cache eviction.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @param   \stdClass $settings The payload to record.
	 *
	 * @return  void
	 */
	private static function store_last_known_good( \stdClass $settings ): void {
		update_option(
			self::LAST_KNOWN_GOOD_OPTION,
			array(
				'settings'  => $settings,
				'timestamp' => time(),
			),
			false
		);
	}

	/**
	 * Returns the last known good payload, or null when there is none or it is past the grace window.
	 *
	 * @since   1.3.1
	 * @version 1.3.1
	 *
	 * @return  \stdClass|null
	 */
	private static function get_last_known_good_settings(): ?\stdClass {
		$stored = get_option( self::LAST_KNOWN_GOOD_OPTION );

		if ( ! \is_array( $stored ) || ! isset( $stored['settings'], $stored['timestamp'] ) || ! \is_object( $stored['settings'] ) ) {
			return null;
		}

		if ( ( time() - (int) $stored['timestamp'] ) >= self::get_grace_period() ) {
			return null;
		}

		return $stored['settings'];
	}

	/**
	 * Reports whether the site is currently refusing all autoupdates because the centralized
	 * settings could not be fetched.
	 *
	 * Reads from storage rather than the module's own settings, because the module only fetches
	 * them once an update needs them, which the REST status request never does.
	 *
	 * A site with no endpoint configured never fetches, so it cannot fail closed.
	 *
	 * @since   1.3.1
	 * @version 1.5.0
	 *
	 * @return  array{settings_url_configured: bool, fail_closed: bool, last_success: int|null, seconds_since_success: int|null}
	 */
	public static function get_settings_state(): array {
		if ( '' === self::get_settings_endpoint() ) {
			return array(
				'settings_url_configured' => false,
				'fail_closed'             => false,
				'last_success'            => null,
				'seconds_since_success'   => null,
			);
		}

		$stored = get_option( self::LAST_KNOWN_GOOD_OPTION );

		if ( ! \is_array( $stored ) || ! isset( $stored['timestamp'] ) ) {
			return array(
				'settings_url_configured' => true,
				'fail_closed'             => true,
				'last_success'            => null,
				'seconds_since_success'   => null,
			);
		}

		$timestamp = (int) $stored['timestamp'];
		$age       = time() - $timestamp;

		return array(
			'settings_url_configured' => true,
			'fail_closed'             => $age >= self::get_grace_period(),
			'last_success'            => $timestamp,
			'seconds_since_success'   => $age,
		);
	}

	/**
	 * If we have hit the "Disable all autoupdates" toggle switch, or if we can't get the centralized settings, don't autoupdate anything.
	 *
	 * @param bool|null $update Whether to update the plugin or not. This can be bool or null as per the docs.
	 * @param object    $item   The update item object.
	 *
	 * @return bool True to update, false to not update.
	 */
	public function filter_maybe_disable_all_autoupdates( $update, $item = null ): bool {
		$settings = $this->get_settings();
		if ( isset( $settings->disable_all ) && true === $settings->disable_all ) {
			return false;
		}

		if ( is_object( $item ) && $this->is_plugin_disabled_in_centralized_settings( $item ) ) {
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
		$settings = $this->get_settings();
		if ( isset( $settings->canary_sites ) && in_array( $site_url, $settings->canary_sites, true ) ) {
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
	 * Hold core auto-updates to the update window, without ever turning them on.
	 *
	 * Where core files are read-only, as on hosts that update core themselves, every attempt fails
	 * with `copy_failed_for_update_core_file` and sends a failure email, so none is made.
	 *
	 * @param bool|null $update Whether to update core or not.
	 * @param object    $item   The core update offer.
	 *
	 * @return bool True to update, false to not update.
	 */
	public function filter_auto_update_core( $update, $item ): bool {
		if ( ! $update || ! self::core_files_are_writable( $item ) ) {
			return false;
		}

		return $this->filter_auto_update_specific_times( $update, $item );
	}

	/**
	 * Skip the "core update available" email where core files are read-only.
	 *
	 * Turning a core update down makes WordPress offer it by email instead. Where the host updates
	 * core itself, that email would arrive for every release the host applies anyway. Only when a
	 * recipient is configured, so other sites keep core's email to their own administrator.
	 *
	 * @param bool        $notify Whether the site administrator is notified.
	 * @param object|null $item   The core update offer.
	 *
	 * @return bool
	 */
	public function filter_core_update_notification_email( $notify, $item = null ): bool {
		if ( '' !== $this->get_notification_recipient() && ! self::core_files_are_writable( $item ) ) {
			return false;
		}

		return (bool) $notify;
	}

	/**
	 * Whether WordPress can overwrite its own core files to apply an update.
	 *
	 * @param object|null $item The core update offer.
	 *
	 * @return bool
	 */
	private static function core_files_are_writable( $item = null ): bool {
		if ( ! function_exists( 'get_filesystem_method' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Without direct file access core updates over FTP or SSH, where local permissions say nothing.
		// Probe ABSPATH, as the core updater does, not the default WP_CONTENT_DIR, and relax file
		// ownership only when core does: for updates that add no new files.
		$relaxed_ownership = \is_object( $item ) && isset( $item->new_files ) && ! $item->new_files;
		if ( 'direct' !== get_filesystem_method( array(), ABSPATH, $relaxed_ownership ) ) {
			return true;
		}

		// The first file a core update copies; its failure is `copy_failed_for_update_core_file`.
		return wp_is_writable( ABSPATH . 'wp-admin/includes/update-core.php' );
	}

	/**
	 * Sends auto-update emails when the centralized settings name a recipient for them.
	 *
	 * Without one, whether they are sent is left to core and the host.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @param   bool $send Whether to send the email.
	 *
	 * @return  bool
	 */
	public function filter_send_update_email( $send ): bool {
		return '' !== $this->get_notification_recipient() ? true : (bool) $send;
	}

	/**
	 * Routes auto-update emails to the recipient the centralized settings name, when they name one.
	 *
	 * @param array  $email              Array of email data.
	 * @param string $type               Type of email to send.
	 * @param array  $successful_updates Array of successful updates.
	 * @param array  $failed_updates     Array of failed updates.
	 *
	 * @return array Array of email data with modified recipient email.
	 */
	public function filter_custom_update_emails( $email, $type, $successful_updates, $failed_updates ): array {
		return $this->set_notification_recipient( $email );
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
		return $this->set_notification_recipient( $email );
	}

	/**
	 * Replaces an email's recipient with the centrally configured one, leaving it alone when there is none.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   array $email The email details.
	 *
	 * @return  array
	 */
	private function set_notification_recipient( array $email ): array {
		$recipient = $this->get_notification_recipient();
		if ( '' !== $recipient ) {
			$email['to'] = $recipient;
		}

		return $email;
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
			return PluginFilterRules::is_filter_disabled_for_plugin_file( $plugin_file );
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
				return PluginFilterRules::is_filter_disabled_for_plugin_file( $installed_plugin_file );
			}
		}

		return false;
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
		// don't show if we are already disabling all updates
		$settings = $this->get_settings();
		if ( isset( $settings->disable_all ) && true === $settings->disable_all ) {
			return;
		}

		$all_plugins = get_plugins();

		foreach ( array_keys( $all_plugins ) as $plugin_file ) {
			// create a fake object to feed to disable_autoupdate_specific_plugins
			$plugin_obj         = new \stdClass();
			$slug               = dirname( $plugin_file );
			$plugin_obj->slug   = $slug;
			$plugin_obj->plugin = $plugin_file;
			$plugin_can_update  = ! PluginFilterRules::is_plugin_blocked_from_autoupdates( $plugin_obj, $settings );
			if ( false === $plugin_can_update ) {
				// add notice next to the "update now" link
				add_filter(
					"in_plugin_update_message-{$plugin_file}",
					function () {
						echo ' <strong style="color:red;"> Caution:</strong> Autoupdates have been explicitly deactivated for this plugin. ', esc_html( self::get_manual_update_caution() );
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
							echo '<div class="notice notice-error"><p><strong style="color:red;"> Caution:</strong> Autoupdates have been explicitly deactivated for ', esc_html( $slug ), '. ', esc_html( self::get_manual_update_caution() ), '</p></div>';
						}
					);
				}
			}
		}
	}

	/**
	 * Check whether centralized settings disable autoupdates for a plugin item.
	 *
	 * @param object $item Plugin update item object.
	 *
	 * @return bool
	 */
	private function is_plugin_disabled_in_centralized_settings( object $item ): bool {
		if ( isset( $item->theme ) ) {
			return false;
		}

		$plugin_obj = new \stdClass();

		if ( isset( $item->plugin ) && is_string( $item->plugin ) ) {
			$plugin_obj->plugin = $item->plugin;
		}

		if ( isset( $item->slug ) && is_string( $item->slug ) ) {
			$plugin_obj->slug = $item->slug;
		}

		if ( ! isset( $plugin_obj->plugin ) && ! isset( $plugin_obj->slug ) ) {
			return false;
		}

		return PluginFilterRules::is_plugin_disabled_by_centralized_settings( $plugin_obj, $this->get_settings() );
	}

	/**
	 * Autoupdates disabled admin notice
	 */
	public function output_auto_updates_disabled_admin_notice(): void {
		// add notice to the top of the screen
		global $pagenow;
		$settings = $this->get_settings();
		if ( 'plugins.php' === $pagenow && isset( $settings->disable_all ) && true === $settings->disable_all ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><strong style="color:red;"> Caution:</strong> All automatic updates are deactivated. ', esc_html( self::get_manual_update_caution( ' plugins' ) ), '</p></div>';
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
