<?php

namespace A8C\SpecialProjects\Atlantis\Modules;

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
		add_action( 'admin_init', array( $this, 'save_message' ) );

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
				'manage_options',
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

		// Check if we're viewing a single message
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( 'new' === $action || ( 'edit' === $action && $id > 0 ) ) {
			$this->render_single_message( $id );
			return;
		}

		$list_table = $this->get_list_table();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Atlantis Messages', 'atlantis' ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Add New', 'atlantis' ); ?></a>
			
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

	/**
	 * Render the single message view for adding/editing messages.
	 *
	 * @param int $id The message ID if editing, 0 for new message.
	 * @return void
	 */
	private function render_single_message( int $id = 0 ): void {
		$message = null;
		if ( $id > 0 ) {
			global $wpdb;
			$table_name = $wpdb->prefix . self::TABLE_NAME;
			$message    = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . $table_name . ' WHERE id = %d',
					$id
				)
			);
		}

		// Load the template
		include A8CSP_ATLANTIS_DIR_PATH . 'templates/admin/message-form.php';
	}

	/**
	 * Handle saving messages.
	 *
	 * @return void
	 */
	public function save_message(): void {
		if ( ! isset( $_POST['action'] ) || 'save_message' !== $_POST['action'] ) {
			return;
		}

		if ( ! check_admin_referer( 'atlantis_message_edit', 'atlantis_message_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'atlantis' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'atlantis' ) );
		}

		$message_id       = isset( $_POST['message_id'] ) ? intval( $_POST['message_id'] ) : 0;
		$message_name     = isset( $_POST['message_name'] ) ? sanitize_text_field( wp_unslash( $_POST['message_name'] ) ) : '';
		$message_content  = isset( $_POST['message_content'] ) ? wp_kses_post( wp_unslash( $_POST['message_content'] ) ) : '';
		$message_type     = isset( $_POST['message_type'] ) ? sanitize_text_field( wp_unslash( $_POST['message_type'] ) ) : '';
		$message_status   = isset( $_POST['message_status'] ) ? sanitize_text_field( wp_unslash( $_POST['message_status'] ) ) : '';
		$message_location = isset( $_POST['message_location'] ) ? sanitize_text_field( wp_unslash( $_POST['message_location'] ) ) : '';

		if ( empty( $message_name ) || empty( $message_content ) || empty( $message_type ) || empty( $message_status ) || empty( $message_location ) ) {
			wp_die( esc_html__( 'All fields are required.', 'atlantis' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		if ( $message_id > 0 ) {
			// Update existing message
			$wpdb->update(
				$table_name,
				array(
					'message_name'     => $message_name,
					'message_content'  => $message_content,
					'message_type'     => $message_type,
					'message_status'   => $message_status,
					'message_location' => $message_location,
				),
				array( 'id' => $message_id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new message
			$wpdb->insert(
				$table_name,
				array(
					'message_name'     => $message_name,
					'message_content'  => $message_content,
					'message_type'     => $message_type,
					'message_status'   => $message_status,
					'message_location' => $message_location,
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
		}

		wp_safe_redirect( remove_query_arg( array( 'action', 'id' ) ) );
		exit;
	}
}
