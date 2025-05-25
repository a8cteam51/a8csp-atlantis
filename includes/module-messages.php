<?php declare( strict_types=1 );

use A8C\SpecialProjects\Atlantis\Message;
use A8C\SpecialProjects\Atlantis\Message_Query;
use A8C\SpecialProjects\Atlantis\Modules\Messages;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcut for retrieving messages from the Atlantis messages table.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   array<string, int|string|bool> $args Optional arguments to filter messages.
 *
 * @return  Message[]
 */
function a8csp_atlantis_get_messages( array $args = array() ): array {
	$query = new Message_Query( $args );
	return $query->get_results();
}

/**
 * Returns the count of active messages in the Atlantis messages table.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  Message[]
 */
function a8csp_atlantis_get_active_messages(): array {
	return a8csp_atlantis_get_messages(
		array(
			'status'         => 'active',
			'posts_per_page' => -1,
		)
	);
}

/**
 * Returns a single message by its ID from the Atlantis messages table.
 *
 * @param   int $message_id The ID of the message to retrieve.
 *
 * @return  Message|null
 */
function a8csp_atlantis_get_message( int $message_id ): ?Message {
	$messages = a8csp_atlantis_get_messages( array( 'id' => $message_id ) );
	return ! empty( $messages ) ? reset( $messages ) : null;
}

/**
 * Deletes a message from the Atlantis messages table by its ID.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   int $message_id The ID of the message to delete.
 *
 * @return  bool
 */
function a8csp_atlantis_delete_message( int $message_id ): bool {
	global $wpdb;

	return false !== $wpdb->delete(
		Messages\CustomTable::get_table_name(),
		array( 'id' => $message_id ),
		array( '%d' )
	);
}

/**
 * Updates the status of a message in the Atlantis messages table.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   int    $message_id The ID of the message to update.
 * @param   string $new_status The new status to set for the message.
 *
 * @return  bool
 */
function a8csp_atlantis_update_message_status( int $message_id, string $new_status ): bool {
	global $wpdb;

	return false !== $wpdb->update(
		Messages\CustomTable::get_table_name(),
		array( 'status' => $new_status ),
		array( 'id' => $message_id ),
		array( '%s' ),
		array( '%d' )
	);
}
