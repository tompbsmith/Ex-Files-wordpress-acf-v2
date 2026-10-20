<?php
/**
 * Appointments and payments in the portal.
 *
 * Clients can't move or cancel an appointment themselves. They send a request, which
 * emails the solicitor and is logged on the order. Cancellations aren't refunded.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * A client's appointments.
 *
 * @param int    $user_id Client.
 * @param string $when    upcoming|past|all.
 * @return int[] Order IDs; upcoming soonest first, past most recent first.
 */
function exf_client_appointments( $user_id, $when = 'all' ) {
	$now  = time();
	$list = array();
	foreach ( exf_client_orders( $user_id ) as $o ) {
		$start = (int) get_post_meta( $o, 'booking_start', true );
		if ( ! $start || ! exf_order_is_live( $o ) ) {
			continue;
		}
		$upcoming = (int) get_post_meta( $o, 'booking_end', true ) > $now;
		if ( 'all' === $when || ( 'upcoming' === $when ) === $upcoming ) {
			$list[ $o ] = $start;
		}
	}
	'upcoming' === $when ? asort( $list ) : arsort( $list );
	return array_keys( $list );
}

/**
 * Appointments the firm cancelled, for the client's history.
 *
 * @param int $user_id Client.
 * @return int[]
 */
function exf_client_cancelled_appointments( $user_id ) {
	return array_values(
		array_filter(
			exf_client_orders( $user_id ),
			function ( $o ) {
				return (bool) get_post_meta( $o, 'cancelled_start', true );
			}
		)
	);
}

/**
 * Orders shown under Payments: anything paid, confirmed or refunded.
 *
 * @param int $user_id Client.
 * @return int[]
 */
function exf_client_payments( $user_id ) {
	return array_values(
		array_filter(
			exf_client_orders( $user_id ),
			function ( $o ) {
				return in_array( exf_order_status( $o ), array( 'paid', 'confirmed', 'refunded' ), true );
			}
		)
	);
}

/**
 * Client-facing order reference.
 *
 * @param int $order_id Order.
 */
function exf_order_reference( $order_id ) {
	return 'EXF-' . str_pad( (string) $order_id, 5, '0', STR_PAD_LEFT );
}

/**
 * Link to download an appointment's calendar file.
 *
 * @param int $order_id Order.
 */
function exf_appointment_ics_url( $order_id ) {
	return add_query_arg(
		array(
			'exf_ics'  => (int) $order_id,
			'_wpnonce' => wp_create_nonce( 'exf_ics_' . (int) $order_id ),
		),
		home_url( '/' )
	);
}

// Serve the calendar file to the client who booked (or staff).
add_action(
	'init',
	function () {
		if ( empty( $_GET['exf_ics'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id    = absint( $_GET['exf_ics'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$owner = (int) get_post_meta( $id, 'client_id', true ) === get_current_user_id();
		if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'exf_ics_' . $id ) || ( ! $owner && ! exf_user_can_manage() )
			|| 'exf_order' !== get_post_type( $id ) || ! get_post_meta( $id, 'booking_start', true ) || ! exf_order_is_live( $id ) || get_post_meta( $id, 'needs_reschedule', true ) ) {
			wp_die( esc_html__( 'Sorry, that appointment is no longer available.', 'exfiles' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="appointment.ics"' );
		echo exf_booking_ics( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- calendar data.
		exit;
	},
	1
);

/**
 * Handle a request to change or cancel an appointment.
 *
 * @param int   $user_id Client.
 * @param array $post    Posted data (unslashed).
 * @return array Errors, or redirects on success.
 */
function exf_handle_appointment_request( $user_id, array $post ) {
	$order   = isset( $post['order'] ) ? absint( $post['order'] ) : 0;
	$kind    = isset( $post['request_kind'] ) && 'cancel' === $post['request_kind'] ? 'cancel' : 'change';
	$message = isset( $post['request_message'] ) ? sanitize_textarea_field( $post['request_message'] ) : '';

	if ( 'exf_order' !== get_post_type( $order ) || (int) get_post_meta( $order, 'client_id', true ) !== $user_id
		|| ! exf_order_is_live( $order ) || (int) get_post_meta( $order, 'booking_start', true ) < time() ) {
		return array( '_form' => __( 'That appointment can no longer be changed. Please contact us.', 'exfiles' ) );
	}
	if ( 'change' === $kind && '' === trim( $message ) ) {
		return array(
			'order'           => $order,
			'request_message' => __( 'Please tell us which days and times would suit you instead.', 'exfiles' ),
		);
	}
	$last = (int) get_post_meta( $order, 'change_requested_at', true );
	if ( $last > time() - 10 * MINUTE_IN_SECONDS ) {
		wp_safe_redirect( add_query_arg( 'requested', 1, exf_portal_url( 'appointments' ) ) );
		exit;
	}

	update_post_meta( $order, 'change_requested_at', time() );
	update_post_meta( $order, 'change_request_kind', $kind );
	exf_order_log( $order, ( 'cancel' === $kind ? 'Client asked to cancel' : 'Client asked to change the time' ) . ( $message ? ': ' . $message : '' ) );

	exf_send_email(
		'admin_appointment_request',
		exf_notify_emails(),
		exf_order_email_vars( $order ) + array(
			'request' => 'cancel' === $kind ? __( 'Cancel the appointment', 'exfiles' ) : __( 'Move the appointment', 'exfiles' ),
			'message' => $message ? $message : '—',
		)
	);
	wp_safe_redirect( add_query_arg( 'requested', 1, exf_portal_url( 'appointments' ) ) );
	exit;
}

// When the firm cancels an appointment, keep the old time so the client still sees it.
add_action(
	'admin_post_exf_order_cancel',
	function () {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked by the main handler.
		if ( exf_user_can_manage() && 'exf_order' === get_post_type( $id ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'exf_order_cancel_' . $id ) ) {
			update_post_meta( $id, 'cancelled_start', (int) get_post_meta( $id, 'booking_start', true ) );
			update_post_meta( $id, 'cancelled_end', (int) get_post_meta( $id, 'booking_end', true ) );
		}
	},
	1
);
