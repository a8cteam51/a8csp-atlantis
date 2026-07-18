<?php
/**
 * Plugin Name: Atlantis Snippet Loader
 * Description: Loads remediation snippets deployed via Atlantis. Managed drop-in — do not edit; changes are overwritten by Atlantis.
 * Version:     1.0.0
 * Author:      Automattic Special Projects
 *
 * This file is a self-installed mu-plugin drop-in written by the Atlantis
 * Snippets module. It loads *before* regular plugins so snippets can intercept
 * hooks that would otherwise be registered too late, and it runs independently
 * of Atlantis so remediations keep working even while Atlantis is mid-update.
 *
 * Every file it loads was signature-verified by Atlantis before being written,
 * so this loader trusts the directory contents. Each snippet is required inside
 * a try/catch so one bad snippet is skipped rather than fataling the whole site
 * — the same resilience posture as the rest of the plugin.
 *
 * @package A8C\SpecialProjects\Atlantis
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'a8csp_atlantis_load_snippets' ) ) {
	/**
	 * Loads every materialized Atlantis snippet, isolating failures.
	 *
	 * @return void
	 */
	function a8csp_atlantis_load_snippets() {
		$dir = __DIR__ . '/atlantis-snippets';

		// Global kill switch: a single marker file disables all snippets at once.
		if ( ! is_dir( $dir ) || file_exists( $dir . '/.disabled' ) ) {
			return;
		}

		$snippets = glob( $dir . '/*.php' );
		if ( false === $snippets ) {
			return;
		}

		foreach ( $snippets as $snippet ) {
			try {
				require_once $snippet;
			} catch ( \Throwable $throwable ) {
				// Survive a broken snippet: log it and record the failure so Atlantis
				// can auto-quarantine it, but never let it take down the request.
				$snippet_id = basename( $snippet, '.php' );

				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					sprintf(
						'[A8CSP Atlantis] Snippet "%s" failed to load: %s',
						$snippet_id,
						$throwable->getMessage()
					)
				);

				if ( function_exists( 'a8csp_atlantis_record_snippet_failure' ) ) {
					a8csp_atlantis_record_snippet_failure( $snippet_id, $throwable->getMessage() );
				}
			}
		}
	}
}

a8csp_atlantis_load_snippets();
