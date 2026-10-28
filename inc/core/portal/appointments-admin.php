<?php
/**
 * Moving and cancelling appointments from the admin (client meeting, October 2026).
 *
 * Clients can't change appointments online (see appointments.php), so the solicitor
 * does it here: Ex Files → Orders → an order → "Change or cancel", or the links on
 * the dashboard. Both keep calendars in step:
 *
 *  - Moving sends the client and the solicitor an updated invite (same UID, higher
 *    SEQUENCE), so the event moves in their calendars rather than being duplicated.
 *  - Cancelling frees the slot and sends a cancellation (METHOD:CANCEL), so the event
 *    is removed. Before October 2026, "Cancel appointment" only freed the slot: the
 *    event stayed in the solicitor's calendar and the client wasn't told.
 *
 * Neither refunds anything: refunds are done in Stripe.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where to change or cancel an order's appointment.
 *
 * @param int $order_id Order.
 */
function exf_appointment_admin_url( $order_id ) {
	return admin_url( 'admin.php?page=exf-appointment&order=' . (int) $order_id );
}

add_action(
	'admin_menu',
	function () {
		// Not in the menu: reached from an order or the dashboard.
		$hook = add_submenu_page( '', __( 'Change or cancel an appointment', 'exfiles' ), '', EXF_CAP, 'exf-appointment', 'exf_appointment_admin_page' );
		if ( $hook ) {
			// Hidden pages have no title of their own for the browser tab.
			add_action(
				'load-' . $hook,
				function () {
					$GLOBALS['title'] = __( 'Change or cancel an appointment', 'exfiles' );
				}
			);
		}
	}
);

/**
 * Cancellation reasons: key => [ label for you, sentence for the client's email ].
 */
function exf_appointment_cancel_reasons() {
	return array(
		'client'        => array( __( 'The client asked to cancel', 'exfiles' ), __( 'As you asked, we have cancelled it.', 'exfiles' ) ),
		'questionnaire' => array( __( "The financial questionnaire wasn't received in time", 'exfiles' ), __( "We haven't received your financial questionnaire, which we need in order to prepare, so we have had to cancel it.", 'exfiles' ) ),
		'firm'          => array( __( "We can't make it", 'exfiles' ), __( "Unfortunately we're no longer able to hold it at that time, so we have had to cancel it. We're sorry for any inconvenience.", 'exfiles' ) ),
		'other'         => array( __( 'Other (no reason given to the client)', 'exfiles' ), '' ),
	);
}

/**
 * The page.
 */
