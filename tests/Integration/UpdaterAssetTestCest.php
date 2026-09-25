<?php
/**
 * Integration tests for which release asset the self-updater installs.
 */

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Updater asset selection tests.
 */
class UpdaterAssetTestCest {
	/**
	 * URL of the asset that is the plugin build. Deliberately not named after the plugin —
	 * selection must depend on it being a zip, not on what it is called.
	 *
	 * @var string
	 */
	private const ZIP_URL = 'https://github.com/a8cteam51/a8csp-atlantis/releases/download/v9.9.9/build-20260903.zip';

	/**
	 * URL of an asset that is not a zip but happens to be listed first.
	 *
	 * @var string
	 */
	private const DECOY_URL = 'https://github.com/a8cteam51/a8csp-atlantis/releases/download/v9.9.9/checksums.txt';

	/**
	 * The updater must install the zip, not whatever GitHub lists first.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function updater_installs_the_zip_not_the_first_asset( IntegrationTester $i ): void {
		$this->cache_release(
			array(
				array(
					'name'                 => 'checksums.txt',
					'content_type'         => 'text/plain',
					'browser_download_url' => self::DECOY_URL,
				),
				array(
					'name'                 => 'build-20260903.zip',
					'content_type'         => 'application/zip',
					'browser_download_url' => self::ZIP_URL,
				),
			)
		);

		try {
			$update = $this->run_update_check();

			Assert::assertIsArray( $update, 'A newer release should produce an update.' );
			Assert::assertSame(
				self::ZIP_URL,
				$update['package'],
				'The updater must select the zip, not the first asset in the list.'
			);
		} finally {
			delete_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );
		}
	}

	/**
	 * A release carrying only the zip must keep working.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function updater_still_works_when_only_the_zip_is_attached( IntegrationTester $i ): void {
		$this->cache_release(
			array(
				array(
					'name'                 => 'build-20260903.zip',
					'content_type'         => 'application/zip',
					'browser_download_url' => self::ZIP_URL,
				),
			)
		);

		try {
			$update = $this->run_update_check();

			Assert::assertIsArray( $update );
			Assert::assertSame( self::ZIP_URL, $update['package'] );
		} finally {
			delete_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );
		}
	}

	/**
	 * A release with no zip at all offers nothing to install.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function updater_offers_nothing_when_the_release_has_no_zip( IntegrationTester $i ): void {
		$this->cache_release(
			array(
				array(
					'name'                 => 'checksums.txt',
					'content_type'         => 'text/plain',
					'browser_download_url' => self::DECOY_URL,
				),
			)
		);

		try {
			Assert::assertFalse(
				$this->run_update_check(),
				'Without a zip there is nothing safe to install.'
			);
		} finally {
			delete_transient( A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY );
		}
	}

	/**
	 * Stores a fake release so the updater does not make a request.
	 *
	 * @param array<int, array<string, string>> $assets The release assets.
	 *
	 * @return void
	 */
	private function cache_release( array $assets ): void {
		set_transient(
			A8CSP_ATLANTIS_GITHUB_RELEASE_TRANSIENT_KEY,
			array(
				'tag_name' => 'v9.9.9',
				'html_url' => 'https://github.com/a8cteam51/a8csp-atlantis/releases/tag/v9.9.9',
				'assets'   => $assets,
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Runs the update check the way WordPress does.
	 *
	 * @return mixed
	 */
	private function run_update_check(): mixed {
		return apply_filters(
			'update_plugins_github.com',
			false,
			array(
				'Version'    => '0.0.1',
				'TextDomain' => 'a8csp-atlantis',
			),
			A8CSP_ATLANTIS_BASENAME
		);
	}
}
