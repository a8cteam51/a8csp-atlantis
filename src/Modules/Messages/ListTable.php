<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Messages;

use A8C\SpecialProjects\Atlantis\Message;
use A8C\SpecialProjects\Atlantis\Messages_List_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the display of messages in an admin table format.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ListTable {
	// region METHODS

	/**
	 * Initializes the list table component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'a8csp/atlantis/admin_menu_registered', array( $this, 'register_admin_menu' ) );

		add_action( 'admin_init', array( $this, 'handle_single_actions' ), 5 );
		add_action( 'admin_init', array( $this, 'handle_bulk_actions' ), 5 );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the admin menu for outputting the list table.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_admin_menu(): void {
		$active_count = count( a8csp_atlantis_get_active_messages() );
		$count_html   = wp_sprintf(
			'<span class="update-plugins count-%1$d"><span class="plugin-count">%2$s</span></span>',
			$active_count,
			esc_html( number_format_i18n( $active_count ) )
		);

		add_submenu_page(
			'a8csp-atlantis',
			_x( 'Atlantis Messages', 'page title', 'a8csp-atlantis' ),
			_x( 'Messages', 'menu title', 'a8csp-atlantis' ) . ' ' . $count_html,
			'manage_options',
			'a8csp-atlantis-messages',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Renders the admin list table or the single message page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function render_admin_page(): void {
		if ( ! a8csp_atlantis_is_automattician() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'a8csp-atlantis' ) );
		}

		$message_action = sanitize_text_field( $_GET['action'] ?? '-1' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \in_array( $message_action, array( 'new', 'edit', 'delete', 'activate', 'deactivate', '-1' ), true ) ) {
			wp_die( esc_html__( 'Invalid action.', 'a8csp-atlantis' ) );
		}

		$message_id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( 'new' === $message_action && 0 !== $message_id ) || ( 'edit' === $message_action && 0 === $message_id ) ) {
			wp_die( esc_html__( 'Invalid message ID.', 'a8csp-atlantis' ) );
		}

		if ( \in_array( $message_action, array( 'new', 'edit' ), true ) ) {
			$this->render_message_form( $message_id );
		} else {
			$this->render_message_list();
		}
	}

	/**
	 * Handles actions for single messages, such as saving or deleting.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 * @SuppressWarnings(PHPMD.ExitExpression)
	 *
	 * @return  void
	 */
	public function handle_single_actions(): void {
		if ( ! isset( $_POST['action'] ) || 'a8csp_atlantis_save_message' !== $_POST['action'] ) {
			return;
		}
		if ( ! check_admin_referer( 'save_message', 'a8csp_atlantis_message_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'a8csp-atlantis' ) );
		}
		if ( ! a8csp_atlantis_is_automattician() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'a8csp-atlantis' ) );
		}

		$message_id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$message_title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$message_content  = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$message_type     = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$message_status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$message_includes = isset( $_POST['location_include'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['location_include'] ) ) : array();
		$message_excludes = isset( $_POST['location_exclude'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['location_exclude'] ) ) : array();

		if ( empty( $message_title ) || empty( $message_content ) || empty( $message_type ) || empty( $message_status ) || empty( $message_includes ) ) {
			wp_die( esc_html__( 'All required fields must be filled out.', 'a8csp-atlantis' ) );
		}

		global $wpdb;
		$table_name = CustomTable::get_table_name();

		$data = array(
			'title'      => $message_title,
			'content'    => a8csp_atlantis_encrypt_data( $message_content ),
			'type'       => $message_type,
			'status'     => $message_status,
			'locations'  => wp_json_encode( $message_includes ),
			'exclusions' => ! empty( $message_excludes ) ? wp_json_encode( $message_excludes ) : null,
		);

		if ( $message_id > 0 ) {
			$result = $wpdb->update( $table_name, $data, array( 'id' => $message_id ), where_format: array( '%d' ) );
		} else {
			$result = $wpdb->insert( $table_name, $data );
		}

		if ( false === $result ) {
			wp_die( esc_html__( 'There was a problem saving your message.', 'a8csp-atlantis' ) );
		}

		wp_safe_redirect( remove_query_arg( array( 'action', 'id' ) ) );
		exit;
	}

	/**
	 * Handles actions for bulk messages, such as activating, deactivating, or deleting.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.ExitExpression)
	 *
	 * @return  void
	 */
	public function handle_bulk_actions(): void {
		if ( ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['message'] ) ) {
			return;
		}
		if ( ! check_admin_referer( 'bulk-messages' ) ) {
			wp_die( esc_html__( 'Security check failed', 'a8csp-atlantis' ) );
		}
		if ( ! a8csp_atlantis_is_automattician() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'a8csp-atlantis' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
		if ( ! \in_array( $action, array( 'delete', 'activate', 'deactivate' ), true ) ) {
			wp_die( esc_html__( 'Invalid bulk action.', 'a8csp-atlantis' ) );
		}

		$message_ids = array_map( 'intval', (array) $_REQUEST['message'] );
		if ( empty( $message_ids ) ) {
			wp_die( esc_html__( 'No messages selected for action.', 'a8csp-atlantis' ) );
		}

		foreach ( $message_ids as $message_id ) {
			switch ( $action ) {
				case 'delete':
					a8csp_atlantis_delete_message( $message_id );
					break;
				case 'activate':
					a8csp_atlantis_update_message_status( $message_id, 'active' );
					break;
				case 'deactivate':
					a8csp_atlantis_update_message_status( $message_id, 'inactive' );
					break;
			}
		}

		wp_safe_redirect( remove_query_arg( array( 'action', 'message', '_wpnonce' ) ) );
		exit;
	}

	// endregion

	// region HELPERS

	/**
	 * Renders the message list table.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function render_message_list(): void {
		$list_table = new Messages_List_Table();

		?>

		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'new' ) ) ); ?>" class="page-title-action">
				<?php echo esc_html_x( 'Add New', 'new message button', 'a8csp-atlantis' ); ?>
			</a>

			<hr class="wp-header-end">

			<form id="a8csp-atlantis-messages-list-table" method="get">
				<input type="hidden" name="page" value="a8csp-atlantis-messages" />
				<?php
					$list_table->prepare_items();
					$list_table->search_box( __( 'Search Messages', 'a8csp-atlantis' ), 'a8csp-atlantis-messages-list-table' );
					$list_table->display();
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Renders the message form for adding or editing messages.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int $message_id The message ID if editing, 0 for a new message.
	 *
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 *
	 * @return  void
	 */
	protected function render_message_form( int $message_id = 0 ): void {
		$a8csp_atlantis_message = new Message( $message_id );
		$a8csp_admin_locations  = $this->generate_admin_locations();

		include A8CSP_ATLANTIS_DIR_PATH . 'templates/admin/message-form.php';
	}

	/**
	 * Returns the list of available admin page locations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 *
	 * @return  string[]
	 */
	private function generate_admin_locations(): array {
		global $menu, $submenu;

		$locations = array(
			'all'              => __( 'All Locations', 'a8csp-atlantis' ),
			'all_post_editors' => __( 'All Post Editors', 'a8csp-atlantis' ),
		);

		// Top-level menu items.
		foreach ( $menu as $menu_item ) {
			if ( empty( $menu_item[0] ) || empty( $menu_item[2] ) ) {
				continue;
			}

			$menu_slug  = $menu_item[2];
			$menu_title = \preg_replace( '/\s\d+$/', '', wp_strip_all_tags( $menu_item[0] ) );

			// Keep the .php extension for exact matching.
			$locations[ $menu_slug ] = $menu_title;

			// Submenu items.
			if ( empty( $submenu[ $menu_slug ] ) ) {
				continue;
			}

			foreach ( $submenu[ $menu_slug ] as $submenu_item ) {
				if ( empty( $submenu_item[0] ) || empty( $submenu_item[2] ) ) {
					continue;
				}

				$submenu_slug  = $submenu_item[2];
				$submenu_title = \preg_replace( '/\s\d+$/', '', wp_strip_all_tags( $submenu_item[0] ) );

				// Skip if it's the same as the parent menu.
				if ( $submenu_slug === $menu_slug ) {
					continue;
				}

				$screen_id               = $submenu_slug;
				$locations[ $screen_id ] = $menu_title . ' → ' . preg_replace( '/\s\d+$/', '', $submenu_title );
			}
		}

		// Custom post type screens.
		$post_types = get_post_types( array( 'show_in_menu' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( \in_array( $post_type->name, array( 'post', 'page' ), true ) ) {
				continue;
			}

			$screen_id               = 'edit.php?post_type=' . $post_type->name;
			$locations[ $screen_id ] = $post_type->label;
		}

		// Taxonomy screens.
		$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			$screen_id               = 'edit-tags.php?taxonomy=' . $taxonomy->name;
			$locations[ $screen_id ] = wp_sprintf(
				/* translators: %s: Taxonomy label */
				__( '%s Categories', 'a8csp-atlantis' ),
				$taxonomy->label
			);
		}

		return $locations;
	}

	// endregion
}
