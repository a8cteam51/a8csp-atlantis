<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Messages;

use A8C\SpecialProjects\Atlantis\Messages_List_Table;
use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Messages Module class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Messages extends AbstractModule {
	// region FIELDS AND CONSTANTS

	/**
	 * The custom table component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var CustomTable
	 */
	public CustomTable $table;

	/**
	 * The list table component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var Messages_List_Table
	 */
	public ListTable $list_table;

	/**
	 * The notifications component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var Notifications
	 */
	public Notifications $notifications;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_name(): string {
		return 'Messages';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function get_description(): string {
		return __( 'Allows the inclusion of site-specific messages to various admin pages for Automattician-eyes only.', 'a8csp-atlantis' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function is_mandatory(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function is_disabled(): bool|\WP_Error {
		if ( ! CustomTable::table_exists() ) {
			if ( did_action( 'init' ) || doing_action( 'init' ) ) {
				return new \WP_Error(
					'custom-table-not-found',
					__( 'The custom table cannot be found or could not be created.', 'a8csp-atlantis' )
				);
			}

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function maybe_initialize(): void {
		$this->table = new CustomTable();
		$this->table->initialize();

		parent::maybe_initialize();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function initialize(): void {
		$this->list_table = new ListTable();
		$this->list_table->initialize();

		$this->notifications = new Notifications();
		$this->notifications->initialize();
	}

	// endregion
}
