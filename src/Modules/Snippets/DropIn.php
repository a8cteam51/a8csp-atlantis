<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the self-installed mu-plugin loader drop-in and the global kill-switch
 * marker.
 *
 * Atlantis owns the loader file the way caching plugins own object-cache.php:
 * it is written (and rewritten on drift) from the shipped stub, never committed
 * to any site repo. The `.disabled` marker is a filesystem-only kill switch the
 * loader honors without needing the database.
 *
 * @since   1.3.0
 * @version 1.3.0
 */
class DropIn {
	// region FIELDS AND CONSTANTS

	/**
	 * Filename of the managed loader drop-in placed at the top level of mu-plugins.
	 *
	 * @var string
	 */
	public const LOADER_FILENAME = 'atlantis-snippets-loader.php';

	/**
	 * Option that engages the global kill switch when truthy.
	 *
	 * @var string
	 */
	private const KILLSWITCH_OPTION = 'a8csp_atlantis_snippets_killswitch';

	// endregion

	// region METHODS

	/**
	 * Writes the managed loader drop-in if it is missing or has drifted from the
	 * shipped stub.
	 *
	 * @param   \WP_Filesystem_Base $filesystem The filesystem instance.
	 * @param   string              $mu_dir     The mu-plugins directory.
	 *
	 * @return  void
	 */
	public function ensure_loader( \WP_Filesystem_Base $filesystem, string $mu_dir ): void {
		if ( ! $filesystem->is_dir( $mu_dir ) ) {
			$filesystem->mkdir( $mu_dir, FS_CHMOD_DIR );
		}

		$desired = $filesystem->get_contents( __DIR__ . '/stubs/snippets-loader.php' );
		if ( ! is_string( $desired ) ) {
			return;
		}

		$target  = $mu_dir . '/' . self::LOADER_FILENAME;
		$current = $filesystem->exists( $target ) ? $filesystem->get_contents( $target ) : false;
		if ( $current === $desired ) {
			return;
		}

		$filesystem->put_contents( $target, $desired, FS_CHMOD_FILE );
	}

	/**
	 * Creates or removes the `.disabled` marker based on the stored kill-switch
	 * state.
	 *
	 * @param   \WP_Filesystem_Base $filesystem    The filesystem instance.
	 * @param   string              $snippets_dir  The snippets directory.
	 *
	 * @return  void
	 */
	public function apply_killswitch( \WP_Filesystem_Base $filesystem, string $snippets_dir ): void {
		$marker = $snippets_dir . '/.disabled';

		if ( $this->is_killswitch_engaged() ) {
			if ( ! $filesystem->exists( $marker ) ) {
				$filesystem->put_contents( $marker, "Atlantis snippet loader disabled.\n", FS_CHMOD_FILE );
			}
		} elseif ( $filesystem->exists( $marker ) ) {
			$filesystem->delete( $marker );
		}
	}

	/**
	 * Removes the loader drop-in file.
	 *
	 * @param   \WP_Filesystem_Base $filesystem The filesystem instance.
	 * @param   string              $mu_dir     The mu-plugins directory.
	 *
	 * @return  void
	 */
	public function purge_loader( \WP_Filesystem_Base $filesystem, string $mu_dir ): void {
		$loader = $mu_dir . '/' . self::LOADER_FILENAME;
		if ( $filesystem->exists( $loader ) ) {
			$filesystem->delete( $loader );
		}
	}

	/**
	 * Whether the global kill switch is engaged. Sourced from a local option that
	 * the Autoupdates-style central settings channel (or an operator) can flip.
	 *
	 * @return  bool
	 */
	private function is_killswitch_engaged(): bool {
		return (bool) get_option( self::KILLSWITCH_OPTION, false );
	}

	// endregion
}
