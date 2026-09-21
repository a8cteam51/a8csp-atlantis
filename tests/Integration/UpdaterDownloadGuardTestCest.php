<?php
/**
 * Integration tests for the update download guard.
 *
 * The guard reads the cached GitHub release to find the published digest. A cache miss is the
 * normal state, not an edge case, because the release is cached for an hour while core keeps the
 * update on offer for twelve.
 */

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Update download guard tests.
 */
class UpdaterDownloadGuardTestCest {
	/**
	 * URL the fake release points at.
	 *
	 * @var string
	 */
	private const PACKAGE_URL = 'https://github.com/a8cteam51/a8csp-atlantis/releases/download/v9.9.9/build.zip';

	/**
	 * A cache miss must not let the package install unverified.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function expired_release_cache_does_not_skip_verification( IntegrationTester $i ): void {
		delete_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );

		$respond = $this->github_release_response();
		add_filter( 'pre_http_request', $respond, 10, 3 );

		try {
			$result = apply_filters(
				'upgrader_pre_download',
				false,
				self::PACKAGE_URL,
				null,
				array( 'plugin' => A8CSP_ATLANTIS_BASENAME )
			);

			Assert::assertNotFalse(
				$result,
				'With no cached release the guard must fetch one, not wave the package through.'
			);
		} finally {
			remove_filter( 'pre_http_request', $respond, 10 );
			delete_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );
		}
	}

	/**
	 * A fetched release must be cached so the next request does not hit GitHub again.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function fetched_release_is_cached( IntegrationTester $i ): void {
		delete_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );

		$respond = $this->github_release_response();
		add_filter( 'pre_http_request', $respond, 10, 3 );

		try {
			apply_filters(
				'upgrader_pre_download',
				false,
				self::PACKAGE_URL,
				null,
				array( 'plugin' => A8CSP_ATLANTIS_BASENAME )
			);

			$cached = get_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );

			Assert::assertIsArray( $cached );
			Assert::assertSame( 'v9.9.9', $cached['tag_name'] );
		} finally {
			remove_filter( 'pre_http_request', $respond, 10 );
			delete_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );
		}
	}

	/**
	 * Updates for other plugins must pass through untouched.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function other_plugins_are_left_alone( IntegrationTester $i ): void {
		delete_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );

		$calls   = 0;
		$counter = static function ( $pre ) use ( &$calls ) {
			++$calls;
			return new WP_Error( 'unexpected', 'No request should be made for another plugin.' );
		};
		add_filter( 'pre_http_request', $counter, 10, 3 );

		try {
			$result = apply_filters(
				'upgrader_pre_download',
				false,
				'https://example.test/other.zip',
				null,
				array( 'plugin' => 'some-other/plugin.php' )
			);

			Assert::assertFalse( $result );
			Assert::assertSame( 0, $calls );
		} finally {
			remove_filter( 'pre_http_request', $counter, 10 );
		}
	}

	/**
	 * Returns a `pre_http_request` callback serving a release with a digest.
	 *
	 * @return callable
	 */
	private function github_release_response(): callable {
		return static function ( $pre, $args, $url ) {
			if ( ! str_contains( (string) $url, 'api.github.com' ) ) {
				// Anything else is the package download, which must not silently succeed.
				return new WP_Error( 'http_request_failed', 'Package download blocked in test.' );
			}

			$body = wp_json_encode(
				array(
					'tag_name' => 'v9.9.9',
					'html_url' => 'https://github.com/a8cteam51/a8csp-atlantis/releases/tag/v9.9.9',
					'assets'   => array(
						array(
							'name'                 => 'build.zip',
							'content_type'         => 'application/zip',
							'digest'               => 'sha256:' . str_repeat( 'a', 64 ),
							'browser_download_url' => self::PACKAGE_URL,
						),
					),
				)
			);

			return array(
				'headers'  => array(),
				'body'     => (string) $body,
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
	}
}
