<?php

namespace A8C\SpecialProjects\Atlantis;

defined( 'ABSPATH' ) || exit;

/**
 * Class MessagesSchema
 * Handles database schema management for the Messages module
 */
class MessagesSchema {
	/**
	 * Current schema version
	 * Increment this when making schema changes
	 */
	const SCHEMA_VERSION = '1.0.2';

	/**
	 * Table name
	 */
	const TABLE_NAME = 'atlantis_messages';

	/**
	 * Get the full table name with prefix
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Get the current schema version from the database
	 */
	public static function get_db_version(): string {
		return get_option( 'atlantis_messages_schema_version', '0' );
	}

	/**
	 * Update the schema version in the database
	 */
	public static function update_db_version(): void {
		update_option( 'atlantis_messages_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Get the table definition
	 */
	public static function get_table_definition(): string {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message_name varchar(255) NOT NULL,
            message_content text NOT NULL,
            message_type varchar(255) NOT NULL,
            message_status varchar(255) NOT NULL,
            message_location text NOT NULL,
            message_exclude text DEFAULT NULL,
            message_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY message_name (message_name)
        ) $charset_collate;";
	}

	/**
	 * Check if the messages table exists.
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Check if schema update is required
	 */
	public static function needs_update(): bool {
		return version_compare( self::get_db_version(), self::SCHEMA_VERSION, '<' );
	}

	/**
	 * Create or update the table schema
	 */
	public static function update_schema(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = self::get_table_definition();
		dbDelta( $sql );

		self::update_db_version();
	}
}
