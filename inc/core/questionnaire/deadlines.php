<?php
/**
 * Questionnaire and report deadlines (client meeting, October 2026):
 *
 *  - Appointments (e.g. Know Your Position): the questionnaire is needed a set time
 *    before the appointment (Service settings → "Questionnaire needed by", 24 hours
 *    by default). Clients are told when they book, reminded the day before the
 *    deadline, and if it still hasn't arrived the solicitor is emailed so she can
 *    cancel or move the appointment (the order's Appointment box, see
 *    portal/appointments-admin.php).
 *  - Written reports (e.g. Check Your Deal): the report goes out within a set number
 *    of days of receiving the questionnaire (Service settings → "Written report sent
 *    within", 7 days by default for Check Your Deal). Clients are told, and the due
 *    date shows on the order and the dashboard.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * When an order's questionnaire is needed by (a timestamp), or 0 if there's no deadline.
 *
 * @param int $order_id Order.
 */
function exf_order_q_deadline( $order_id ) {
	$service = exf_service( (int) get_post_meta( $order_id, 'service_id', true ) );
	$start   = (int) get_post_meta( $order_id, 'booking_start', true );
	if ( ! $service || ! $service['q_due_hours'] || ! $start || get_post_meta( $order_id, 'parent_order', true ) ) {
		return 0;
	}
	return $start - $service['q_due_hours'] * HOUR_IN_SECONDS;
}

/**
 * Whether the questionnaire for an order is still outstanding.
 *
 * @param int $order_id Order.
 */
function exf_order_q_outstanding( $order_id ) {
	$q = (int) get_post_meta( $order_id, 'questionnaire_id', true );
	return $q && 'submitted' !== exf_q_status( $q );
}

/**
 * When an order's written report is due (a timestamp), or 0 if there's no report
 * deadline or the clock hasn't started (questionnaire not received yet).
 *
 * @param int $order_id Order.
 */
function exf_order_report_due( $order_id ) {
	$service = exf_service( (int) get_post_meta( $order_id, 'service_id', true ) );
	if ( ! $service || ! $service['report_days'] || get_post_meta( $order_id, 'parent_order', true ) || ! exf_order_is_live( $order_id ) ) {
		return 0;
	}
	$paid = (int) get_post_meta( $order_id, 'paid_at', true );
	$paid = $paid ? $paid : (int) get_post_timestamp( $order_id );
	$q    = (int) get_post_meta( $order_id, 'questionnaire_id', true );
	if ( $q ) {
		if ( 'submitted' !== exf_q_status( $q ) ) {
			return 0;
		}
		// A questionnaire submitted for an earlier service counts from this payment.
		$base = max( $paid, (int) get_post_meta( $q, 'q_submitted_at', true ) );
	} else {
		$base = $paid;
	}
	// Without an early start, work begins once the 14-day cancellation period ends.
	if ( function_exists( 'exf_order_cooling_off_end' ) ) {
		$base = max( $base, exf_order_cooling_off_end( $order_id ) );
	}
	return $base + $service['report_days'] * DAY_IN_SECONDS;
}

/**
 * How long before an appointment, in words ("24 hours", "2 days").
 *
 * @param int $hours Hours.
 */
function exf_hours_words( $hours ) {
	if ( $hours >= 48 && 0 === $hours % 24 ) {
		/* translators: %d: days */
		return sprintf( _n( '%d day', '%d days', $hours / 24, 'exfiles' ), $hours / 24 );
	}
	/* translators: %d: hours */
	return sprintf( _n( '%d hour', '%d hours', $hours, 'exfiles' ), $hours );
}

/**
 * The deadline in general terms, for the basket and service pages.
 *
 * @param array $service From exf_service().
 * @return string
 */
function exf_service_deadline_note( array $service ) {
	$notes = array();
	if ( $service['q_due_hours'] ) {
		/* translators: %s: e.g. 24 hours */
		$notes[] = sprintf( __( 'We need your completed financial questionnaire at least %s before your appointment, so we can prepare for it.', 'exfiles' ), exf_hours_words( $service['q_due_hours'] ) );
	}
	if ( $service['report_days'] ) {
		$notes[] = $service['questionnaire']
			/* translators: %d: days */
			? sprintf( _n( "We'll send your written report within %d day of receiving your completed financial questionnaire.", "We'll send your written report within %d days of receiving your completed financial questionnaire.", $service['report_days'], 'exfiles' ), $service['report_days'] )
			/* translators: %d: days */
			: sprintf( _n( "We'll send your written report within %d day.", "We'll send your written report within %d days.", $service['report_days'], 'exfiles' ), $service['report_days'] );
	}
	return implode( ' ', $notes );
}

