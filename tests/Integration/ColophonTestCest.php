<?php
/**
 * Integration tests for Colophon module behavior.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\Colophon\Colophon;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Colophon module integration tests.
 */
class ColophonTestCest {
	/**
	 * Ensure action callback outputs expected credits markup.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function credits_action_outputs_markup( IntegrationTester $i ): void {
		ob_start();
		do_action(
			'team51_credits',
			array(
				'separator' => ' | ',
				'wpcom'     => 'Designed with WordPress.',
				'pressable' => 'Hosted by Pressable.',
			)
		);
		$output = (string) ob_get_clean();

		Assert::assertStringContainsString( 'Designed with WordPress.', $output );
		Assert::assertStringContainsString( 'Hosted by Pressable.', $output );
		Assert::assertStringContainsString( ' | ', $output );
	}

	/**
	 * Ensure shortcodes are registered and render expected output.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function shortcodes_are_registered_and_render( IntegrationTester $i ): void {
		$module = new Colophon();
		$module->register_shortcodes();

		Assert::assertTrue( shortcode_exists( 'team51-credits' ) );
		Assert::assertTrue( shortcode_exists( 'team51-current-year' ) );

		$current_year = do_shortcode( '[team51-current-year format="Y"]' );
		Assert::assertSame( gmdate( 'Y' ), $current_year );

		$credits = do_shortcode( '[team51-credits wpcom="Designed with WordPress." pressable=""]' );
		Assert::assertStringContainsString( 'Designed with WordPress.', $credits );
		Assert::assertStringNotContainsString( 'Hosted by Pressable.', $credits );
	}

	/**
	 * Ensure current-year shortcode supports custom date format.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function current_year_shortcode_supports_custom_format( IntegrationTester $i ): void {
		$module = new Colophon();
		$module->register_shortcodes();

		$year_short = do_shortcode( '[team51-current-year format="y"]' );
		Assert::assertSame( gmdate( 'y' ), $year_short );
	}

	/**
	 * The referral attribution credits the Special Projects team, so the credit links only carry
	 * it on a site the team manages. Anywhere else they are plain links.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function referral_attribution_is_only_added_on_a_managed_site( IntegrationTester $i ): void {
		$module = new Colophon();
		$module->register_shortcodes();

		try {
			update_option( 'a8csp_atlantis_managed_site', '1' );
			$credits = do_shortcode( '[team51-credits]' );

			Assert::assertStringContainsString( 'utm_campaign=Concierge', $credits );
			Assert::assertStringContainsString( 'partner_domain=', $credits );

			update_option( 'a8csp_atlantis_managed_site', '0' );
			$credits = do_shortcode( '[team51-credits]' );

			Assert::assertStringContainsString( 'href="https://wordpress.com/website-builder/"', $credits );
			Assert::assertStringContainsString( 'href="https://pressable.com/"', $credits );
			Assert::assertStringNotContainsString( 'utm_', $credits );
			Assert::assertStringNotContainsString( 'partner_domain', $credits );
		} finally {
			delete_option( 'a8csp_atlantis_managed_site' );
		}
	}
}
