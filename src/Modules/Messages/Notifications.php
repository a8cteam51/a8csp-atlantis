<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Messages;

use A8C\SpecialProjects\Atlantis\Message;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the output of messages as notifications in the admin area.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Notifications {
	// region METHODS

	/**
	 * Initializes the Notifications component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'admin_notices', array( $this, 'output_messages' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Outputs messages as admin notices.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function output_messages(): void {
		if ( ! a8csp_atlantis_is_automattician() ) {
			return;
		}

		$active_messages = $this->get_active_messages();
		if ( empty( $active_messages ) ) {
			return;
		}

		foreach ( $active_messages as $message ) {
			if ( $this->is_block_editor() ) {
				wp_add_inline_script(
					'wp-edit-post',
					wp_sprintf(
						'wp.data.dispatch("core/notices").createNotice("%s", %s, { isDismissible: false, __unstableHTML: true });',
						$message->type,
						wp_json_encode( wp_kses_post( $message->content ) )
					)
				);
			} else {
				wp_admin_notice( $message->content, array( 'type' => $message->type ) );
			}
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the active messages for the current location.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 *
	 * @return  Message[]
	 */
	protected function get_active_messages(): array {
		$all_active_messages = a8csp_atlantis_get_active_messages();
		if ( empty( $all_active_messages ) ) {
			return array();
		}

		$is_block_editor  = $this->is_block_editor();
		$current_location = $this->get_current_location();

		$local_active_messages = array();
		foreach ( $all_active_messages as $message ) {
			$excluded_locations = $message->exclusions;
			if ( \in_array( $current_location, $excluded_locations, true ) ) {
				continue;
			}
			if ( $is_block_editor && in_array( 'all_post_editors', $excluded_locations, true ) ) {
				continue;
			}

			$included_locations = $message->locations;
			if ( \in_array( 'all', $included_locations, true ) || \in_array( $current_location, $included_locations, true ) ) {
				$local_active_messages[] = $message;
			}
			if ( $is_block_editor && \in_array( 'all_post_editors', $included_locations, true ) ) {
				$local_active_messages[] = $message;
			}
		}

		return $local_active_messages;
	}

	/**
	 * Returns whether the current screen is the block editor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	protected function is_block_editor(): bool {
		$screen = get_current_screen();
		return $screen && $screen->is_block_editor;
	}

	/**
	 * Returns the current location for messages. Basically, tries to match the current admin page
	 * with the locations defined in messages.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 * @see     ListTable::generate_admin_locations
	 *
	 * @return  string
	 */
	private function get_current_location(): string {
		global $pagenow;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['post_type'] ) ) {
			return $pagenow . '?post_type=' . sanitize_key( $_GET['post_type'] );
		}
		if ( ! empty( $_GET['taxonomy'] ) ) {
			return $pagenow . '?taxonomy=' . sanitize_key( $_GET['taxonomy'] );
		}
		if ( ! empty( $_GET['page'] ) ) {
			return sanitize_key( $_GET['page'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $pagenow;
	}

	// endregion
}
