<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\REST;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller that forces a fresh plugin update-check on demand.
 *
 * OpsOasis (and the team51 CLI) call this over the authenticated Jetpack REST tunnel — the same
 * WordPress.com -> site path the status controller uses — to make a site re-detect a just-published
 * release without waiting out WordPress core's ~12h check throttle. No WordPress.com / Jetpack API can
 * clear a site's `update_plugins` transient, so only code running on the site can; this route does it
 * and re-runs the update check. The caller then installs via the normal (version-checked) update path.
 *
 * @since   1.2.4
 * @version 1.2.4
 */
class Force_Update_Check_Controller {
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
	private string $rest_base = 'force-update-check';

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
					'methods'             => \WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'callback'            => array( $this, 'create_item' ),
				),
			)
		);
	}

	// endregion

	// region CALLBACKS

	/**
	 * Permission check: only site admins (or equivalents authenticated via Jetpack-tunneled WordPress.com
	 * calls) may force an update check.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param \WP_REST_Request $request The REST request (unused; declared for the conventional signature).
	 *
	 * @return true|\WP_Error
	 */
	public function create_item_permissions_check( \WP_REST_Request $request ): true|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				\__( 'You do not have permission to force an update check.', 'a8csp-atlantis' ),
				array( 'status' => \rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Clears the throttled plugin-update caches and re-runs the update check.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param \WP_REST_Request $request The REST request (unused; declared for the conventional signature).
	 *
	 * @return \WP_REST_Response
	 */
	public function create_item( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return \rest_ensure_response( $this->force_update_check() );
	}

	// endregion

	// region HELPERS

	/**
	 * Deletes the throttled plugin-update caches and re-runs the update check.
	 *
	 * We delete only the `update_plugins` transient — not Atlantis's own cached GitHub release lookup.
	 * Clearing `update_plugins` forces WordPress.org-hosted plugins to be re-fetched (they have no
	 * per-plugin shield), while Update-URI/GitHub plugins keep serving their still-cached release info,
	 * so a fleet-wide refresh does not stampede GitHub's unauthenticated rate limit (those are pushed via
	 * the CLI `--force` path). WooCommerce.com-managed plugins have their own shield, flushed separately.
	 *
	 * Returns an honest outcome rather than an unconditional success: `wp_update_plugins()` is `void` and
	 * swallows a transport error / non-200 from api.wordpress.org inside core. Crucially, core writes
	 * `update_plugins` *defensively* (a bare object holding only `last_checked`, "to prevent multiple
	 * blocking requests if request hangs") *before* the remote call and then returns early on failure, so a
	 * mere object left behind is not proof the check completed. Core assigns `response`, `translations` and
	 * `no_update` together only in its closing write, so we key success off those — but not off `no_update`
	 * alone: that defensive pre-write passes through `pre_set_site_transient_update_plugins`, the same hook
	 * `WC_Helper_Updater` uses (see below), and a WooCommerce.com injector populates `response`/`no_update`
	 * on it without touching `translations`. So we additionally require `translations`, which only core
	 * sets, or a failed check on a Woo-connected site would score as success. Because we deleted the
	 * transient first, a failed re-check would otherwise leave the site with *no* update data at all, so we
	 * snapshot the previous list and restore it on failure — and report `refreshed => false` so the caller
	 * does not install against a wiped check and silently find nothing.
	 *
	 * @return array{refreshed: bool, last_checked?: int, updates?: int, woocommerce: bool|null}
	 */
	private function force_update_check(): array {
		$previous = \get_site_transient( 'update_plugins' );

		\delete_site_transient( 'update_plugins' );
		$woocommerce = $this->flush_woocommerce_update_cache();

		\wp_update_plugins();

		$updates = \get_site_transient( 'update_plugins' );
		if ( ! \is_object( $updates ) || ! isset( $updates->no_update, $updates->translations ) ) {
			// The re-check did not complete. Roll back to the pre-refresh list rather than leave it wiped.
			if ( false !== $previous ) {
				\set_site_transient( 'update_plugins', $previous );
			}

			return array(
				'refreshed'   => false,
				'woocommerce' => $woocommerce,
			);
		}

		return array(
			'refreshed'    => true,
			'last_checked' => (int) ( $updates->last_checked ?? 0 ),
			'updates'      => \count( (array) ( $updates->response ?? array() ) ),
			'woocommerce'  => $woocommerce,
		);
	}

	/**
	 * Flushes WooCommerce.com's separate plugin-update cache when WooCommerce is present.
	 *
	 * WooCommerce.com-managed plugins are shielded by WC Helper's own ~12h update cache
	 * (`_woocommerce_helper_updates`): its `pre_set_site_transient_update_plugins` hook re-injects updates
	 * from that cache, so clearing core's `update_plugins` transient alone re-serves stale Woo data.
	 * `WC_Helper_Updater::flush_updates_cache()` drops that cache (and the core update transients) so the
	 * re-check re-fetches fresh versions from WooCommerce.com. A no-op on non-WooCommerce sites; installing
	 * a detected update still requires the woo-update-manager plugin and an active subscription.
	 *
	 * @return bool|null `null` when the WooCommerce.com updater is not present (nothing to flush), `true`
	 *                   when the flush ran, `false` when it threw (logged, so the caller can observe it).
	 */
	private function flush_woocommerce_update_cache(): ?bool {
		if ( ! \is_callable( array( 'WC_Helper_Updater', 'flush_updates_cache' ) ) ) {
			return null;
		}

		try {
			\WC_Helper_Updater::flush_updates_cache();
			return true;
		} catch ( \Throwable $throwable ) {
			// Never let a WooCommerce helper failure abort the core refresh.
			\error_log( '[A8CSP Atlantis] WooCommerce update-cache flush failed: ' . $throwable->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}
	}

	// endregion
}
