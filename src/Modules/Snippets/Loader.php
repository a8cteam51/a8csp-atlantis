<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Materializes deployed snippets to disk and keeps the filesystem reconciled
 * with the database (the source of truth).
 *
 * Snippets are executed by a small, self-installed mu-plugin *loader drop-in*
 * (see stubs/snippets-loader.php) rather than from within Atlantis itself. This
 * buys two things: the snippets load *before* regular plugins (so they can
 * intercept hooks Atlantis would be too late for), and they keep working even
 * while Atlantis is mid-update. Atlantis verifies each snippet's signature
 * *before* writing its file, so the loader can trust any file it finds.
 *
 * @since   1.3.0
 * @version 1.3.0
 */
class Loader {
	// region FIELDS AND CONSTANTS

	/**
	 * Directory (under mu-plugins) that holds the materialized snippet files.
	 *
	 * @var string
	 */
	private const SNIPPETS_DIRNAME = 'atlantis-snippets';

	/**
	 * Number of recorded runtime fatals after which a snippet is auto-quarantined.
	 *
	 * @var int
	 */
	private const QUARANTINE_THRESHOLD = 3;

	/**
	 * The snippet data store.
	 *
	 * @var SnippetStore
	 */
	private SnippetStore $store;

	/**
	 * The loader drop-in / kill-switch manager.
	 *
	 * @var DropIn
	 */
	private DropIn $dropin;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @param   SnippetStore $store The snippet data store.
	 */
	public function __construct( SnippetStore $store ) {
		$this->store  = $store;
		$this->dropin = new DropIn();
	}

	// endregion

	// region PATHS

