<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\REST;

use A8C\SpecialProjects\Atlantis\Modules\Snippets\Loader;
use A8C\SpecialProjects\Atlantis\Modules\Snippets\Signature;
use A8C\SpecialProjects\Atlantis\Modules\Snippets\SnippetStore;

defined( 'ABSPATH' ) || exit;

/**
 * REST receiver for pushed snippet deployments.
 *
 * OpsOasis (fanning out over the managed fleet with the blog/partner token)
 * deposits signed snippets here; the site never pulls. Two independent gates
 * protect execution:
 *
 *  1. the permission check — only site admins / Jetpack-tunneled WPCOM partner
 *     calls may reach the route at all; and
 *  2. the Ed25519 signature — even a fully authenticated caller cannot make a
 *     site store or remove a snippet without OpsOasis's offline private key.
 *
 * @since   1.3.0
 * @version 1.3.0
 */
class Snippets_Controller {
	// region FIELDS AND CONSTANTS

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
	private string $rest_base = 'snippets';

	/**
	 * The snippet data store.
	 *
	 * @var SnippetStore
	 */
	private SnippetStore $store;

	/**
	 * The filesystem materializer / reconciler.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @param   SnippetStore $store  The snippet data store.
	 * @param   Loader       $loader The filesystem materializer / reconciler.
	 */
	public function __construct( SnippetStore $store, Loader $loader ) {
		$this->store  = $store;
		$this->loader = $loader;
	}

