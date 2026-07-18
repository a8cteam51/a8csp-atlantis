<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Data-access layer for deployed snippets.
 *
 * Thin wrapper around the custom table (the on-site source of truth). All reads
 * and writes to snippet state go through here.
 *
 * @since   1.3.0
 * @version 1.3.0
 */
class SnippetStore {
	// region READS

	/**
	 * Returns a single snippet row by its stable id, or null if not found.
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 *
	 * @return  array<string, mixed>|null
	 */
	public function get( string $snippet_id ): ?array {
		global $wpdb;

		if ( ! CustomTable::table_exists() ) {
			return null;
		}

		$table = CustomTable::get_table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `$table` WHERE snippet_id = %s", $snippet_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Returns all snippet rows, newest first.
	 *
	 * @return  array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;

		if ( ! CustomTable::table_exists() ) {
			return array();
		}

		$table = CustomTable::get_table_name();
		$rows  = $wpdb->get_results( "SELECT * FROM `$table` ORDER BY updated_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Returns the rows that should currently be materialized and loaded:
	 * status `active` and not past their expiry.
	 *
	 * @return  array<int, array<string, mixed>>
	 */
	public function all_loadable(): array {
		global $wpdb;

		if ( ! CustomTable::table_exists() ) {
			return array();
		}

		$table = CustomTable::get_table_name();
		$now   = current_time( 'mysql', true );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `$table` WHERE status = 'active' AND ( expires_at IS NULL OR expires_at > %s ) ORDER BY snippet_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	// endregion

	// region WRITES

	/**
	 * Inserts or updates a snippet, returning the stored row on success or a
	 * WP_Error on a replayed/stale version or a database failure.
	 *
	 * @param   array<string, mixed> $data The snippet data: snippet_id (string),
	 *                                     version (int), code (raw PHP bytes),
	 *                                     sha256 (hex), signature (base64), notes
	 *                                     (string|null), expires_at (UTC datetime|null).
	 *
	 * @return  array<string, mixed>|\WP_Error
	 */
	public function upsert( array $data ): array|\WP_Error {
		global $wpdb;

		$existing = $this->get( $data['snippet_id'] );
		if ( is_array( $existing ) && (int) $existing['version'] >= (int) $data['version'] ) {
			return new \WP_Error(
				'a8csp_atlantis_snippet_stale_version',
				sprintf(
					/* translators: 1: incoming version, 2: stored version */
					__( 'Refusing snippet: incoming version %1$d is not newer than stored version %2$d.', 'a8csp-atlantis' ),
					(int) $data['version'],
					(int) $existing['version']
				)
			);
		}

		$row = array(
			'snippet_id' => $data['snippet_id'],
			'version'    => (int) $data['version'],
			'code'       => $data['code'],
			'sha256'     => $data['sha256'],
			'signature'  => $data['signature'],
			'status'     => 'active',
			'notes'      => $data['notes'] ?? null,
			'fail_count' => 0,
			'last_error' => null,
			'expires_at' => $data['expires_at'] ?? null,
		);

		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

		if ( is_array( $existing ) ) {
			$result = $wpdb->update( CustomTable::get_table_name(), $row, array( 'snippet_id' => $data['snippet_id'] ), $formats, array( '%s' ) );
		} else {
			$result = $wpdb->insert( CustomTable::get_table_name(), $row, $formats );
		}

		if ( false === $result ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_db_error', __( 'Failed to persist the snippet.', 'a8csp-atlantis' ) );
		}

		$stored = $this->get( $data['snippet_id'] );
		return is_array( $stored ) ? $stored : new \WP_Error( 'a8csp_atlantis_snippet_db_error', __( 'Snippet vanished after write.', 'a8csp-atlantis' ) );
	}

	/**
	 * Sets a snippet's status (e.g. `removed`, `quarantined`, `expired`).
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 * @param   string $status     The new status.
	 *
	 * @return  bool
	 */
	public function set_status( string $snippet_id, string $status ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			CustomTable::get_table_name(),
			array( 'status' => $status ),
			array( 'snippet_id' => $snippet_id ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Marks a snippet removed at (at least) the given version, bumping the stored
	 * version so an older deploy cannot be replayed to bring it back.
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 * @param   int    $version    The removal version.
	 *
	 * @return  bool
	 */
	public function mark_removed( string $snippet_id, int $version ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			CustomTable::get_table_name(),
			array(
				'status'  => 'removed',
				'version' => $version,
			),
			array( 'snippet_id' => $snippet_id ),
			array( '%s', '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Records a runtime failure for a snippet: increments its fail counter and
	 * stores the last error message.
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 * @param   string $error      The error message.
	 *
	 * @return  void
	 */
	public function record_failure( string $snippet_id, string $error ): void {
		global $wpdb;

		$table = CustomTable::get_table_name();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$table` SET fail_count = fail_count + 1, last_error = %s WHERE snippet_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$error,
				$snippet_id
			)
		);
	}

	/**
	 * Permanently deletes a snippet row.
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 *
	 * @return  bool
	 */
	public function delete( string $snippet_id ): bool {
		global $wpdb;

		return false !== $wpdb->delete(
			CustomTable::get_table_name(),
			array( 'snippet_id' => $snippet_id ),
			array( '%s' )
		);
	}

	// endregion
}