	/**
	 * Returns the mu-plugins directory, or null if it is not resolvable.
	 *
	 * @return  string|null
	 */
	private function mu_plugins_dir(): ?string {
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			return rtrim( WPMU_PLUGIN_DIR, '/\\' );
		}

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			return rtrim( WP_CONTENT_DIR, '/\\' ) . '/mu-plugins';
		}

		return null;
	}

	/**
	 * Returns the absolute path to the snippets directory, or null if mu-plugins
	 * cannot be resolved.
	 *
	 * @return  string|null
	 */
	private function snippets_dir(): ?string {
		$mu = $this->mu_plugins_dir();
		return null === $mu ? null : $mu . '/' . self::SNIPPETS_DIRNAME;
	}

	/**
	 * Returns the absolute path to a snippet's materialized file.
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 *
	 * @return  string|null
	 */
	private function snippet_file( string $snippet_id ): ?string {
		$dir = $this->snippets_dir();
		return null === $dir ? null : $dir . '/' . $this->safe_basename( $snippet_id ) . '.php';
	}

	/**
	 * Normalizes a snippet id to a filesystem-safe basename. Ids are already
	 * validated on receipt (see Snippets_Controller); this is defense in depth.
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 *
	 * @return  string
	 */
	private function safe_basename( string $snippet_id ): string {
		return (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( $snippet_id ) );
	}

	// endregion

	// region RECONCILE

	/**
	 * Makes the filesystem match the database:
	 *
	 * - ensures the loader drop-in exists;
	 * - materializes a file for every loadable (active, unexpired) snippet whose
	 *   file is missing or whose contents have drifted from the stored hash;
	 * - retires expired snippets and quarantines repeatedly-fatal ones;
	 * - removes files for any snippet that should no longer be present.
	 *
	 * Cheap enough to run on every `init`; the write paths only fire on drift.
	 *
	 * @return  void
	 */
	public function reconcile(): void {
		$dir = $this->snippets_dir();
		if ( null === $dir ) {
			return;
		}

		$filesystem = $this->get_filesystem();
		if ( null === $filesystem ) {
			return; // Not writable (e.g. non-direct filesystem); nothing we can do.
		}

		if ( ! $filesystem->is_dir( $dir ) ) {
			$filesystem->mkdir( $dir, FS_CHMOD_DIR );
		}

		$mu_dir = $this->mu_plugins_dir();
		if ( null !== $mu_dir ) {
			$this->dropin->ensure_loader( $filesystem, $mu_dir );
		}

		$this->dropin->apply_killswitch( $filesystem, $dir );
		$this->retire_and_quarantine();

		$loadable = $this->store->all_loadable();
		$expected = array();

		foreach ( $loadable as $row ) {
			$snippet_id = (string) $row['snippet_id'];
			$file       = $this->snippet_file( $snippet_id );
			if ( null === $file ) {
				continue;
			}

			$expected[ basename( $file ) ] = true;

			$current = $filesystem->exists( $file ) ? $filesystem->get_contents( $file ) : false;
			if ( is_string( $current ) && hash( 'sha256', $current ) === $row['sha256'] ) {
				continue; // Already materialized and intact.
			}

			$filesystem->put_contents( $file, (string) $row['code'], FS_CHMOD_FILE );
		}

		$this->remove_unexpected_files( $filesystem, $dir, $expected );
	}

	/**
	 * Deletes any *.php file in the snippets dir that is not in the expected set.
	 *
	 * @param   \WP_Filesystem_Base $filesystem The filesystem instance.
	 * @param   string              $dir        The snippets directory.
	 * @param   array<string, bool> $expected   Map of expected basenames.
	 *
	 * @return  void
	 */
	private function remove_unexpected_files( \WP_Filesystem_Base $filesystem, string $dir, array $expected ): void {
		$existing = glob( $dir . '/*.php' );
		if ( false === $existing ) {
			return;
		}

		foreach ( $existing as $path ) {
			if ( ! isset( $expected[ basename( $path ) ] ) ) {
				$filesystem->delete( $path );
			}
		}
	}

	/**
	 * Flags expired snippets as `expired` and auto-quarantines snippets whose
	 * recorded fatal count has crossed the threshold.
	 *
	 * @return  void
	 */
	private function retire_and_quarantine(): void {
		foreach ( $this->store->all() as $row ) {
			$snippet_id = (string) $row['snippet_id'];

			if ( 'active' === $row['status'] && ! is_null( $row['expires_at'] ) && '' !== $row['expires_at'] && $row['expires_at'] <= current_time( 'mysql', true ) ) {
				$this->store->set_status( $snippet_id, 'expired' );
				continue;
			}

			if ( 'active' === $row['status'] && (int) $row['fail_count'] >= self::QUARANTINE_THRESHOLD ) {
				$this->store->set_status( $snippet_id, 'quarantined' );
			}
		}
	}

	// endregion

	// region FILESYSTEM

	/**
	 * Returns a direct WP_Filesystem instance, or null when direct filesystem
	 * access is unavailable (in which case snippets cannot be materialized and
	 * the module is environmentally disabled — see Snippets::is_disabled()).
	 *
	 * @return  \WP_Filesystem_Base|null
	 */
	private function get_filesystem(): ?\WP_Filesystem_Base {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( 'direct' !== get_filesystem_method() ) {
			return null;
		}

		WP_Filesystem();
		return $wp_filesystem;
	}

	/**
	 * Whether snippet files can be written on this host (direct filesystem access
	 * to a writable mu-plugins directory).
	 *
	 * @return  bool
	 */
	public function can_materialize(): bool {
		$mu_dir = $this->mu_plugins_dir();
		if ( null === $mu_dir ) {
			return false;
		}

		$filesystem = $this->get_filesystem();
		if ( null === $filesystem ) {
			return false;
		}

		return $filesystem->is_dir( $mu_dir ) ? $filesystem->is_writable( $mu_dir ) : $filesystem->is_writable( dirname( $mu_dir ) );
	}

	// endregion

	// region CLEANUP

	/**
	 * Removes the loader drop-in and every materialized snippet file. Used on
	 * module teardown / plugin uninstall so nothing outlives Atlantis.
	 *
	 * @return  void
	 */
	public function purge_filesystem(): void {
		$filesystem = $this->get_filesystem();
		if ( null === $filesystem ) {
			return;
		}

		$dir = $this->snippets_dir();
		if ( null !== $dir && $filesystem->is_dir( $dir ) ) {
			$filesystem->delete( $dir, true );
		}

		$mu_dir = $this->mu_plugins_dir();
		if ( null !== $mu_dir ) {
			$this->dropin->purge_loader( $filesystem, $mu_dir );
		}
	}

	// endregion
}
