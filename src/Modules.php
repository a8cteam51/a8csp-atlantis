<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\Modules\Colophon;

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
	 * Colophon module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public Colophon $colophon;

	/**
	 * Initialize the Modules functionality.
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 11 );
	}

	/**
	 * Add the Modules page to the Atlantis menu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page(
				'a8csp-atlantis',
				__( 'Modules', 'atlantis' ),
				__( 'Modules', 'atlantis' ),
				'edit_posts',
				'atlantis-modules',
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Render the access logs admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'atlantis' ) );
		}
		?>
		<div class="wrap">
				<h1>Team51 Atlantis Settings</h1>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'atlantis_settings_group' );
					$enabled = get_option( 'atlantis_enabled_modules', array() );
					?>
					<table class="form-table">
						<tr>
							<th scope="row">Autoupdate Filter</th>
							<td>
								<input type="checkbox" name="atlantis_enabled_modules[autoupdate-filter]" value="1" <?php checked( ! empty( $enabled['autoupdate-filter'] ) ); ?> />
								Enable
							</td>
						</tr>
						<tr>
							<th scope="row">Tracking</th>
							<td>
								<input type="checkbox" name="atlantis_enabled_modules[tracking]" value="1" <?php checked( ! empty( $enabled['tracking'] ) ); ?> />
								Enable
							</td>
						</tr>
						<tr>
							<th scope="row">Colophon</th>
							<td>
								<input type="checkbox" name="atlantis_enabled_modules[colophon]" value="1" <?php checked( ! empty( $enabled['colophon'] ) ); ?> />
								Enable
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
		<?php
	}
}
