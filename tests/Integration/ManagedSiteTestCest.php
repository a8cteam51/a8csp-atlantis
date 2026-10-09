<?php
/**
 * Integration tests for the managed-site flag.
 *
 * Atlantis is public, so a site that installs it is not thereby one the WordPress Special Projects
 * team manages. The flag is what tells the two apart, and these tests cover how it is read and how
 * it is first recorded.
 */

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Managed-site flag tests.
 */
class ManagedSiteTestCest {
	/**
	 * The option holding the flag.
	 *
	 * @var string
	 */
	private const OPTION = 'a8csp_atlantis_managed_site';

	/**
	 * The Messages module's settings row, which is what says Atlantis has run here before.
	 *
	 * @var string
	 */
	private const MESSAGES_OPTION = 'a8csp_module_messages';

	/**
	 * The Messages settings as they were before a test replaced them.
	 *
	 * @var mixed
	 */
	private mixed $messages_settings = null;

	/**
	 * Remembers the Messages settings, which the recording tests remove.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$this->messages_settings = get_option( self::MESSAGES_OPTION, null );
		delete_option( self::OPTION );
	}

	/**
	 * Puts the Messages settings back and clears the flag.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _after( IntegrationTester $i ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		if ( null === $this->messages_settings ) {
			delete_option( self::MESSAGES_OPTION );
		} else {
			update_option( self::MESSAGES_OPTION, $this->messages_settings );
		}

		delete_option( self::OPTION );
	}

	/**
	 * Only the exact stored value turns the flag on, and nothing stored means unmanaged: a site
	 * must never become managed by accident.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function only_the_stored_flag_makes_a_site_managed( IntegrationTester $i ): void {
		Assert::assertFalse( defined( 'A8CSP_ATLANTIS_MANAGED_SITE' ), 'Test precondition: the constant override is not in play.' );
		Assert::assertFalse( a8csp_atlantis_is_managed_site(), 'Nothing stored means unmanaged.' );

		update_option( self::OPTION, '1' );
		Assert::assertTrue( a8csp_atlantis_is_managed_site() );

		foreach ( array( '0', '', 'yes', 'true' ) as $value ) {
			update_option( self::OPTION, $value );
			Assert::assertFalse( a8csp_atlantis_is_managed_site(), "A stored `$value` is not the flag." );
		}
	}

	/**
	 * A site that ran Atlantis before the flag existed keeps the behaviour it had.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_site_that_already_ran_atlantis_is_recorded_as_managed( IntegrationTester $i ): void {
		update_option( self::MESSAGES_OPTION, array( 'enabled' => '1' ) );

		a8csp_atlantis_maybe_record_managed_site();

		Assert::assertSame( '1', get_option( self::OPTION ) );
		Assert::assertTrue( a8csp_atlantis_is_managed_site() );
	}

	/**
	 * A fresh install is recorded as unmanaged — including one whose activation hook has already
	 * written the Autoupdates settings, which happens before the plugin has ever loaded.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_fresh_install_is_recorded_as_unmanaged( IntegrationTester $i ): void {
		$autoupdates_option   = a8csp_atlantis_generate_module_settings_key( 'Autoupdates' );
		$autoupdates_settings = get_option( $autoupdates_option, null );

		delete_option( self::MESSAGES_OPTION );
		update_option( $autoupdates_option, array( 'enabled' => '0' ) );

		try {
			a8csp_atlantis_maybe_record_managed_site();

			Assert::assertSame( '0', get_option( self::OPTION ) );
			Assert::assertFalse( a8csp_atlantis_is_managed_site() );
		} finally {
			if ( null === $autoupdates_settings ) {
				delete_option( $autoupdates_option );
			} else {
				update_option( $autoupdates_option, $autoupdates_settings );
			}
		}
	}

	/**
	 * The answer is written once. Whoever provisions a site sets the flag before installing, and
	 * an operator may turn it off on a site that was grandfathered in; neither may be undone by
	 * the next request.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function a_recorded_answer_is_never_overwritten( IntegrationTester $i ): void {
		// Provisioned as managed before Atlantis ever ran.
		delete_option( self::MESSAGES_OPTION );
		update_option( self::OPTION, '1' );

		a8csp_atlantis_maybe_record_managed_site();
		Assert::assertSame( '1', get_option( self::OPTION ) );

		// Turned off by hand on a site that has run Atlantis all along.
		update_option( self::MESSAGES_OPTION, array( 'enabled' => '1' ) );
		update_option( self::OPTION, '0' );

		a8csp_atlantis_maybe_record_managed_site();
		Assert::assertSame( '0', get_option( self::OPTION ) );
	}
}
