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
		add_action( 'a8csp/atlantis/admin_menu_registered', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'save_message' ) );
		add_action( 'admin_init', array( $this, 'handle_bulk_actions' ) );

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
			message_location text NOT NULL,
			message_exclude text DEFAULT NULL,
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
	 * Get count of active messages.
	 *
	 * @return int Number of active messages.
	 */
	private function get_active_messages_count(): int {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE message_status = %s",
				'active'
			)
		);
	}

	/**
	 * Add the access logs page to the Media submenu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		if ( a8csp_atlantis_is_user_automattician() ) {
			$active_count = $this->get_active_messages_count();

			$menu_title = sprintf(
				/* translators: %s: Number of active messages */
				__( 'Messages %s', 'atlantis' ),
				$active_count > 0 ? '<span class="update-plugins count-' . $active_count . '"><span class="plugin-count">' . number_format_i18n( $active_count ) . '</span></span>' : ''
			);

			add_submenu_page(
				'a8csp-atlantis',
				__( 'Atlantis Messages', 'atlantis' ),
				$menu_title,
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
		if ( ! a8csp_atlantis_is_user_automattician() ) {
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
	 * Enqueue message form assets.
	 *
	 * @param array $current_location Current included locations.
	 * @param array $current_exclude  Current excluded locations.
	 * @return void
	 */
	private function enqueue_message_form_assets( $current_location = array(), $current_exclude = array() ): void {
		wp_enqueue_style(
			'atlantis-message-form',
			A8CSP_ATLANTIS_DIR_URL . 'assets/css/build/message-form.css',
			array(),
			a8csp_atlantis_get_plugin_metadata( 'Version' )
		);

		wp_enqueue_script(
			'atlantis-message-form',
			A8CSP_ATLANTIS_DIR_URL . 'assets/js/build/message-form.js',
			array(),
			a8csp_atlantis_get_plugin_metadata( 'Version' ),
			true
		);

		// Pass data to JavaScript
		wp_localize_script(
			'atlantis-message-form',
			'atlantisLocations',
			array(
				'locations' => $this->get_admin_locations(),
				'include'   => $current_location,
				'exclude'   => $current_exclude,
			)
		);
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
			$message = $wpdb->get_row(
				$wpdb->prepare(
					//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					"SELECT * FROM {$wpdb->prefix}" . self::TABLE_NAME . ' WHERE id = %d',
					$id
				)
			);
		}

		$locations        = $this->get_admin_locations();
		$current_location = $message ? maybe_unserialize( $message->message_location ) : array();
		$current_exclude  = $message && ! empty( $message->message_exclude ) ? maybe_unserialize( $message->message_exclude ) : array();

		// Enqueue assets
		$this->enqueue_message_form_assets( $current_location, $current_exclude );

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

		if ( ! a8csp_atlantis_is_user_automattician() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'atlantis' ) );
		}

		$message_id       = isset( $_POST['message_id'] ) ? intval( $_POST['message_id'] ) : 0;
		$message_name     = isset( $_POST['message_name'] ) ? sanitize_text_field( wp_unslash( $_POST['message_name'] ) ) : '';
		$message_content  = isset( $_POST['message_content'] ) ? wp_kses_post( wp_unslash( $_POST['message_content'] ) ) : '';
		$message_type     = isset( $_POST['message_type'] ) ? sanitize_text_field( wp_unslash( $_POST['message_type'] ) ) : '';
		$message_status   = isset( $_POST['message_status'] ) ? sanitize_text_field( wp_unslash( $_POST['message_status'] ) ) : '';
		$message_location = isset( $_POST['message_location_include'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['message_location_include'] ) ) : array();
		$message_exclude  = isset( $_POST['message_location_exclude'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['message_location_exclude'] ) ) : array();

		if ( empty( $message_name ) || empty( $message_content ) || empty( $message_type ) || empty( $message_status ) || empty( $message_location ) ) {
			wp_die( esc_html__( 'All fields are required.', 'atlantis' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$data = array(
			'message_name'     => $message_name,
			'message_content'  => $message_content,
			'message_type'     => $message_type,
			'message_status'   => $message_status,
			'message_location' => maybe_serialize( $message_location ),
			'message_exclude'  => ! empty( $message_exclude ) ? maybe_serialize( $message_exclude ) : null,
		);

		$format = array(
			'%s', // message_name
			'%s', // message_content
			'%s', // message_type
			'%s', // message_status
			'%s', // message_location
			'%s', // message_exclude
		);

		if ( $message_id > 0 ) {
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'id' => $message_id ),
				$format,
				array( '%d' )
			);
		} else {
			$result = $wpdb->insert(
				$table_name,
				$data,
				$format
			);
		}

		if ( false === $result ) {
			wp_die( esc_html__( 'Error saving message.', 'atlantis' ) );
		}

		wp_safe_redirect( remove_query_arg( array( 'action', 'id' ) ) );
		exit;
	}

	/**
	 * Update the status of messages.
	 *
	 * @param array  $message_ids Array of message IDs to update.
	 * @param string $status     New status value.
	 * @return void
	 */
	private function update_messages_status( array $message_ids, string $status ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		if ( empty( $message_ids ) ) {
			return;
		}

		$placeholders = array_fill( 0, count( $message_ids ), '%d' );
		$placeholders = implode( ',', $placeholders );

		$wpdb->query(
			$wpdb->prepare(
				//phpcs:ignore
				"UPDATE {$wpdb->prefix}" . self::TABLE_NAME . " SET message_status = %s WHERE id IN ({$placeholders})",
				array_merge( array( $status ), $message_ids )
			)
		);
	}

	/**
	 * Get available admin page locations.
	 *
	 * @return array Array of admin page locations with labels.
	 */
	private function get_admin_locations(): array {
		global $menu, $submenu;

		$locations = array(
			'all' => __( 'All Locations', 'atlantis' ),
		);

		// Top-level menu items
		foreach ( $menu as $menu_item ) {
			if ( ! empty( $menu_item[0] ) && ! empty( $menu_item[2] ) ) {
				$menu_slug  = $menu_item[2];
				$menu_title = strip_tags( $menu_item[0] );

				// Keep the .php extension for exact matching
				$locations[ $menu_slug ] = $menu_title;

				// Submenu items
				if ( isset( $submenu[ $menu_slug ] ) ) {
					foreach ( $submenu[ $menu_slug ] as $submenu_item ) {
						if ( ! empty( $submenu_item[0] ) && ! empty( $submenu_item[2] ) ) {
							$submenu_slug  = $submenu_item[2];
							$submenu_title = strip_tags( $submenu_item[0] );

							// Skip if it's the same as the parent menu
							if ( $submenu_slug === $menu_slug ) {
								continue;
							}

							$screen_id = $submenu_slug;

							$locations[ $screen_id ] = $menu_title . ' -> ' . $submenu_title;
						}
					}
				}
			}
		}

		// Custom post type screens
		$post_types = get_post_types( array( 'show_in_menu' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( ! in_array( $post_type->name, array( 'post', 'page' ), true ) ) {
				$screen_id               = 'edit.php?post_type=' . $post_type->name;
				$locations[ $screen_id ] = $post_type->label;
			}
		}

		// Taxonomy screens
		$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			$screen_id               = 'edit-tags.php?taxonomy=' . $taxonomy->name;
			$locations[ $screen_id ] = sprintf(
				/* translators: %s: Taxonomy label */
				__( '%s Categories', 'atlantis' ),
				$taxonomy->label
			);
		}

		return $locations;
	}

	/**
	 * Handle bulk actions for messages.
	 *
	 * @return void
	 */
	public function handle_bulk_actions(): void {
		if ( ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['message'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'bulk-messages' ) ) {
			wp_die( esc_html__( 'Security check failed', 'atlantis' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'atlantis' ) );
		}

		$action      = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
		$message_ids = array_map( 'intval', (array) $_REQUEST['message'] );

		switch ( $action ) {
			case 'delete':
				$this->delete_messages( $message_ids );
				break;
			case 'activate':
				$this->update_messages_status( $message_ids, 'active' );
				break;
			case 'deactivate':
				$this->update_messages_status( $message_ids, 'inactive' );
				break;
		}

		wp_safe_redirect( remove_query_arg( array( 'action', 'message', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Delete messages from the database.
	 *
	 * @param array $message_ids Array of message IDs to delete.
	 * @return void
	 */
	private function delete_messages( array $message_ids ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		if ( empty( $message_ids ) ) {
			return;
		}

		$placeholders = array_fill( 0, count( $message_ids ), '%d' );
		$placeholders = implode( ',', $placeholders );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}" . self::TABLE_NAME . " WHERE id IN ({$placeholders})",
				$message_ids
			)
		);
	}
}
