<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\CLI;

use A8C\SpecialProjects\Atlantis\Modules\Snippets\Loader;
use A8C\SpecialProjects\Atlantis\Modules\Snippets\Signature;
use A8C\SpecialProjects\Atlantis\Modules\Snippets\SnippetStore;

defined( 'ABSPATH' ) || exit;

/**
 * Inspect and manage deployed Atlantis remediation snippets on this site.
 *
 * These are the per-site handle on the fleet snippet runner: list what is
 * active, audit a snippet's code, re-check signatures, remove one locally, or
 * throw the local kill switch — all without SSH-spelunking the filesystem.
 *
 * ## EXAMPLES
 *
 *     # List every snippet stored on this site.
 *     $ wp atlantis snippet list
 *
 *     # Show one snippet's code and metadata for audit.
 *     $ wp atlantis snippet show wc-94-checkout-total
 *
 *     # Re-verify stored signatures against stored code.
 *     $ wp atlantis snippet verify
 *
 *     # Remove one snippet locally and clear its file.
 *     $ wp atlantis snippet remove wc-94-checkout-total
 *
 *     # Engage the local kill switch (disables all snippets at once).
 *     $ wp atlantis snippet disable
 *
 * @since   1.3.0
 * @version 1.3.0
 */
class Snippet_Command {
	/**
	 * Default fields returned by `list`.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_FIELDS = array( 'snippet_id', 'version', 'status', 'fail_count', 'expires_at' );

	// region SUBCOMMANDS

	/**
	 * Lists every snippet stored on this site.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to show.
	 * ---
	 * default: snippet_id,version,status,fail_count,expires_at
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
	 * @subcommand list
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function list_( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$rows = array();
		foreach ( $this->store()->all() as $row ) {
			$rows[] = array(
				'snippet_id' => $row['snippet_id'],
				'version'    => (int) $row['version'],
				'status'     => $row['status'],
				'sha256'     => $row['sha256'],
				'fail_count' => (int) $row['fail_count'],
				'expires_at' => $row['expires_at'] ?? '',
				'updated_at' => $row['updated_at'],
			);
		}

		$fields = isset( $assoc_args['fields'] )
			? \array_map( 'trim', \explode( ',', (string) $assoc_args['fields'] ) )
			: self::DEFAULT_FIELDS;

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
		$formatter->display_items( $rows );
	}

	/**
	 * Shows a single snippet's metadata and code.
	 *
	 * ## OPTIONS
	 *
	 * <snippet_id>
	 * : The snippet id.
	 *
	 * @param array<int, string>         $args       Positional args: <snippet_id>.
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function show( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$row = $this->require_snippet( $args[0] ?? '' );

		\WP_CLI::log( \sprintf( 'ID:         %s', $row['snippet_id'] ) );
		\WP_CLI::log( \sprintf( 'Version:    %d', (int) $row['version'] ) );
		\WP_CLI::log( \sprintf( 'Status:     %s', $row['status'] ) );
		\WP_CLI::log( \sprintf( 'SHA-256:    %s', $row['sha256'] ) );
		\WP_CLI::log( \sprintf( 'Fail count: %d', (int) $row['fail_count'] ) );
		\WP_CLI::log( \sprintf( 'Expires:    %s', $row['expires_at'] ?? '(never)' ) );
		\WP_CLI::log( \sprintf( 'Deployed:   %s', $row['deployed_at'] ) );
		if ( ! is_null( $row['notes'] ) && '' !== $row['notes'] ) {
			\WP_CLI::log( \sprintf( 'Notes:      %s', $row['notes'] ) );
		}
		if ( ! is_null( $row['last_error'] ) && '' !== $row['last_error'] ) {
			\WP_CLI::log( \sprintf( 'Last error: %s', $row['last_error'] ) );
		}
		\WP_CLI::log( '' );
		\WP_CLI::log( '--- code ---' );
		\WP_CLI::log( (string) $row['code'] );
	}

	/**
	 * Re-verifies stored signatures against stored code and hashes.
	 *
	 * Reports per-snippet. Exits non-zero if any snippet fails verification.
	 *
	 * ## OPTIONS
	 *
	 * [<snippet_id>]
	 * : Verify a single snippet. Omit to verify all.
	 *
	 * @param array<int, string>         $args       Positional args: optional <snippet_id>.
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function verify( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$rows = isset( $args[0] ) && '' !== $args[0]
			? array( $this->require_snippet( $args[0] ) )
			: $this->store()->all();

		if ( 0 === \count( $rows ) ) {
			\WP_CLI::log( 'No snippets stored.' );
			return;
		}

		$failures = 0;
		foreach ( $rows as $row ) {
			$snippet_id = (string) $row['snippet_id'];

			if ( $this->row_signature_valid( $row ) ) {
				\WP_CLI::success( \sprintf( "Snippet '%s' verified.", $snippet_id ) );
			} else {
				\WP_CLI::warning( \sprintf( "Snippet '%s' FAILED verification.", $snippet_id ) );
				++$failures;
			}
		}

		if ( 0 < $failures ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Recomputes a snippet's hash and verifies its stored signature.
	 *
	 * @param array<string, mixed> $row The snippet row.
	 *
	 * @return bool
	 */
	private function row_signature_valid( array $row ): bool {
		$hash_ok = hash_equals( hash( 'sha256', (string) $row['code'] ), (string) $row['sha256'] );
		if ( ! $hash_ok ) {
			return false;
		}

		$expires = ( is_null( $row['expires_at'] ) || '' === $row['expires_at'] ) ? '' : gmdate( 'c', (int) strtotime( (string) $row['expires_at'] ) );
		$message = Signature::deploy_message( (string) $row['snippet_id'], (int) $row['version'], (string) $row['sha256'], $expires );

		return Signature::verify( $message, (string) $row['signature'] );
	}

