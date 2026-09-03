<?php
/**
 * Integration tests for Message_Query SQL preparation.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Message_Query;
use A8C\SpecialProjects\Atlantis\Modules\Messages\CustomTable;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Message query integration tests.
 */
class MessageQueryTestCest {
	/**
	 * With no filters and no limit the query carries no placeholders, so passing it through
	 * `prepare()` makes core complain on every call.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function unfiltered_unlimited_query_does_not_call_prepare_without_placeholders( IntegrationTester $i ): void {
		$this->ensure_messages_table_exists();

		$complaints = $this->capture_doing_it_wrong(
			static function () {
				new Message_Query( array( 'per_page' => -1 ) );
			}
		);

		Assert::assertNotContains(
			'wpdb::prepare',
			$complaints,
			'An unfiltered, unlimited query must not pass a placeholder-free statement to prepare().'
		);
	}

	/**
	 * The filtered and limited paths do bind parameters, so they must keep using prepare().
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function filtered_and_limited_queries_still_return_rows( IntegrationTester $i ): void {
		$this->ensure_messages_table_exists();

		$complaints = $this->capture_doing_it_wrong(
			static function () {
				new Message_Query( array( 'status' => 'active' ) );
				new Message_Query( array( 'per_page' => 5 ) );
				new Message_Query( array( 'search' => 'anything' ) );
			}
		);

		Assert::assertNotContains( 'wpdb::prepare', $complaints );
	}

	/**
	 * Runs a callable while recording which functions core flagged via `_doing_it_wrong()`.
	 *
	 * @param callable $callable The code to run.
	 *
	 * @return array
	 */
	private function capture_doing_it_wrong( callable $callable ): array {
		$complaints = array();

		$capture = static function ( $function_name ) use ( &$complaints ) {
			$complaints[] = $function_name;
		};

		add_action( 'doing_it_wrong_run', $capture );

		try {
			$callable();
		} finally {
			remove_action( 'doing_it_wrong_run', $capture );
		}

		return $complaints;
	}

	/**
	 * Ensure the custom messages table is created before querying it.
	 *
	 * @return void
	 */
	private function ensure_messages_table_exists(): void {
		if ( CustomTable::table_exists() ) {
			return;
		}

		$table = new CustomTable();
		$table->maybe_create_table();
	}
}
