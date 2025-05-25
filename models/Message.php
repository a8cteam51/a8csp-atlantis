<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a message in the Atlantis custom table.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @property-read int    $id
 * @property-read string $created_at
 * @property-read string $updated_at
 *
 * @property string $title
 * @property string $content
 * @property string $type
 * @property string $status
 * @property array<string, string> $locations
 * @property array<string, string> $exclusions
 */
class Message {
	// region FIELDS AND CONSTANTS

	/**
	 * Raw row data from the database.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var \stdClass
	 */
	protected \stdClass $data;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int|\stdClass|null $input Message ID, row object, or null for a new message.
	 *
	 * @throws  \InvalidArgumentException If an invalid message ID is provided.
	 */
	public function __construct( int|\stdClass|null $input = null ) {
		if ( is_int( $input ) && 0 < $input ) {
			$message = a8csp_atlantis_get_message( $input );
			if ( $message instanceof self ) {
				$this->data = $message->get_raw_data();
			} else {
				throw new \InvalidArgumentException( 'Invalid message ID provided.' );
			}
		} elseif ( $input instanceof \stdClass ) {
			$this->data = $input;
		} else {
			$this->data = $this->get_default_data();
		}
	}

	/**
	 * Magic method to access properties dynamically.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $key The property name to access.
	 *
	 * @return  mixed
	 */
	public function __get( string $key ) {
		return match ( $key ) {
			'id' => absint( $this->data->id ?? 0 ),
			'title', 'type', 'status', 'created_at', 'updated_at' => $this->data->$key ?? null,
			'content' => a8csp_atlantis_decrypt_data( $this->data->content ?? '' ),
			'locations', 'exclusions' => json_decode( $this->data->$key ?? '{}', true ),
			default => null,
		};
	}

	/**
	 * Magic method to set properties dynamically.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $key   The property name to set.
	 * @param   mixed  $value The value to set.
	 *
	 * @throws  \InvalidArgumentException If an invalid property is set.
	 *
	 * @return  void
	 */
	public function __set( string $key, mixed $value ): void {
		switch ( $key ) {
			case 'title':
			case 'type':
			case 'status':
				$this->data->$key = $value;
				break;
			case 'content':
				$this->data->content = a8csp_atlantis_encrypt_data( $value );
				break;
			case 'locations':
			case 'exclusions':
				$this->data->$key = wp_json_encode( $value );
				break;
			default:
				throw new \InvalidArgumentException( 'Invalid property.' );
		}
	}

	// endregion

	// region GETTERS

	/**
	 * Returns the raw row data.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  \stdClass
	 */
	public function get_raw_data(): \stdClass {
		return $this->data;
	}

	// endregion

	// region METHODS

	/**
	 * Creates an instance from a given database row.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   \stdClass $row The row data from the database.
	 *
	 * @return  self
	 */
	public static function get_instance( \stdClass $row ): self {
		return new self( $row );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the default data structure for a new message.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  \stdClass
	 */
	protected function get_default_data(): \stdClass {
		return (object) array(
			'id'         => 0,
			'title'      => '',
			'content'    => a8csp_atlantis_encrypt_data( '' ),
			'type'       => 'info',
			'status'     => 'active',
			'locations'  => wp_json_encode( array() ),
			'exclusions' => wp_json_encode( array() ),
			'created_at' => null,
			'updated_at' => null,
		);
	}

	// endregion
}
