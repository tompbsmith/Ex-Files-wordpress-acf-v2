<?php
/**
 * Section: Contact form.
 *
 * @package exfiles
 */

$exf_heading = get_sub_field( 'heading' );
$exf_lead    = get_sub_field( 'lead' );
$exf_email   = get_sub_field( 'show_email' ) ? get_sub_field( 'email' ) : '';
$exf_phone   = (bool) get_sub_field( 'show_phone' );
$exf_button  = get_sub_field( 'button_label' ) ? get_sub_field( 'button_label' ) : __( 'Send Message', 'exfiles' );
$exf_success = get_sub_field( 'success_message' ) ? get_sub_field( 'success_message' ) : __( "Thanks — we've received your message and will be in touch shortly.", 'exfiles' );
$exf_uid     = wp_unique_id( 'exf-form-' );
?>
<section <?php exf_section_attrs( 'contact' ); ?>>
	<div class="wrap contact-grid">
		<div>
			<?php if ( $exf_heading ) : ?>
				<h2><?php echo esc_html( $exf_heading ); ?></h2>
			<?php endif; ?>
			<?php if ( $exf_lead ) : ?>
				<p class="lead"><?php echo exf_lines( $exf_lead ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
			<?php if ( $exf_email ) : ?>
				<div class="contact-info">
					<a href="mailto:<?php echo esc_attr( antispambot( $exf_email ) ); ?>"><?php echo esc_html( antispambot( $exf_email ) ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<form class="contact-form" data-contact-form data-success="<?php echo esc_attr( $exf_success ); ?>" data-label="<?php echo esc_attr( $exf_button ); ?>">
			<div class="field">
				<label for="<?php echo esc_attr( $exf_uid ); ?>-name"><?php esc_html_e( 'Name', 'exfiles' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $exf_uid ); ?>-name" name="name" autocomplete="name" required>
			</div>
			<div class="field">
				<label for="<?php echo esc_attr( $exf_uid ); ?>-email"><?php esc_html_e( 'Email', 'exfiles' ); ?></label>
				<input type="email" id="<?php echo esc_attr( $exf_uid ); ?>-email" name="email" autocomplete="email" required>
			</div>
			<?php if ( $exf_phone ) : ?>
				<div class="field">
					<label for="<?php echo esc_attr( $exf_uid ); ?>-phone"><?php esc_html_e( 'Phone (optional)', 'exfiles' ); ?></label>
					<input type="tel" id="<?php echo esc_attr( $exf_uid ); ?>-phone" name="phone" autocomplete="tel">
				</div>
			<?php endif; ?>
			<div class="field">
				<label for="<?php echo esc_attr( $exf_uid ); ?>-message"><?php esc_html_e( 'Message', 'exfiles' ); ?></label>
				<textarea id="<?php echo esc_attr( $exf_uid ); ?>-message" name="message" required></textarea>
			</div>
			<div class="hp-field" aria-hidden="true">
				<label for="<?php echo esc_attr( $exf_uid ); ?>-company">Company</label>
				<input type="text" id="<?php echo esc_attr( $exf_uid ); ?>-company" name="company" tabindex="-1" autocomplete="off">
			</div>
			<button type="submit" class="btn"><?php echo esc_html( $exf_button ); ?></button>
			<p class="form-status" role="status" aria-live="polite"></p>
		</form>
	</div>
</section>
