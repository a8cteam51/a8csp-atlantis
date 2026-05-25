<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\REST;

use A8C\SpecialProjects\Atlantis\Message_Query;
use A8C\SpecialProjects\Atlantis\Modules\Messages\CustomTable;
use A8C\SpecialProjects\Atlantis\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller that exposes Atlantis plugin and module status for
 * fleet-wide reporting (e.g. via OpsOasis and the team51 CLI).
 *
 * @since   1.2.0
 * @version 1.2.0
 */
class Status_Controller {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private string $namespace = 'a8csp-atlantis/v1';

	/**
	 * REST base for this controller.
	 *
	 * @var string
	 */
	private string $rest_base = 'status';

	// region METHODS

	/**
	 * Registers the controller hooks.
	 *
	 * @return void
	 */
	public function initialize(): void {
		\add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the REST routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'callback'            => array( $this, 'get_item' ),
				),
			)
		);
	}

	// endregion

	// region CALLBACKS

	/**
	 * Permission check: only site admins (or equivalents authenticated via
	 * Jetpack-tunneled WPCOM calls) may read the status.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param \WP_REST_Request $request The REST request (unused; declared for the
	 *                                  conventional REST callback signature).
	 *
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check( \WP_REST_Request $request ): true|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				\__( 'You do not have permission to read Atlantis status.', 'a8csp-atlantis' ),
				array( 'status' => \rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Returns the plugin and module status payload.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param \WP_REST_Request $request The REST request (unused; declared for the
	 *                                  conventional REST callback signature).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_item( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$modules = array();

		foreach ( Plugin::get_instance()->modules->modules as $key => $module ) {
			$modules[ $key ] = array(
				'name'    => $module->get_name(),
				'enabled' => $module->is_active(),
			);
		}

		if ( isset( $modules['messages'] ) ) {
			$modules['messages']['count'] = $this->count_messages();
		}

		return \rest_ensure_response(
			array(
				'plugin'  => array(
					'version' => \a8csp_atlantis_get_plugin_version(),
				),
				'modules' => $modules,
			)
		);
	}

	/**
	 * Returns the number of stored custom messages, or 0 if the count cannot
	 * be determined (table missing, module failed to initialise, etc.).
	 *
	 * @return int
	 */
	private function count_messages(): int {
		if ( ! CustomTable::table_exists() ) {
			return 0;
		}

		try {
			$query = new Message_Query( array( 'per_page' => 1 ) );
			return $query->found_rows;
		} catch ( \Throwable $t ) {
			return 0;
		}
	}

	// endregion
}
