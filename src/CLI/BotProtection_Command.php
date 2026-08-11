<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\CLI;

use A8C\SpecialProjects\Atlantis\Modules\BotProtection\BotProtection;

defined( 'ABSPATH' ) || exit;

/**
 * Manage WP Cloud Bot Protection from the command line.
 *
 * ## EXAMPLES
 *
 *     # Show the current enforcement state and WP Cloud context.
 *     $ wp atlantis module bot-protection status
 *
 *     # Output just the state (for scripting).
 *     $ wp atlantis module bot-protection status --field=state
 *
 *     # Force bot protection off on a site, or defer to WP Cloud's default.
 *     $ wp atlantis module bot-protection set off
 *     $ wp atlantis module bot-protection set inherit
 *
 * @since   1.4.0
 * @version 1.4.0
 */
class BotProtection_Command {
	/**
	 * Default fields returned by `status`.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_FIELDS = array( 'state', 'wp_cloud', 'mu_plugin_present', 'environment' );

	// region SUBCOMMANDS

	/**
	 * Shows the current bot protection enforcement state and WP Cloud context.
	 *
	 * ## OPTIONS
	 *
	 * [--field=<field>]
	 * : Output just this field's value.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to show.
	 * ---
	 * default: state,wp_cloud,mu_plugin_present,environment
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
	 * * state             - Enforcement state: inherit or off.
	 * * wp_cloud          - Whether WP Cloud credentials (ATOMIC_SITE_ID / ATOMIC_SITE_API_KEY) are present.
	 * * mu_plugin_present - Whether the wpcloud-bot-protection mu-plugin is loaded.
	 * * environment       - The site's environment type (informational; enforcement is driven by state).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis module bot-protection status
	 *     $ wp atlantis module bot-protection status --field=state
	 *     $ wp atlantis module bot-protection status --format=json
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function status( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Booleans (not yes/no strings) to match the /status REST payload and the
		// `mandatory` field of `wp atlantis module`, so a fleet consumer reading
		// either surface gets the same types.
		$row = array(
			'state'             => BotProtection::get_configured_state(),
			'wp_cloud'          => BotProtection::is_wp_cloud(),
			'mu_plugin_present' => BotProtection::is_mu_plugin_present(),
			'environment'       => \wp_get_environment_type(),
		);

		$fields = isset( $assoc_args['fields'] )
			? \array_map( 'trim', \explode( ',', (string) $assoc_args['fields'] ) )
			: self::DEFAULT_FIELDS;

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
		$formatter->display_items( array( $row ) );
	}

	/**
	 * Sets the bot protection enforcement state.
	 *
	 * `inherit` leaves WP Cloud's own enablement tiers untouched; `off` forces
	 * protection disabled via the `wpcloud_bot_protection_enable` filter,
	 * overriding every other tier (including any client-level percentage
	 * rollout). There is no `on`: the filter cannot enable a site that no tier
	 * has armed — enable via the constant or a client-level rollout instead.
	 *
	 * ## OPTIONS
	 *
	 * <state>
	 * : The enforcement state.
	 * ---
	 * options:
	 *   - inherit
	 *   - off
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis module bot-protection set off
	 *     $ wp atlantis module bot-protection set inherit
	 *
	 * @param array<int, string>         $args       Positional args: <state>.
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function set( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$state = (string) ( $args[0] ?? '' );

		// Always persist rather than short-circuit on the normalized current
		// state: a bogus stored value reads as `inherit`, so a `set inherit`
		// must still rewrite it to a clean value.
		$result = BotProtection::set_state( $state );
		if ( \is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::success( \sprintf( "Bot protection enforcement set to '%s'.", $state ) );
		$this->maybe_warn_no_effect( $state );
	}

	// endregion

	// region HELPERS

	/**
	 * Warns that a force-off has no effect when the site is not WP Cloud or the
	 * mu-plugin is absent.
	 *
	 * @param string $state The enforcement state that was requested.
	 *
	 * @return void
	 */
	private function maybe_warn_no_effect( string $state ): void {
		if ( BotProtection::STATE_OFF !== $state ) {
			return;
		}

		if ( ! BotProtection::is_wp_cloud() ) {
			\WP_CLI::warning( 'This site is not a WP Cloud site, so this setting has no effect here.' );
		} elseif ( ! BotProtection::is_mu_plugin_present() ) {
			\WP_CLI::warning( 'The WP Cloud bot protection mu-plugin is not present on this site, so this setting has no effect here.' );
		}
	}

	// endregion
}
