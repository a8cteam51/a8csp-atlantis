<?php

namespace A8C\SpecialProjects\Atlantis\Modules\Messages;

use A8C\SpecialProjects\Atlantis\MessagesList;
use A8C\SpecialProjects\Atlantis\MessagesSchema;
use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Messages Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Messages extends AbstractModule {
	// region FIELDS AND CONSTANTS

	/**
	 * List table instance
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var MessagesList
	 */
	private $list_table;

	/**
	 * Notifications instance
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var Notifications
	 */
	public Notifications $notifications;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_name(): string {
		return 'messages';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_description(): string {
		return __( 'Handles admin messages and notifications.', 'a8csp-atlantis' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function is_active(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function register_settings(): void {
		// No settings. Always active.
	}

	/**
	 * Initialize the messages functionality.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	protected function initialize(): void {
		$this->notifications = new Notifications();
		$this->notifications->initialize();

		add_action( 'a8csp/atlantis/admin_menu_registered', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'save_message' ) );
		add_action( 'admin_init', array( $this, 'handle_bulk_actions' ) );

		// Only run table creation on plugin activation or when forced
		add_action( 'init', array( $this, 'maybe_create_table' ) );
	}

	// endregion

	/**
	 * Check if table needs to be created and create it if necessary.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function maybe_create_table(): void {
		if ( MessagesSchema::needs_update() ) {
			MessagesSchema::update_schema();
		}
	}

	/**
	 * Create the messages database table if it doesn't exist.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function create_table(): void {
		MessagesSchema::update_schema();
	}

	/**
	 * Get count of active messages.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return int Number of active messages.
	 */
	private function get_active_messages_count(): int {
		global $wpdb;
		$table_name = MessagesSchema::get_table_name();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				//phpcs:ignore
				"SELECT COUNT(*) FROM {$table_name} WHERE message_status = %s",
				'active'
			)
		);
	}

	/**
	 * Add the messages page to the Atlantis submenu.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		if ( a8csp_atlantis_is_automattician() ) {
			$active_count = $this->get_active_messages_count();

			$menu_title = sprintf(
				/* translators: %s: Number of active messages */
				__( 'Messages %s', 'a8csp-atlantis' ),
				$active_count > 0 ? '<span class="update-plugins count-' . $active_count . '"><span class="plugin-count">' . number_format_i18n( $active_count ) . '</span></span>' : ''
			);

			add_submenu_page(
				'a8csp-atlantis',
				__( 'Atlantis Messages', 'a8csp-atlantis' ),
				$menu_title,
				'manage_options',
				'a8csp-atlantis-messages',
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Get the list table instance.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
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
	 * Render the messages admin page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! a8csp_atlantis_is_automattician() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'a8csp-atlantis' ) );
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
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Atlantis Messages', 'a8csp-atlantis' ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Add New', 'a8csp-atlantis' ); ?></a>

			<hr class="wp-header-end">

			<form id="atlantis-messages-filter" method="get">
				<input type="hidden" name="page" value="atlantis-messages" />
		<?php
		$list_table->prepare_items();
		$list_table->search_box( __( 'Search Messages', 'a8csp-atlantis' ), 'atlantis-messages' );
		$list_table->display();
		?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue message form assets.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array $current_location Current included locations.
	 * @param array $current_exclude  Current excluded locations.
	 *
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
			array( 'jquery' ),
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
	 * Fetch a single message from the database.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $id The message ID.
	 *
	 * @return object|null The message object or null if not found.
	 */
	private function fetch_single_message( int $id ): object|null {
		global $wpdb;
		$message = $wpdb->get_row(
			$wpdb->prepare(
				//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'SELECT * FROM ' . MessagesSchema::get_table_name() . ' WHERE id = %d',
				$id
			)
		);

		return $message;
	}

	/**
	 * Render the single message view for adding/editing messages.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $id The message ID if editing, 0 for new message.
	 *
	 * @return void
	 */
	private function render_single_message( int $id = 0 ): void {
		$message = null;
		if ( $id > 0 ) {
			$message = $this->fetch_single_message( $id );
		}

		$locations        = $this->get_admin_locations();
		$current_location = $message ? maybe_unserialize( $message->message_location ) : array();
		$current_exclude  = $message && ! empty( $message->message_exclude ) ? maybe_unserialize( $message->message_exclude ) : array();

		$message_content = a8csp_atlantis_decrypt_data( $message->message_content );

		if ( ! is_wp_error( $message_content ) ) {
			$message->message_content = $message_content;
		}

		// Enqueue assets
		$this->enqueue_message_form_assets( $current_location, $current_exclude );

		// Load the template
		include A8CSP_ATLANTIS_DIR_PATH . 'templates/admin/message-form.php';
	}

	/**
	 * Handle saving messages.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function save_message(): void {
		if ( ! isset( $_POST['action'] ) || 'save_message' !== $_POST['action'] ) {
			return;
		}

		if ( ! check_admin_referer( 'atlantis_message_edit', 'atlantis_message_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'a8csp-atlantis' ) );
		}

		if ( ! a8csp_atlantis_is_automattician() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'a8csp-atlantis' ) );
		}

		$message_id       = isset( $_POST['message_id'] ) ? intval( $_POST['message_id'] ) : 0;
		$message_name     = isset( $_POST['message_name'] ) ? sanitize_text_field( wp_unslash( $_POST['message_name'] ) ) : '';
		$message_content  = isset( $_POST['message_content'] ) ? wp_kses_post( wp_unslash( $_POST['message_content'] ) ) : '';
		$message_type     = isset( $_POST['message_type'] ) ? sanitize_text_field( wp_unslash( $_POST['message_type'] ) ) : '';
		$message_status   = isset( $_POST['message_status'] ) ? sanitize_text_field( wp_unslash( $_POST['message_status'] ) ) : '';
		$message_location = isset( $_POST['message_location_include'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['message_location_include'] ) ) : array();
		$message_exclude  = isset( $_POST['message_location_exclude'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['message_location_exclude'] ) ) : array();

		if ( empty( $message_name ) || empty( $message_content ) || empty( $message_type ) || empty( $message_status ) || empty( $message_location ) ) {
			wp_die( esc_html__( 'All fields are required.', 'a8csp-atlantis' ) );
		}

		global $wpdb;
		$table_name = MessagesSchema::get_table_name();

		$data = array(
			'message_name'     => $message_name,
			'message_content'  => a8csp_atlantis_encrypt_data( $message_content ),
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
			wp_die( esc_html__( 'Error saving message.', 'a8csp-atlantis' ) );
		}

		wp_safe_redirect( remove_query_arg( array( 'action', 'id' ) ) );
		exit;
	}

	/**
	 * Update the status of messages.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array  $message_ids Array of message IDs to update.
	 * @param string $status     New status value.
	 *
	 * @return void
	 */
	private function update_messages_status( array $message_ids, string $status ): void {
		global $wpdb;

		if ( empty( $message_ids ) ) {
			return;
		}

		$placeholders = array_fill( 0, count( $message_ids ), '%d' );
		$placeholders = implode( ',', $placeholders );

		$wpdb->query(
			$wpdb->prepare(
				//phpcs:ignore
				'UPDATE ' . MessagesSchema::get_table_name() . " SET message_status = %s WHERE id IN ({$placeholders})",
				array_merge( array( $status ), $message_ids )
			)
		);
	}

	/**
	 * Get available admin page locations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return array Array of admin page locations with labels.
	 */
	private function get_admin_locations(): array {
		global $menu, $submenu;

		$locations = array(
			'all'              => __( 'All Locations', 'a8csp-atlantis' ),
			'all_post_editors' => __( 'All Post Editors', 'a8csp-atlantis' ),
		);

		// Top-level menu items
		foreach ( $menu as $menu_item ) {
			if ( ! empty( $menu_item[0] ) && ! empty( $menu_item[2] ) ) {
				$menu_slug  = $menu_item[2];
				$menu_title = preg_replace( '/\s\d+$/', '', wp_strip_all_tags( $menu_item[0] ) );

				// Keep the .php extension for exact matching
				$locations[ $menu_slug ] = $menu_title;

				// Submenu items
				if ( isset( $submenu[ $menu_slug ] ) ) {
					foreach ( $submenu[ $menu_slug ] as $submenu_item ) {
						if ( ! empty( $submenu_item[0] ) && ! empty( $submenu_item[2] ) ) {
							$submenu_slug  = $submenu_item[2];
							$submenu_title = wp_strip_all_tags( $submenu_item[0] );

							// Skip if it's the same as the parent menu
							if ( $submenu_slug === $menu_slug ) {
								continue;
							}

							$screen_id = $submenu_slug;

							$locations[ $screen_id ] = $menu_title . ' → ' . preg_replace( '/\s\d+$/', '', $submenu_title );
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
				__( '%s Categories', 'a8csp-atlantis' ),
				$taxonomy->label
			);
		}

		return $locations;
	}

	/**
	 * Handle bulk actions for messages.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function handle_bulk_actions(): void {
		if ( ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['message'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'bulk-messages' ) ) {
			wp_die( esc_html__( 'Security check failed', 'a8csp-atlantis' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'a8csp-atlantis' ) );
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
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array $message_ids Array of message IDs to delete.
	 *
	 * @return void
	 */
	private function delete_messages( array $message_ids ): void {
		global $wpdb;

		if ( empty( $message_ids ) ) {
			return;
		}

		$placeholders = array_fill( 0, count( $message_ids ), '%d' );
		$placeholders = implode( ',', $placeholders );

		$wpdb->query(
			$wpdb->prepare(
				//phpcs:ignore
				"DELETE FROM " . MessagesSchema::get_table_name() . " WHERE id IN ({$placeholders})",
				$message_ids
			)
		);
	}
}
