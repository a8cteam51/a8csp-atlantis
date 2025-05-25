<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Messages;

defined( 'ABSPATH' ) || exit;

/**
 * Handles database schema management for the Messages module.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class CustomTable {
	// region FIELDS AND CONSTANTS

	/**
	 * The name of the custom table.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var string
	 */
	protected const string TABLE_NAME = 'a8csp_atlantis_messages';

	/**
	 * Current schema version.
	 * Increment this when making schema changes.
	 *
	 * @var string Current schema version
	 */
	protected const string SCHEMA_VERSION = '1.0.0';

	// endregion

	// region METHODS

	/**
	 * Returns the full table name including the WordPress prefix.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	public static function get_table_name(): string {
		return $GLOBALS['wpdb']->prefix . self::TABLE_NAME;
	}

	/**
	 * Checks if the custom table exists.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
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
	 * @since   1.0.0
	 * @version 1.0.0
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
	 * @since   1.0.0
	 * @version 1.0.0
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
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function update_schema(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = $this->get_table_definition();
		dbDelta( $sql );

		$this->update_db_version();
	}

	/**
	 * Get the table definition
	 */
	protected function get_table_definition(): string {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE `$table_name` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `content` text NOT NULL,
            `type` varchar(255) NOT NULL,
            `status` varchar(255) NOT NULL DEFAULT 'active',
            `locations` text NOT NULL,
            `exclusions` text DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (`id`),
            KEY `title` (`title`),
            KEY `type` (`type`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) $charset_collate;";
	}

	/**
	 * Checks if the database schema needs to be updated.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	protected function needs_update(): bool {
		return version_compare( $this->get_db_version(), self::SCHEMA_VERSION, '<' );
	}

	/**
	 * Returns the current schema version from the database.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	protected function get_db_version(): string {
		return get_option( 'a8csp_atlantis_messages_schema_version', '0.0.0' );
	}

	/**
	 * Updates the database version to the current schema version.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function update_db_version(): void {
		update_option( 'a8csp_atlantis_messages_schema_version', self::SCHEMA_VERSION );
	}

	// endregion
}
