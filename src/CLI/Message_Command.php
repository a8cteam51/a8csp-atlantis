<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\CLI;

use A8C\SpecialProjects\Atlantis\Message;
use A8C\SpecialProjects\Atlantis\Message_Query;
use A8C\SpecialProjects\Atlantis\Modules\Messages\CustomTable;

defined( 'ABSPATH' ) || exit;

/**
 * Inspect Atlantis custom messages from the command line.
 *
 * ## EXAMPLES
 *
 *     # List the 20 most recent messages.
 *     $ wp atlantis message list
 *
 *     # List every message as JSON.
 *     $ wp atlantis message list --per_page=-1 --format=json
 *
 *     # Filter by type and status.
 *     $ wp atlantis message list --type=warning --status=active
 *
 *     # Inspect a single message including its decrypted content.
 *     $ wp atlantis message get 42 --fields=id,title,type,status,content
 *
 * @since   1.2.0
 * @version 1.2.0
 */
class Message_Command {
	/**
	 * Default fields returned by `list` and `get`.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_FIELDS = array( 'id', 'title', 'type', 'status', 'created_at' );

	/**
	 * All fields a message can be projected onto.
	 *
	 * @var array<int, string>
	 */
	private const AVAILABLE_FIELDS = array(
		'id',
		'title',
		'type',
		'status',
		'content',
		'locations',
		'exclusions',
		'created_at',
		'updated_at',
	);

	// region SUBCOMMANDS

	/**
	 * Lists custom messages from the Atlantis messages table.
	 *
	 * Content is decrypted only when explicitly requested via `--fields`.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Filter by message type (exact match).
	 *
	 * [--status=<status>]
	 * : Filter by message status (exact match).
	 *
	 * [--search=<term>]
	 * : Substring match across title, type, status, locations, exclusions.
	 *
	 * [--per_page=<n>]
	 * : Maximum rows to return. Defaults to 20. Use -1 for no limit.
	 *
	 * [--paged=<n>]
	 * : Page number when paginating.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--orderby=<field>]
	 * : Field to sort by.
	 * ---
	 * default: created_at
	 * options:
	 *   - title
	 *   - content
	 *   - type
	 *   - status
	 *   - locations
	 *   - exclusions
	 *   - created_at
	 *   - updated_at
	 * ---
	 *
	 * [--order=<direction>]
	 * : Sort direction.
	 * ---
	 * default: DESC
	 * options:
	 *   - ASC
	 *   - DESC
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to show. Available: id, title, type,
	 *   status, content, locations, exclusions, created_at, updated_at.
	 *   Defaults to id,title,type,status,created_at (or all fields with --full).
	 *
	 * [--format=<format>]
	 * : Render output in the given format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 *   - ids
	 * ---
	 *
	 * [--full]
	 * : Shortcut for "all messages, all columns" — equivalent to
	 *   `--per_page=-1 --fields=id,title,type,status,content,locations,exclusions,created_at,updated_at`.
	 *   Explicit `--per_page` or `--fields` overrides the corresponding default.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis message list
	 *     $ wp atlantis message list --type=warning --format=json
	 *     $ wp atlantis message list --per_page=-1 --fields=id,title
	 *     $ wp atlantis message list --full --format=json
	 *
	 * @subcommand list
	 *
	 * @param array<int, string>         $args       Positional args (unused).
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function list_( array $args, array $assoc_args ): void {
		$this->require_messages_table();

		$full = isset( $assoc_args['full'] ) && true === $assoc_args['full'];

		$per_page = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : ( $full ? -1 : 20 );
		if ( 0 === $per_page || $per_page < -1 ) {
			\WP_CLI::error( '--per_page must be a positive integer or -1 (no limit).' );
		}

		$query_args = array(
			'type'     => $assoc_args['type'] ?? null,
			'status'   => $assoc_args['status'] ?? null,
			'search'   => (string) ( $assoc_args['search'] ?? '' ),
			'per_page' => $per_page,
			'paged'    => (int) ( $assoc_args['paged'] ?? 1 ),
			'orderby'  => (string) ( $assoc_args['orderby'] ?? 'created_at' ),
			'order'    => (string) ( $assoc_args['order'] ?? 'DESC' ),
		);

		$query = new Message_Query( $query_args );

		$fields = $this->resolve_fields( $assoc_args, $full ? self::AVAILABLE_FIELDS : self::DEFAULT_FIELDS );
		$rows   = \array_map( fn( Message $m ) => $this->message_to_row( $m, $fields ), $query->get_results() );

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
		$formatter->display_items( $rows );
	}

	/**
	 * Fetches a single message by ID.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The numeric message ID.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to show.
	 * ---
	 * default: id,title,type,status,created_at
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in the given format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp atlantis message get 42
	 *     $ wp atlantis message get 42 --fields=id,title,content --format=json
	 *
	 * @param array<int, string>         $args       Positional args: <id>.
	 * @param array<string, string|bool> $assoc_args Flags.
	 */
	public function get( array $args, array $assoc_args ): void {
		$this->require_messages_table();

		$id = (int) ( $args[0] ?? 0 );
		if ( 0 >= $id ) {
			\WP_CLI::error( 'A positive integer message ID is required.' );
		}

		try {
			$message = new Message( $id );
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( \sprintf( 'No message found with ID %d.', $id ) );
		}

		$fields    = $this->resolve_fields( $assoc_args, self::DEFAULT_FIELDS );
		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
		$formatter->display_items( array( $this->message_to_row( $message, $fields ) ) );
	}

	// endregion

	// region HELPERS

	/**
	 * Bails with a clean `WP_CLI::error()` when the messages table is missing,
	 * preventing a downstream `$wpdb` null result from blowing up `Message_Query`.
	 *
	 * @return void
	 */
	private function require_messages_table(): void {
		if ( ! CustomTable::table_exists() ) {
			\WP_CLI::error( 'The Atlantis messages table does not exist on this site. The Messages module may have failed to initialise.' );
		}
	}

	/**
	 * Resolves and validates the requested field list.
	 *
	 * @param array<string, string|bool> $assoc_args Flags.
	 * @param array<int, string>         $defaults   Fields to use when `--fields` is absent.
	 *
	 * @return array<int, string>
	 */
	private function resolve_fields( array $assoc_args, array $defaults ): array {
		$fields = isset( $assoc_args['fields'] )
			? \array_map( 'trim', \explode( ',', (string) $assoc_args['fields'] ) )
			: $defaults;

		$unknown = \array_diff( $fields, self::AVAILABLE_FIELDS );
		if ( 0 < \count( $unknown ) ) {
			\WP_CLI::error(
				\sprintf(
					'Unknown field(s): %s. Available: %s.',
					\implode( ', ', $unknown ),
					\implode( ', ', self::AVAILABLE_FIELDS )
				)
			);
		}

		return $fields;
	}

	/**
	 * Projects a Message onto the requested field list with display-safe values.
	 *
	 * @param Message            $message Message instance.
	 * @param array<int, string> $fields  Fields to include.
	 *
	 * @return array<string, mixed>
	 */
	private function message_to_row( Message $message, array $fields ): array {
		$row = array();
		foreach ( $fields as $field ) {
			// Field names are validated against AVAILABLE_FIELDS by resolve_fields() before reaching here.
			$value = $message->$field; // @phpstan-ignore property.dynamicName
			if ( \in_array( $field, array( 'locations', 'exclusions' ), true ) && \is_array( $value ) ) {
				$value = \wp_json_encode( $value );
			}
			$row[ $field ] = $value;
		}

		return $row;
	}

	// endregion
}
