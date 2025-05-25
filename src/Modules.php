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

		$this->modules = array(
			'messages'    => new Messages(),
			'colophon'    => new Colophon(),
			'tracking'    => new Tracking(),
			'autoupdates' => new AutoUpdatePluginsFilter(),
		);
		foreach ( $this->modules as $module ) {
			$module->maybe_initialize();
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
