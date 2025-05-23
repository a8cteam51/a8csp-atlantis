<?php
/**
 * Template for displaying the message form
 *
 * @package A8C\SpecialProjects\Atlantis
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_editor();
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php echo $id > 0 ? esc_html__( 'Edit Message', 'a8csp-atlantis' ) : esc_html__( 'Add New Message', 'a8csp-atlantis' ); ?>
	</h1>
	<a href="<?php echo esc_url( remove_query_arg( array( 'action', 'id' ) ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Back to List', 'a8csp-atlantis' ); ?></a>

	<hr class="wp-header-end">

	<form method="post" action="">
		<?php wp_nonce_field( 'atlantis_message_edit', 'atlantis_message_nonce' ); ?>
		<input type="hidden" name="action" value="save_message">
		<input type="hidden" name="message_id" value="<?php echo esc_attr( $id ); ?>">

		<table class="form-table">
			<tr>
				<th scope="row"><label for="message_name"><?php echo esc_html__( 'Message Name', 'a8csp-atlantis' ); ?></label></th>
				<td>
					<input type="text" name="message_name" id="message_name" class="regular-text" value="<?php echo esc_attr( $message ? $message->message_name : '' ); ?>" required>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_content"><?php echo esc_html__( 'Message Content', 'a8csp-atlantis' ); ?></label></th>
				<td>
					<?php
					wp_editor(
						$message ? $message->message_content : '',
						'message_content',
						array(
							'textarea_name' => 'message_content',
							'media_buttons' => false,
							'textarea_rows' => 10,
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_type"><?php echo esc_html__( 'Message Type', 'a8csp-atlantis' ); ?></label></th>
				<td>
					<select name="message_type" id="message_type" required>
						<option value="info" <?php selected( $message ? $message->message_type : '', 'info' ); ?>><?php echo esc_html__( 'Info', 'a8csp-atlantis' ); ?></option>
						<option value="warning" <?php selected( $message ? $message->message_type : '', 'warning' ); ?>><?php echo esc_html__( 'Warning', 'a8csp-atlantis' ); ?></option>
						<option value="error" <?php selected( $message ? $message->message_type : '', 'error' ); ?>><?php echo esc_html__( 'Error', 'a8csp-atlantis' ); ?></option>
						<option value="success" <?php selected( $message ? $message->message_type : '', 'success' ); ?>><?php echo esc_html__( 'Success', 'a8csp-atlantis' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_status"><?php echo esc_html__( 'Status', 'a8csp-atlantis' ); ?></label></th>
				<td>
					<select name="message_status" id="message_status" required>
						<option value="active" <?php selected( $message ? $message->message_status : '', 'active' ); ?>><?php echo esc_html__( 'Active', 'a8csp-atlantis' ); ?></option>
						<option value="inactive" <?php selected( $message ? $message->message_status : '', 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'a8csp-atlantis' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_location_include"><?php echo esc_html__( 'Included Locations', 'a8csp-atlantis' ); ?></label></th>
				<td>
					<div id="atlantis-included-locations" class="atlantis-location-list">
						<?php
						foreach ( $current_location as $location ) :
							?>
							<div class="atlantis-location-item">
								<span><?php echo esc_html( $locations[ $location ] ?? $location ); ?></span>
								<button type="button" class="button-link delete-location" data-location="<?php echo esc_attr( $location ); ?>"><?php echo esc_html__( 'Remove', 'a8csp-atlantis' ); ?></button>
								<input type="hidden" name="message_location_include[]" value="<?php echo esc_attr( $location ); ?>">
							</div>
						<?php endforeach; ?>
					</div>
					<select class="atlantis-location-dropdown" data-target="include">
						<option value=""><?php echo esc_html__( '-- Include a location --', 'a8csp-atlantis' ); ?></option>
						<?php
						foreach ( $locations as $key => $label ) :
							if ( ! in_array( $key, $current_location, true ) ) :
								?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php
							endif;
						endforeach;
						?>
					</select>
					<p class="description"><?php echo esc_html__( 'Select one or more Admin pages where this notice should be displayed.', 'a8csp-atlantis' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_location_exclude"><?php echo esc_html__( 'Excluded Locations', 'a8csp-atlantis' ); ?></label></th>
				<td>
					<div id="atlantis-excluded-locations" class="atlantis-location-list">
						<?php
						foreach ( $current_exclude as $location ) :
							?>
							<div class="atlantis-location-item">
								<span><?php echo esc_html( $locations[ $location ] ?? $location ); ?></span>
								<button type="button" class="button-link delete-location" data-location="<?php echo esc_attr( $location ); ?>"><?php echo esc_html__( 'Remove', 'a8csp-atlantis' ); ?></button>
								<input type="hidden" name="message_location_exclude[]" value="<?php echo esc_attr( $location ); ?>">
							</div>
						<?php endforeach; ?>
					</div>
					<select class="atlantis-location-dropdown" data-target="exclude">
						<option value=""><?php echo esc_html__( '-- Exclude a location --', 'a8csp-atlantis' ); ?></option>
						<?php
						foreach ( $locations as $key => $label ) :
							if ( ! in_array( $key, $current_exclude, true ) ) :
								?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php
							endif;
						endforeach;
						?>
					</select>
					<p class="description"><?php echo esc_html__( 'Select one or more Admin pages where this notice should NOT be displayed.', 'a8csp-atlantis' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $id > 0 ? esc_attr__( 'Update Message', 'a8csp-atlantis' ) : esc_attr__( 'Add Message', 'a8csp-atlantis' ); ?>">
		</p>
	</form>
</div>
