<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\CLI;

use A8C\SpecialProjects\Atlantis\Modules\Autoupdates\AutoUpdatePluginsFilter;
use WP_CLI\Formatter;

defined( 'ABSPATH' ) || exit;

/**
 * Manage how Atlantis treats this site from the command line.
 *
 * ## EXAMPLES
 *
 *     # Show whether the site is managed and whether a centralized settings endpoint is set.
 *     $ wp atlantis site status
 *
 *     # Mark the site as managed by the WordPress Special Projects team, or not.
 *     $ wp atlantis site managed on
 *     $ wp atlantis site managed off
 *
 *     # Point the Autoupdates module at a centralized settings endpoint, or clear it.
 *     $ wp atlantis site settings-url https://example.com/wp-json/example/v1/settings/
 *     $ wp atlantis site settings-url --clear
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class Site_Command {
	/**
	 * Default fields returned by `status`.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_FIELDS = array( 'managed', 'managed_source', 'settings_url_configured', 'settings_url_source' );

	// region SUBCOMMANDS

	/**
	 * Shows whether the site is managed and whether a centralized settings endpoint is configured.
	 *
	 * The endpoint itself is not printed: `wp option get a8csp_atlantis_autoupdate_settings_url`
	 * shows the stored value to whoever needs it.
	 *
	 * ## OPTIONS
	 *
	 * [--field=<field>]
	 * : Output just this field's value.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to show.
	 * ---
	 * default: managed,managed_source,settings_url_configured,settings_url_source
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in the given format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * * managed                 - Whether Atlantis treats this as a site managed by the team.
	 * * managed_source          - Where that answer comes from: constant or option.
	 * * settings_url_configured - Whether the Autoupdates module has a centralized settings endpoint.
	 * * settings_url_source     - Where the endpoint comes from: constant, option, filter or none.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis site status
	 *     $ wp atlantis site status --field=managed
	 *     $ wp atlantis site status --format=json
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function status( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Booleans (not yes/no strings) to match the /status REST payload.
		$row = array(
			'managed'                 => \a8csp_atlantis_is_managed_site(),
			'managed_source'          => \defined( 'A8CSP_ATLANTIS_MANAGED_SITE' ) ? 'constant' : 'option',
			'settings_url_configured' => '' !== AutoUpdatePluginsFilter::get_settings_endpoint(),
			'settings_url_source'     => $this->get_settings_url_source(),
		);

		$fields = isset( $assoc_args['fields'] )
			? \array_map( 'trim', \explode( ',', (string) $assoc_args['fields'] ) )
			: self::DEFAULT_FIELDS;

		$formatter = new Formatter( $assoc_args, $fields );
		$formatter->display_items( array( $row ) );
	}

	// The `<state>` options list below quotes "on" and "off" on purpose: WP-CLI parses the `---`
	// synopsis block as YAML, where the bare words coerce to booleans. Do not unquote.
	/**
	 * Marks the site as managed by the WordPress Special Projects team, or not.
	 *
	 * A managed site gets the team's behaviour: forced usage tracking, the Special Projects RUM
	 * tag, admin screens limited to Automatticians, referral attribution on the footer credits and
	 * team-worded notices. An unmanaged site gets none of it.
	 *
	 * ## OPTIONS
	 *
	 * <state>
	 * : Whether the site is managed.
	 * ---
	 * options:
	 *   - "on"
	 *   - "off"
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis site managed on
	 *     $ wp atlantis site managed off
	 *
	 * @param array<int, string>         $args       Positional args: <state>.
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function managed( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$managed = 'on' === (string) ( $args[0] ?? '' );

		\update_option( A8CSP_ATLANTIS_MANAGED_SITE_OPTION, $managed ? '1' : '0' );

		\WP_CLI::success( $managed ? 'This site is now marked as managed.' : 'This site is now marked as unmanaged.' );

		if ( \defined( 'A8CSP_ATLANTIS_MANAGED_SITE' ) ) {
			\WP_CLI::warning( 'The A8CSP_ATLANTIS_MANAGED_SITE constant is defined and overrides the stored value.' );
		}
	}

	/**
	 * Sets or clears the centralized settings endpoint the Autoupdates module reads.
	 *
	 * With no endpoint the module makes no remote request and runs on its local rules alone.
	 *
	 * ## OPTIONS
	 *
	 * [<url>]
	 * : The endpoint URL (http or https).
	 *
	 * [--clear]
	 * : Remove the stored endpoint.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis site settings-url https://example.com/wp-json/example/v1/settings/
	 *     $ wp atlantis site settings-url --clear
	 *
	 * @subcommand settings-url
	 *
	 * @param array<int, string>         $args       Positional args: [<url>].
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function settings_url( array $args, array $assoc_args ): void {
		$clear   = (bool) ( $assoc_args['clear'] ?? false );
		$url     = (string) ( $args[0] ?? '' );
		$has_url = '' !== $url;

		if ( ! ( $clear xor $has_url ) ) {
			\WP_CLI::error( 'Pass either a URL or --clear.' );
		}

		if ( $clear ) {
			\delete_option( A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL_OPTION );
			\WP_CLI::success( 'The centralized settings endpoint was cleared.' );
		} else {
			$sanitized = \esc_url_raw( \trim( $url ), array( 'http', 'https' ) );
			if ( '' === $sanitized || false === \wp_http_validate_url( $sanitized ) ) {
				\WP_CLI::error( 'That is not a valid http or https URL.' );
			}

			\update_option( A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL_OPTION, $sanitized );
			\WP_CLI::success( 'The centralized settings endpoint was saved.' );
		}

		// The settings are cached for five minutes; drop them so the change applies now.
		AutoUpdatePluginsFilter::flush_settings_cache();

		if ( \defined( 'A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL' ) ) {
			\WP_CLI::warning( 'The A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL constant is defined and overrides the stored value.' );
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Names where the effective settings endpoint comes from.
	 *
	 * @return string
	 */
	private function get_settings_url_source(): string {
		$effective = AutoUpdatePluginsFilter::get_settings_endpoint();
		if ( '' === $effective ) {
			return 'none';
		}

		if ( \defined( 'A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL' ) ) {
			$configured = \constant( 'A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL' );
			$source     = 'constant';
		} else {
			$configured = \get_option( A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL_OPTION, '' );
			$source     = 'option';
		}

		$configured = \is_string( $configured ) ? \esc_url_raw( \trim( $configured ), array( 'http', 'https' ) ) : '';

		return $effective === $configured ? $source : 'filter';
	}

	// endregion
}
