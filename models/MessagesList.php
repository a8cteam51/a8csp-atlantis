<?php

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\Modules\Messages;

use WP_List_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Class MessagesList
 * Handles the display of messages in an admin table format.
 *
 * @package A8C\SpecialProjects\Atlantis
 */
class MessagesList extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'message',
				'plural'   => 'messages',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'               => '<input type="checkbox" />',
			'message_name'     => __( 'Name', 'atlantis' ),
			'message_content'  => __( 'Content', 'atlantis' ),
			'message_type'     => __( 'Type', 'atlantis' ),
			'message_status'   => __( 'Status', 'atlantis' ),
			'message_location' => __( 'Location', 'atlantis' ),
			'message_time'     => __( 'Time', 'atlantis' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array(
			'message_name'     => array( 'message_name', false ),
			'message_content'  => array( 'message_content', false ),
			'message_type'     => array( 'message_type', false ),
			'message_status'   => array( 'message_status', false ),
			'message_location' => array( 'message_location', false ),
			'message_time'     => array( 'message_time', true ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		return array(
			'delete'     => __( 'Delete', 'atlantis' ),
			'activate'   => __( 'Activate', 'atlantis' ),
			'deactivate' => __( 'Deactivate', 'atlantis' ),
		);
	}

	/**
	 * Handle the checkbox column.
	 *
	 * @param object $item Item being displayed.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="message[]" value="%s" />',
			$item->id
		);
	}

	/**
	 * Display an error message when the table doesn't exist.
	 *
	 * @return void
	 */
	private function display_table_error(): void {
		global $wpdb;
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Table name */
						__( 'The messages table "%s" does not exist. Please deactivate and reactivate the plugin to create it.', 'atlantis' ),
						esc_html( $wpdb->prefix . Messages::TABLE_NAME )
					),
					array( 'strong' => array() )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Prepare items for table.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		global $wpdb;

		// Check if table exists
		if ( ! Messages::table_exists() ) {
			$this->display_table_error();
			return;
		}

		$per_page = 20;
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'message_time';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$valid_orderby_values = array( 'message_name', 'message_content', 'message_type', 'message_status', 'message_location', 'message_time' );
		if ( ! in_array( $orderby, $valid_orderby_values, true ) ) {
			$orderby = 'message_time';
		}

		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		$search_conditions = array();
		$query_args        = array();

		if ( ! empty( $search ) ) {
			$search_like         = '%' . $wpdb->esc_like( $search ) . '%';
			$search_conditions[] = 'message_name LIKE %s';
			$search_conditions[] = 'message_content LIKE %s';
			$search_conditions[] = 'message_type LIKE %s';
			$search_conditions[] = 'message_status LIKE %s';
			$search_conditions[] = 'message_location LIKE %s';
			$query_args          = array( $search_like, $search_like, $search_like, $search_like, $search_like );
		}

		$where_clause = ! empty( $search_conditions ) ? ' WHERE ' . implode( ' OR ', $search_conditions ) : '';

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// For table names, use $wpdb->get_blog_prefix() + constant
		$table_name = $wpdb->get_blog_prefix() . Messages::TABLE_NAME;

		// For count query
		if ( ! empty( $search_conditions ) ) {
			$count_sql = "SELECT COUNT(id) FROM $table_name WHERE " . implode( ' OR ', $search_conditions );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total_items = $wpdb->get_var( $wpdb->prepare( $count_sql, ...$query_args ) );
		} else {
			// phpcs:ignore
			$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
		}

		// For main query
		$sql = "SELECT l.id, l.message_name, l.message_content, l.message_type, l.message_status, l.message_location, l.message_time FROM $table_name l";

		if ( ! empty( $search_conditions ) ) {
			$sql        .= ' WHERE ' . implode( ' OR ', $search_conditions );
			$sql        .= ' ORDER BY ' . esc_sql( $orderby ) . ' ' . esc_sql( $order );
			$sql        .= ' LIMIT %d OFFSET %d';
			$this->items = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare( $sql, array_merge( $query_args, array( $per_page, $offset ) ) )
			);
		} else {
			$sql        .= ' ORDER BY ' . esc_sql( $orderby ) . ' ' . esc_sql( $order );
			$sql        .= ' LIMIT %d OFFSET %d';
			$this->items = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare( $sql, $per_page, $offset )
			);
		}

		// Apply the filter to each item
		foreach ( $this->items as $key => $item ) {
			$this->items[ $key ] = apply_filters( 'atlantis_message_item', $item );
		}

		// Set pagination arguments
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Get default column value.
	 *
	 * @param object $item        Item being displayed.
	 * @param string $column_name Column being displayed.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'message_name':
				$actions = array(
					'edit'   => sprintf(
						'<a href="%s">%s</a>',
						esc_url(
							add_query_arg(
								array(
									'action' => 'edit',
									'id'     => $item->id,
								)
							)
						),
						__( 'Edit', 'atlantis' )
					),
					'delete' => sprintf(
						'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
						esc_url(
							wp_nonce_url(
								add_query_arg(
									array(
										'action'  => 'delete',
										'message' => $item->id,
									)
								),
								'bulk-messages'
							)
						),
						esc_js( __( 'Are you sure you want to delete this message?', 'atlantis' ) ),
						__( 'Delete', 'atlantis' )
					),
				);
				return sprintf(
					'%1$s %2$s',
					'<strong>' . esc_html( $item->message_name ) . '</strong>',
					$this->row_actions( $actions )
				);
			case 'message_content':
				return wp_kses_post( $item->message_content );
			case 'message_type':
				return esc_html( $item->message_type );
			case 'message_status':
				$status = 'active' === $item->message_status ? __( 'Active', 'atlantis' ) : __( 'Inactive', 'atlantis' );
				return sprintf(
					'<span class="status-%s">%s</span>',
					esc_attr( $item->message_status ),
					esc_html( $status )
				);
			case 'message_location':
				return esc_html( $item->message_location );
			case 'message_time':
				return esc_html( get_date_from_gmt( $item->message_time ) );
			default:
				return '';
		}
	}
}
