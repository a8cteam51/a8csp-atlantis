<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\Modules\Messages;

defined( 'ABSPATH' ) || exit;

/**
 * Queries the Atlantis messages table.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Message_Query {
	// region FIELDS AND CONSTANTS

	/**
	 * The results of the query.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var Message[]
	 */
	protected array $results = array();

	/**
	 * The total number of matching rows.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var int
	 */
	public int $found_rows = 0;

	/**
	 * The current page number.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var int
	 */
	public int $paged = 1;

	/**
	 * The maximum number of pages based on the total results.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var int
	 */
	public int $max_num_pages = 1;

	/**
	 * The query variables used to filter the results.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var array
	 */
	protected array $query_vars = array();

	/**
	 * Default query args.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var array
	 */
	protected array $defaults = array(
		'search'   => '',
		'orderby'  => 'created_at',
		'order'    => 'DESC',
		'paged'    => 1,
		'per_page' => 20,
		'id'       => null,
		'type'     => null,
		'status'   => null,
	);

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @param array $args The query arguments to filter messages.
	 */
	public function __construct( array $args = array() ) {
		global $wpdb;

		$this->query_vars = wp_parse_args( $args, $this->defaults );
		$this->paged      = \max( 1, (int) $this->query_vars['paged'] );

		$per_page  = (int) $this->query_vars['per_page'];
		$unlimited = -1 === $per_page;
		$offset    = $unlimited ? 0 : ( $this->paged - 1 ) * $per_page;

		$order   = \strtoupper( $this->query_vars['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$orderby = \in_array( $this->query_vars['orderby'], array( 'title', 'content', 'type', 'status', 'locations', 'exclusions', 'created_at', 'updated_at' ), true )
			? $this->query_vars['orderby']
			: 'created_at';

		$table  = Messages\CustomTable::get_table_name();
		$where  = array();
		$params = array();

		// Specific column filters
		foreach ( array( 'id', 'type', 'status' ) as $field ) {
			if ( null !== $this->query_vars[ $field ] ) {
				$where[]  = "`$field` = %s";
				$params[] = $this->query_vars[ $field ];
			}
		}

		// Search across multiple columns
		if ( '' !== $this->query_vars['search'] ) {
			$like    = '%' . $wpdb->esc_like( $this->query_vars['search'] ) . '%';
			$where[] = '(title LIKE %s OR type LIKE %s OR status LIKE %s OR locations LIKE %s OR exclusions LIKE %s)';
			$params  = array_merge( $params, array_fill( 0, 5, $like ) );
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Count total rows
		$count_sql = "SELECT COUNT(id) FROM `$table`";
		if ( count( $params ) ) {
			$count_sql = $wpdb->prepare( "$count_sql $where_sql", ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		$this->found_rows    = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->max_num_pages = $unlimited ? 1 : (int) ceil( $this->found_rows / $per_page );

		// Main query
		$sql = "SELECT * FROM `$table` $where_sql ORDER BY `$orderby` $order";
		if ( ! $unlimited ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $per_page;
			$params[] = $offset;
		}

		$this->results = array_map(
			array( Message::class, 'get_instance' ),
			$wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	// endregion

	// region GETTERS

	/**
	 * Get retrieved Message objects.
	 *
	 * @return Message[]
	 */
	public function get_results(): array {
		return $this->results;
	}

	// endregion
}
