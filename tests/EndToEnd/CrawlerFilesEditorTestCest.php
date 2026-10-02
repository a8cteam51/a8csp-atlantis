<?php
/**
 * End-to-end tests for the Settings → Robots & llms.txt editor.
 *
 * These drive the real screens: who can open the editor, saving through options.php, the
 * validation round trip, and the files that are then served over HTTP.
 */

declare(strict_types=1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Assert;
use Tests\Support\EndToEndTester;

/**
 * Crawler Files editor end-to-end tests.
 */
class CrawlerFilesEditorTestCest {
	/**
	 * The editor's address, relative to wp-admin.
	 *
	 * @var string
	 */
	private const EDITOR_PAGE = 'options-general.php?page=a8csp-atlantis-crawler-files';

	/**
	 * The module's settings option.
	 *
	 * @var string
	 */
	private const MODULE_OPTION = 'a8csp_module_crawler-files';

	/**
	 * The robots.txt rules option and textarea ID.
	 *
	 * @var string
	 */
	private const ROBOTS_OPTION = 'a8csp_atlantis_robots_txt_rules';

	/**
	 * The llms.txt option and textarea ID.
	 *
	 * @var string
	 */
	private const LLMS_OPTION = 'a8csp_atlantis_llms_txt';

	/**
	 * Password shared by the users each test creates.
	 *
	 * @var string
	 */
	private const PASSWORD = 'crawler_files_password';

	/**
	 * What core prints when a page needs a capability the user lacks.
	 *
	 * @var string
	 */
	private const NOT_ALLOWED = 'Sorry, you are not allowed to access this page.';

	/**
	 * Activates Atlantis with the module on and editor access limited to Automatticians, and
	 * lets this local site serve the files as if it were production.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( EndToEndTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$i->haveOptionInDatabase( 'active_plugins', array( 'a8csp-plugin-scaffold/a8csp-atlantis.php' ) );
		$this->set_editor_access( $i, false );

		$i->haveMuPlugin(
			'crawler-files-serve.php',
			'<?php
			add_filter( "a8csp_atlantis_crawler_files_serve", "__return_true" );'
		);
	}

	/**
	 * A client administrator cannot see or open the editor while access is limited.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_client_administrator_is_kept_out_by_default( EndToEndTester $i ): void {
		$this->login_as_new_admin( $i, 'client_out', 'client@example.com' );

		$i->amOnAdminPage( 'options-general.php' );
		$i->dontSee( 'Robots & llms.txt', '#adminmenu' );

		$i->amOnAdminPage( self::EDITOR_PAGE );
		$i->see( self::NOT_ALLOWED );
		$i->dontSeeElement( '#' . self::ROBOTS_OPTION );
	}

	/**
	 * Once an Automattician allows it, a client administrator can open the editor and save.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_client_administrator_can_edit_once_allowed( EndToEndTester $i ): void {
		$this->set_editor_access( $i, true );
		$this->login_as_new_admin( $i, 'client_in', 'client@example.com' );

		$i->amOnAdminPage( 'options-general.php' );
		$i->see( 'Robots & llms.txt', '#adminmenu' );

		$this->open_editor( $i );
		$i->fillField( '#' . self::LLMS_OPTION, "# Client Site\n\n> Saved by the client." );
		$this->save( $i );

		$i->see( 'Settings saved.' );
		$i->seeOptionInDatabase(
			array(
				'option_name'  => self::LLMS_OPTION,
				'option_value' => "# Client Site\n\n> Saved by the client.",
			)
		);
	}

	/**
	 * An Automattician turns on editor access from the Atlantis Modules screen, and a client
	 * administrator cannot reach that screen at all.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function an_automattician_controls_editor_access( EndToEndTester $i ): void {
		$this->login_as_new_admin( $i, 'a8c_flag', 'flag@a8c.com' );

		$i->amOnAdminPage( 'admin.php?page=a8csp-atlantis-modules' );
		$i->waitForElement( '#' . self::MODULE_OPTION . '_allow_admins', 10 );
		$i->dontSeeCheckboxIsChecked( '#' . self::MODULE_OPTION . '_allow_admins' );
		$i->checkOption( '#' . self::MODULE_OPTION . '_allow_admins' );
		$this->save( $i );

		$i->seeCheckboxIsChecked( '#' . self::MODULE_OPTION . '_allow_admins' );
		$stored = $i->grabOptionFromDatabase( self::MODULE_OPTION );
		Assert::assertSame( '1', $stored['allow_admins'] ?? null );
		Assert::assertSame( '1', $stored['enabled'] ?? null, 'Saving the flag leaves the module enabled.' );

		$this->login_as_new_admin( $i, 'client_flag', 'client@example.com' );
		$i->amOnAdminPage( 'admin.php?page=a8csp-atlantis-modules' );
		$i->see( self::NOT_ALLOWED );
	}

	/**
	 * Saved rules and content are served: robots.txt gets the rules appended after core's lines,
	 * and /llms.txt answers with the content as plain text.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function saved_files_are_served( EndToEndTester $i ): void {
		$this->login_as_new_admin( $i, 'a8c_serve', 'serve@a8c.com' );

		$this->open_editor( $i );
		$i->fillField( '#' . self::ROBOTS_OPTION, "User-agent: GPTBot\nDisallow: /" );
		$i->fillField( '#' . self::LLMS_OPTION, "# E2E Site\n\n> A & B <stay> %20 intact." );
		$this->save( $i );

		$i->see( 'Settings saved.' );
		$i->seeInField( '#' . self::ROBOTS_OPTION, "User-agent: GPTBot\nDisallow: /" );
		$i->see( 'Last changed by' );

		// robots.txt via its query var, which needs no rewrite rules.
		$i->amOnPage( '/?robots=1' );
		$robots = $i->grabTextFrom( 'pre' );
		Assert::assertStringStartsWith( "User-agent: *\nDisallow: /wp-admin/", $robots );
		Assert::assertStringEndsWith( "User-agent: GPTBot\nDisallow: /", trim( $robots ) );

		// /llms.txt is a real path, so the web server must hand it to WordPress.
		$htaccess = $this->write_htaccess( $i );

		try {
			$i->amOnPage( '/llms.txt' );
			Assert::assertSame( "# E2E Site\n\n> A & B <stay> %20 intact.", trim( $i->grabTextFrom( 'pre' ) ) );

			$response = wp_remote_get(
				'http://localhost/llms.txt',
				array(
					// The web server inside the container listens on port 80; send the site's own host so WordPress does not redirect.
					'headers'     => array( 'Host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ) . ':' . (string) wp_parse_url( home_url(), PHP_URL_PORT ) ),
					'redirection' => 0,
				)
			);

			Assert::assertFalse( is_wp_error( $response ), is_wp_error( $response ) ? $response->get_error_message() : '' );
			Assert::assertSame( 200, wp_remote_retrieve_response_code( $response ) );
			Assert::assertSame( 'text/plain; charset=utf-8', wp_remote_retrieve_header( $response, 'content-type' ) );
			Assert::assertSame( 'nosniff', wp_remote_retrieve_header( $response, 'x-content-type-options' ) );
			Assert::assertSame( 'noindex, follow', wp_remote_retrieve_header( $response, 'x-robots-tag' ) );
			Assert::assertSame( 'public, max-age=300', wp_remote_retrieve_header( $response, 'cache-control' ) );
			Assert::assertSame( "# E2E Site\n\n> A & B <stay> %20 intact.\n", wp_remote_retrieve_body( $response ) );
		} finally {
			$i->deleteFile( $htaccess );
		}
	}

	/**
	 * Rules with an error are not saved, the reason is shown, and the submitted text comes back
	 * in the editor rather than being lost.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function invalid_rules_are_refused_and_shown_again( EndToEndTester $i ): void {
		$this->login_as_new_admin( $i, 'a8c_invalid', 'invalid@a8c.com' );

		$this->open_editor( $i );
		$i->fillField( '#' . self::ROBOTS_OPTION, "Disallow: /private/\nUser-agent: *" );
		$this->save( $i );

		$i->waitForText( 'The robots.txt rules were not saved', 10 );
		$i->see( 'comes before any User-agent line' );
		$i->see( 'These are the rules you submitted. They have not been saved.' );
		$i->seeInField( '#' . self::ROBOTS_OPTION, "Disallow: /private/\nUser-agent: *" );
		$i->dontSeeOptionInDatabase(
			array(
				'option_name'  => self::ROBOTS_OPTION,
				'option_value' => "Disallow: /private/\nUser-agent: *",
			)
		);

		// The rejected text is shown once; reloading shows what is actually saved.
		$this->open_editor( $i );
		$i->seeInField( '#' . self::ROBOTS_OPTION, '' );
	}

	/**
	 * Rules that block the whole site need the confirmation box ticked.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function blocking_the_whole_site_needs_confirmation( EndToEndTester $i ): void {
		$this->login_as_new_admin( $i, 'a8c_block', 'block@a8c.com' );

		$this->open_editor( $i );
		$i->dontSeeElement( 'input[name="a8csp_atlantis_robots_txt_confirm_block_all"]' );
		$i->fillField( '#' . self::ROBOTS_OPTION, "User-agent: *\nDisallow: /" );
		$this->save( $i );

		$i->waitForText( 'they stop search engines from crawling the whole site', 10 );
		$i->dontSeeOptionInDatabase( array( 'option_name' => self::ROBOTS_OPTION, 'option_value' => "User-agent: *\nDisallow: /" ) );

		$i->checkOption( 'input[name="a8csp_atlantis_robots_txt_confirm_block_all"]' );
		$this->save( $i );

		$i->see( 'Settings saved.' );
		$i->seeOptionInDatabase( array( 'option_name' => self::ROBOTS_OPTION, 'option_value' => "User-agent: *\nDisallow: /" ) );
	}

	/**
	 * The starter link fills the llms.txt editor without saving anything.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function starter_content_is_offered_but_not_saved( EndToEndTester $i ): void {
		$i->haveOptionInDatabase( 'blogname', 'Starter Test Site' );
		$this->login_as_new_admin( $i, 'a8c_starter', 'starter@a8c.com' );

		$this->open_editor( $i );
		$i->click( 'Start from the site\'s name, tagline and pages' );
		$i->waitForText( 'Starter content loaded', 10 );

		$starter = $i->grabValueFrom( '#' . self::LLMS_OPTION );
		Assert::assertStringStartsWith( "# Starter Test Site\n", $starter );
		Assert::assertStringContainsString( '- [Home](', $starter );

		$i->dontSeeOptionInDatabase( array( 'option_name' => self::LLMS_OPTION, 'option_value' => $starter ) );
	}

	/**
	 * While Yoast SEO is active the editor hands off to Yoast instead of showing its form.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function the_editor_hands_off_to_yoast( EndToEndTester $i ): void {
		$i->haveMuPlugin(
			'crawler-files-pretend-yoast.php',
			'<?php
			add_filter( "a8csp_atlantis_crawler_files_defer_to_yoast", "__return_true" );'
		);
		$i->haveOptionInDatabase( self::LLMS_OPTION, '# Saved before Yoast' );

		$this->login_as_new_admin( $i, 'a8c_yoast', 'yoast@a8c.com' );
		$this->open_editor( $i, false );

		$i->see( 'Yoast SEO is active on this site, so it manages robots.txt and llms.txt.' );
		$i->seeElement( 'a[href$="admin.php?page=wpseo_page_settings#/llms-txt"]' );
		$i->see( 'llms.txt content saved in Atlantis (kept, not served)' );
		$i->dontSeeElement( '#' . self::LLMS_OPTION );
		$i->dontSeeElement( 'form[action="options.php"]' );
	}

	/**
	 * Sets whether every administrator may use the editor.
	 *
	 * @param EndToEndTester $i       Tester instance.
	 * @param bool           $allowed Whether every administrator may.
	 *
	 * @return void
	 */
	private function set_editor_access( EndToEndTester $i, bool $allowed ): void {
		$i->haveOptionInDatabase(
			self::MODULE_OPTION,
			array(
				'enabled'      => '1',
				'allow_admins' => $allowed ? '1' : '0',
			)
		);
	}

	/**
	 * Creates an administrator and logs in as them, starting from a clean browser session.
	 *
	 * The fixture is reloaded between tests, which wipes session tokens while the browser still
	 * holds the cookie, so every test drops cookies and waits for the dashboard before going on.
	 *
	 * @param EndToEndTester $i     Tester instance.
	 * @param string         $login The login to create.
	 * @param string         $email The user's email address.
	 *
	 * @return void
	 */
	private function login_as_new_admin( EndToEndTester $i, string $login, string $email ): void {
		$i->haveUserInDatabase(
			$login,
			'administrator',
			array(
				'user_pass'  => self::PASSWORD,
				'user_email' => $email,
			)
		);

		$i->amOnPage( '/' );
		$i->executeInSelenium(
			static function ( $webdriver ) {
				$webdriver->manage()->deleteAllCookies();
			}
		);

		$i->loginAs( $login, self::PASSWORD );
		$i->waitForElement( '#wpadminbar', 10 );
	}

	/**
	 * Opens the editor and waits for it to render.
	 *
	 * @param EndToEndTester $i         Tester instance.
	 * @param bool           $with_form Whether to wait for the form.
	 *
	 * @return void
	 */
	private function open_editor( EndToEndTester $i, bool $with_form = true ): void {
		$i->amOnAdminPage( self::EDITOR_PAGE );
		$i->waitForElement( $with_form ? '#' . self::LLMS_OPTION : '.wrap h1', 10 );
	}

	/**
	 * Submits the settings form and waits for the response to render.
	 *
	 * Submitting navigates; asserting straight away would sample the pre-submit page.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	private function save( EndToEndTester $i ): void {
		$i->executeJS( 'window.__a8cspBeforeSave = true;' );
		$i->click( '#submit' );
		$i->waitForJS( 'return typeof window.__a8cspBeforeSave === "undefined" && document.readyState === "complete";', 10 );
	}

	/**
	 * Writes the standard WordPress rewrite rules so the web server hands /llms.txt to WordPress.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return string The file written.
	 */
	private function write_htaccess( EndToEndTester $i ): string {
		$path = ABSPATH . '.htaccess';
		$i->writeToFile(
			$path,
			"<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n"
		);

		return $path;
	}
}
