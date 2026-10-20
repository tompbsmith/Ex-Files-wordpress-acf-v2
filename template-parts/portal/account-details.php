<?php
/**
 * Portal: account details, email address and password.
 *
 * @package exfiles
 */

$exf_user = $args['user'];
$exf_uid  = (int) $exf_user->ID;
$exf_d    = exf_client_details( $exf_uid );
$exf_saved = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exf_msgs  = array(
	'details'       => array( false, __( 'Your details have been saved.', 'exfiles' ) ),
	'email-sent'    => array( false, __( "We've sent a link to your new email address. Click it (while logged in) to finish the change. Until then we'll keep using your current address.", 'exfiles' ) ),
	'email'         => array( false, __( 'Your email address has been changed.', 'exfiles' ) ),
	'email-expired' => array( true, __( "That link has expired or can't be used. Please try changing your email address again.", 'exfiles' ) ),
	'password'      => array( false, __( 'Your password has been changed.', 'exfiles' ) ),
);
$exf_e_details = exf_portal_errors( 'details' );
$exf_e_email   = exf_portal_errors( 'email' );
$exf_e_pass    = exf_portal_errors( 'password' );

// After a failed save, show what was typed.
// phpcs:disable WordPress.Security.NonceVerification.Missing
$exf_posted = $exf_e_details ? array_map( 'sanitize_text_field', wp_unslash( array_intersect_key( $_POST, array_flip( array( 'phone', 'address_1', 'address_2', 'city', 'postcode' ) ) ) ) ) : array();
// phpcs:enable
$exf_values = $exf_posted + array(
	'phone'     => $exf_d['phone'],
	'address_1' => (string) get_user_meta( $exf_uid, 'billing_address_1', true ),
	'address_2' => (string) get_user_meta( $exf_uid, 'billing_address_2', true ),
	'city'      => (string) get_user_meta( $exf_uid, 'billing_city', true ),
	'postcode'  => $exf_d['postcode'],
);
$exf_pending = get_user_meta( $exf_uid, 'exf_new_email', true );
?>
<header class="portal-head">
	<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
	<h1><?php esc_html_e( 'Account details', 'exfiles' ); ?></h1>
</header>

<?php if ( isset( $exf_msgs[ $exf_saved ] ) ) : ?>
	<div class="notice-box<?php echo $exf_msgs[ $exf_saved ][0] ? ' notice-box--error' : ''; ?>" role="status"><p><?php echo esc_html( $exf_msgs[ $exf_saved ][1] ); ?></p></div>
<?php endif; ?>

<div class="guide-form portal-form">
	<h2 class="portal-h2" style="margin-top:0"><?php esc_html_e( 'About you', 'exfiles' ); ?></h2>
	<dl class="portal-dl">
		<dt><?php esc_html_e( 'Name', 'exfiles' ); ?></dt>
		<dd><?php echo esc_html( trim( $exf_d['first_name'] . ' ' . $exf_d['last_name'] ) ); ?></dd>
		<?php if ( $exf_d['dob'] ) : ?>
			<dt><?php esc_html_e( 'Date of birth', 'exfiles' ); ?></dt>
			<dd><?php echo esc_html( wp_date( 'j F Y', strtotime( exf_norm_dob( $exf_d['dob'] ) . ' 12:00' ) ) ); ?></dd>
		<?php endif; ?>
	</dl>
	<p class="field-note account-locked-note"><?php echo esc_html( exf_portal_copy( 'locked_details' ) ); ?></p>

	<form method="post" novalidate>
		<?php exf_portal_form_fields( 'details' ); ?>
		<?php
		$exf_a = array(
			'values' => $exf_values,
			'errors' => $exf_e_details,
		);
		exf_form_field( 'phone', __( 'Phone number', 'exfiles' ), $exf_a + array( 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel' ) );
		exf_form_field( 'address_1', __( 'Address line 1', 'exfiles' ), $exf_a + array( 'required' => true, 'autocomplete' => 'address-line1' ) );
		exf_form_field( 'address_2', __( 'Address line 2', 'exfiles' ), $exf_a + array( 'autocomplete' => 'address-line2' ) );
		?>
		<div class="field-row">
			<?php
			exf_form_field( 'city', __( 'Town or city', 'exfiles' ), $exf_a + array( 'required' => true, 'autocomplete' => 'address-level2' ) );
			exf_form_field( 'postcode', __( 'Postcode', 'exfiles' ), $exf_a + array( 'required' => true, 'autocomplete' => 'postal-code' ) );
			?>
		</div>
		<label class="gq-option gq-option--check gq-consent"><input type="checkbox" name="marketing" value="1" <?php checked( (bool) get_user_meta( $exf_uid, 'exf_marketing', true ) ); ?>><span><?php esc_html_e( 'I’d like The Ex Files to email me about their services. You can unsubscribe at any time.', 'exfiles' ); ?></span></label>
		<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Save details', 'exfiles' ); ?></button></div>
	</form>
</div>

<div class="guide-form portal-form">
	<h2 class="portal-h2" style="margin-top:0"><?php esc_html_e( 'Email address', 'exfiles' ); ?></h2>
	<p>
		<?php
		/* translators: %s: email address */
		echo esc_html( sprintf( __( 'We use %s to contact you and for logging in.', 'exfiles' ), $exf_user->user_email ) );
		?>
	</p>
	<?php if ( is_array( $exf_pending ) && ! empty( $exf_pending['email'] ) && (int) $exf_pending['expires'] > time() ) : ?>
		<p class="field-note">
			<?php
			/* translators: %s: email address */
			echo esc_html( sprintf( __( 'Waiting for you to confirm %s using the link we sent there.', 'exfiles' ), $exf_pending['email'] ) );
			?>
		</p>
	<?php endif; ?>
	<details<?php echo $exf_e_email ? ' open' : ''; ?>>
		<summary class="link-button"><?php esc_html_e( 'Change email address', 'exfiles' ); ?></summary>
		<form method="post" novalidate>
			<?php exf_portal_form_fields( 'email' ); ?>
			<?php
			exf_form_field( 'new_email', __( 'New email address', 'exfiles' ), array( 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'errors' => $exf_e_email ) );
			exf_form_field( 'current_password_email', __( 'Your current password', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'errors' => $exf_e_email ) );
			?>
			<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Send confirmation link', 'exfiles' ); ?></button></div>
		</form>
	</details>
</div>

<div class="guide-form portal-form">
	<h2 class="portal-h2" style="margin-top:0"><?php esc_html_e( 'Password', 'exfiles' ); ?></h2>
	<details<?php echo $exf_e_pass ? ' open' : ''; ?>>
		<summary class="link-button"><?php esc_html_e( 'Change password', 'exfiles' ); ?></summary>
		<form method="post" novalidate>
			<?php exf_portal_form_fields( 'password' ); ?>
			<?php
			exf_form_field( 'current_password', __( 'Current password', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'errors' => $exf_e_pass ) );
			exf_form_field( 'new_password', __( 'New password', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'errors' => $exf_e_pass, 'note' => __( 'At least 10 characters.', 'exfiles' ) ) );
			exf_form_field( 'new_password_confirm', __( 'Confirm new password', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'errors' => $exf_e_pass ) );
			?>
			<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Change password', 'exfiles' ); ?></button></div>
		</form>
	</details>
</div>
