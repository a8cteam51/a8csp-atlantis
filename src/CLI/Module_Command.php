<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\CLI;

use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;
use A8C\SpecialProjects\Atlantis\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Atlantis modules from the command line.
 *
 * ## EXAMPLES
 *
 *     # List every module and whether it's enabled.
 *     $ wp atlantis module list
 *
 *     # Activate one or more modules.
 *     $ wp atlantis module activate autoupdates messages
 *
 *     # Deactivate a module.
 *     $ wp atlantis module deactivate tracking
 *
 *     # Get a single module's state as JSON for scripting.
 *     $ wp atlantis module status autoupdates --format=json
 *
 * @since   1.2.0
 * @version 1.2.0
 */
class Module_Command {
	/**
	 * Default fields returned by `list` and `status`.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_FIELDS = array( 'key', 'status', 'environment' );

	// region SUBCOMMANDS

	/**
	 * Lists every registered Atlantis module.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to show.
	 * ---
	 * default: key,status,environment
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
	 *   - count
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * * key         - Module identifier used by activate/deactivate.
	 * * status      - "Active" or "Inactive".
	 * * name        - Human-readable module name (opt-in).
	 * * mandatory   - Whether the module is mandatory and cannot be disabled (opt-in).
	 * * environment - Reason the module is blocked by environment, if any.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis module list
	 *     $ wp atlantis module list --fields=key,status --format=csv
	 *
	 * @subcommand list
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function list_( array $args, array $assoc_args ): void {
		$rows = array();
		foreach ( $this->get_modules() as $key => $module ) {
			$rows[] = $this->module_to_row( $key, $module );
		}

		$fields = isset( $assoc_args['fields'] )
			? \array_map( 'trim', \explode( ',', (string) $assoc_args['fields'] ) )
			: self::DEFAULT_FIELDS;

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
		$formatter->display_items( $rows );
	}

	/**
	 * Shows the status of a single module.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Module key (e.g. `messages`, `autoupdates`).
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to show.
	 * ---
	 * default: key,status,environment
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
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis module status autoupdates
	 *     $ wp atlantis module status messages --format=json
	 *
	 * @param array<int, string>         $args       Positional args: <key>.
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function status( array $args, array $assoc_args ): void {
		$module = $this->require_module( $args[0] ?? '' );

		$fields = isset( $assoc_args['fields'] )
			? \array_map( 'trim', \explode( ',', (string) $assoc_args['fields'] ) )
			: self::DEFAULT_FIELDS;

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
		$formatter->display_items( array( $this->module_to_row( $args[0], $module ) ) );
	}

	/**
	 * Activates one or more modules.
	 *
	 * Reports per-key. Exits non-zero if any key failed.
	 *
	 * ## OPTIONS
	 *
	 * <key>...
	 * : One or more module keys to activate.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis module activate messages
	 *     $ wp atlantis module activate messages autoupdates
	 *
	 * @param array<int, string>         $args       Positional args: one or more <key>.
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function activate( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->bulk_set_enabled( $args, true );
	}

	/**
	 * Deactivates one or more modules.
	 *
	 * Reports per-key. Exits non-zero if any key failed. Mandatory modules
	 * cannot be deactivated.
	 *
	 * ## OPTIONS
	 *
	 * <key>...
	 * : One or more module keys to deactivate.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis module deactivate tracking
	 *
	 * @param array<int, string>         $args       Positional args: one or more <key>.
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function deactivate( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->bulk_set_enabled( $args, false );
	}

	// endregion

	// region HELPERS

	/**
	 * Applies enable/disable to every key in $keys and reports per-key.
	 *
	 * @param array<int, string> $keys    Module keys.
	 * @param bool               $enabled Target state.
	 */
	private function bulk_set_enabled( array $keys, bool $enabled ): void {
		if ( 0 === \count( $keys ) ) {
			\WP_CLI::error( 'At least one module key is required.' );
		}

		$modules   = $this->get_modules();
		$verb_past = $enabled ? 'activated' : 'deactivated';
		$verb_ing  = $enabled ? 'active' : 'inactive';
		$failures  = 0;

		foreach ( $keys as $key ) {
			if ( ! isset( $modules[ $key ] ) ) {
				\WP_CLI::warning( \sprintf( "Unknown module '%s'. Available: %s.", $key, \implode( ', ', \array_keys( $modules ) ) ) );
				++$failures;
				continue;
			}

			$module = $modules[ $key ];
			if ( $module->is_active() === $enabled ) {
				\WP_CLI::log( \sprintf( "Module '%s' is already %s.", $key, $verb_ing ) );
				$this->maybe_warn_environment_disabled( $key, $module, $enabled );
				continue;
			}

			$result = $module->set_enabled( $enabled );
			if ( \is_wp_error( $result ) ) {
				\WP_CLI::warning( \sprintf( "Module '%s': %s", $key, $result->get_error_message() ) );
				++$failures;
				continue;
			}

			\WP_CLI::success( \sprintf( "Module '%s' %s.", $key, $verb_past ) );
			$this->maybe_warn_environment_disabled( $key, $module, $enabled );
		}

		if ( 0 < $failures ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Emits a warning when activating a module that is environmentally disabled.
	 *
	 * Fired regardless of whether `set_enabled()` changed the stored flag, so
	 * users see the warning even when the module is already "active" in settings.
	 *
	 * @param string         $key     Module key.
	 * @param AbstractModule $module  Module instance.
	 * @param bool           $enabled Target state passed to bulk_set_enabled().
	 *
	 * @return void
	 */
	private function maybe_warn_environment_disabled( string $key, AbstractModule $module, bool $enabled ): void {
		if ( ! $enabled ) {
			return;
		}

		$environment = $module->is_disabled();
		if ( \is_wp_error( $environment ) ) {
			\WP_CLI::warning( \sprintf( "Module '%s' is environmentally disabled and will stay dormant: %s", $key, $environment->get_error_message() ) );
		}
	}

	/**
	 * Returns the modules registry, erroring out if Atlantis is not initialised.
	 *
	 * @return array<string, AbstractModule>
	 */
	private function get_modules(): array {
		$plugin = Plugin::get_instance();
		if ( ! isset( $plugin->modules ) ) {
			\WP_CLI::error( 'Atlantis is not initialised on this site.' );
		}

		return $plugin->modules->modules;
	}

	/**
	 * Resolves $key to a module or aborts with an error listing valid keys.
	 *
	 * @param string $key Module key.
	 *
	 * @return AbstractModule
	 */
	private function require_module( string $key ): AbstractModule {
		$modules = $this->get_modules();
		if ( '' === $key || ! isset( $modules[ $key ] ) ) {
			\WP_CLI::error( \sprintf( "Unknown module '%s'. Available: %s.", $key, \implode( ', ', \array_keys( $modules ) ) ) );
		}

		return $modules[ $key ];
	}

	/**
	 * Shapes a module into a row of scalar fields suitable for the Formatter.
	 *
	 * @param string         $key    Module key.
	 * @param AbstractModule $module Module instance.
	 *
	 * @return array<string, mixed>
	 */
	private function module_to_row( string $key, AbstractModule $module ): array {
		$environment = $module->is_disabled();

		return array(
			'key'         => $key,
			'status'      => $module->is_active() ? 'Active' : 'Inactive',
			'name'        => $module->get_name(),
			'mandatory'   => $module->is_mandatory(),
			'environment' => \is_wp_error( $environment ) ? $environment->get_error_message() : '',
		);
	}

	// endregion
}
