<?php
/**
 * Integration tests for plugin uninstall cleanup.
 *
 * Mirrors what WordPress does when the plugin is deleted from
 * Plugins -> Installed Plugins -> Delete.
 */

declare(strict_types=1);

use A8C\SpecialProjects\Atlantis\Modules\Messages\CustomTable;
use PHPUnit\Framework\Assert;
use Tests\Support\IntegrationTester;

/**
 * Uninstall behaviour tests.
 */
class UninstallTestCest {
	/**
	 * Deleting the plugin must drop the messages table and remove every option it created.
	 *
	 * @param IntegrationTester $i Tester instance.
	 *
	 * @return void
	 */
	public function plugin_uninstall_drops_table_and_removes_options( IntegrationTester $i ): void {
		$this->ensure_messages_table_exists();
		$this->seed_plugin_state();

		Assert::assertTrue( CustomTable::table_exists(), 'Precondition: messages table exists.' );
		Assert::assertNotFalse( get_option( 'a8csp_atlantis_inserted_encryption_key' ), 'Precondition: encryption-key option exists.' );
		Assert::assertNotFalse( get_option( 'plugin_update_delays' ), 'Precondition: delay option exists.' );

		$this->run_uninstall();

		Assert::assertFalse( CustomTable::table_exists(), 'Messages table should be dropped on uninstall.' );
		Assert::assertFalse( get_option( 'a8csp_atlantis_inserted_encryption_key' ), 'Encryption-key option should be removed.' );
		Assert::assertFalse( get_option( 'a8csp_atlantis_messages_schema_version' ), 'Schema-version option should be removed.' );
		Assert::assertFalse( get_option( 'plugin_update_delays' ), 'plugin_update_delays should be removed.' );

		foreach ( array( 'Tracking', 'Colophon', 'Messages', 'Autoupdates', 'Bot Protection' ) as $module_name ) {
			$key = a8csp_atlantis_generate_module_settings_key( $module_name );
			Assert::assertFalse( get_option( $key ), "Module option {$key} should be removed." );
		}
	}

	/**
	 * Ensure the messages table exists before the test runs uninstall.
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

	/**
	 * Seed the options and table state a real site would have.
	 *
	 * @return void
	 */
	private function seed_plugin_state(): void {
		update_option( 'a8csp_atlantis_inserted_encryption_key', 'yes' );
		update_option( 'a8csp_atlantis_messages_schema_version', '1.0.0' );
		update_option( 'plugin_update_delays', array( 'delay' => 7 ) );

		foreach ( array( 'Tracking', 'Colophon', 'Messages', 'Autoupdates', 'Bot Protection' ) as $module_name ) {
			update_option( a8csp_atlantis_generate_module_settings_key( $module_name ), array( 'enabled' => '1' ) );
		}
	}

	/**
	 * Include the plugin's uninstall.php the same way WordPress core does on delete.
	 *
	 * @return void
	 */
	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'a8csp-atlantis/a8csp-atlantis.php' );
		}

		$uninstall_file = constant( 'A8CSP_ATLANTIS_DIR_PATH' ) . 'uninstall.php';
		Assert::assertFileExists( $uninstall_file, 'Plugin must ship an uninstall.php in its root.' );

		include $uninstall_file;
	}
}