/**
 * The deadline for one order, with the actual date where there is one.
 *
 * @param int $order_id Order.
 * @return string
 */
function exf_order_deadline_note( $order_id ) {
	$service = exf_service( (int) get_post_meta( $order_id, 'service_id', true ) );
	if ( ! $service || get_post_meta( $order_id, 'parent_order', true ) ) {
		return '';
	}
	$deadline = exf_order_q_deadline( $order_id );
	if ( $deadline && $deadline <= time() + HOUR_IN_SECONDS && exf_order_q_outstanding( $order_id ) ) {
		// Booked at short notice: the usual deadline has already gone.
		return __( 'Please complete your financial questionnaire as soon as you can: we need it to prepare for your appointment.', 'exfiles' );
	}
	if ( $deadline && exf_order_q_outstanding( $order_id ) ) {
		/* translators: 1: date and time, 2: e.g. 24 hours */
		return sprintf( __( 'Please complete your financial questionnaire by %1$s (%2$s before your appointment), so we can prepare. If we don’t have it by then, we may need to cancel or move your appointment.', 'exfiles' ), exf_booking_label( $deadline ), exf_hours_words( $service['q_due_hours'] ) );
	}
	$due = exf_order_report_due( $order_id );
	if ( $due ) {
		/* translators: %s: date */
		return sprintf( __( "We'll send your written report by %s.", 'exfiles' ), wp_date( 'l j F Y', $due ) );
	}
	return exf_order_q_outstanding( $order_id ) && $service['report_days'] ? exf_service_deadline_note( array_merge( $service, array( 'q_due_hours' => 0 ) ) ) : '';
}

/* -------------------------------------------------------------------------
 * Overdue questionnaires: tell the solicitor once the deadline has passed.
 * ---------------------------------------------------------------------- */

add_action( 'exf_hourly', 'exf_q_check_overdue' );

/**
 * Email the solicitor about appointments whose questionnaire hasn't arrived in time.
 */
function exf_q_check_overdue() {
	$orders = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'booking_start',
					'value'   => time(),
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => 'q_overdue_at',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	foreach ( $orders as $o ) {
		$deadline = exf_order_q_deadline( $o );
		if ( ! $deadline || $deadline > time() || ! exf_order_is_live( $o ) || ! exf_order_q_outstanding( $o ) ) {
			continue;
		}
		update_post_meta( $o, 'q_overdue_at', time() );
		exf_order_log( $o, 'Questionnaire not received by the deadline (' . exf_booking_label( $deadline ) . ')' );
		exf_send_email(
			'admin_q_overdue',
			exf_notify_emails(),
			array_merge(
				exf_order_email_vars( $o ),
				array( 'deadline' => exf_booking_label( $deadline ) )
			)
		);
	}
}

/**
 * Orders whose questionnaire is late, for the dashboard: appointment still to come.
 *
 * @return int[]
 */
function exf_orders_q_overdue() {
	$ids = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'q_overdue_at',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'booking_start',
					'value'   => time(),
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	return array_values(
		array_filter(
			array_map( 'intval', $ids ),
			function ( $o ) {
				return exf_order_is_live( $o ) && exf_order_q_outstanding( $o );
			}
		)
	);
}

/**
 * Reports due soon (or overdue) that aren't marked completed, soonest first.
 *
 * @return array<int,int> order => due timestamp.
 */
function exf_reports_due() {
	$out = array();
	foreach ( function_exists( 'exf_orders_to_do' ) ? exf_orders_to_do() : array() as $o ) {
		$due = exf_order_report_due( $o );
		if ( $due ) {
			$out[ $o ] = $due;
		}
	}
	asort( $out );
	return $out;
}

// Moving the appointment (or the questionnaire arriving) clears the "late" flag.
add_action(
	'exf_appointment_moved',
	function ( $order_id ) {
		delete_post_meta( $order_id, 'q_overdue_at' );
	}
);