	/**
	 * Removes a snippet locally and clears its materialized file.
	 *
	 * ## OPTIONS
	 *
	 * <snippet_id>
	 * : The snippet id to remove.
	 *
	 * @param array<int, string>         $args       Positional args: <snippet_id>.
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function remove( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$row = $this->require_snippet( $args[0] ?? '' );

		$this->store()->set_status( (string) $row['snippet_id'], 'removed' );
		$this->loader()->reconcile();

		\WP_CLI::success( \sprintf( "Snippet '%s' removed locally.", $row['snippet_id'] ) );
	}

	/**
	 * Deletes every snippet on this site and purges the loader drop-in and files.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function flush( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		\WP_CLI::confirm( 'Delete ALL Atlantis snippets on this site and remove the loader?', $assoc_args );

		foreach ( $this->store()->all() as $row ) {
			$this->store()->delete( (string) $row['snippet_id'] );
		}

		$this->loader()->purge_filesystem();

		\WP_CLI::success( 'All snippets flushed.' );
	}

	/**
	 * Forces a reconcile pass (re-materialize the filesystem from the database).
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function reconcile( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->loader()->reconcile();
		\WP_CLI::success( 'Reconciled snippet files with the database.' );
	}

	/**
	 * Engages the local kill switch: disables all snippets at once.
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function disable( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		update_option( 'a8csp_atlantis_snippets_killswitch', true );
		$this->loader()->reconcile();
		\WP_CLI::success( 'Snippet kill switch ENGAGED. All snippets are disabled on this site.' );
	}

	/**
	 * Releases the local kill switch: re-enables snippet loading.
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags (unused).
	 */
	public function enable( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		update_option( 'a8csp_atlantis_snippets_killswitch', false );
		$this->loader()->reconcile();
		\WP_CLI::success( 'Snippet kill switch released. Snippets re-enabled on this site.' );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the snippet store, or aborts if the module is unavailable.
	 *
	 * @return SnippetStore
	 */
	private function store(): SnippetStore {
		return $this->require_module()->get_store();
	}

	/**
	 * Returns the loader, or aborts if the module is unavailable.
	 *
	 * @return Loader
	 */
	private function loader(): Loader {
		return $this->require_module()->get_loader();
	}

	/**
	 * Returns the Snippets module, or aborts with an error.
	 *
	 * @return \A8C\SpecialProjects\Atlantis\Modules\Snippets\Snippets
	 */
	private function require_module(): \A8C\SpecialProjects\Atlantis\Modules\Snippets\Snippets {
		$module = a8csp_atlantis_get_snippets_module();
		if ( null === $module ) {
			\WP_CLI::error( 'The Atlantis Snippets module is not active on this site.' );
		}

		return $module;
	}

	/**
	 * Resolves a snippet id to its row or aborts with an error.
	 *
	 * @param string $snippet_id The snippet id.
	 *
	 * @return array<string, mixed>
	 */
	private function require_snippet( string $snippet_id ): array {
		if ( '' === $snippet_id ) {
			\WP_CLI::error( 'A snippet id is required.' );
		}

		$row = $this->store()->get( $snippet_id );
		if ( null === $row ) {
			\WP_CLI::error( \sprintf( "Unknown snippet '%s'.", $snippet_id ) );
		}

		return $row;
	}

	// endregion
}
