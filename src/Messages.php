<?php

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\MessagesList;

defined( 'ABSPATH' ) || exit;

/**
 * Class AccessLog
 * Handles logging and displaying access records for protected media files.
 *
 * @package A8C\SpecialProjects\Atlantis
 */
class Messages {
	/**
	 * Database table name for Atlantis messages.
	 *
	 * @var string
	 */
	public const TABLE_NAME = 'atlantis_messages';

	/**
	 * List table instance
	 *
	 * @var MessagesList
	 */
	private $list_table;

	/**
	 * Initialize the access log functionality.
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Only run table creation on plugin activation or when forced
		add_action( 'init', array( $this, 'maybe_create_table' ) );
	}

	/**
	 * Check if table needs to be created and create it if necessary.
	 *
	 * @return void
	 */
	public function maybe_create_table(): void {
		// Check if we've already created the table
		$table_version = get_option( 'atlantis_messages_table_version' );

		// If table version doesn't exist or is different from current plugin version, create/update table
		if ( ! $table_version || version_compare( $table_version, a8csp_atlantis_get_plugin_metadata( 'Version' ), '<' ) ) {
			$this->create_table();

			// Store current version to prevent future checks
			update_option( 'atlantis_messages_table_version', a8csp_atlantis_get_plugin_metadata( 'Version' ) );
		}
	}

	/**
	 * Create the messages database table if it doesn't exist.
	 *
	 * @return void
	 */
	public function create_table(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// First check if table already exists to avoid unnecessary dbDelta calls
		if ( self::table_exists() ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			message_name varchar(255) NOT NULL,
			message_content text NOT NULL,
			message_type varchar(255) NOT NULL,
			message_status varchar(255) NOT NULL,
			message_location varchar(255) NOT NULL,
			message_time datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY message_name (message_name)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Check if the messages table exists.
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Insert a message into the database.
	 *
	 * @param int    $message_id       The ID of the message.
	 * @param string $message_name     The name of the message.
	 * @param string $message_content  The content of the message.
	 * @param string $message_type     The type of the message.
	 * @param string $message_status   The status of the message.
	 * @param string $message_location The location of the message.
	 *
	 * @return bool|int False on failure, number of rows affected on success.
	 */
	public function insert_message( int $message_id, string $message_name, string $message_content, string $message_type, string $message_status, string $message_location ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		if ( ! self::table_exists() ) {
			$this->create_table();
		}

		return $wpdb->insert(
			$table_name,
			array(
				'message_id'       => $message_id,
				'message_name'     => $message_name,
				'message_content'  => $message_content,
				'message_type'     => $message_type,
				'message_status'   => $message_status,
				'message_location' => $message_location,
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Add the access logs page to the Media submenu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page(
				'a8csp-atlantis',
				__( 'Atlantis Messages', 'atlantis' ),
				__( 'Atlantis Messages', 'atlantis' ),
				'edit_posts',
				'atlantis-messages',
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Get the list table instance.
	 *
	 * @return MessagesList The list table instance.
	 */
	private function get_list_table(): MessagesList {
		if ( null === $this->list_table ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
			$this->list_table = new MessagesList();
		}
		return $this->list_table;
	}

	/**
	 * Render the access logs admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'atlantis' ) );
		}

		$list_table = $this->get_list_table();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Atlantis Messages', 'atlantis' ); ?></h1>
			
			<hr class="wp-header-end">

			<form id="atlantis-messages-filter" method="get">
				<input type="hidden" name="page" value="atlantis-messages" />
		<?php
		$list_table->prepare_items();
		$list_table->search_box( __( 'Search Messages', 'atlantis' ), 'atlantis-messages' );
		$list_table->display();
		?>
			</form>
		</div>
		<?php
	}
}
