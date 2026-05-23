<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\REST;

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
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check(): true|\WP_Error {
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
	 * @return \WP_REST_Response
	 */
	public function get_item(): \WP_REST_Response {
		$modules = array();

		foreach ( Plugin::get_instance()->modules->modules as $key => $module ) {
			$modules[ $key ] = array(
				'name'    => $module->get_name(),
				'enabled' => $module->is_active(),
			);
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

	// endregion
}
