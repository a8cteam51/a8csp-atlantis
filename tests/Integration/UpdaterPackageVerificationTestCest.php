<?php
/**
 * Integration tests for verifying the downloaded update package against GitHub's digest.
 */

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Update package verification tests.
 */
class UpdaterPackageVerificationTestCest {
	/**
	 * The digest GitHub publishes for the zip is picked out of the release.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function digest_is_read_from_the_zip_asset( IntegrationTester $i ): void {
		$release = array(
			'assets' => array(
				array(
					'name'                 => 'checksums.txt',
					'content_type'         => 'text/plain',
					'digest'               => 'sha256:' . str_repeat( 'a', 64 ),
					'browser_download_url' => 'https://example.test/checksums.txt',
				),
				array(
					'name'                 => 'build.zip',
					'content_type'         => 'application/zip',
					'digest'               => 'sha256:' . str_repeat( 'b', 64 ),
					'browser_download_url' => 'https://example.test/build.zip',
				),
			),
		);

		Assert::assertSame(
			str_repeat( 'b', 64 ),
			a8csp_atlantis_get_release_package_digest( $release ),
			'The digest must come from the zip, not from another asset.'
		);
	}

	/**
	 * Releases published before GitHub added digests carry none.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function missing_digest_reads_as_null( IntegrationTester $i ): void {
		$release = array(
			'assets' => array(
				array(
					'name'                 => 'build.zip',
					'content_type'         => 'application/zip',
					'digest'               => null,
					'browser_download_url' => 'https://example.test/build.zip',
				),
			),
		);

		Assert::assertNull( a8csp_atlantis_get_release_package_digest( $release ) );
	}

	/**
	 * A file matching the published digest is accepted.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function matching_package_is_accepted( IntegrationTester $i ): void {
		$file = $this->write_temp_file( 'plugin zip contents' );

		try {
			$result = a8csp_atlantis_verify_package_digest( $file, hash_file( 'sha256', $file ) );

			Assert::assertTrue( $result );
		} finally {
			wp_delete_file( $file );
		}
	}

	/**
	 * A file that does not match must be refused rather than installed.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function altered_package_is_refused( IntegrationTester $i ): void {
		$file = $this->write_temp_file( 'something else entirely' );

		try {
			$result = a8csp_atlantis_verify_package_digest( $file, str_repeat( 'b', 64 ) );

			Assert::assertInstanceOf( WP_Error::class, $result );
			Assert::assertSame( 'a8csp_atlantis_package_digest_mismatch', $result->get_error_code() );
		} finally {
			wp_delete_file( $file );
		}
	}

	/**
	 * With no digest published there is nothing to check, and the update must still proceed.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function package_is_accepted_when_no_digest_was_published( IntegrationTester $i ): void {
		$file = $this->write_temp_file( 'plugin zip contents' );

		try {
			Assert::assertTrue( a8csp_atlantis_verify_package_digest( $file, null ) );
		} finally {
			wp_delete_file( $file );
		}
	}

	/**
	 * A file that cannot be read must not be treated as verified.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function unreadable_package_is_refused( IntegrationTester $i ): void {
		$result = a8csp_atlantis_verify_package_digest(
			get_temp_dir() . 'a8csp-atlantis-does-not-exist.zip',
			str_repeat( 'b', 64 )
		);

		Assert::assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Writes a temporary file with the given contents.
	 *
	 * @param string $contents The contents to write.
	 *
	 * @return string
	 */
	private function write_temp_file( string $contents ): string {
		$file = wp_tempnam( 'a8csp-atlantis-package' );

		file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return $file;
	}
}