function exf_appointment_admin_page() {
	if ( ! exf_user_can_manage() ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$id   = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
	$done = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
	$err  = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
	// phpcs:enable
	if ( 'exf_order' !== get_post_type( $id ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Change or cancel an appointment', 'exfiles' ) . '</h1><p>' . esc_html__( 'Order not found.', 'exfiles' ) . '</p></div>';
		return;
	}
	$service = exf_service( (int) get_post_meta( $id, 'service_id', true ) );
	$start   = (int) get_post_meta( $id, 'booking_start', true );
	$end     = (int) get_post_meta( $id, 'booking_end', true );
	$minutes = $start && $end > $start ? (int) ( ( $end - $start ) / MINUTE_IN_SECONDS ) : ( $service ? $service['minutes'] : 60 );
	$client  = get_userdata( (int) get_post_meta( $id, 'client_id', true ) );
	$slots   = exf_available_slots( $minutes, null, $id );
	$q       = (int) get_post_meta( $id, 'questionnaire_id', true );
	$errors  = array(
		'taken'  => __( 'That time is no longer free. Please choose another.', 'exfiles' ),
		'time'   => __( 'Please choose a time, or enter a date and time.', 'exfiles' ),
		'past'   => __( 'That time has already passed.', 'exfiles' ),
		'order'  => __( "This order isn't paid, so its appointment can't be changed here.", 'exfiles' ),
		'reason' => __( 'Please choose a reason.', 'exfiles' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Change or cancel an appointment', 'exfiles' ); ?></h1>
		<p><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">&larr; <?php echo esc_html( get_the_title( $id ) ); ?></a></p>

		<?php if ( 'moved' === $done ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Appointment moved. Updated calendar invites have been sent.', 'exfiles' ); ?></p></div>
		<?php elseif ( 'cancelled' === $done ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Appointment cancelled and the slot freed. A calendar cancellation has been sent.', 'exfiles' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $errors[ $err ] ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $errors[ $err ] ); ?></p></div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'Client', 'exfiles' ); ?></th><td><?php echo $client ? esc_html( $client->display_name . ' (' . $client->user_email . ')' ) : '—'; ?></td></tr>
			<tr><th><?php esc_html_e( 'Service', 'exfiles' ); ?></th><td><?php echo esc_html( (string) get_post_meta( $id, 'service_name', true ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Appointment', 'exfiles' ); ?></th><td><strong><?php echo esc_html( $start ? exf_booking_label( $start, $end ) : __( 'None booked', 'exfiles' ) ); ?></strong>
				<?php if ( get_post_meta( $id, 'change_requested_at', true ) ) : ?>
					<br><?php echo esc_html( sprintf( /* translators: 1: move|cancel, 2: date */ __( 'The client asked to %1$s it on %2$s.', 'exfiles' ), 'cancel' === get_post_meta( $id, 'change_request_kind', true ) ? __( 'cancel', 'exfiles' ) : __( 'move', 'exfiles' ), wp_date( 'j M Y', (int) get_post_meta( $id, 'change_requested_at', true ) ) ) ); ?>
				<?php endif; ?>
			</td></tr>
			<?php if ( $q ) : ?>
				<tr><th><?php esc_html_e( 'Questionnaire', 'exfiles' ); ?></th><td><a href="<?php echo esc_url( get_edit_post_link( $q ) ); ?>"><?php echo esc_html( exf_questionnaire_status_label( exf_q_status( $q ) ) ); ?></a>
					<?php $exf_dl = function_exists( 'exf_order_q_deadline' ) ? exf_order_q_deadline( $id ) : 0; ?>
					<?php if ( $exf_dl && 'submitted' !== exf_q_status( $q ) ) : ?>
						<?php
						/* translators: %s: date and time */
						echo esc_html( ' · ' . sprintf( $exf_dl < time() ? __( 'was needed by %s', 'exfiles' ) : __( 'needed by %s', 'exfiles' ), exf_booking_label( $exf_dl ) ) );
						?>
					<?php endif; ?>
				</td></tr>
			<?php endif; ?>
		</table>

		<?php if ( ! exf_order_is_live( $id ) ) : ?>
			<p><?php echo esc_html( $errors['order'] ); ?></p>
		<?php else : ?>
			<h2><?php echo esc_html( $start ? __( 'Move to a new time', 'exfiles' ) : __( 'Book a time', 'exfiles' ) ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="exf_appointment_move">
				<input type="hidden" name="order" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'exf_appointment_move_' . $id ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="exf-appt-slot"><?php esc_html_e( 'A free time', 'exfiles' ); ?></label></th>
						<td>
							<select id="exf-appt-slot" name="slot">
								<option value=""><?php esc_html_e( '— Choose —', 'exfiles' ); ?></option>
								<?php foreach ( $slots as $exf_times ) : ?>
									<optgroup label="<?php echo esc_attr( wp_date( 'l j F', $exf_times[0] ) ); ?>">
										<?php foreach ( $exf_times as $exf_t ) : ?>
											<option value="<?php echo (int) $exf_t; ?>"><?php echo esc_html( wp_date( 'D j M, g:ia', $exf_t ) ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php
								/* translators: %d: minutes */
								echo esc_html( sprintf( __( 'Times from your availability in Settings → Bookings, %d minutes long.', 'exfiles' ), $minutes ) );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Or any other time', 'exfiles' ); ?></th>
						<td>
							<input type="date" name="custom_date" aria-label="<?php esc_attr_e( 'Date', 'exfiles' ); ?>">
							<input type="time" name="custom_time" step="300" aria-label="<?php esc_attr_e( 'Time (UK)', 'exfiles' ); ?>">
							<p class="description"><?php esc_html_e( 'UK time. Use this for a time outside your usual availability that you’ve agreed with the client. It still can’t overlap another booking.', 'exfiles' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tell the client', 'exfiles' ); ?></th>
						<td><label><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e( 'Email the client the new time, with an updated calendar invite', 'exfiles' ); ?></label></td>
					</tr>
				</table>
				<p><button class="button button-primary"><?php echo esc_html( $start ? __( 'Move appointment', 'exfiles' ) : __( 'Book this time', 'exfiles' ) ); ?></button></p>
			</form>

			<?php if ( $start ) : ?>
				<h2><?php esc_html_e( 'Cancel the appointment', 'exfiles' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Cancel this appointment and free the slot? This does not refund the payment.', 'exfiles' ) ); ?>')">
					<input type="hidden" name="action" value="exf_appointment_cancel">
					<input type="hidden" name="order" value="<?php echo (int) $id; ?>">
					<?php wp_nonce_field( 'exf_appointment_cancel_' . $id ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Reason', 'exfiles' ); ?></th>
							<td>
								<?php $exf_default = get_post_meta( $id, 'q_overdue_at', true ) ? 'questionnaire' : ( 'cancel' === get_post_meta( $id, 'change_request_kind', true ) ? 'client' : '' ); ?>
								<?php foreach ( exf_appointment_cancel_reasons() as $exf_k => $exf_r ) : ?>
									<label style="display:block;margin-bottom:6px"><input type="radio" name="reason" value="<?php echo esc_attr( $exf_k ); ?>" <?php checked( $exf_default, $exf_k ); ?>> <?php echo esc_html( $exf_r[0] ); ?></label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Tell the client', 'exfiles' ); ?></th>
							<td>
								<label><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e( 'Email the client that it’s cancelled, with a calendar cancellation', 'exfiles' ); ?></label>
								<p class="description"><?php esc_html_e( 'The wording is in Ex Files → Email Copy → Appointment cancelled. It doesn’t mention refunds: if you’re refunding, do it in Stripe and tell the client.', 'exfiles' ); ?></p>
							</td>
						</tr>
					</table>
					<p><button class="button"><?php esc_html_e( 'Cancel appointment', 'exfiles' ); ?></button></p>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Send the solicitor (and optionally the client) an email with an invite or cancellation.
 *
 * @param int    $order_id Order.
 * @param string $key      Client email key.
 * @param array  $vars     Merge tags.
 * @param string $ics      Calendar data.
 * @param bool   $client   Email the client too.
 */
function exf_appointment_send( $order_id, $key, array $vars, $ics, $client ) {
	$user = get_userdata( (int) get_post_meta( $order_id, 'client_id', true ) );
	if ( $client && $user ) {
		exf_send_email_with_ics( $key, $user->user_email, $vars, $ics );
	}
	// Always to you, so the event in your calendar updates too.
	exf_send_email_with_ics( 'admin_appointment_changed', exf_notify_emails(), $vars + array( 'what' => 'appointment_moved' === $key ? __( 'moved', 'exfiles' ) : __( 'cancelled', 'exfiles' ) ), $ics );
}

add_action(
	'admin_post_exf_appointment_move',
	function () {
		$id = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		check_admin_referer( 'exf_appointment_move_' . $id );
		if ( ! exf_user_can_manage() || 'exf_order' !== get_post_type( $id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$back = exf_appointment_admin_url( $id );
		if ( ! exf_order_is_live( $id ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'order', $back ) );
			exit;
		}
		$start = absint( $_POST['slot'] ?? 0 );
		$date  = sanitize_text_field( wp_unslash( $_POST['custom_date'] ?? '' ) );
		$time  = sanitize_text_field( wp_unslash( $_POST['custom_time'] ?? '' ) );
		if ( $date && $time ) {
			$dt    = date_create_immutable( $date . ' ' . $time, wp_timezone() );
			$start = $dt ? $dt->getTimestamp() : 0;
		}
		if ( ! $start ) {
			wp_safe_redirect( add_query_arg( 'error', 'time', $back ) );
			exit;
		}
		if ( $start < time() ) {
			wp_safe_redirect( add_query_arg( 'error', 'past', $back ) );
			exit;
		}
		$old_start = (int) get_post_meta( $id, 'booking_start', true );
		$old_end   = (int) get_post_meta( $id, 'booking_end', true );
		$service   = exf_service( (int) get_post_meta( $id, 'service_id', true ) );
		$minutes   = $old_start && $old_end > $old_start ? (int) ( ( $old_end - $old_start ) / MINUTE_IN_SECONDS ) : ( $service ? $service['minutes'] : 60 );
		$end       = $start + $minutes * MINUTE_IN_SECONDS;

		// Another booking blocks it. A time you typed yourself may go where your own
		// calendar is busy (you've agreed it with the client); a chosen free time is
		// checked against everything, in case it's gone since the page loaded.
		$custom = $date && $time;
		if ( ! exf_lock( 'booking', 20 ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'taken', $back ) );
			exit;
		}
		foreach ( exf_busy_periods( $start - DAY_IN_SECONDS, $end + DAY_IN_SECONDS, $id, ! $custom ) as $b ) {
			if ( $start < $b[1] && $end > $b[0] ) {
				exf_unlock( 'booking' );
				wp_safe_redirect( add_query_arg( 'error', 'taken', $back ) );
				exit;
			}
		}
		update_post_meta( $id, 'booking_start', $start );
		update_post_meta( $id, 'booking_end', $end );
		exf_unlock( 'booking' );

		update_post_meta( $id, 'ics_sequence', (int) get_post_meta( $id, 'ics_sequence', true ) + 1 );
		foreach ( array( 'needs_reschedule', 'reminder_sent', 'change_requested_at', 'change_request_kind', 'cancelled_start', 'cancelled_end' ) as $k ) {
			delete_post_meta( $id, $k );
		}
		exf_order_log( $id, sprintf( 'Appointment %s %s by %s', $old_start ? 'moved from ' . exf_booking_label( $old_start ) . ' to' : 'booked for', exf_booking_label( $start, $end ), wp_get_current_user()->display_name ) );
		if ( function_exists( 'exf_audit' ) ) {
			exf_audit( 'appointment_moved', sprintf( 'Moved appointment for order %d to %s', $id, exf_booking_label( $start ) ), 'post', $id );
		}
		do_action( 'exf_appointment_moved', $id );
		do_action( 'exf_before_appointment_moved_emails', $id ); // e.g. move the Teams meeting (teams.php).

		$vars = exf_order_email_vars( $id ) + array( 'old_appointment' => $old_start ? exf_booking_label( $old_start, $old_end ) : __( 'none', 'exfiles' ) );
		exf_appointment_send( $id, 'appointment_moved', $vars, exf_booking_ics( $id ), ! empty( $_POST['notify'] ) );

		wp_safe_redirect( add_query_arg( 'done', 'moved', $back ) );
		exit;
	}
);

add_action(
	'admin_post_exf_appointment_cancel',
	function () {
		$id = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		check_admin_referer( 'exf_appointment_cancel_' . $id );
		if ( ! exf_user_can_manage() || 'exf_order' !== get_post_type( $id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$back    = exf_appointment_admin_url( $id );
		$reasons = exf_appointment_cancel_reasons();
		$reason  = sanitize_key( wp_unslash( $_POST['reason'] ?? '' ) );
		if ( ! isset( $reasons[ $reason ] ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'reason', $back ) );
			exit;
		}
		$start = (int) get_post_meta( $id, 'booking_start', true );
		$end   = (int) get_post_meta( $id, 'booking_end', true );
		if ( ! $start ) {
			wp_safe_redirect( $back );
			exit;
		}
		// The client still sees the old time, marked cancelled (appointments.php).
		update_post_meta( $id, 'cancelled_start', $start );
		update_post_meta( $id, 'cancelled_end', $end );
		update_post_meta( $id, 'cancel_reason', $reason );
		delete_post_meta( $id, 'booking_start' );
		delete_post_meta( $id, 'booking_end' );
		delete_post_meta( $id, 'change_requested_at' );
		delete_post_meta( $id, 'change_request_kind' );
		update_post_meta( $id, 'ics_sequence', (int) get_post_meta( $id, 'ics_sequence', true ) + 1 );
		exf_order_log( $id, sprintf( 'Appointment on %s cancelled by %s (%s)', exf_booking_label( $start ), wp_get_current_user()->display_name, $reasons[ $reason ][0] ) );
		if ( function_exists( 'exf_audit' ) ) {
			exf_audit( 'appointment_cancelled', sprintf( 'Cancelled appointment for order %d (%s): %s', $id, exf_booking_label( $start ), $reasons[ $reason ][0] ), 'post', $id );
		}

		$vars = exf_order_email_vars( $id );
		$vars = array_merge(
			$vars,
			array(
				'appointment' => exf_booking_label( $start, $end ),
				'reason'      => $reasons[ $reason ][1],
			)
		);
		exf_appointment_send( $id, 'appointment_cancelled', $vars, exf_booking_ics( $id, 'CANCEL', $start, $end ), ! empty( $_POST['notify'] ) );
		do_action( 'exf_appointment_cancelled', $id ); // e.g. delete the Teams meeting (teams.php).

		wp_safe_redirect( add_query_arg( 'done', 'cancelled', $back ) );
		exit;
	}
);
