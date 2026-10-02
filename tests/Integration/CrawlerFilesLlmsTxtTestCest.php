<?php
/**
 * Integration tests for the Crawler Files module's llms.txt.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\CrawlerFiles;
use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\LlmsTxt;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * llms.txt request matching, responses, validation and starter content.
 */
class CrawlerFilesLlmsTxtTestCest {
	/**
	 * Posts created by a test, removed afterwards.
	 *
	 * @var int[]
	 */
	private array $post_ids = array();

	/**
	 * Resets everything a test may have changed.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		delete_option( LlmsTxt::OPTION );
		CrawlerFiles::pull_rejected( LlmsTxt::OPTION );
		remove_all_filters( CrawlerFiles::SERVE_FILTER );
		remove_all_filters( CrawlerFiles::YOAST_FILTER );
		remove_all_filters( LlmsTxt::CONTENT_FILTER );
		remove_all_filters( LlmsTxt::CACHE_FILTER );
		remove_all_filters( 'pre_option_home' );
		$GLOBALS['wp_settings_errors'] = array();

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();
	}

	// region REQUEST MATCHING

	/**
	 * Only /llms.txt at the root matches. The query string is ignored; case, trailing slashes
	 * and other paths are not llms.txt.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function only_the_root_llms_txt_path_matches( IntegrationTester $i ): void {
		$cases = array(
			'/llms.txt'                          => true,
			'/llms.txt?utm_source=x'             => true,
			'https://example.org/llms.txt'       => true,
			'/LLMS.TXT'                          => false,
			'/llms.txt/'                         => false,
			'/blog/llms.txt'                     => false,
			'/llms.txt.bak'                      => false,
			'/llms-full.txt'                     => false,
			'/'                                  => false,
			''                                   => false,
			'/index.php?pagename=llms.txt'       => false,
			'//llms.txt'                         => false,
		);

		foreach ( $cases as $uri => $expected ) {
			Assert::assertSame( $expected, LlmsTxt::is_llms_txt_path( $uri ), (string) $uri );
		}
	}

	/**
	 * A site whose address has a path cannot serve the domain root, so nothing matches.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_site_in_a_subdirectory_never_matches( IntegrationTester $i ): void {
		Assert::assertTrue( LlmsTxt::is_home_at_root() );

		add_filter( 'pre_option_home', static fn(): string => 'http://example.org/blog' );

		Assert::assertFalse( LlmsTxt::is_home_at_root() );
		Assert::assertFalse( LlmsTxt::is_llms_txt_path( '/llms.txt' ) );
		Assert::assertFalse( LlmsTxt::is_llms_txt_path( '/blog/llms.txt' ) );

		remove_all_filters( 'pre_option_home' );
		add_filter( 'pre_option_home', static fn(): string => 'http://example.org/' );
		Assert::assertTrue( LlmsTxt::is_home_at_root(), 'A trailing slash is still the root.' );
	}

	// endregion

	// region RESPONSES

	/**
	 * Saved content is served to GET and HEAD in production, with one trailing newline.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function saved_content_is_served_in_production( IntegrationTester $i ): void {
		$this->serve();
		update_option( LlmsTxt::OPTION, "# Site\n\n> Summary" );

		Assert::assertSame( "# Site\n\n> Summary\n", LlmsTxt::get_response_body( '/llms.txt', 'GET' ) );
		Assert::assertSame( "# Site\n\n> Summary\n", LlmsTxt::get_response_body( '/llms.txt', 'head' ) );
	}

	/**
	 * Other methods, other paths, empty content, non-production and Yoast all fall through.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function requests_fall_through_unless_every_condition_holds( IntegrationTester $i ): void {
		$this->serve();
		Assert::assertNull( LlmsTxt::get_response_body( '/llms.txt', 'GET' ), 'Nothing saved.' );

		update_option( LlmsTxt::OPTION, '# Site' );
		Assert::assertNull( LlmsTxt::get_response_body( '/llms.txt', 'POST' ), 'POST.' );
		Assert::assertNull( LlmsTxt::get_response_body( '/robots.txt', 'GET' ), 'Another path.' );

		remove_all_filters( CrawlerFiles::SERVE_FILTER );
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_false' );
		Assert::assertNull( LlmsTxt::get_response_body( '/llms.txt', 'GET' ), 'Not production.' );

		remove_all_filters( CrawlerFiles::SERVE_FILTER );
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		remove_all_filters( CrawlerFiles::YOAST_FILTER );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_true' );
		Assert::assertNull( LlmsTxt::get_response_body( '/llms.txt', 'GET' ), 'Yoast active.' );
	}

	/**
	 * Code can supply or change the content through the filter; whitespace-only or non-string
	 * results serve nothing.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function the_content_filter_can_supply_the_file( IntegrationTester $i ): void {
		$this->serve();

		add_filter( LlmsTxt::CONTENT_FILTER, static fn(): string => "# From code\n\n\n" );
		Assert::assertSame( "# From code\n", LlmsTxt::get_response_body( '/llms.txt', 'GET' ) );

		remove_all_filters( LlmsTxt::CONTENT_FILTER );
		update_option( LlmsTxt::OPTION, '# Saved' );
		add_filter( LlmsTxt::CONTENT_FILTER, static fn(): string => "  \n " );
		Assert::assertNull( LlmsTxt::get_response_body( '/llms.txt', 'GET' ) );

		remove_all_filters( LlmsTxt::CONTENT_FILTER );
		add_filter( LlmsTxt::CONTENT_FILTER, static fn(): array => array( 'not a string' ) );
		Assert::assertNull( LlmsTxt::get_response_body( '/llms.txt', 'GET' ) );
	}

	/**
	 * The headers keep the file plain text, out of search results, and briefly cacheable.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function response_headers_are_plain_text_noindex_and_cacheable( IntegrationTester $i ): void {
		Assert::assertSame(
			array(
				'Content-Type'           => 'text/plain; charset=utf-8',
				'X-Content-Type-Options' => 'nosniff',
				'X-Robots-Tag'           => 'noindex, follow',
				'Cache-Control'          => 'public, max-age=300',
			),
			LlmsTxt::get_response_headers()
		);

		add_filter( LlmsTxt::CACHE_FILTER, static fn(): int => 60 );
		Assert::assertSame( 'public, max-age=60', LlmsTxt::get_response_headers()['Cache-Control'] );

		remove_all_filters( LlmsTxt::CACHE_FILTER );
		add_filter( LlmsTxt::CACHE_FILTER, static fn(): int => -5 );
		Assert::assertSame( 'public, max-age=0', LlmsTxt::get_response_headers()['Cache-Control'] );

		remove_all_filters( LlmsTxt::CACHE_FILTER );
		add_filter( LlmsTxt::CACHE_FILTER, static fn(): string => 'soon' );
		Assert::assertSame( 'public, max-age=300', LlmsTxt::get_response_headers()['Cache-Control'] );
	}

	// endregion

	// region VALIDATION AND SANITIZATION

	/**
	 * Content without a title draws a warning; oversized content is an error.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function validation_wants_a_title_and_a_sane_size( IntegrationTester $i ): void {
		Assert::assertSame( array(), LlmsTxt::validate( "# Site\n\n> Summary" )['warnings'] );
		Assert::assertCount( 1, LlmsTxt::validate( "Site\n\n> Summary" )['warnings'] );
		Assert::assertCount( 1, LlmsTxt::validate( '## Not a title' )['warnings'] );

		$huge = '# Site' . str_repeat( "\n- [Page](https://example.org/page)", 20000 );
		Assert::assertCount( 1, LlmsTxt::validate( $huge )['errors'] );
	}

	/**
	 * Markdown, angle brackets and percent-encoding survive; line endings, BOM and control
	 * characters are cleaned up.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function sanitize_keeps_markdown_and_urls_intact( IntegrationTester $i ): void {
		$submitted = "\xEF\xBB\xBF# Site \r\n\r\n> A & B <tags> stay\r\n\r\n- [Docs](https://example.org/a%20b?x=1&y=2)\x07\r\n";

		Assert::assertSame(
			"# Site\n\n> A & B <tags> stay\n\n- [Docs](https://example.org/a%20b?x=1&y=2)",
			LlmsTxt::sanitize( $submitted )
		);
		Assert::assertSame( array(), get_settings_errors( LlmsTxt::OPTION ) );
	}

	/**
	 * Oversized content keeps what was saved.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function oversized_content_keeps_the_saved_content( IntegrationTester $i ): void {
		update_option( LlmsTxt::OPTION, '# Saved' );

		Assert::assertSame( '# Saved', LlmsTxt::sanitize( '# Site' . str_repeat( 'x', CrawlerFiles::MAX_BYTES ) ) );
		Assert::assertSame( 'error', get_settings_errors( LlmsTxt::OPTION )[0]['type'] );
		Assert::assertNull( CrawlerFiles::pull_rejected( LlmsTxt::OPTION ), 'Oversized content is not held on to.' );
	}

	/**
	 * A missing title is saved with a warning.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_missing_title_is_saved_with_a_warning( IntegrationTester $i ): void {
		Assert::assertSame( 'No title here', LlmsTxt::sanitize( 'No title here' ) );
		Assert::assertSame( 'warning', get_settings_errors( LlmsTxt::OPTION )[0]['type'] );
	}

	// endregion

	// region STARTER CONTENT

	/**
	 * The starter lists the site name, tagline, home and published top-level pages, as plain
	 * Markdown.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function starter_content_describes_the_site( IntegrationTester $i ): void {
		add_filter( 'pre_option_blogname', static fn(): string => 'Ana &amp; Bo&#8217;s <em>Shop</em>' );
		add_filter( 'pre_option_blogdescription', static fn(): string => 'Hand-made things' );

		try {
			$about  = $this->create_page( 'About [us]', 0 );
			$child  = $this->create_page( 'Team', $about );
			$draft  = $this->create_page( 'Secret draft', 0, 'draft' );
			$starter = LlmsTxt::get_starter_content();
		} finally {
			remove_all_filters( 'pre_option_blogname' );
			remove_all_filters( 'pre_option_blogdescription' );
		}

		Assert::assertStringStartsWith( "# Ana & Bo’s Shop\n\n> Hand-made things\n\n## Pages\n\n- [Home](" . home_url( '/' ) . ")\n", $starter );
		Assert::assertStringContainsString( '- [About us](' . get_permalink( $about ) . ')', $starter );
		Assert::assertStringNotContainsString( 'Team', $starter, 'Child pages are left out.' );
		Assert::assertStringNotContainsString( 'Secret draft', $starter, 'Drafts are left out.' );
		Assert::assertSame( array(), LlmsTxt::validate( trim( $starter ) )['warnings'], 'The starter passes validation.' );

		unset( $child, $draft );
	}

	/**
	 * Without a tagline the summary line is omitted.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function starter_content_skips_an_empty_tagline( IntegrationTester $i ): void {
		add_filter( 'pre_option_blogdescription', '__return_empty_string' );

		try {
			$starter = LlmsTxt::get_starter_content();
		} finally {
			remove_all_filters( 'pre_option_blogdescription' );
		}

		Assert::assertStringNotContainsString( '>', $starter );
	}

	// endregion

	// region HELPERS

	/**
	 * Turns serving on and Yoast deferral off.
	 *
	 * @return void
	 */
	private function serve(): void {
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );
	}

	/**
	 * Creates a page and remembers it for cleanup.
	 *
	 * @param string $title  The title.
	 * @param int    $parent The parent page ID.
	 * @param string $status The post status.
	 *
	 * @return int
	 */
	private function create_page( string $title, int $parent, string $status = 'publish' ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_status' => $status,
				'post_parent' => $parent,
			)
		);

		Assert::assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	// endregion
}
