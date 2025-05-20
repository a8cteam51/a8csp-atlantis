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
		<?php echo $id > 0 ? esc_html__( 'Edit Message', 'atlantis' ) : esc_html__( 'Add New Message', 'atlantis' ); ?>
	</h1>
	<a href="<?php echo esc_url( remove_query_arg( array( 'action', 'id' ) ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Back to List', 'atlantis' ); ?></a>
	
	<hr class="wp-header-end">

	<form method="post" action="">
		<?php wp_nonce_field( 'atlantis_message_edit', 'atlantis_message_nonce' ); ?>
		<input type="hidden" name="action" value="save_message">
		<input type="hidden" name="message_id" value="<?php echo esc_attr( $id ); ?>">

		<table class="form-table">
			<tr>
				<th scope="row"><label for="message_name"><?php echo esc_html__( 'Message Name', 'atlantis' ); ?></label></th>
				<td>
					<input type="text" name="message_name" id="message_name" class="regular-text" value="<?php echo esc_attr( $message ? $message->message_name : '' ); ?>" required>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_content"><?php echo esc_html__( 'Message Content', 'atlantis' ); ?></label></th>
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
				<th scope="row"><label for="message_type"><?php echo esc_html__( 'Message Type', 'atlantis' ); ?></label></th>
				<td>
					<select name="message_type" id="message_type" required>
						<option value=""><?php echo esc_html__( 'Select Type', 'atlantis' ); ?></option>
						<option value="info" <?php selected( $message ? $message->message_type : '', 'info' ); ?>><?php echo esc_html__( 'Info', 'atlantis' ); ?></option>
						<option value="warning" <?php selected( $message ? $message->message_type : '', 'warning' ); ?>><?php echo esc_html__( 'Warning', 'atlantis' ); ?></option>
						<option value="error" <?php selected( $message ? $message->message_type : '', 'error' ); ?>><?php echo esc_html__( 'Error', 'atlantis' ); ?></option>
						<option value="success" <?php selected( $message ? $message->message_type : '', 'success' ); ?>><?php echo esc_html__( 'Success', 'atlantis' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_status"><?php echo esc_html__( 'Status', 'atlantis' ); ?></label></th>
				<td>
					<select name="message_status" id="message_status" required>
						<option value=""><?php echo esc_html__( 'Select Status', 'atlantis' ); ?></option>
						<option value="active" <?php selected( $message ? $message->message_status : '', 'active' ); ?>><?php echo esc_html__( 'Active', 'atlantis' ); ?></option>
						<option value="inactive" <?php selected( $message ? $message->message_status : '', 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'atlantis' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_location_include"><?php echo esc_html__( 'Included Locations', 'atlantis' ); ?></label></th>
				<td>
					<div id="atlantis-included-locations" class="atlantis-location-list">
						<?php
						foreach ( $current_location as $location ) :
							?>
							<div class="atlantis-location-item">
								<span><?php echo esc_html( $locations[ $location ] ?? $location ); ?></span>
								<button type="button" class="button-link delete-location" data-location="<?php echo esc_attr( $location ); ?>"><?php echo esc_html__( 'Remove', 'atlantis' ); ?></button>
								<input type="hidden" name="message_location_include[]" value="<?php echo esc_attr( $location ); ?>">
							</div>
						<?php endforeach; ?>
					</div>
					<select class="atlantis-location-dropdown" data-target="include">
						<option value=""><?php echo esc_html__( '-- Select Location --', 'atlantis' ); ?></option>
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
					<button type="button" class="button" id="atlantis-add-include-location"><?php echo esc_html__( 'Add More', 'atlantis' ); ?></button>
					<p class="description"><?php echo esc_html__( 'Select admin pages where this message should be displayed.', 'atlantis' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="message_location_exclude"><?php echo esc_html__( 'Excluded Locations', 'atlantis' ); ?></label></th>
				<td>
					<div id="atlantis-excluded-locations" class="atlantis-location-list">
						<?php
						foreach ( $current_exclude as $location ) :
							?>
							<div class="atlantis-location-item">
								<span><?php echo esc_html( $locations[ $location ] ?? $location ); ?></span>
								<button type="button" class="button-link delete-location" data-location="<?php echo esc_attr( $location ); ?>"><?php echo esc_html__( 'Remove', 'atlantis' ); ?></button>
								<input type="hidden" name="message_location_exclude[]" value="<?php echo esc_attr( $location ); ?>">
							</div>
						<?php endforeach; ?>
					</div>
					<select class="atlantis-location-dropdown" data-target="exclude">
						<option value=""><?php echo esc_html__( '-- Select Location --', 'atlantis' ); ?></option>
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
					<button type="button" class="button" id="atlantis-add-exclude-location"><?php echo esc_html__( 'Add More', 'atlantis' ); ?></button>
					<p class="description"><?php echo esc_html__( 'Select admin pages where this message should NOT be displayed.', 'atlantis' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $id > 0 ? esc_attr__( 'Update Message', 'atlantis' ) : esc_attr__( 'Add Message', 'atlantis' ); ?>">
		</p>
	</form>
</div> 