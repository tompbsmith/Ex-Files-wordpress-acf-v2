<?php
/**
 * Template Name: Open Your Ex File
 *
 * Registration in four steps (assets/js/register.js shows one at a time). Also
 * shows the "check your email", "link expired" and "unable to open" messages.
 * Form handling is in inc/core/accounts/register.php.
 *
 * @package exfiles
 */

$exf_result = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exf_errors = $GLOBALS['exf_register_errors'] ?? array();
$exf_values = $GLOBALS['exf_register_values'] ?? array();
$exf_f      = function ( $name ) {
	return function_exists( 'get_field' ) ? (string) get_field( $name ) : '';
};

if ( empty( $exf_values['service'] ) && isset( $_GET['service'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$exf_values['service'] = sanitize_key( wp_unslash( $_GET['service'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

$exf_args = function ( $extra = array() ) use ( $exf_values, $exf_errors ) {
	return array_merge(
		array(
			'values' => $exf_values,
			'errors' => $exf_errors,
		),
		$extra
	);
};

// The first step with an error is where the form opens after a failed submit.
$exf_error_step = 0;
foreach ( exf_register_fields() as $exf_name => $exf_def ) {
	if ( isset( $exf_errors[ $exf_name ] ) ) {
		$exf_error_step = $exf_def[2];
		break;
	}
}

get_header();
?>
<section class="guide-form-section register-section">
	<div class="wrap guide-form-wrap">

	<?php if ( 'check-email' === $exf_result || 'resent' === $exf_result || 'link-expired' === $exf_result ) : ?>

		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Open your Ex File', 'exfiles' ); ?></p>
			<?php if ( 'link-expired' === $exf_result ) : ?>
				<h1><?php esc_html_e( 'That link has expired', 'exfiles' ); ?></h1>
				<p class="lead"><?php esc_html_e( 'Confirmation links work for 48 hours. Enter your email address and we’ll send you a new one.', 'exfiles' ); ?></p>
			<?php else : ?>
				<h1><?php echo esc_html( $exf_f( 'check_heading' ) ? $exf_f( 'check_heading' ) : __( 'Check your email', 'exfiles' ) ); ?></h1>
				<p class="lead"><?php echo exf_lines( $exf_f( 'check_text' ) ? $exf_f( 'check_text' ) : __( "We've sent you a link to confirm your email address. Click it to finish opening your Ex File. It may take a few minutes to arrive, so check your junk folder if you can't see it.", 'exfiles' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
		</header>
		<?php if ( 'resent' === $exf_result ) : ?>
			<div class="notice-box" role="status"><p><?php esc_html_e( "If there's an unconfirmed account for that address, we've sent a new link.", 'exfiles' ); ?></p></div>
		<?php endif; ?>
		<form class="guide-form resend-form" method="post">
			<?php wp_nonce_field( 'exf_resend' ); ?>
			<input type="hidden" name="exf_resend" value="1">
			<p class="gq-label"><?php esc_html_e( 'Need a new link?', 'exfiles' ); ?></p>
			<?php exf_form_field( 'email', __( 'Email address', 'exfiles' ), array( 'type' => 'email', 'required' => true, 'autocomplete' => 'email' ) ); ?>
			<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Send a new link', 'exfiles' ); ?></button></div>
		</form>

	<?php elseif ( 'unable' === $exf_result ) : ?>

		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Open your Ex File', 'exfiles' ); ?></p>
			<h1><?php echo esc_html( $exf_f( 'unable_heading' ) ? $exf_f( 'unable_heading' ) : __( "We're unable to help on this occasion", 'exfiles' ) ); ?></h1>
			<p class="lead"><?php echo exf_lines( $exf_f( 'unable_text' ) ? $exf_f( 'unable_text' ) : __( "Thank you for your interest in The Ex Files. Unfortunately we're unable to take on your matter. We can't give details, as it can be for a number of reasons. We've sent you an email confirming this. If you have any questions, please get in touch.", 'exfiles' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<p style="margin-top:28px"><a class="btn outline" href="<?php echo esc_url( home_url( '/#contact' ) ); ?>"><?php esc_html_e( 'Contact us', 'exfiles' ); ?></a></p>
		</header>

	<?php else : ?>

		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Open your Ex File', 'exfiles' ); ?></p>
			<h1><?php echo esc_html( $exf_f( 'intro_heading' ) ? $exf_f( 'intro_heading' ) : __( 'Open your Ex File', 'exfiles' ) ); ?></h1>
			<p class="lead"><?php echo exf_lines( $exf_f( 'intro_text' ) ? $exf_f( 'intro_text' ) : __( 'Your Ex File is your private account with us. It holds your questionnaires, documents and appointments.', 'exfiles' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<p class="field-note" style="margin-top:14px">
				<?php
				printf(
					/* translators: %s: login URL */
					wp_kses( __( 'Already have an account? <a href="%s">Log in</a>.', 'exfiles' ), array( 'a' => array( 'href' => array() ) ) ),
					esc_url( wp_login_url( exf_page_url( 'account' ) ) )
				);
				?>
			</p>
		</header>

		<?php if ( isset( $exf_errors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_errors['_form'] ); ?></p></div>
		<?php elseif ( $exf_errors ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php esc_html_e( 'Please check the highlighted fields.', 'exfiles' ); ?></p></div>
		<?php endif; ?>

		<form class="guide-form register-form" method="post" novalidate data-register-form data-start-step="<?php echo (int) max( 1, $exf_error_step ); ?>">
			<?php wp_nonce_field( 'exf_register' ); ?>
			<input type="hidden" name="exf_register" value="1">
			<input type="hidden" name="service" value="<?php echo esc_attr( $exf_values['service'] ?? '' ); ?>">

			<div class="guide-progress" aria-hidden="true"><span data-reg-progress></span></div>
			<p class="field-note reg-step-count" data-reg-count aria-live="polite"></p>

			<fieldset class="reg-step" data-reg-step="1">
				<legend class="gq-label" tabindex="-1"><?php esc_html_e( 'About you', 'exfiles' ); ?></legend>
				<div class="field-row">
					<?php
					exf_form_field( 'title', __( 'Title', 'exfiles' ), $exf_args( array( 'type' => 'select', 'options' => array_combine( exf_title_options(), array_merge( array( '—' ), array_slice( exf_title_options(), 1 ) ) ), 'class' => 'field--narrow', 'autocomplete' => 'honorific-prefix' ) ) );
					exf_form_field( 'first_name', __( 'First name', 'exfiles' ), $exf_args( array( 'required' => true, 'autocomplete' => 'given-name' ) ) );
					exf_form_field( 'last_name', __( 'Last name', 'exfiles' ), $exf_args( array( 'required' => true, 'autocomplete' => 'family-name' ) ) );
					?>
				</div>
				<?php
				exf_form_field( 'prev_names', __( 'Any previous or other names', 'exfiles' ), $exf_args( array( 'note' => __( 'For example a maiden name. Separate several with commas.', 'exfiles' ) ) ) );
				exf_form_field( 'dob', __( 'Date of birth', 'exfiles' ), $exf_args( array( 'type' => 'date', 'required' => true, 'autocomplete' => 'bday' ) ) );
				?>
				<div class="field-row">
					<?php
					exf_form_field( 'email', __( 'Email address', 'exfiles' ), $exf_args( array( 'type' => 'email', 'required' => true, 'autocomplete' => 'email' ) ) );
					exf_form_field( 'phone', __( 'Phone number', 'exfiles' ), $exf_args( array( 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel' ) ) );
					?>
				</div>
				<?php
				exf_form_field( 'address_1', __( 'Address line 1', 'exfiles' ), $exf_args( array( 'required' => true, 'autocomplete' => 'address-line1' ) ) );
				exf_form_field( 'address_2', __( 'Address line 2', 'exfiles' ), $exf_args( array( 'autocomplete' => 'address-line2' ) ) );
				?>
				<div class="field-row">
					<?php
					exf_form_field( 'city', __( 'Town or city', 'exfiles' ), $exf_args( array( 'required' => true, 'autocomplete' => 'address-level2' ) ) );
					exf_form_field( 'postcode', __( 'Postcode', 'exfiles' ), $exf_args( array( 'required' => true, 'autocomplete' => 'postal-code' ) ) );
					?>
				</div>
			</fieldset>

			<fieldset class="reg-step" data-reg-step="2">
				<legend class="gq-label" tabindex="-1"><?php esc_html_e( 'About your ex', 'exfiles' ); ?></legend>
				<div class="reg-why">
					<?php echo wp_kses_post( wpautop( $exf_f( 'why_text' ) ? $exf_f( 'why_text' ) : __( "We ask for your ex's details to make sure we haven't already been instructed by them, which would stop us acting for you.\n\nThe more you can tell us, the quicker the check. If we can't tell your ex apart from someone else with the same name, we may need to ask you for more information.", 'exfiles' ) ) ); ?>
				</div>
				<div class="field-row">
					<?php
					exf_form_field( 'ex_first_name', __( 'Their first name', 'exfiles' ), $exf_args( array( 'required' => true, 'autocomplete' => 'off' ) ) );
					exf_form_field( 'ex_last_name', __( 'Their last name', 'exfiles' ), $exf_args( array( 'required' => true, 'autocomplete' => 'off' ) ) );
					?>
				</div>
				<?php
				exf_form_field( 'ex_prev_names', __( 'Any previous or other names they use', 'exfiles' ), $exf_args( array( 'autocomplete' => 'off' ) ) );
				exf_form_field( 'ex_dob', __( 'Their date of birth', 'exfiles' ), $exf_args( array( 'type' => 'date', 'autocomplete' => 'off', 'required' => false ) ) );
				?>
				<label class="gq-option gq-option--check reg-unknown"><input type="checkbox" name="ex_dob_unknown" value="1" <?php checked( ! empty( $exf_values['ex_dob_unknown'] ) ); ?>><span><?php esc_html_e( "I don't know their date of birth", 'exfiles' ); ?></span></label>
				<div class="field-row">
					<?php
					exf_form_field( 'ex_email', __( 'Their email address', 'exfiles' ), $exf_args( array( 'type' => 'email', 'autocomplete' => 'off' ) ) );
					exf_form_field( 'ex_phone', __( 'Their phone number', 'exfiles' ), $exf_args( array( 'type' => 'tel', 'autocomplete' => 'off' ) ) );
					?>
				</div>
				<?php exf_form_field( 'ex_postcode', __( 'Their last known postcode', 'exfiles' ), $exf_args( array( 'autocomplete' => 'off', 'class' => 'field--half' ) ) ); ?>
			</fieldset>

			<fieldset class="reg-step" data-reg-step="3">
				<legend class="gq-label" tabindex="-1"><?php esc_html_e( 'Your relationship', 'exfiles' ); ?></legend>
				<fieldset class="gq-services">
					<legend><?php esc_html_e( 'Are you married or in a civil partnership with them?', 'exfiles' ); ?></legend>
					<?php foreach ( exf_relationship_options() as $exf_k => $exf_l ) : ?>
						<label class="gq-option"><input type="radio" name="relationship" value="<?php echo esc_attr( $exf_k ); ?>" required <?php checked( $exf_values['relationship'] ?? '', $exf_k ); ?>><span><?php echo esc_html( $exf_l ); ?></span></label>
					<?php endforeach; ?>
					<?php if ( isset( $exf_errors['relationship'] ) ) : ?>
						<p class="field-error"><?php echo esc_html( $exf_errors['relationship'] ); ?></p>
					<?php endif; ?>
				</fieldset>
				<div class="field-row" style="margin-top:22px">
					<?php
					exf_form_field( 'marriage_date', __( 'Date of marriage or civil partnership', 'exfiles' ), $exf_args( array( 'type' => 'date', 'class' => 'reg-if-married' ) ) );
					exf_form_field( 'separation_date', __( 'Date you separated', 'exfiles' ), $exf_args( array( 'type' => 'date', 'note' => __( "Leave blank if you haven't separated yet.", 'exfiles' ) ) ) );
					?>
				</div>
			</fieldset>

			<fieldset class="reg-step" data-reg-step="4">
				<legend class="gq-label" tabindex="-1"><?php esc_html_e( 'Your account', 'exfiles' ); ?></legend>
				<?php
				exf_form_field( 'password', __( 'Choose a password', 'exfiles' ), $exf_args( array( 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'note' => __( 'At least 10 characters.', 'exfiles' ), 'attrs' => ' minlength="10"' ) ) );
				exf_form_field( 'password2', __( 'Confirm your password', 'exfiles' ), $exf_args( array( 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password' ) ) );
				?>
				<label class="gq-option gq-option--check gq-consent">
					<input type="checkbox" name="terms" value="1" required <?php checked( ! empty( $exf_values['terms'] ) ); ?>>
					<span>
						<?php
						$exf_privacy = get_privacy_policy_url();
						echo wp_kses(
							$exf_f( 'terms_label' ) ? $exf_f( 'terms_label' ) : (
								$exf_privacy
									/* translators: %s: privacy policy URL */
									? sprintf( __( 'I agree to the terms of business and the <a href="%s" target="_blank">privacy notice</a>.', 'exfiles' ), esc_url( $exf_privacy ) )
									: __( 'I agree to the terms of business and the privacy notice.', 'exfiles' )
							),
							array( 'a' => array( 'href' => array(), 'target' => array() ) )
						);
						?>
					</span>
				</label>
				<?php if ( isset( $exf_errors['terms'] ) ) : ?>
					<p class="field-error"><?php echo esc_html( $exf_errors['terms'] ); ?></p>
				<?php endif; ?>
				<label class="gq-option gq-option--check gq-consent"><input type="checkbox" name="marketing" value="1" <?php checked( ! empty( $exf_values['marketing'] ) ); ?>><span><?php esc_html_e( 'I’d like The Ex Files to email me about their services. You can change this at any time in your Ex File.', 'exfiles' ); ?></span></label>
			</fieldset>

			<div class="hp-field" aria-hidden="true">
				<label for="exf-reg-company">Company</label>
				<input type="text" id="exf-reg-company" name="company" tabindex="-1" autocomplete="off">
			</div>

			<p class="form-status err" role="alert" data-reg-error></p>

			<div class="guide-nav">
				<button type="button" class="btn outline" data-reg-back hidden><?php esc_html_e( 'Back', 'exfiles' ); ?></button>
				<button type="button" class="btn" data-reg-next hidden><?php esc_html_e( 'Next', 'exfiles' ); ?></button>
				<button type="submit" class="btn" data-reg-submit><?php esc_html_e( 'Open my Ex File', 'exfiles' ); ?></button>
			</div>
		</form>

	<?php endif; ?>
	</div>
</section>
<?php
get_footer();
