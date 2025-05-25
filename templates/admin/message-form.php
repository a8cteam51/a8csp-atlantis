<?php
/**
 * Template for displaying the message form
 *
 * @since   1.0.0
 * @version 1.0.0
 * @package A8C\SpecialProjects\Atlantis
 *
 * @var \A8C\SpecialProjects\Atlantis\Message $a8csp_atlantis_message The message object.
 * @var string[]                              $a8csp_admin_locations List of available locations.
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_editor();

wp_enqueue_style(
	'a8csp-atlantis-message-form',
	A8CSP_ATLANTIS_DIR_URL . 'assets/css/build/message-form.css',
	array(),
	a8csp_atlantis_get_plugin_metadata( 'Version' )
);

wp_enqueue_script(
	'a8csp-atlantis-message-form',
	A8CSP_ATLANTIS_DIR_URL . 'assets/js/build/message-form.js',
	array( 'jquery' ),
	a8csp_atlantis_get_plugin_metadata( 'Version' ),
	true
);

wp_localize_script(
	'atlantis-message-form',
	'atlantisLocations',
	array(
		'locations' => $a8csp_admin_locations,
		'include'   => $a8csp_atlantis_message->locations,
		'exclude'   => $a8csp_atlantis_message->excludes,
	)
);

?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php echo 0 < $a8csp_atlantis_message->id ? esc_html__( 'Edit Message', 'a8csp-atlantis' ) : esc_html__( 'Add New Message', 'a8csp-atlantis' ); ?>
	</h1>
	<a href="<?php echo esc_url( remove_query_arg( array( 'action', 'id' ) ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Back to List', 'a8csp-atlantis' ); ?>
	</a>

	<hr class="wp-header-end">

	<form method="post" action="">
		<?php wp_nonce_field( 'save_message', 'a8csp_atlantis_message_nonce' ); ?>
		<input type="hidden" name="action" value="a8csp_atlantis_save_message">
		<input type="hidden" name="id" value="<?php echo esc_attr( $a8csp_atlantis_message->id ); ?>">

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="title">
						<?php echo esc_html__( 'Message Title', 'a8csp-atlantis' ); ?>
					</label>
				</th>
				<td>
					<input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $a8csp_atlantis_message->title ); ?>" required>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="content">
						<?php echo esc_html__( 'Message Content', 'a8csp-atlantis' ); ?>
					</label>
				</th>
				<td>
					<?php
					wp_editor(
						$a8csp_atlantis_message->content,
						'content',
						array(
							'textarea_name' => 'content',
							'media_buttons' => false,
							'textarea_rows' => 10,
						)
					);
					?>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="type">
						<?php echo esc_html__( 'Message Type', 'a8csp-atlantis' ); ?>
					</label>
				</th>
				<td>
					<select name="type" id="type" required>
						<?php
						foreach ( array( 'info', 'warning', 'error', 'success' ) as $a8csp_atlantis_message_type ) {
							printf(
								'<option value="%1$s" %2$s>%3$s</option>',
								esc_attr( $a8csp_atlantis_message_type ),
								selected( $a8csp_atlantis_message->type, $a8csp_atlantis_message_type, false ),
								esc_html( ucfirst( $a8csp_atlantis_message_type ) )
							);
						}
						?>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="status">
						<?php echo esc_html__( 'Status', 'a8csp-atlantis' ); ?>
					</label>
				</th>
				<td>
					<select name="status" id="status" required>
						<option value="active" <?php selected( $a8csp_atlantis_message->status, 'active' ); ?>>
							<?php esc_html_e( 'Active', 'a8csp-atlantis' ); ?>
						</option>
						<option value="inactive" <?php selected( $a8csp_atlantis_message->status, 'inactive' ); ?>>
							<?php esc_html_e( 'Inactive', 'a8csp-atlantis' ); ?>
						</option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php echo esc_html__( 'Included Locations', 'a8csp-atlantis' ); ?>
				</th>
				<td>
					<div id="atlantis-included-locations" class="atlantis-location-list">
						<?php
						foreach ( $a8csp_atlantis_message->locations as $a8csp_atlantis_location ) :
							?>
							<div class="atlantis-location-item">
								<span><?php echo esc_html( $locations[ $a8csp_atlantis_location ] ?? $a8csp_atlantis_location ); ?></span>
								<button type="button" class="button-link delete-location" data-location="<?php echo esc_attr( $a8csp_atlantis_location ); ?>"><?php echo esc_html__( 'Remove', 'a8csp-atlantis' ); ?></button>
								<input type="hidden" name="location_include[]" value="<?php echo esc_attr( $a8csp_atlantis_location ); ?>">
							</div>
						<?php endforeach; ?>
					</div>

					<select class="atlantis-location-dropdown" data-target="include">
						<option value="">
							<?php echo esc_html__( '-- Include a location --', 'a8csp-atlantis' ); ?>
						</option>
						<?php
						foreach ( $a8csp_admin_locations as $a8csp_location_key => $a8csp_location_label ) :
							if ( ! in_array( $a8csp_location_key, $a8csp_atlantis_message->locations, true ) ) :
								?>
								<option value="<?php echo esc_attr( $a8csp_location_key ); ?>"><?php echo esc_html( $a8csp_location_label ); ?></option>
								<?php
							endif;
						endforeach;
						?>
					</select>

					<p class="description">
						<?php echo esc_html__( 'Select one or more Admin pages where this notice should be displayed.', 'a8csp-atlantis' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Excluded Locations', 'a8csp-atlantis' ); ?>
				</th>
				<td>
					<div id="atlantis-excluded-locations" class="atlantis-location-list">
						<?php
						foreach ( $a8csp_atlantis_message->exclusions as $a8csp_atlantis_location ) :
							?>
							<div class="atlantis-location-item">
								<span><?php echo esc_html( $locations[ $a8csp_atlantis_location ] ?? $a8csp_atlantis_location ); ?></span>
								<button type="button" class="button-link delete-location" data-location="<?php echo esc_attr( $a8csp_atlantis_location ); ?>"><?php echo esc_html__( 'Remove', 'a8csp-atlantis' ); ?></button>
								<input type="hidden" name="location_exclude[]" value="<?php echo esc_attr( $a8csp_atlantis_location ); ?>">
							</div>
						<?php endforeach; ?>
					</div>

					<select class="atlantis-location-dropdown" data-target="exclude">
						<option value=""><?php echo esc_html__( '-- Exclude a location --', 'a8csp-atlantis' ); ?></option>
						<?php
						foreach ( $a8csp_admin_locations as $a8csp_location_key => $a8csp_location_label ) :
							if ( ! in_array( $a8csp_location_key, $a8csp_atlantis_message->exclusions, true ) ) :
								?>
								<option value="<?php echo esc_attr( $a8csp_location_key ); ?>"><?php echo esc_html( $a8csp_location_label ); ?></option>
								<?php
							endif;
						endforeach;
						?>
					</select>

					<p class="description">
						<?php echo esc_html__( 'Select one or more Admin pages where this notice should NOT be displayed.', 'a8csp-atlantis' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo 0 < $a8csp_atlantis_message->id ? esc_attr__( 'Update Message', 'a8csp-atlantis' ) : esc_attr__( 'Add Message', 'a8csp-atlantis' ); ?>">
		</p>
	</form>
</div>
