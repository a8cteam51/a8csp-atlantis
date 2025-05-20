<?php

namespace A8C\SpecialProjects\Atlantis\Modules;

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
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'admin_notices', array( $this, 'display_notifications' ) );
	}

	/**
	 * Get active messages for current location.
	 *
	 * @return array Array of active messages.
	 */
	private function get_active_messages(): array {
		global $wpdb;
		$table_name = $wpdb->prefix . Messages::TABLE_NAME;

		// Get current location
		$current_location = $this->get_current_location();

		// Get active messages for current location
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE message_status = %s AND (message_location = %s OR message_location = %s)',
				$table_name,
				'active',
				$current_location,
				'all'
			)
		);

		if ( ! $messages ) {
			return array();
		}

		return $messages;
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

		// Default: just return the page slug (e.g., plugins.php)
		return $pagenow;
	}

	/**
	 * Display notifications as admin notices.
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

		foreach ( $messages as $message ) {
			$this->render_notification( $message );
		}
	}

	/**
	 * Render a single notification as an admin notice.
	 *
	 * @param object $message Message object.
	 * @return void
	 */
	private function render_notification( $message ): void {
		$type    = $this->get_notice_type( $message->message_type );
		$content = wp_kses_post( $message->message_content );

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
	 * @param string $message_type Message type from database.
	 * @return string WordPress notice type.
	 */
	private function get_notice_type( string $message_type ): string {
		return $message_type;
	}
}
