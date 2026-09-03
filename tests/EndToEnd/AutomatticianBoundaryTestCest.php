<?php
/**
 * End-to-end checks on the Automattician trust boundary.
 *
 * `a8csp_atlantis_is_automattician()` trusts an administrator whose own email address ends in an
 * Automattic domain. This walks the real profile screen to establish whether a non-Automattic
 * administrator can simply set such an address on themselves.
 */

declare(strict_types=1);

namespace Tests\EndToEnd;

use Tests\Support\EndToEndTester;

/**
 * Automattician boundary end-to-end tests.
 */
class AutomatticianBoundaryTestCest {
	/**
	 * Login for the administrator created by each test.
	 *
	 * @var string
	 */
	private const USER_LOGIN = 'outsider_admin';

	/**
	 * Password for the administrator created by each test.
	 *
	 * @var string
	 */
	private const USER_PASSWORD = 'outsider_password';

	/**
	 * The address the user starts with.
	 *
	 * @var string
	 */
	private const ORIGINAL_EMAIL = 'outsider@example.com';

	/**
	 * The address the user attempts to move to.
	 *
	 * @var string
	 */
	private const TARGET_EMAIL = 'outsider@a8c.com';

	/**
	 * An administrator editing their own profile is told the address must be confirmed.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function own_profile_warns_that_a_new_address_needs_confirming( EndToEndTester $i ): void {
		$this->create_outsider_admin( $i );

		$i->loginAs( self::USER_LOGIN, self::USER_PASSWORD );
		$i->amOnAdminPage( 'profile.php' );

		$i->see( 'The new address will not become active until confirmed.' );
	}

	/**
	 * Submitting a new address does not change the account. Core parks it in `_new_email` until
	 * whoever owns the new mailbox clicks the confirmation link, so an administrator cannot hand
	 * themselves an Automattic address they do not control.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function changing_own_email_does_not_take_effect_until_confirmed( EndToEndTester $i ): void {
		$user_id = $this->create_outsider_admin( $i );

		$i->loginAs( self::USER_LOGIN, self::USER_PASSWORD );
		$i->amOnAdminPage( 'profile.php' );

		$i->fillField( '#email', self::TARGET_EMAIL );
		$i->click( '#submit' );

		// Core parks the request rather than applying it.
		$i->see( 'There is a pending change of your email to' );
		$i->see( self::TARGET_EMAIL );

		// The account itself is untouched, so the Automattician check still sees the old domain.
		$i->seeUserInDatabase(
			array(
				'ID'         => $user_id,
				'user_email' => self::ORIGINAL_EMAIL,
			)
		);
		$i->dontSeeUserInDatabase(
			array(
				'ID'         => $user_id,
				'user_email' => self::TARGET_EMAIL,
			)
		);
	}

	/**
	 * Creates an administrator with a non-Automattic address.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return int
	 */
	private function create_outsider_admin( EndToEndTester $i ): int {
		return $i->haveUserInDatabase(
			self::USER_LOGIN,
			'administrator',
			array(
				'user_pass'  => self::USER_PASSWORD,
				'user_email' => self::ORIGINAL_EMAIL,
			)
		);
	}
}
