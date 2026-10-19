<?php
/**
 * Portal: appointments. No self-service changes: clients send a request instead.
 *
 * @package exfiles
 */

$exf_uid       = (int) $args['user']->ID;
$exf_upcoming  = exf_client_appointments( $exf_uid, 'upcoming' );
$exf_past      = exf_client_appointments( $exf_uid, 'past' );
$exf_cancelled = exf_client_cancelled_appointments( $exf_uid );
$exf_errors    = exf_portal_errors( 'appointment' );
$exf_details   = (string) exf_setting( 'meeting_details', '' );
?>
<header class="portal-head">
	<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
	<h1><?php esc_html_e( 'Appointments', 'exfiles' ); ?></h1>
</header>

<?php if ( isset( $_GET['requested'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice-box" role="status"><p><?php esc_html_e( "Thank you. We've received your request and will be in touch shortly. Your appointment stays as it is until we've confirmed any change.", 'exfiles' ); ?></p></div>
<?php endif; ?>
<?php if ( isset( $exf_errors['_form'] ) ) : ?>
	<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_errors['_form'] ); ?></p></div>
<?php endif; ?>

<h2 class="portal-h2"><?php esc_html_e( 'Upcoming', 'exfiles' ); ?></h2>
<?php if ( ! $exf_upcoming ) : ?>
	<p class="portal-empty"><?php esc_html_e( 'You have no upcoming appointments.', 'exfiles' ); ?></p>
	<?php if ( EXF_STATUS_ACTIVE === exf_user_status( $exf_uid ) ) : ?>
		<p><a class="btn outline" href="<?php echo esc_url( exf_page_url( 'buy' ) ); ?>"><?php esc_html_e( 'Choose a service', 'exfiles' ); ?></a></p>
	<?php endif; ?>
<?php else : ?>
	<?php foreach ( $exf_upcoming as $exf_o ) : ?>
		<?php
		$exf_start   = (int) get_post_meta( $exf_o, 'booking_start', true );
		$exf_req     = (int) get_post_meta( $exf_o, 'change_requested_at', true );
		$exf_has_err = isset( $exf_errors['order'] ) && (int) $exf_errors['order'] === $exf_o;
		?>
		<div class="guide-card portal-appt">
			<p class="eyebrow"><?php echo esc_html( (string) get_post_meta( $exf_o, 'service_name', true ) ); ?></p>
			<h3><?php echo esc_html( exf_booking_label( $exf_start, (int) get_post_meta( $exf_o, 'booking_end', true ) ) ); ?></h3>
			<?php if ( $exf_details ) : ?>
				<p class="portal-appt-details"><?php echo exf_lines( $exf_details ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
			<p class="portal-actions">
				<a class="btn small outline" href="<?php echo esc_url( exf_appointment_ics_url( $exf_o ) ); ?>"><?php esc_html_e( 'Add to calendar', 'exfiles' ); ?></a>
			</p>
			<?php if ( $exf_req ) : ?>
				<p class="field-note">
					<?php
					/* translators: %s: date */
					echo esc_html( sprintf( __( 'You sent us a request about this appointment on %s. We’ll be in touch.', 'exfiles' ), wp_date( 'j F', $exf_req ) ) );
					?>
				</p>
			<?php endif; ?>
			<details class="portal-request"<?php echo $exf_has_err ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Need to move or cancel this appointment?', 'exfiles' ); ?></summary>
				<p class="field-note"><?php echo exf_lines( exf_portal_copy( 'change_policy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<form method="post" class="portal-form">
					<?php exf_portal_form_fields( 'appointment' ); ?>
					<input type="hidden" name="order" value="<?php echo (int) $exf_o; ?>">
					<fieldset class="portal-fieldset">
						<legend><?php esc_html_e( 'What would you like to do?', 'exfiles' ); ?></legend>
						<label class="gq-option"><input type="radio" name="request_kind" value="change" checked><span><?php esc_html_e( 'Move it to another time', 'exfiles' ); ?></span></label>
						<label class="gq-option"><input type="radio" name="request_kind" value="cancel"><span><?php esc_html_e( 'Cancel it (not refunded)', 'exfiles' ); ?></span></label>
					</fieldset>
					<?php
					exf_form_field(
						'request_message',
						__( 'Which days and times suit you, or anything else we should know', 'exfiles' ),
						array(
							'type'   => 'textarea',
							'errors' => $exf_has_err ? $exf_errors : array(),
						)
					);
					?>
					<button type="submit" class="btn small"><?php esc_html_e( 'Send request', 'exfiles' ); ?></button>
				</form>
			</details>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( $exf_past || $exf_cancelled ) : ?>
	<h2 class="portal-h2"><?php esc_html_e( 'Past and cancelled', 'exfiles' ); ?></h2>
	<ul class="portal-list">
		<?php foreach ( $exf_past as $exf_o ) : ?>
			<li>
				<div>
					<strong><?php echo esc_html( (string) get_post_meta( $exf_o, 'service_name', true ) ); ?></strong>
					<span class="portal-list-meta"><?php echo esc_html( exf_booking_label( (int) get_post_meta( $exf_o, 'booking_start', true ), (int) get_post_meta( $exf_o, 'booking_end', true ) ) ); ?></span>
				</div>
				<span class="portal-pill"><?php esc_html_e( 'Past', 'exfiles' ); ?></span>
			</li>
		<?php endforeach; ?>
		<?php foreach ( $exf_cancelled as $exf_o ) : ?>
			<li>
				<div>
					<strong><?php echo esc_html( (string) get_post_meta( $exf_o, 'service_name', true ) ); ?></strong>
					<span class="portal-list-meta"><?php echo esc_html( exf_booking_label( (int) get_post_meta( $exf_o, 'cancelled_start', true ), (int) get_post_meta( $exf_o, 'cancelled_end', true ) ) ); ?></span>
				</div>
				<span class="portal-pill"><?php esc_html_e( 'Cancelled', 'exfiles' ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
