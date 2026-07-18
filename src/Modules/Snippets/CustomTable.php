<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Handles database schema management for the Snippets module.
 *
 * The table is the on-site source of truth for deployed remediation snippets.
 * It lives in the database, which code deploys do not touch, so active snippets
 * survive normal deployments and filesystem resets. The materialized mu-plugin
 * files (see {@see Loader}) are a re-creatable cache of the `active` rows here.
 *
 * @since   1.3.0
 * @version 1.3.0
 */
class CustomTable {
	// region FIELDS AND CONSTANTS

	/**
	 * The name of the custom table.
	 *
	 * @var string
	 */
	protected const TABLE_NAME = 'a8csp_atlantis_snippets';

	/**
	 * Current schema version.
	 * Increment this when making schema changes.
	 *
	 * @var string
	 */
	protected const SCHEMA_VERSION = '1.0.0';

	// endregion

	// region METHODS

	/**
	 * Returns the full table name including the WordPress prefix.
	 *
	 * @return  string
	 */
	public static function get_table_name(): string {
		return $GLOBALS['wpdb']->prefix . self::TABLE_NAME;
	}

	/**
	 * Checks if the custom table exists.
	 *
	 * @return  bool
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Initializes the Custom Table component.
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'init', array( $this, 'maybe_create_table' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Checks if the table needs to be created or updated.
	 *
	 * @return  void
	 */
	public function maybe_create_table(): void {
		if ( ! self::table_exists() || $this->needs_update() ) {
			$this->update_schema();
		}
	}

	// endregion

	// region SCHEMA MANAGEMENT

	/**
	 * Creates the custom table if it does not exist, or updates it if the schema version is outdated.
	 *
	 * @return  void
	 */
	protected function update_schema(): void {
		/* @phpstan-ignore requireOnce.fileNotFound */
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = $this->get_table_definition();
		dbDelta( $sql );

		$this->update_db_version();
	}

	/**
	 * Get the table definition.
	 *
	 * @return  string
	 */
	protected function get_table_definition(): string {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE `$table_name` (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`snippet_id` varchar(191) NOT NULL,
			`version` int(11) NOT NULL DEFAULT 0,
			`code` longtext NOT NULL,
			`sha256` char(64) NOT NULL,
			`signature` text NOT NULL,
			`status` varchar(20) NOT NULL DEFAULT 'active',
			`notes` text DEFAULT NULL,
			`fail_count` int(11) NOT NULL DEFAULT 0,
			`last_error` text DEFAULT NULL,
			`expires_at` datetime DEFAULT NULL,
			`deployed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (`id`),
			UNIQUE KEY `snippet_id` (`snippet_id`),
			KEY `status` (`status`),
			KEY `expires_at` (`expires_at`)
		) $charset_collate;";
	}

	/**
	 * Checks if the database schema needs to be updated.
	 *
	 * @return  bool
	 */
	protected function needs_update(): bool {
		return \version_compare( $this->get_db_version(), self::SCHEMA_VERSION, '<' );
	}

	/**
	 * Returns the current schema version from the database.
	 *
	 * @return  string
	 */
	protected function get_db_version(): string {
		return get_option( 'a8csp_atlantis_snippets_schema_version', '0.0.0' );
	}

	/**
	 * Updates the database version to the current schema version.
	 *
	 * @return  void
	 */
	protected function update_db_version(): void {
		update_option( 'a8csp_atlantis_snippets_schema_version', self::SCHEMA_VERSION );
	}

	// endregion
}
