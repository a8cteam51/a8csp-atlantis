<?php
/**
 * Integration tests for the Crawler Files module's robots.txt rules.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\CrawlerFiles;
use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\RobotsTxt;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * robots.txt validation, sanitization and appending.
 */
class CrawlerFilesRobotsTxtTestCest {
	/**
	 * What core's do_robots() builds for a root install, before filters.
	 *
	 * @var string
	 */
	private const CORE_OUTPUT = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";

	/**
	 * Resets everything a test may have changed.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		delete_option( RobotsTxt::OPTION );
		CrawlerFiles::pull_rejected( RobotsTxt::OPTION );
		remove_all_filters( CrawlerFiles::SERVE_FILTER );
		remove_all_filters( CrawlerFiles::YOAST_FILTER );
		remove_filter( 'robots_txt', array( RobotsTxt::class, 'append_rules' ), PHP_INT_MAX );
		unset( $_POST[ RobotsTxt::CONFIRM_FIELD ] );
		$GLOBALS['wp_settings_errors'] = array();
		wp_set_current_user( 0 );
	}

	// region VALIDATION

	/**
	 * A normal group validates cleanly.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_well_formed_group_has_no_findings( IntegrationTester $i ): void {
		$report = RobotsTxt::validate( "# Keep AI crawlers out\nUser-agent: GPTBot\nUser-agent: CCBot\nDisallow: /\n\nUser-agent: *\nDisallow: /private/\nAllow: /private/public-page\nDisallow:\nCrawl-delay: 5" );

		Assert::assertSame( array(), $report['errors'] );
		Assert::assertSame( array(), $report['warnings'] );
		Assert::assertFalse( $report['blocks_all'] );
	}

	/**
	 * A rule before any User-agent would join whatever group robots.txt ends with, so it is an
	 * error, reported once however many such rules there are.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_rule_before_any_user_agent_is_an_error_reported_once( IntegrationTester $i ): void {
		$report = RobotsTxt::validate( "# comment\nDisallow: /a\nAllow: /b\nCrawl-delay: 3\nUser-agent: *\nDisallow: /c" );

		Assert::assertCount( 1, $report['errors'] );
		Assert::assertStringContainsString( 'Line 2', $report['errors'][0] );
	}

	/**
	 * Sitemap stands outside groups, so it may come first.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_sitemap_before_any_user_agent_is_fine( IntegrationTester $i ): void {
		$report = RobotsTxt::validate( 'Sitemap: ' . home_url( '/extra-sitemap.xml' ) . "\nUser-agent: *\nDisallow: /tmp/" );

		Assert::assertSame( array(), $report['errors'] );
		Assert::assertSame( array(), $report['warnings'] );
	}

	/**
	 * Comments, blank lines and inline comments are ignored.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function comments_and_blank_lines_are_ignored( IntegrationTester $i ): void {
		$report = RobotsTxt::validate( "# only a comment\n\n   \n# another" );
		Assert::assertSame( array(), $report['errors'] );
		Assert::assertSame( array(), $report['warnings'] );

		$report = RobotsTxt::validate( "User-agent: * # everyone\nDisallow: /tmp/ # scratch" );
		Assert::assertSame( array(), $report['errors'] );
		Assert::assertSame( array(), $report['warnings'] );
	}

	/**
	 * Lines crawlers would ignore draw warnings, not errors.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function unrecognized_lines_are_warnings( IntegrationTester $i ): void {
		$report = RobotsTxt::validate( "User-agent: *\nNoindex: /a\nthis is not a directive\nDisallow: private\nUser-agent:" );

		Assert::assertSame( array(), $report['errors'] );
		Assert::assertCount( 4, $report['warnings'] );
		Assert::assertStringContainsString( 'Noindex', $report['warnings'][0] );
		Assert::assertStringContainsString( 'this is not a directive', $report['warnings'][1] );
		Assert::assertStringContainsString( '"private"', $report['warnings'][2] );
		Assert::assertStringContainsString( 'empty User-agent', $report['warnings'][3] );
	}

	/**
	 * Directive names are case-insensitive.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function directive_names_are_case_insensitive( IntegrationTester $i ): void {
		$report = RobotsTxt::validate( "USER-AGENT: *\nDISALLOW: /x\nallow: /y" );

		Assert::assertSame( array(), $report['errors'] );
		Assert::assertSame( array(), $report['warnings'] );
	}

	/**
	 * Disallow: / for every crawler, Googlebot or Bingbot needs confirmation; for any other
	 * crawler it does not.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function blocking_the_whole_site_for_search_engines_is_flagged( IntegrationTester $i ): void {
		$cases = array(
			"User-agent: *\nDisallow: /"                                       => true,
			"User-agent: Googlebot\nDisallow: /"                               => true,
			"User-agent: bingbot\nDisallow: /*"                                => true,
			"User-agent: GPTBot\nUser-agent: *\nDisallow: /"                   => true,
			"User-agent: GPTBot\nDisallow: /"                                  => false,
			"User-agent: *\nDisallow: /private"                                => false,
			"User-agent: *\nDisallow:"                                         => false,
			// A User-agent after a rule starts a new group, so `*` is not in GPTBot's group.
			"User-agent: *\nDisallow: /private\nUser-agent: GPTBot\nDisallow: /" => false,
		);

		foreach ( $cases as $rules => $expected ) {
			Assert::assertSame( $expected, RobotsTxt::validate( $rules )['blocks_all'], $rules );
		}
	}

	/**
	 * A sitemap must be absolute, and one on another host is probably a staging domain.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function sitemap_values_are_checked( IntegrationTester $i ): void {
		$report = RobotsTxt::validate( "Sitemap: /sitemap.xml\nSitemap: https://staging.example.net/sitemap.xml\nSitemap: " . home_url( '/sitemap.xml' ) );

		Assert::assertSame( array(), $report['errors'] );
		Assert::assertCount( 2, $report['warnings'] );
		Assert::assertStringContainsString( 'full URL', $report['warnings'][0] );
		Assert::assertStringContainsString( 'staging.example.net', $report['warnings'][1] );
	}

	/**
	 * Rules past Google's 500 KiB limit are refused.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function oversized_rules_are_an_error( IntegrationTester $i ): void {
		$rules = "User-agent: *\n" . str_repeat( "Disallow: /some/long/path/\n", 20000 );

		Assert::assertGreaterThan( CrawlerFiles::MAX_BYTES, strlen( $rules ) );
		Assert::assertCount( 1, RobotsTxt::validate( $rules )['errors'] );
	}

	// endregion

	// region SANITIZATION

	/**
	 * Valid rules are normalized and saved.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function valid_rules_are_normalized_and_saved( IntegrationTester $i ): void {
		$submitted = "\xEF\xBB\xBFUser-agent: GPTBot  \r\nDisallow: /a%20b/\0\r\n\r\n";

		Assert::assertSame( "User-agent: GPTBot\nDisallow: /a%20b/", RobotsTxt::sanitize( $submitted ) );
		Assert::assertSame( array(), get_settings_errors( RobotsTxt::OPTION ) );
	}

	/**
	 * Non-strings and empty input clear the rules.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function empty_or_non_string_input_clears_the_rules( IntegrationTester $i ): void {
		update_option( RobotsTxt::OPTION, "User-agent: *\nDisallow: /old/" );

		Assert::assertSame( '', RobotsTxt::sanitize( "  \n\t\n" ) );
		Assert::assertSame( '', RobotsTxt::sanitize( array( 'User-agent: *' ) ) );
		Assert::assertSame( '', RobotsTxt::sanitize( null ) );
	}

	/**
	 * Rules with an error keep the saved rules, explain why, and hold the rejected text for the
	 * editor to show once.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function invalid_rules_keep_the_saved_rules( IntegrationTester $i ): void {
		$saved = "User-agent: *\nDisallow: /old/";
		update_option( RobotsTxt::OPTION, $saved );

		Assert::assertSame( $saved, RobotsTxt::sanitize( "Disallow: /<script>alert(1)</script>\nUser-agent: *" ) );

		$errors = get_settings_errors( RobotsTxt::OPTION );
		Assert::assertCount( 1, $errors );
		Assert::assertSame( 'error', $errors[0]['type'] );
		Assert::assertStringNotContainsString( '<script>', $errors[0]['message'], 'Messages are printed unescaped by core, so they must be escaped here.' );

		Assert::assertSame( "Disallow: /<script>alert(1)</script>\nUser-agent: *", CrawlerFiles::pull_rejected( RobotsTxt::OPTION ) );
		Assert::assertNull( CrawlerFiles::pull_rejected( RobotsTxt::OPTION ), 'Rejected content is shown once.' );
	}

	/**
	 * Rejected content is held per user.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function rejected_content_is_held_per_user( IntegrationTester $i ): void {
		wp_set_current_user( 1 );
		RobotsTxt::sanitize( 'Disallow: /x' );

		wp_set_current_user( 0 );
		Assert::assertNull( CrawlerFiles::pull_rejected( RobotsTxt::OPTION ) );

		wp_set_current_user( 1 );
		Assert::assertSame( 'Disallow: /x', CrawlerFiles::pull_rejected( RobotsTxt::OPTION ) );
	}

	/**
	 * Blocking the whole site is refused until the confirmation box is ticked.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function blocking_the_whole_site_needs_confirmation( IntegrationTester $i ): void {
		$block_all = "User-agent: *\nDisallow: /";

		Assert::assertSame( '', RobotsTxt::sanitize( $block_all ) );
		Assert::assertSame( 'a8csp-robots-txt-blocks-all', get_settings_errors( RobotsTxt::OPTION )[0]['code'] );

		$GLOBALS['wp_settings_errors'] = array();
		CrawlerFiles::pull_rejected( RobotsTxt::OPTION );
		$_POST[ RobotsTxt::CONFIRM_FIELD ] = '1';

		Assert::assertSame( $block_all, RobotsTxt::sanitize( $block_all ) );
		Assert::assertSame( array(), get_settings_errors( RobotsTxt::OPTION ) );
	}

	/**
	 * Warnings do not stop a save.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function warnings_are_reported_but_the_rules_are_saved( IntegrationTester $i ): void {
		Assert::assertSame( "User-agent: *\nNoindex: /a", RobotsTxt::sanitize( "User-agent: *\nNoindex: /a" ) );

		$errors = get_settings_errors( RobotsTxt::OPTION );
		Assert::assertCount( 1, $errors );
		Assert::assertSame( 'warning', $errors[0]['type'] );
		Assert::assertStringContainsString( 'were saved', $errors[0]['message'], 'Core drops "Settings saved." when a notice exists, so the warning must say it saved.' );
	}

	/**
	 * When the rules have an error, warnings are reported too, so everything can be fixed in one
	 * pass.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function errors_and_warnings_are_reported_together( IntegrationTester $i ): void {
		Assert::assertSame( '', RobotsTxt::sanitize( "Disallow: /private/\nUser-agent: *\nNoindex: /x" ) );

		$notices = wp_list_pluck( get_settings_errors( RobotsTxt::OPTION ), 'message', 'type' );
		Assert::assertEqualsCanonicalizing( array( 'error', 'warning' ), array_keys( $notices ) );
		Assert::assertStringContainsString( 'Also check', $notices['warning'], 'A refused save must not claim the rules were saved.' );
	}

	/**
	 * Core sanitizes twice when update_option() falls through to add_option(); the notice must
	 * still appear once.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_notice_is_added_once_per_save( IntegrationTester $i ): void {
		RobotsTxt::sanitize( 'Disallow: /x' );
		RobotsTxt::sanitize( 'Disallow: /x' );

		Assert::assertCount( 1, get_settings_errors( RobotsTxt::OPTION ) );
	}

	// endregion

	// region APPENDING

	/**
	 * The rules are added after everything else, separated by a blank line.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function rules_are_appended_after_the_existing_output( IntegrationTester $i ): void {
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );
		update_option( RobotsTxt::OPTION, "User-agent: GPTBot\nDisallow: /" );

		Assert::assertSame(
			self::CORE_OUTPUT . "\nUser-agent: GPTBot\nDisallow: /\n",
			RobotsTxt::append_rules( self::CORE_OUTPUT )
		);
	}

	/**
	 * Nothing is appended when there are no rules, outside production, or while Yoast is active.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function nothing_is_appended_unless_serving_with_rules( IntegrationTester $i ): void {
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		Assert::assertSame( self::CORE_OUTPUT, RobotsTxt::append_rules( self::CORE_OUTPUT ), 'No rules saved.' );

		update_option( RobotsTxt::OPTION, "User-agent: GPTBot\nDisallow: /" );

		remove_all_filters( CrawlerFiles::SERVE_FILTER );
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_false' );
		Assert::assertSame( self::CORE_OUTPUT, RobotsTxt::append_rules( self::CORE_OUTPUT ), 'Not production.' );

		remove_all_filters( CrawlerFiles::SERVE_FILTER );
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		remove_all_filters( CrawlerFiles::YOAST_FILTER );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_true' );
		Assert::assertSame( self::CORE_OUTPUT, RobotsTxt::append_rules( self::CORE_OUTPUT ), 'Yoast active.' );
	}

	/**
	 * If another filter emptied robots.txt or returned a non-string, the rules stand alone.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function rules_stand_alone_when_the_existing_output_is_empty( IntegrationTester $i ): void {
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );
		update_option( RobotsTxt::OPTION, "User-agent: GPTBot\nDisallow: /" );

		Assert::assertSame( "User-agent: GPTBot\nDisallow: /\n", RobotsTxt::append_rules( '' ) );
		Assert::assertSame( "User-agent: GPTBot\nDisallow: /\n", RobotsTxt::append_rules( null ) );
	}

	/**
	 * Running at PHP_INT_MAX puts the rules after Yoast's 99999 filter, which rewrites what it
	 * is given, and after anything else.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function rules_land_after_late_filters_such_as_yoast( IntegrationTester $i ): void {
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );
		update_option( RobotsTxt::OPTION, "User-agent: GPTBot\nDisallow: /" );

		( new RobotsTxt() )->initialize();
		Assert::assertSame( PHP_INT_MAX, has_filter( 'robots_txt', array( RobotsTxt::class, 'append_rules' ) ) );

		$fake_yoast = static fn( string $output ): string => rtrim( $output ) . "\n\n# START YOAST BLOCK\n# END YOAST BLOCK";
		add_filter( 'robots_txt', $fake_yoast, 99999 );

		try {
			$output = apply_filters( 'robots_txt', self::CORE_OUTPUT, true );
		} finally {
			remove_filter( 'robots_txt', $fake_yoast, 99999 );
		}

		Assert::assertStringEndsWith( "# END YOAST BLOCK\n\nUser-agent: GPTBot\nDisallow: /\n", $output );
		Assert::assertStringStartsWith( self::CORE_OUTPUT, $output );
	}

	// endregion
}
