<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	/* @phpstan-ignore requireOnce.fileNotFound */
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Handles the display of messages in an admin table format.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Messages_List_Table extends \WP_List_Table {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
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

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$per_page              = 20;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$query = new Message_Query(
			array(
				'search'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
				'orderby'  => isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at',
				'order'    => isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC',
				'per_page' => $per_page,
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->items = $query->get_results();
		$this->set_pagination_args(
			array(
				'total_items' => $query->found_rows,
				'per_page'    => $per_page,
				'total_pages' => $query->max_num_pages,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'title'      => __( 'Title', 'a8csp-atlantis' ),
			'content'    => __( 'Content', 'a8csp-atlantis' ),
			'type'       => __( 'Type', 'a8csp-atlantis' ),
			'status'     => __( 'Status', 'a8csp-atlantis' ),
			'locations'  => __( 'Included Locations', 'a8csp-atlantis' ),
			'exclusions' => __( 'Excluded Locations', 'a8csp-atlantis' ),
			'created_at' => __( 'Created', 'a8csp-atlantis' ),
			'updated_at' => __( 'Last Updated', 'a8csp-atlantis' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  array<string, string|array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'title'      => array( 'title', false ),
			'type'       => array( 'type', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
			'updated_at' => array( 'updated_at', true ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   Message $item        The message item to display.
	 * @param   string  $column_name The name of the column to display.
	 *
	 * @phpstan-ignore-next-line
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'title':
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
						__( 'Edit', 'a8csp-atlantis' )
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
						esc_js( __( 'Are you sure you want to delete this message?', 'a8csp-atlantis' ) ),
						__( 'Delete', 'a8csp-atlantis' )
					),
				);
				return wp_sprintf(
					'%1$s %2$s',
					'<strong>' . esc_html( $item->title ) . '</strong>',
					$this->row_actions( $actions )
				);

			case 'content':
				$content = wp_strip_all_tags( $item->content );
				if ( 120 < mb_strlen( $content ) ) {
					$content = mb_substr( $content, 0, 120 ) . '…';
				}

				return esc_html( $content );

			case 'type':
				return wp_sprintf(
					'<span class="type-%s">%s</span>',
					esc_attr( $item->type ),
					esc_html( ucfirst( $item->type ) )
				);

			case 'status':
				$status = 'active' === $item->status
					? __( 'Active', 'a8csp-atlantis' )
					: __( 'Inactive', 'a8csp-atlantis' );

				return wp_sprintf(
					'<span class="status-%s">%s</span>',
					esc_attr( $item->status ),
					esc_html( $status )
				);

			case 'locations':
				return esc_html( implode( ', ', $item->locations ) );

			case 'exclusions':
				return 0 === count( $item->exclusions ) ? '—' : esc_html( implode( ', ', $item->exclusions ) );

			case 'created_at':
			case 'updated_at':
				return esc_html( get_date_from_gmt( $item->$column_name ) );

			default:
				return '';
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   Message $item The message item to display.
	 */
	protected function column_cb( $item ): string {
		return wp_sprintf(
			'<input type="checkbox" name="message[]" value="%s" />',
			$item->id
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'delete'     => __( 'Delete', 'a8csp-atlantis' ),
			'activate'   => __( 'Activate', 'a8csp-atlantis' ),
			'deactivate' => __( 'Deactivate', 'a8csp-atlantis' ),
		);
	}

	// endregion
}
