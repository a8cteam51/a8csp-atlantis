<?php declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function atlantis_register_settings() {
	register_setting( 'atlantis_settings_group', 'atlantis_enabled_modules' );
}
add_action( 'admin_init', 'atlantis_register_settings' );

function atlantis_settings_page() {
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

function atlantis_add_settings_page() {
	add_options_page(
		'Team51 Atlantis Settings',
		'Team51 Atlantis',
		'manage_options',
		'atlantis-settings',
		'atlantis_settings_page'
	);
}
add_action( 'admin_menu', 'atlantis_add_settings_page' );

