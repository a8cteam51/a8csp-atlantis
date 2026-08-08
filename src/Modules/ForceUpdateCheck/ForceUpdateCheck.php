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
	private const CRON_SCHEDULE = 'a8csp_atlantis_every_two_minutes';

	/**
	 * The cron interval, in seconds. Kept short so an urgent security update propagates across the
	 * fleet within a couple of minutes; the poll itself is a single cached option read on OpsOasis.
	 *
	 * @var int
	 */
	private const CRON_INTERVAL = 2 * MINUTE_IN_SECONDS;

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
	 * Registers the custom two-minute cron schedule used to poll the refresh directive.
	 *
	 * @param   array<string,array{interval:int,display:string}> $schedules The existing cron schedules.
	 *
	 * @return  array<string,array{interval:int,display:string}>
	 */
	public function register_cron_schedule( array $schedules ): array {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = array(
				'interval' => self::CRON_INTERVAL,
				'display'  => __( 'Every two minutes (Atlantis force update-check)', 'a8csp-atlantis' ),
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
	 * Deletes the throttled plugin-update cache and re-runs the update check.
	 *
	 * We delete only the `update_plugins` transient — not Atlantis's own cached GitHub release
	 * lookup. Clearing `update_plugins` forces WordPress.org-hosted plugins to be re-fetched (they
	 * have no per-plugin shield), while Update-URI/GitHub plugins keep serving their still-cached
	 * release info, so a fleet-wide refresh does not stampede GitHub's unauthenticated rate limit.
	 *
	 * @return  void
	 */
	private function force_update_check(): void {
		delete_site_transient( 'update_plugins' );

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . WPINC . '/update.php';
		}

		wp_update_plugins();
	}

	// endregion
}
