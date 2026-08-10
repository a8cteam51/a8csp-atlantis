<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\ForceUpdateCheck;

use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Force Update Check module.
 *
 * Gives the Special Projects team a way to make the whole fleet re-check for plugin updates on
 * demand, without SSH-ing into every site. No WordPress.com / Jetpack API can clear a site's
 * `update_plugins` transient, so a freshly published release stays invisible until core's 12h
 * check throttle lapses (see the reported symptom in team51-cli PR #138). Only code running on
 * the site can bust that throttle — which is what this module does.
 *
 * Mechanism: a lightweight cron polls a public OpsOasis endpoint for a monotonically increasing
 * "refresh epoch". When the CLI raises that epoch (right after pushing an urgent update), each
 * site sees the new value on its next tick, deletes the `update_plugins` transient, and re-runs
 * the update check so the new version becomes visible to the normal update path.
 *
 * @since   1.4.0
 * @version 1.4.0
 */
final class ForceUpdateCheck extends AbstractModule {
	// region FIELDS AND CONSTANTS

	/**
	 * The cron hook that polls the refresh directive.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'a8csp_atlantis_force_update_check';

	/**
	 * The custom cron schedule name.
	 *
	 * @var string
	 */
	private const CRON_SCHEDULE = 'a8csp_atlantis_every_five_minutes';

	/**
	 * The cron interval, in seconds. Matches the cadence the fleet already sustains for the Autoupdates
	 * settings poll, so the lever adds no more polling pressure than the existing baseline; the poll
	 * itself is a single (cacheable) read on OpsOasis.
	 *
	 * @var int
	 */
	private const CRON_INTERVAL = 5 * MINUTE_IN_SECONDS;

	/**
	 * The option storing the highest refresh epoch this site has already acted on.
	 *
	 * @var string
	 */
	private const LAST_EPOCH_OPTION = 'a8csp_atlantis_last_force_check_epoch';

	/**
	 * The public OpsOasis endpoint exposing the fleet-wide refresh directive. It returns only an
	 * opaque `{ epoch, expires_at }` pulse — never any site or plugin identifiers.
	 *
	 * @var string
	 */
	private const DIRECTIVE_URL = 'https://opsoasis.wpspecialprojects.com/wp-json/wpcomsp/wpcom/v1/sites/batch/plugin-refresh';

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'Force Update Check';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Lets the Special Projects team trigger a fleet-wide plugin update-check on demand so urgent updates are detected without waiting out the 12-hour update throttle.', 'a8csp-atlantis' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_mandatory(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function initialize(): void {
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Two-minute interval is intentional; the poll is a single cached option read.
		add_action( self::CRON_HOOK, array( $this, 'run_directive_check' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the custom five-minute cron schedule used to poll the refresh directive.
	 *
	 * @param   array<string,array{interval:int,display:string}> $schedules The existing cron schedules.
	 *
	 * @return  array<string,array{interval:int,display:string}>
	 */
	public function register_cron_schedule( array $schedules ): array {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = array(
				'interval' => self::CRON_INTERVAL,
				'display'  => __( 'Every five minutes (Atlantis force update-check)', 'a8csp-atlantis' ),
			);
		}

		return $schedules;
	}

	/**
	 * Ensures the polling event is scheduled.
	 *
	 * @return  void
	 */
	public function maybe_schedule_cron(): void {
		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Polls the refresh directive and, on a newer epoch, forces a fresh plugin update-check.
	 *
	 * @return  void
	 */
	public function run_directive_check(): void {
		$directive = $this->fetch_directive();
		if ( null === $directive ) {
			return;
		}

		$epoch = (int) ( $directive['epoch'] ?? 0 );
		if ( 0 >= $epoch ) {
			return;
		}

		$last_epoch = (int) get_option( self::LAST_EPOCH_OPTION, 0 );
		if ( $epoch <= $last_epoch ) {
			return; // Already acted on this epoch (or a newer one).
		}

		// Record the epoch *before* doing the work so a transient failure downstream cannot make
		// this site clear its cache on every single tick. A missed directive is re-tried by the
		// next (higher-epoch) refresh, and the site still re-checks on its own schedule.
		update_option( self::LAST_EPOCH_OPTION, $epoch, false );

		$expires_at = (int) ( $directive['expires_at'] ?? 0 );
		if ( 0 < $expires_at && time() >= $expires_at ) {
			return; // Stale directive (e.g. this site was offline for hours); nothing to do.
		}

		$this->force_update_check();
	}

	// endregion

	// region HELPERS

	/**
	 * Fetches the current refresh directive from OpsOasis.
	 *
	 * Unauthenticated by design, mirroring the module that polls the central autoupdate settings:
	 * the directive is public and carries no sensitive data. A short timeout keeps a slow or down
	 * OpsOasis from holding up the cron run.
	 *
	 * @return  array<string,int>|null The decoded directive, or null on any failure.
	 */
	private function fetch_directive(): ?array {
		$response = wp_safe_remote_get(
			self::DIRECTIVE_URL,
			array(
				'timeout' => 2,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( ! str_starts_with( (string) wp_remote_retrieve_response_code( $response ), '2' ) ) {
			return null;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Deletes the throttled plugin-update caches and re-runs the update check.
	 *
	 * We delete only the `update_plugins` transient — not Atlantis's own cached GitHub release
	 * lookup. Clearing `update_plugins` forces WordPress.org-hosted plugins to be re-fetched (they
	 * have no per-plugin shield), while Update-URI/GitHub plugins keep serving their still-cached
	 * release info, so a fleet-wide refresh does not stampede GitHub's unauthenticated rate limit.
	 * WooCommerce.com-managed plugins have their own shield, flushed separately below.
	 *
	 * @return  void
	 */
	private function force_update_check(): void {
		delete_site_transient( 'update_plugins' );
		$this->flush_woocommerce_update_cache();

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . WPINC . '/update.php';
		}

		wp_update_plugins();
	}

	/**
	 * Flushes WooCommerce.com's separate plugin-update cache when WooCommerce is present.
	 *
	 * WooCommerce.com-managed plugins are shielded by WC Helper's own ~12h update cache
	 * (`_woocommerce_helper_updates`): its `pre_set_site_transient_update_plugins` hook re-injects
	 * updates from that cache, so clearing core's `update_plugins` transient alone re-serves stale Woo
	 * data. `WC_Helper_Updater::flush_updates_cache()` drops that cache (and the core update transients)
	 * so the re-check re-fetches fresh versions from WooCommerce.com. The helper and its injection hook
	 * are wired up on every request via `WC_WCCOM_Site`, so this works from the cron context this module
	 * runs in. A no-op on non-WooCommerce sites; on sites not connected to a WooCommerce.com account it
	 * simply refreshes the public update data. Installing the update still requires the woo-update-manager
	 * plugin (which injects the authenticated package URL) and an active subscription.
	 *
	 * @return  void
	 */
	private function flush_woocommerce_update_cache(): void {
		if ( class_exists( 'WC_Helper_Updater' ) && method_exists( 'WC_Helper_Updater', 'flush_updates_cache' ) ) {
			\WC_Helper_Updater::flush_updates_cache();
		}
	}

	// endregion
}
