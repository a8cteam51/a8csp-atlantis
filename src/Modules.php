<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\Modules\Colophon\Colophon;
use A8C\SpecialProjects\Atlantis\Modules\Tracking\Tracking;
use A8C\SpecialProjects\Atlantis\Modules\Autoupdates\AutoUpdatePluginsFilter;
use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;
use A8C\SpecialProjects\Atlantis\Modules\Messages\Messages;


defined( 'ABSPATH' ) || exit;

/**
 * Modules class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Modules {
	// region FIELDS AND CONSTANTS

	/**
	 * Available modules.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var array<string, ?AbstractModule>
	 */
	public array $modules;

	// endregion

	// region METHODS

	/**
	 * Initialize the submodules.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'a8csp/atlantis/admin_menu_registered', array( $this, 'register_admin_menu' ) );

		$this->modules = array();
		$this->try_initialize_module( 'messages', static fn() => new Messages() );
		$this->try_initialize_module( 'colophon', static fn() => new Colophon() );
		$this->try_initialize_module( 'tracking', static fn() => new Tracking() );
		$this->try_initialize_module( 'autoupdates', static fn() => new AutoUpdatePluginsFilter() );
	}

	/**
	 * Attempts to initialize a module, catching any errors to prevent
	 * a single module failure from breaking the entire plugin.
	 *
	 * @since   1.0.9
	 * @version 1.0.9
	 *
	 * @param string                     $key     The module key.
	 * @param callable(): AbstractModule $factory A callable that creates the module instance.
	 *
	 * @return void
	 */
	private function try_initialize_module( string $key, callable $factory ): void {
		try {
			$module = $factory();
			$module->maybe_initialize();
			$this->modules[ $key ] = $module;
		} catch ( \Throwable $throwable ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[A8CSP Atlantis] Failed to initialize module "%s": %s',
					$key,
					$throwable->getMessage()
				)
			);
		}
	}

	// endregion

	// region HOOKS

	/**
	 * Registers a submenu page for the Atlantis Modules settings.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'a8csp-atlantis',
			_x( 'Modules', 'page title', 'a8csp-atlantis' ),
			_x( 'Modules', 'menu title', 'a8csp-atlantis' ),
			'manage_options',
			'a8csp-atlantis-modules',
			function () {
				?>
				<div class="wrap">
					<h1><?php esc_html_e( 'Modules Settings', 'a8csp-atlantis' ); ?></h1>
					<form method="post" action="options.php">
						<?php
						settings_fields( 'a8csp_modules_group' );
						do_settings_sections( 'a8csp-atlantis-modules' );
						submit_button();
						?>
					</form>
				</div>
				<?php
			}
		);
	}

	// endregion
}
