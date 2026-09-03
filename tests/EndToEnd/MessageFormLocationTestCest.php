<?php
/**
 * End-to-end tests for adding locations in the message form.
 */

declare(strict_types=1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Assert;
use Tests\Support\EndToEndTester;

/**
 * Message form location tests.
 */
class MessageFormLocationTestCest {
	/**
	 * Menu slug carrying a double quote, which the form places inside quoted attributes.
	 *
	 * @var string
	 */
	private const CRAFTED_SLUG = 'atlantis-probe"><b id="atlantis-broke-out">x</b><span data-z="';

	/**
	 * Registers an admin page whose slug contains a quote.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function _before( EndToEndTester $i ): void {
		$i->haveMuPlugin(
			'atlantis-crafted-location.php',
			'<?php
			add_action( "admin_menu", static function () {
				add_menu_page( "Probe", "Probe", "manage_options", ' . var_export( self::CRAFTED_SLUG, true ) . ', "__return_null" );
			} );'
		);
	}

	/**
	 * A menu slug containing a quote must not become markup when added as a location.
	 *
	 * @param EndToEndTester $i Tester instance.
	 *
	 * @return void
	 */
	public function crafted_menu_slug_does_not_become_markup( EndToEndTester $i ): void {
		$i->loginAsAdmin();
		$i->amOnAdminPage( 'admin.php?page=a8csp-atlantis-messages&action=new' );

		$i->seeElement( '.atlantis-location-dropdown' );

		$result = $i->executeJS(
			'const slug = ' . wp_json_encode( self::CRAFTED_SLUG ) . ';
			const select = document.querySelector( \'.atlantis-location-dropdown[data-target="include"]\' );
			const option = Array.from( select.options ).find( ( o ) => o.value === slug );
			if ( ! option ) { return "option-missing"; }
			select.value = slug;
			const event = jQuery.Event( "select2:select" );
			event.params = { data: { id: option.value, element: option } };
			try { jQuery( select ).trigger( event ); } catch ( e ) { return "triggered"; }
			return "triggered";'
		);

		Assert::assertSame( 'triggered', $result, 'The crafted menu slug should appear as an option.' );

		$i->wait( 1 );

		$i->dontSeeElement( '#atlantis-broke-out' );
	}
}