	// endregion

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
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, 'list_items' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, 'deploy_item' ),
				),
			)
		);

		// Removal accepts POST as well as DELETE: the OpsOasis fan-out proxies over
		// WPCOM's Jetpack tunnel, which only forwards a request body for editable
		// (POST/PUT/PATCH) methods, and the signed removal payload travels in the body.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<snippet_id>[a-z0-9][a-z0-9_-]*)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE . ', ' . \WP_REST_Server::DELETABLE,
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, 'remove_item' ),
				),
			)
		);
	}

	// endregion

	// region CALLBACKS

	/**
	 * Permission check: only site admins (or equivalents authenticated via
	 * Jetpack-tunneled WPCOM calls) may reach the snippet routes. The signature
	 * check in the handlers is the gate that actually authorizes execution.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param \WP_REST_Request $request The REST request (unused).
	 *
	 * @return true|\WP_Error
	 */
	public function permissions_check( \WP_REST_Request $request ): true|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				\__( 'You do not have permission to manage Atlantis snippets.', 'a8csp-atlantis' ),
				array( 'status' => \rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Stores a pushed, signed snippet and materializes it immediately.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function deploy_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$snippet_id = (string) $request->get_param( 'snippet_id' );
		$version    = (int) $request->get_param( 'version' );
		$code_b64   = (string) $request->get_param( 'code' );
		$sha256     = strtolower( (string) $request->get_param( 'sha256' ) );
		$signature  = (string) $request->get_param( 'signature' );
		$expires    = (string) ( $request->get_param( 'expires' ) ?? '' );
		$notes      = $request->get_param( 'notes' );

		if ( ! $this->is_valid_id( $snippet_id ) ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_invalid_id', \__( 'Invalid snippet id.', 'a8csp-atlantis' ), array( 'status' => 400 ) );
		}

		if ( $version < 1 ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_invalid_version', \__( 'A positive integer version is required.', 'a8csp-atlantis' ), array( 'status' => 400 ) );
		}

		$code = base64_decode( $code_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $code || '' === $code ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_bad_encoding', \__( 'Snippet code is not valid base64.', 'a8csp-atlantis' ), array( 'status' => 400 ) );
		}

		if ( ! hash_equals( hash( 'sha256', $code ), $sha256 ) ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_hash_mismatch', \__( 'Snippet hash does not match its code.', 'a8csp-atlantis' ), array( 'status' => 400 ) );
		}

		$message = Signature::deploy_message( $snippet_id, $version, $sha256, $expires );
		if ( ! Signature::verify( $message, $signature ) ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_bad_signature', \__( 'Snippet signature verification failed.', 'a8csp-atlantis' ), array( 'status' => 403 ) );
		}

		$stored = $this->store->upsert(
			array(
				'snippet_id' => $snippet_id,
				'version'    => $version,
				'code'       => $code,
				'sha256'     => $sha256,
				'signature'  => $signature,
				'notes'      => is_string( $notes ) ? $notes : null,
				'expires_at' => $this->normalize_expiry( $expires ),
			)
		);

		if ( \is_wp_error( $stored ) ) {
			$stored->add_data( array( 'status' => 409 ) );
			return $stored;
		}

		$this->loader->reconcile();

		return \rest_ensure_response(
			array(
				'stored'     => true,
				'snippet_id' => $snippet_id,
				'version'    => $version,
				'sha256'     => $sha256,
				'applied'    => $this->loader->can_materialize(),
			)
		);
	}

	/**
	 * Marks a snippet removed (signed removal) and clears its materialized file.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function remove_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$snippet_id = (string) $request->get_param( 'snippet_id' );
		$version    = (int) $request->get_param( 'version' );
		$signature  = (string) $request->get_param( 'signature' );

		if ( ! $this->is_valid_id( $snippet_id ) ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_invalid_id', \__( 'Invalid snippet id.', 'a8csp-atlantis' ), array( 'status' => 400 ) );
		}

		$existing = $this->store->get( $snippet_id );
		if ( null === $existing ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_not_found', \__( 'Snippet not found.', 'a8csp-atlantis' ), array( 'status' => 404 ) );
		}

		if ( $version <= (int) $existing['version'] ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_stale_version', \__( 'Removal version must be newer than the stored version.', 'a8csp-atlantis' ), array( 'status' => 409 ) );
		}

		$message = Signature::remove_message( $snippet_id, $version );
		if ( ! Signature::verify( $message, $signature ) ) {
			return new \WP_Error( 'a8csp_atlantis_snippet_bad_signature', \__( 'Snippet removal signature verification failed.', 'a8csp-atlantis' ), array( 'status' => 403 ) );
		}

		$this->store->mark_removed( $snippet_id, $version );
		$this->loader->reconcile();

		return \rest_ensure_response(
			array(
				'removed'    => true,
				'snippet_id' => $snippet_id,
				'version'    => $version,
			)
		);
	}

	/**
	 * Returns the current snippet inventory for fleet status reporting.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param \WP_REST_Request $request The REST request (unused).
	 *
	 * @return \WP_REST_Response
	 */
	public function list_items( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$items = array();

		foreach ( $this->store->all() as $row ) {
			$items[] = array(
				'snippet_id' => $row['snippet_id'],
				'version'    => (int) $row['version'],
				'status'     => $row['status'],
				'sha256'     => $row['sha256'],
				'fail_count' => (int) $row['fail_count'],
				'expires_at' => $row['expires_at'],
				'updated_at' => $row['updated_at'],
			);
		}

		return \rest_ensure_response(
			array(
				'can_materialize' => $this->loader->can_materialize(),
				'snippets'        => $items,
			)
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Validates a snippet id (lowercase slug).
	 *
	 * @param   string $snippet_id The candidate id.
	 *
	 * @return  bool
	 */
	private function is_valid_id( string $snippet_id ): bool {
		return 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,190}$/', $snippet_id );
	}

	/**
	 * Normalizes an ISO-8601 expiry into a MySQL UTC datetime, or null.
	 *
	 * @param   string $expires The ISO-8601 expiry, or ''.
	 *
	 * @return  string|null
	 */
	private function normalize_expiry( string $expires ): ?string {
		if ( '' === $expires ) {
			return null;
		}

		$timestamp = strtotime( $expires );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	// endregion
}
