<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\Modules\Colophon\Colophon;
use A8C\SpecialProjects\Atlantis\Modules\Tracking\Tracking;
use A8C\SpecialProjects\Atlantis\Modules\AutoUpdatePluginsFilter\AutoUpdatePluginsFilter;
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
	/**
	 * Available modules.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var array<string, array{class: string, instance: ?AbstractModule}>
	 */
	private array $modules = array();

	/**
	 * Messages module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public Messages $messages;

	/**
	 * Initialize the Modules functionality.
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 11 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		$this->messages = new Messages();
		$this->messages->initialize();

		$this->modules['colophon'] = new Colophon();
		$this->modules['colophon']->maybe_initialize();

		$this->modules['tracking'] = new Tracking();
		$this->modules['tracking']->maybe_initialize();

		$this->modules['auto-update-plugins-filter'] = new AutoUpdatePluginsFilter();
		$this->modules['auto-update-plugins-filter']->maybe_initialize();
	}

	// region METHODS

	/**
	 * Add the Modules page to the Atlantis menu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page(
				'a8csp-atlantis',
				__( 'Modules', 'a8csp-atlantis' ),
				__( 'Modules', 'a8csp-atlantis' ),
				'edit_posts',
				'atlantis-modules',
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Register the settings for the modules.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'atlantis_settings_group', 'atlantis_enabled_modules' );
	}

	/**
	 * Render the module activation settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'a8csp-atlantis' ) );
		}

		$enabled = get_option( 'atlantis_enabled_modules', array() );
		?>
		<div class="wrap">
			<h1>Team51 Atlantis Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'atlantis_settings_group' ); ?>
				<table class="form-table">
					<?php
					foreach ( $this->modules as $module ) :
						$is_disabled = $module->is_disabled();
						$is_wp_error = is_wp_error( $is_disabled );
						$key         = $module->get_settings_key();
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $module->get_name() ); ?></th>
							<td>
								<input
									type="checkbox"
									name="atlantis_enabled_modules[<?php echo esc_attr( $key ); ?>]"
									id="atlantis_enabled_modules[<?php echo esc_attr( $key ); ?>]"
									value="1"
									<?php checked( ! empty( $enabled[ $key ] ) ); ?>
									<?php disabled( $is_wp_error ); ?>
								/>
								<label for="atlantis_enabled_modules[<?php echo esc_attr( $key ); ?>]">
									Enable
								</label>
								<?php if ( $is_wp_error ) : ?>
									<p class="description">
										<?php echo esc_html( $is_disabled->get_error_message() ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
