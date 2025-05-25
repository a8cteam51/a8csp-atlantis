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

		$table = Messages\CustomTable::get_table_name();

		$this->query_vars = $this->sanitize_query_vars( $args );
		$where_data       = $this->build_where_clause();

		// Figure out pagination data first.
		$count_sql = $wpdb->prepare( 'SELECT COUNT(id) FROM %i', $table );
		if ( ! empty( $where_data['clause'] ) ) {
			$count_sql .= ' ' . $where_data['clause'];
			if ( ! empty( $where_data['params'] ) ) {
				$count_sql = $wpdb->prepare( $count_sql, ...$where_data['params'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		$this->found_rows    = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->paged         = \max( 1, (int) $this->query_vars['paged'] );
		$this->max_num_pages = ( -1 === $this->query_vars['per_page'] ) ? 1
			: (int) ceil( $this->found_rows / $this->query_vars['per_page'] );

		// Perform the main query.
		$per_page = (int) $this->query_vars['per_page'];
		$offset   = -1 === $per_page ? 0 : ( $this->paged - 1 ) * $per_page;
		$order    = $this->query_vars['order'];
		$orderby  = $this->query_vars['orderby'];

		$sql = "SELECT * FROM `$table` {$where_data['clause']} ORDER BY `$orderby` $order";
		if ( -1 !== $per_page ) {
			$sql                   .= ' LIMIT %d OFFSET %d';
			$where_data['params'][] = $per_page;
			$where_data['params'][] = $offset;
		}

		$this->results = array_map(
			array( Message::class, 'get_instance' ),
			$wpdb->get_results( $wpdb->prepare( $sql, ...$where_data['params'] ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

	// region HELPERS

	/**
	 * Sanitize and validate query variables.
	 *
	 * @param   array $args The query arguments to sanitize.
	 *
	 * @return  array
	 */
	protected function sanitize_query_vars( array $args ): array {
		$args = wp_parse_args( $args, $this->defaults );

		$args['order']   = \strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$args['orderby'] = \in_array(
			$args['orderby'],
			array(
				'title',
				'content',
				'type',
				'status',
				'locations',
				'exclusions',
				'created_at',
				'updated_at',
			),
			true
		) ? $args['orderby'] : 'created_at';

		return $args;
	}

	/**
	 * Generate the WHERE clause and parameters for the SQL query.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  array
	 */
	private function build_where_clause(): array {
		global $wpdb;

		$where  = array();
		$params = array();

		foreach ( array( 'id', 'type', 'status' ) as $field ) {
			if ( null !== $this->query_vars[ $field ] ) {
				$where[]  = "`$field` = %s";
				$params[] = $this->query_vars[ $field ];
			}
		}

		if ( '' !== $this->query_vars['search'] ) {
			$like    = '%' . $wpdb->esc_like( $this->query_vars['search'] ) . '%';
			$where[] = '(title LIKE %s OR type LIKE %s OR status LIKE %s OR locations LIKE %s OR exclusions LIKE %s)';
			$params  = \array_merge( $params, \array_fill( 0, 5, $like ) );
		}

		return array(
			'clause' => $where ? 'WHERE ' . implode( ' AND ', $where ) : '',
			'params' => $params,
		);
	}

	// endregion
}
