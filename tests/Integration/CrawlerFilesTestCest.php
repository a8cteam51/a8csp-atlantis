<?php
/**
 * Integration tests for the Crawler Files module: registration, settings, who may edit, what is
 * served, change tracking and status reporting.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\ChangeLog;
use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\CrawlerFiles;
use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\EditorPage;
use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\LlmsTxt;
use A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles\RobotsTxt;
use A8C\SpecialProjects\Atlantis\REST\Status_Controller;
use A8C\SpecialProjects\Atlantis\Settings;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Crawler Files module integration tests.
 */
class CrawlerFilesTestCest {
	/**
	 * The module's settings option.
	 *
	 * @var string
	 */
	private const MODULE_OPTION = 'a8csp_module_crawler-files';

	/**
	 * The module option as it was before the test.
	 *
	 * @var mixed
	 */
	private mixed $original_module_option = null;

	/**
	 * Users created by a test, removed afterwards.
	 *
	 * @var int[]
	 */
	private array $user_ids = array();

	/**
	 * Remembers the module option.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$this->original_module_option = get_option( self::MODULE_OPTION, null );
	}

	/**
	 * Resets everything a test may have changed.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		if ( null === $this->original_module_option ) {
			delete_option( self::MODULE_OPTION );
		} else {
			update_option( self::MODULE_OPTION, $this->original_module_option );
		}

		foreach ( array( RobotsTxt::OPTION, LlmsTxt::OPTION, ChangeLog::OPTION ) as $option ) {
			delete_option( $option );
		}

		remove_all_filters( CrawlerFiles::SERVE_FILTER );
		remove_all_filters( CrawlerFiles::YOAST_FILTER );
		remove_filter( 'user_has_cap', array( CrawlerFiles::class, 'filter_grant_capability' ) );
		remove_all_filters( 'sanitize_option_' . self::MODULE_OPTION );
		unregister_setting( EditorPage::OPTION_GROUP, RobotsTxt::OPTION );
		unregister_setting( EditorPage::OPTION_GROUP, LlmsTxt::OPTION );
		$GLOBALS['wp_settings_errors'] = array();

		wp_set_current_user( 0 );

		if ( ! function_exists( 'wp_delete_user' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = array();
	}

	// region REGISTRATION

	/**
	 * The module is registered under its key, optional, and stored under a stable option.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function module_is_registered_and_optional( IntegrationTester $i ): void {
		$modules = a8csp_atlantis_get_plugin_instance()->modules->modules;

		Assert::assertArrayHasKey( 'crawler-files', $modules );
		Assert::assertInstanceOf( CrawlerFiles::class, $modules['crawler-files'] );
		Assert::assertSame( 'Crawler Files', $modules['crawler-files']->get_name() );
		Assert::assertFalse( $modules['crawler-files']->is_mandatory() );
		Assert::assertSame( self::MODULE_OPTION, a8csp_atlantis_generate_module_settings_key( 'Crawler Files' ) );
	}

	/**
	 * Out of the box the module is on but nobody outside Automattic can edit and nothing is
	 * saved, so it serves nothing.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function defaults_change_nothing( IntegrationTester $i ): void {
		delete_option( self::MODULE_OPTION );
		( new CrawlerFiles() )->maybe_set_default_settings();

		Assert::assertSame( array( 'enabled' => '1' ), get_option( self::MODULE_OPTION ) );
		Assert::assertFalse( CrawlerFiles::allows_all_administrators() );
		Assert::assertSame( '', RobotsTxt::get_rules() );
		Assert::assertSame( '', LlmsTxt::get_content() );

		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );
		Assert::assertSame( 'core output', RobotsTxt::append_rules( 'core output' ) );
		Assert::assertNull( LlmsTxt::get_response_body( '/llms.txt', 'GET' ) );
	}

	// endregion

	// region MODULE SETTINGS

	/**
	 * Both flags become '0' or '1'; an unticked checkbox is simply missing from the POST.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function module_settings_are_sanitized_to_flags( IntegrationTester $i ): void {
		$cases = array(
			array( array( 'enabled' => '1', 'allow_admins' => '1' ), array( 'enabled' => '1', 'allow_admins' => '1' ) ),
			array( array( 'enabled' => '1' ), array( 'enabled' => '1', 'allow_admins' => '0' ) ),
			array( array( 'allow_admins' => '1' ), array( 'enabled' => '0', 'allow_admins' => '1' ) ),
			array( array( 'enabled' => 'yes', 'allow_admins' => 'on' ), array( 'enabled' => '0', 'allow_admins' => '0' ) ),
			array( null, array( 'enabled' => '0', 'allow_admins' => '0' ) ),
			array( 'garbage', array( 'enabled' => '0', 'allow_admins' => '0' ) ),
		);

		foreach ( $cases as [ $input, $expected ] ) {
			Assert::assertSame( $expected, CrawlerFiles::sanitize_settings( $input ), wp_json_encode( $input ) );
		}
	}

	/**
	 * Toggling the module from WP-CLI keeps the editor-access flag.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function toggling_the_module_keeps_editor_access( IntegrationTester $i ): void {
		$this->load_settings_api();

		$module = new CrawlerFiles();
		$module->register_settings();
		update_option( self::MODULE_OPTION, array( 'enabled' => '1', 'allow_admins' => '1' ) );

		$module->set_enabled( false );
		Assert::assertSame( array( 'enabled' => '0', 'allow_admins' => '1' ), get_option( self::MODULE_OPTION ) );

		$module->set_enabled( true );
		Assert::assertSame( array( 'enabled' => '1', 'allow_admins' => '1' ), get_option( self::MODULE_OPTION ) );
	}

	// endregion

	// region WHO MAY EDIT

	/**
	 * The capability matrix: Automattician administrators always; other administrators only when
	 * the flag is on; never anyone below administrator.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function only_automatticians_can_edit_until_the_flag_is_on( IntegrationTester $i ): void {
		add_filter( 'user_has_cap', array( CrawlerFiles::class, 'filter_grant_capability' ), 10, 4 );

		$automattician = $this->create_user( 'insider@a8c.com', 'administrator' );
		$wpcom         = $this->create_user( 'insider@wordpress.com', 'administrator' );
		$client        = $this->create_user( 'client@example.com', 'administrator' );
		$a8c_editor    = $this->create_user( 'writer@automattic.com', 'editor' );

		update_option( self::MODULE_OPTION, array( 'enabled' => '1', 'allow_admins' => '0' ) );
		Assert::assertTrue( user_can( $automattician, CrawlerFiles::CAPABILITY ) );
		Assert::assertTrue( user_can( $wpcom, CrawlerFiles::CAPABILITY ) );
		Assert::assertFalse( user_can( $client, CrawlerFiles::CAPABILITY ), 'Flag off: a client administrator is kept out.' );
		Assert::assertFalse( user_can( $a8c_editor, CrawlerFiles::CAPABILITY ), 'Editors never get in.' );

		update_option( self::MODULE_OPTION, array( 'enabled' => '1', 'allow_admins' => '1' ) );
		Assert::assertTrue( user_can( $automattician, CrawlerFiles::CAPABILITY ) );
		Assert::assertTrue( user_can( $client, CrawlerFiles::CAPABILITY ), 'Flag on: every administrator gets in.' );
		Assert::assertFalse( user_can( $a8c_editor, CrawlerFiles::CAPABILITY ), 'Editors still never get in.' );
	}

	/**
	 * The filter grants only its own capability.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function the_capability_filter_grants_nothing_else( IntegrationTester $i ): void {
		$user = new WP_User( $this->create_user( 'insider@a8c.com', 'administrator' ) );
		$caps = array( 'manage_options' => true );

		Assert::assertSame( $caps, CrawlerFiles::filter_grant_capability( $caps, array( 'edit_posts' ), array(), $user ) );
		Assert::assertSame(
			$caps + array( CrawlerFiles::CAPABILITY => true ),
			CrawlerFiles::filter_grant_capability( $caps, array( CrawlerFiles::CAPABILITY ), array(), $user )
		);
	}

	/**
	 * Core's options.php gate for the content is the editor capability, so a client
	 * administrator cannot POST content while the flag is off, page or no page.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function saving_content_through_options_php_needs_the_editor_capability( IntegrationTester $i ): void {
		add_filter( 'user_has_cap', array( CrawlerFiles::class, 'filter_grant_capability' ), 10, 4 );
		( new EditorPage() )->initialize();

		$capability = (string) apply_filters( 'option_page_capability_' . EditorPage::OPTION_GROUP, 'manage_options' );
		Assert::assertSame( CrawlerFiles::CAPABILITY, $capability );

		$client = $this->create_user( 'client@example.com', 'administrator' );
		update_option( self::MODULE_OPTION, array( 'enabled' => '1', 'allow_admins' => '0' ) );

		wp_set_current_user( $client );
		Assert::assertTrue( current_user_can( 'manage_options' ), 'Test precondition: the client is an administrator.' );
		Assert::assertFalse( current_user_can( $capability ) );

		update_option( self::MODULE_OPTION, array( 'enabled' => '1', 'allow_admins' => '1' ) );
		Assert::assertTrue( current_user_can( $capability ) );
	}

	/**
	 * Letting every administrator edit the files does not let them change who may edit: that
	 * flag lives in the modules group, which stays Automattician-only.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_client_administrator_cannot_flip_the_flag( IntegrationTester $i ): void {
		add_filter( 'user_has_cap', array( CrawlerFiles::class, 'filter_grant_capability' ), 10, 4 );
		update_option( self::MODULE_OPTION, array( 'enabled' => '1', 'allow_admins' => '1' ) );

		wp_set_current_user( $this->create_user( 'client@example.com', 'administrator' ) );

		$modules_capability = (string) apply_filters( 'option_page_capability_' . Settings::MODULES_OPTION_GROUP, 'manage_options' );
		Assert::assertSame( Settings::MANAGE_MODULES_CAP, $modules_capability );
		Assert::assertTrue( current_user_can( CrawlerFiles::CAPABILITY ), 'Test precondition: the flag lets them edit the files.' );
		Assert::assertFalse( current_user_can( $modules_capability ) );
	}

	// endregion

	// region WHAT IS SERVED

	/**
	 * Serving follows the environment type unless filtered, and Yoast turns it off.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function serving_needs_production_and_no_yoast( IntegrationTester $i ): void {
		Assert::assertSame( 'production' === wp_get_environment_type(), CrawlerFiles::is_serving_environment() );
		Assert::assertSame( defined( 'WPSEO_VERSION' ), CrawlerFiles::is_yoast_active() );

		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );
		Assert::assertTrue( CrawlerFiles::is_serving() );

		remove_all_filters( CrawlerFiles::YOAST_FILTER );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_true' );
		Assert::assertFalse( CrawlerFiles::is_serving() );

		remove_all_filters( CrawlerFiles::YOAST_FILTER );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );
		remove_all_filters( CrawlerFiles::SERVE_FILTER );
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_false' );
		Assert::assertFalse( CrawlerFiles::is_serving() );
	}

	/**
	 * A physical file in the site root is detected.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_physical_file_in_the_site_root_is_detected( IntegrationTester $i ): void {
		$filename = 'a8csp-crawler-files-test.txt';

		Assert::assertFalse( CrawlerFiles::physical_file_exists( $filename ) );

		if ( false === file_put_contents( ABSPATH . $filename, 'x' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			Assert::markTestSkipped( 'The site root is not writable here.' );
		}

		try {
			Assert::assertTrue( CrawlerFiles::physical_file_exists( $filename ) );
		} finally {
			wp_delete_file( ABSPATH . $filename );
		}
	}

	// endregion

	// region EDITOR PAGE

	/**
	 * The content options are created empty and not autoloaded, and the settings group accepts
	 * exactly those two options.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function the_editor_registers_two_non_autoloaded_options( IntegrationTester $i ): void {
		global $wpdb, $new_allowed_options;

		EditorPage::prepare_options();
		EditorPage::prepare_options(); // Idempotent.

		foreach ( EditorPage::get_content_options() as $option ) {
			$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Assert::assertContains( $autoload, array( 'off', 'no' ), "{$option} must not be autoloaded." );
			Assert::assertSame( '', get_option( $option ) );
		}

		$this->load_settings_api();
		( new EditorPage() )->register_settings();
		Assert::assertEqualsCanonicalizing( array( RobotsTxt::OPTION, LlmsTxt::OPTION ), array_values( array_unique( $new_allowed_options[ EditorPage::OPTION_GROUP ] ?? array() ) ) );

		$registered = get_registered_settings();
		Assert::assertFalse( $registered[ RobotsTxt::OPTION ]['show_in_rest'] );
		Assert::assertFalse( $registered[ LlmsTxt::OPTION ]['show_in_rest'] );
	}

	// endregion

	// region CHANGE LOG

	/**
	 * A change records who made it; creating the empty option does not.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function changes_are_recorded_with_their_author( IntegrationTester $i ): void {
		( new ChangeLog() )->initialize();

		EditorPage::prepare_options();
		Assert::assertNull( ChangeLog::get_last_change( RobotsTxt::OPTION ), 'Creating the empty option is not an edit.' );

		$user_id = $this->create_user( 'insider@a8c.com', 'administrator', 'Ana Insider' );
		wp_set_current_user( $user_id );

		$before = time();
		update_option( LlmsTxt::OPTION, '# Site' );

		$change = ChangeLog::get_last_change( LlmsTxt::OPTION );
		Assert::assertNotNull( $change );
		Assert::assertSame( $user_id, $change['user_id'] );
		Assert::assertGreaterThanOrEqual( $before, $change['time'] );
		Assert::assertStringStartsWith( 'Last changed by Ana Insider on ', (string) ChangeLog::describe( LlmsTxt::OPTION ) );
		Assert::assertNull( ChangeLog::get_last_change( RobotsTxt::OPTION ), 'Only the changed file is recorded.' );

		wp_set_current_user( 0 );
		update_option( LlmsTxt::OPTION, '# Site from WP-CLI' );
		Assert::assertStringStartsWith( 'Last changed by code or WP-CLI on ', (string) ChangeLog::describe( LlmsTxt::OPTION ) );

		foreach ( EditorPage::get_content_options() as $option ) {
			remove_action( "add_option_{$option}", array( ChangeLog::class, 'record_added' ) );
			remove_action( "update_option_{$option}", array( ChangeLog::class, 'record_updated' ) );
		}
	}

	// endregion

	// region NOTICES

	/**
	 * Problems are escaped, since core prints settings messages raw, and long lists are capped.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function problem_lists_are_escaped_and_capped( IntegrationTester $i ): void {
		$problems = array_merge( array( '<img src=x onerror=alert(1)>' ), array_fill( 0, 14, 'Another problem' ) );
		$message  = CrawlerFiles::format_problems( 'Heading <b>', $problems );

		Assert::assertStringNotContainsString( '<img', $message );
		Assert::assertStringNotContainsString( 'Heading <b>', $message );
		Assert::assertStringContainsString( '&lt;img', $message );
		Assert::assertSame( 9, substr_count( $message, 'Another problem' ) );
		Assert::assertStringContainsString( 'and 5 more', $message );
	}

	// endregion

	// region STATUS ENDPOINT

	/**
	 * The status endpoint reports the module's state for fleet tooling.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function status_endpoint_reports_the_module( IntegrationTester $i ): void {
		update_option( self::MODULE_OPTION, array( 'enabled' => '1', 'allow_admins' => '1' ) );
		update_option( LlmsTxt::OPTION, '# Site' );
		add_filter( CrawlerFiles::SERVE_FILTER, '__return_true' );
		add_filter( CrawlerFiles::YOAST_FILTER, '__return_false' );

		$data = ( new Status_Controller() )->get_item( new WP_REST_Request( 'GET', '/a8csp-atlantis/v1/status' ) )->get_data();

		Assert::assertSame(
			array(
				'name'              => 'Crawler Files',
				'enabled'           => true,
				'allow_admins'      => true,
				'serving'           => true,
				'deferred_to_yoast' => false,
				'environment'       => wp_get_environment_type(),
				'robots_txt_rules'  => false,
				'llms_txt'          => true,
			),
			$data['modules']['crawler-files']
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Loads the admin Settings API functions, which integration tests do not load.
	 *
	 * @return void
	 */
	private function load_settings_api(): void {
		if ( ! function_exists( 'add_settings_section' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}
	}

	/**
	 * Creates a user and remembers them for cleanup.
	 *
	 * @param string $email        The email address.
	 * @param string $role         The role.
	 * @param string $display_name The display name.
	 *
	 * @return int
	 */
	private function create_user( string $email, string $role, string $display_name = '' ): int {
		$user_id = wp_insert_user(
			array(
				'user_login'   => 'crawler_files_' . wp_rand( 100000, 999999 ),
				'user_pass'    => wp_generate_password(),
				'user_email'   => $email,
				'role'         => $role,
				'display_name' => $display_name,
			)
		);

		Assert::assertIsInt( $user_id, 'Test precondition: the user could not be created.' );
		$this->user_ids[] = $user_id;

		return $user_id;
	}

	// endregion
}
