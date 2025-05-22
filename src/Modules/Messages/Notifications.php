<?php

namespace A8C\SpecialProjects\Atlantis\Modules\Messages;

use A8C\SpecialProjects\Atlantis\MessagesSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Notifications
 * Handles displaying active messages in WordPress admin using the notice system.
 *
 * @package A8C\SpecialProjects\Atlantis
 */
class Notifications {

	/**
	 * Initialize the notifications functionality.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'admin_notices', array( $this, 'display_notifications' ) );
	}

	/**
	 * Get active messages for current location.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return array Array of active messages.
	 */
	private function get_active_messages(): array {
		global $wpdb;
		$table_name = MessagesSchema::get_table_name();

		$is_block_editor = $this->is_block_editor();

		// Get current location
		$current_location = $this->get_current_location();

		// Get active messages
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE message_status = %s',
				$table_name,
				'active'
			)
		);

		if ( ! $messages ) {
			return array();
		}

		// Filter messages based on location and exclude rules
		$filtered_messages = array();
		foreach ( $messages as $message ) {
			$locations = maybe_unserialize( $message->message_location );
			$excludes  = ! empty( $message->message_exclude ) ? maybe_unserialize( $message->message_exclude ) : array();

			// Skip if current location is in excludes
			if ( ! empty( $excludes ) && in_array( $current_location, $excludes, true ) ) {
				continue;
			}

			if ( $is_block_editor && ! empty( $excludes ) && in_array( 'all_post_editors', $excludes, true ) ) {
				continue;
			}

			// Include if 'all' is in locations or current location matches
			if ( in_array( 'all', $locations, true ) || in_array( $current_location, $locations, true ) ) {
				$filtered_messages[] = $message;
			}

			if ( $is_block_editor && in_array( 'all_post_editors', $locations, true ) ) {
				$filtered_messages[] = $message;
			}
		}

		return $filtered_messages;
	}

	/**
	 * Get current page location.
	 *
	 * @return string Current location identifier.
	 */
	private function get_current_location(): string {
		// Use global $pagenow for the base admin page (e.g., plugins.php, edit.php)
		global $pagenow;

		// For custom post types and taxonomies, add query string as in get_admin_locations
		if ( isset( $_GET['post_type'] ) && ! empty( $_GET['post_type'] ) ) {
			return $pagenow . '?post_type=' . sanitize_key( $_GET['post_type'] );
		}

		if ( isset( $_GET['taxonomy'] ) && ! empty( $_GET['taxonomy'] ) ) {
			return $pagenow . '?taxonomy=' . sanitize_key( $_GET['taxonomy'] );
		}

		if ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) ) {
			return sanitize_key( $_GET['page'] );
		}

		// Default: just return the page slug (e.g., plugins.php)
		return $pagenow;
	}

	/**
	 * Display notifications as admin notices.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function display_notifications(): void {

		if ( ! a8csp_atlantis_is_user_automattician() ) {
			return;
		}

		$messages = $this->get_active_messages();

		if ( empty( $messages ) ) {
			return;
		}

		if ( $this->is_block_editor() ) {
			foreach ( $messages as $message ) {
				$this->render_editor_notification( $message );
			}

			return;
		}

		foreach ( $messages as $message ) {
			$this->render_notification( $message );
		}
	}

	/**
	 * Check if the current screen is the block editor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return bool True if the current screen is the block editor, false otherwise.
	 */
	private function is_block_editor(): bool {
		$screen = get_current_screen();
		return $screen && $screen->is_block_editor;
	}

	/**
	 * Render a notification for the block editor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param object $message Message object.
	 *
	 * @return void
	 */
	private function render_editor_notification( $message ): void {
		$type    = $this->get_notice_type( $message->message_type );
		$content = wp_kses_post( $message->message_content );

		// For block editor, we'll use JavaScript to render the notification
		wp_add_inline_script(
			'wp-edit-post',
			sprintf(
				'wp.data.dispatch("core/notices").createNotice("%s", %s, { isDismissible: false });',
				$type,
				wp_json_encode( $content )
			)
		);
	}

	/**
	 * Render a single notification as an admin notice.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param object $message Message object.
	 *
	 * @return void
	 */
	private function render_notification( $message ): void {
		$type    = $this->get_notice_type( $message->message_type );
		$content = wp_kses_post( $message->message_content );

		// For regular admin pages, use the standard admin notice
		wp_admin_notice(
			$content,
			array(
				'type'        => $type,
				'dismissible' => false,
			)
		);
	}

	/**
	 * Convert message type to WordPress notice type.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $message_type Message type from database.
	 *
	 * @return string WordPress notice type.
	 */
	private function get_notice_type( string $message_type ): string {
		return $message_type;
	}
}
