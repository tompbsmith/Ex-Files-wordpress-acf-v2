<?php
/**
 * Section: Service enquiry form (sends like the contact form, through inc/contact.php).
 *
 * @package exfiles
 */

$exf_heading  = get_sub_field( 'heading' );
$exf_text     = get_sub_field( 'text' );
$exf_button   = get_sub_field( 'button_label' ) ? get_sub_field( 'button_label' ) : __( 'Send Request', 'exfiles' );
$exf_success  = get_sub_field( 'success_message' ) ? get_sub_field( 'success_message' ) : __( "Thank you. We've received your enquiry and will get back to you as soon as possible.", 'exfiles' );
$exf_services = array_filter( array_map( 'trim', preg_split( '/\R/', (string) get_sub_field( 'services' ) ) ) );
if ( ! $exf_services && function_exists( 'exf_services' ) ) {
	$exf_services = wp_list_pluck( exf_services(), 'name' );
}
$exf_uid = wp_unique_id( 'exf-inq-' );
?>
<section <?php exf_section_attrs( 'inquiry' ); ?>>
	<div class="wrap inquiry-wrap">
		<?php if ( $exf_heading ) : ?>
			<h2><?php echo esc_html( $exf_heading ); ?></h2>
		<?php endif; ?>
		<?php if ( $exf_text ) : ?>
			<p class="inquiry-text"><?php echo exf_lines( $exf_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<form class="inquiry-form" data-contact-form data-success="<?php echo esc_attr( $exf_success ); ?>" data-label="<?php echo esc_attr( $exf_button ); ?>" novalidate>
			<input type="hidden" name="form" value="service_enquiry">
			<div class="inquiry-row">
				<div class="field">
					<label for="<?php echo esc_attr( $exf_uid ); ?>-first"><?php esc_html_e( 'First name', 'exfiles' ); ?> *</label>
					<input type="text" id="<?php echo esc_attr( $exf_uid ); ?>-first" name="first_name" autocomplete="given-name" required>
				</div>
				<div class="field">
					<label for="<?php echo esc_attr( $exf_uid ); ?>-last"><?php esc_html_e( 'Last name', 'exfiles' ); ?> *</label>
					<input type="text" id="<?php echo esc_attr( $exf_uid ); ?>-last" name="last_name" autocomplete="family-name" required>
				</div>
			</div>
			<div class="inquiry-row">
				<div class="field">
					<label for="<?php echo esc_attr( $exf_uid ); ?>-email"><?php esc_html_e( 'Email', 'exfiles' ); ?> *</label>
					<input type="email" id="<?php echo esc_attr( $exf_uid ); ?>-email" name="email" autocomplete="email" required>
				</div>
				<div class="field">
					<label for="<?php echo esc_attr( $exf_uid ); ?>-phone"><?php esc_html_e( 'Phone number', 'exfiles' ); ?> *</label>
					<input type="tel" id="<?php echo esc_attr( $exf_uid ); ?>-phone" name="phone" autocomplete="tel" required>
				</div>
			</div>
			<div class="field">
				<label for="<?php echo esc_attr( $exf_uid ); ?>-service"><?php esc_html_e( 'Which service are you interested in?', 'exfiles' ); ?></label>
				<select id="<?php echo esc_attr( $exf_uid ); ?>-service" name="service">
					<option value=""><?php esc_html_e( 'Choose a service', 'exfiles' ); ?></option>
					<?php foreach ( $exf_services as $exf_s ) : ?>
						<option value="<?php echo esc_attr( $exf_s ); ?>"><?php echo esc_html( $exf_s ); ?></option>
					<?php endforeach; ?>
					<option value="<?php esc_attr_e( 'Not sure yet', 'exfiles' ); ?>"><?php esc_html_e( 'Not sure yet', 'exfiles' ); ?></option>
				</select>
			</div>
			<div class="field">
				<label for="<?php echo esc_attr( $exf_uid ); ?>-message"><?php esc_html_e( 'Add a message or tell us more about what you need', 'exfiles' ); ?></label>
				<textarea id="<?php echo esc_attr( $exf_uid ); ?>-message" name="message"></textarea>
			</div>
			<div class="hp-field" aria-hidden="true">
				<label for="<?php echo esc_attr( $exf_uid ); ?>-company">Company</label>
				<input type="text" id="<?php echo esc_attr( $exf_uid ); ?>-company" name="company" tabindex="-1" autocomplete="off">
			</div>
			<button type="submit" class="btn inquiry-submit"><?php echo esc_html( $exf_button ); ?></button>
			<p class="form-status" role="status" aria-live="polite"></p>
		</form>
	</div>
</section>
