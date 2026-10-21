<?php
/**
 * Orders: one per purchase (or free booking), linked to a client, an Ex File and a
 * service page.
 *
 * Status (meta order_status):
 *   pending    waiting for payment; any appointment slot is held for 30 minutes
 *   paid       paid through Stripe
 *   confirmed  a free service, confirmed without payment
 *   cancelled  payment abandoned, or cancelled by the solicitor
 *   refunded   refunded in full (recorded from Stripe)
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'exf_order',
			array(
				'labels'              => exf_post_type_labels( __( 'Order', 'exfiles' ), __( 'Orders', 'exfiles' ) ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=ex_file',
				'show_in_rest'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'map_meta_cap'        => false,
				'capabilities'        => exf_post_type_caps( false ),
			)
		);
	}
);

/**
 * Order status.
 *
 * @param int $order_id Order.
 */
function exf_order_status( $order_id ) {
	return (string) get_post_meta( $order_id, 'order_status', true );
}

/**
 * Label for a status.
 *
 * @param string $status Status.
 */
function exf_order_status_label( $status ) {
	$labels = array(
		'pending'   => __( 'Awaiting payment', 'exfiles' ),
		'paid'      => __( 'Paid', 'exfiles' ),
		'confirmed' => __( 'Confirmed (free)', 'exfiles' ),
		'cancelled' => __( 'Cancelled', 'exfiles' ),
		'refunded'  => __( 'Refunded', 'exfiles' ),
	);
	return $labels[ $status ] ?? $status;
}

/**
 * Whether an order is live (paid or confirmed).
 *
 * @param int $order_id Order.
 */
function exf_order_is_live( $order_id ) {
	return in_array( exf_order_status( $order_id ), array( 'paid', 'confirmed' ), true );
}

/**
 * Add a line to an order's log, and to its Ex File's activity.
 *
 * @param int    $order_id Order.
 * @param string $message  Message.
 */
function exf_order_log( $order_id, $message ) {
	$log   = array_filter( (array) get_post_meta( $order_id, 'order_log', true ) );
	$log[] = array(
		'time' => time(),
		'msg'  => $message,
	);
	update_post_meta( $order_id, 'order_log', $log );
	$ex_file = (int) get_post_meta( $order_id, 'ex_file_id', true );
	if ( $ex_file ) {
		exf_ex_file_log( $ex_file, sprintf( 'Order %d: %s', $order_id, $message ) );
	}
}

/**
 * Change an order's status.
 *
 * @param int    $order_id Order.
 * @param string $status   New status.
 * @param string $note     Why.
 */
function exf_order_set_status( $order_id, $status, $note = '' ) {
	$old = exf_order_status( $order_id );
	if ( $old === $status && '' === $note ) {
		return;
	}
	update_post_meta( $order_id, 'order_status', $status );
	if ( 'cancelled' === $status ) {
		delete_post_meta( $order_id, 'hold_expires' );
	}
	exf_order_log( $order_id, ( $old !== $status ? exf_order_status_label( $status ) : '' ) . ( $note ? ( $old !== $status ? ': ' : '' ) . $note : '' ) );
}

/**
 * Create an order. For appointments, the caller must hold the booking lock.
 *
 * @param int   $client_id  Client.
 * @param int   $ex_file_id Ex File.
 * @param array $service    From exf_service().
 * @param int   $start      Appointment start, or 0.
 * @return int Order ID.
 */
function exf_order_create( $client_id, $ex_file_id, array $service, $start = 0 ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'exf_order',
			'post_status' => 'publish',
			'post_author' => $client_id,
			'post_title'  => $service['name'],
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	wp_update_post(
		array(
			'ID'         => $id,
			/* translators: 1: order number, 2: service */
			'post_title' => sprintf( __( 'Order %1$d – %2$s', 'exfiles' ), $id, $service['name'] ),
		)
	);
	$meta = array(
		'order_status' => 'pending',
		'client_id'    => $client_id,
		'ex_file_id'   => $ex_file_id,
		'service_id'   => $service['id'],
		'service_name' => $service['name'],
		'service_type' => $service['type'],
		'amount_pence' => $service['pence'],
		'attempt'      => 1,
	);
	if ( $start ) {
		$meta['booking_start'] = (int) $start;
		$meta['booking_end']   = (int) $start + $service['minutes'] * MINUTE_IN_SECONDS;
		$meta['hold_expires']  = time() + EXF_HOLD_MINUTES * MINUTE_IN_SECONDS;
	}
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	exf_order_log( $id, sprintf( 'Created for %s%s', $service['name'], $start ? ', appointment ' . exf_booking_label( $start ) : '' ) );
	return (int) $id;
}

/**
 * Record a successful payment (from the webhook or the return page). Safe to call twice.
 *
 * @param int   $order_id Order.
 * @param array $session  Stripe Checkout Session.
 */
function exf_order_mark_paid( $order_id, array $session ) {
	if ( 'exf_order' !== get_post_type( $order_id ) || ! exf_lock( 'order_' . $order_id, 30 ) ) {
		return;
	}
	if ( exf_order_is_live( $order_id ) ) {
		exf_unlock( 'order_' . $order_id );
		return;
	}
	$was = exf_order_status( $order_id );

	update_post_meta( $order_id, 'payment_intent', (string) ( $session['payment_intent'] ?? '' ) );
	update_post_meta( $order_id, 'paid_at', time() );
	update_post_meta( $order_id, 'amount_paid_pence', (int) ( $session['amount_total'] ?? get_post_meta( $order_id, 'amount_pence', true ) ) );
	delete_post_meta( $order_id, 'hold_expires' );
	exf_order_set_status( $order_id, 'paid', sprintf( '£%s received through Stripe', number_format( (int) ( $session['amount_total'] ?? 0 ) / 100, 2 ) ) );

	// Paid after the hold lapsed: the slot may have gone to someone else.
	$start = (int) get_post_meta( $order_id, 'booking_start', true );
	if ( $start && 'cancelled' === $was && ! exf_slot_is_free_for_paid( $order_id ) ) {
		update_post_meta( $order_id, 'needs_reschedule', 1 );
		exf_order_log( $order_id, 'Paid after the slot hold expired, and the slot is now taken: needs rescheduling' );
	}

	exf_order_fulfil( $order_id );
	exf_unlock( 'order_' . $order_id );
}

/**
 * Whether a paid order's slot clashes with another live booking.
 *
 * @param int $order_id Order.
 */
function exf_slot_is_free_for_paid( $order_id ) {
	$start = (int) get_post_meta( $order_id, 'booking_start', true );
	$end   = (int) get_post_meta( $order_id, 'booking_end', true );
	foreach ( exf_busy_periods( $start - DAY_IN_SECONDS, $end + DAY_IN_SECONDS, $order_id ) as $b ) {
		if ( $start < $b[1] && $end > $b[0] ) {
			return false;
		}
	}
	return true;
}

/**
 * Confirm a free order (no payment).
 *
 * @param int $order_id Order.
 */
function exf_order_confirm_free( $order_id ) {
	delete_post_meta( $order_id, 'hold_expires' );
	exf_order_set_status( $order_id, 'confirmed', 'Free service confirmed' );
	exf_order_fulfil( $order_id );
}

/**
 * After payment or confirmation: questionnaire, emails and calendar invites.
 *
 * @param int $order_id Order.
 */
function exf_order_fulfil( $order_id ) {
	if ( get_post_meta( $order_id, 'fulfilled_at', true ) ) {
		return;
	}
	update_post_meta( $order_id, 'fulfilled_at', time() );

	$client_id = (int) get_post_meta( $order_id, 'client_id', true );
	$ex_file   = (int) get_post_meta( $order_id, 'ex_file_id', true );
	$service   = exf_service( (int) get_post_meta( $order_id, 'service_id', true ) );

	// One financial questionnaire per Ex File: a later service uses the one the
	// client already has (submitted or not) rather than starting another. If their
	// circumstances change, it can be reopened from the admin.
	if ( $service && $service['questionnaire'] ) {
		$existing = $ex_file && function_exists( 'exf_client_questionnaires' ) ? exf_client_questionnaires( $client_id, $ex_file ) : array();
		if ( $existing ) {
			$q = $existing[0];
			exf_q_link_order( $q, $order_id );
			update_post_meta( $order_id, 'questionnaire_id', $q );
			if ( function_exists( 'exf_q_log' ) ) {
				/* translators: %s: service name */
				exf_q_log( $q, sprintf( 'Also used for %s', $service['name'] ) );
			}
		} else {
			$q = wp_insert_post(
				array(
					'post_type'   => 'fs_questionnaire',
					'post_status' => 'publish',
					'post_author' => $client_id,
					/* translators: %s: Ex File */
					'post_title'  => sprintf( __( 'Financial questionnaire – %s', 'exfiles' ), get_the_title( $ex_file ) ),
				)
			);
			if ( $q && ! is_wp_error( $q ) ) {
				update_post_meta( $q, 'ex_file_id', $ex_file );
				update_post_meta( $q, 'order_id', $order_id );
				exf_q_link_order( $q, $order_id );
				update_post_meta( $q, 'q_status', 'to_do' );
				update_post_meta( $order_id, 'questionnaire_id', $q );
			}
		}
	}

	$vars = exf_order_email_vars( $order_id );
	// No calendar invite for a time that has to be rearranged.
	$ics  = get_post_meta( $order_id, 'booking_start', true ) && ! get_post_meta( $order_id, 'needs_reschedule', true ) ? exf_booking_ics( $order_id ) : '';
	$user = get_userdata( $client_id );
	if ( $user ) {
		exf_send_email_with_ics( 'order_confirmed', $user->user_email, $vars, $ics );
	}
	exf_send_email_with_ics( 'admin_order', exf_notify_emails(), $vars, $ics );
}

/**
 * Record that a questionnaire is used for an order (meta "order_ids").
 *
 * @param int $q        Questionnaire.
 * @param int $order_id Order.
 */
function exf_q_link_order( $q, $order_id ) {
	$ids = array_map( 'intval', (array) get_post_meta( $q, 'order_ids', true ) );
	if ( ! in_array( (int) $order_id, $ids, true ) ) {
		$ids[] = (int) $order_id;
		update_post_meta( $q, 'order_ids', array_values( array_filter( $ids ) ) );
	}
}

/**
 * Merge tags for an order.
 *
 * @param int $order_id Order.
 */
function exf_order_email_vars( $order_id ) {
	$ex_file = (int) get_post_meta( $order_id, 'ex_file_id', true );
	$start   = (int) get_post_meta( $order_id, 'booking_start', true );
	$end     = (int) get_post_meta( $order_id, 'booking_end', true );
	$pence   = (int) get_post_meta( $order_id, 'amount_pence', true );
	$service = exf_service( (int) get_post_meta( $order_id, 'service_id', true ) );

	$next = '';
	if ( $service && $service['questionnaire'] ) {
		$q = (int) get_post_meta( $order_id, 'questionnaire_id', true );
		if ( $q && 'submitted' === get_post_meta( $q, 'q_status', true ) ) {
			$next = __( "We'll use the financial questionnaire you've already completed, so there's nothing more to fill in. If anything has changed since, please let us know and we'll reopen it for you.", 'exfiles' );
		} elseif ( $q && function_exists( 'exf_q_url' ) ) {
			/* translators: %s: link */
			$next = sprintf( __( "Next, please complete your financial questionnaire. Your answers are saved as you go:\n%s", 'exfiles' ), exf_q_url( $q ) );
		} else {
			$next = __( 'Next, please complete your questionnaire in your Ex File.', 'exfiles' );
		}
	}

	return array_merge( ( $ex_file ? exf_ex_file_email_vars( $ex_file ) : array() ), array(
		'order_id'        => $order_id,
		'service'         => (string) get_post_meta( $order_id, 'service_name', true ),
		'amount'          => $pence ? '£' . number_format( $pence / 100, 2 ) : __( 'Free', 'exfiles' ),
		'appointment'     => ! $start ? __( 'No appointment needed', 'exfiles' ) : ( get_post_meta( $order_id, 'needs_reschedule', true )
			/* translators: %s: the time they chose */
			? sprintf( __( 'To be rearranged. The time you chose (%s) was booked by someone else while you were paying, so we’ll contact you to arrange a new time.', 'exfiles' ), exf_booking_label( $start, $end ) )
			: exf_booking_label( $start, $end ) ),
		'meeting_details' => (string) exf_setting( 'meeting_details', '' ),
		'next_steps'      => $next,
		'link'            => exf_page_url( 'account' ),
		'admin_link'      => admin_url( 'post.php?post=' . $order_id . '&action=edit' ),
	) );
}

/**
 * Send an account email with an optional calendar invite attached.
 *
 * @param string          $key  Email key.
 * @param string|string[] $to   Recipients.
 * @param array           $vars Merge tags.
 * @param string          $ics  Calendar data, or ''.
 */
function exf_send_email_with_ics( $key, $to, array $vars, $ics ) {
	if ( '' === $ics ) {
		return exf_send_email( $key, $to, $vars );
	}
	$dir = trailingslashit( get_temp_dir() ) . 'exf-' . wp_generate_password( 12, false, false );
	wp_mkdir_p( $dir );
	$file = $dir . '/appointment.ics';
	file_put_contents( $file, $ics ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	$attach = function ( $args ) use ( $file ) {
		$args['attachments'] = array_merge( (array) $args['attachments'], array( $file ) );
		return $args;
	};
	add_filter( 'wp_mail', $attach );
	$sent = exf_send_email( $key, $to, $vars );
	remove_filter( 'wp_mail', $attach );

	wp_delete_file( $file );
	rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	return $sent;
}

/**
 * Order for a Stripe payment intent.
 *
 * @param string $payment_intent ID.
 */
function exf_order_by_payment_intent( $payment_intent ) {
	$ids = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'payment_intent', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $payment_intent, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * A client's orders, newest first.
 *
 * @param int $client_id Client.
 * @param int $ex_file   Optional Ex File filter.
 * @return int[]
 */
function exf_client_orders( $client_id, $ex_file = 0 ) {
	$args = array(
		'post_type'      => 'exf_order',
		'post_status'    => 'any',
		'author'         => (int) $client_id,
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);
	if ( $ex_file ) {
		$args['meta_key']   = 'ex_file_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$args['meta_value'] = (int) $ex_file; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}
	return array_map( 'intval', get_posts( $args ) );
}

/* -------------------------------------------------------------------------
 * Scheduled jobs: release lapsed holds, appointment reminders
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'exf_hourly' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'exf_hourly' );
		}
	}
);

add_action( 'exf_hourly', 'exf_release_lapsed_holds' );
add_action( 'exf_hourly', 'exf_send_booking_reminders' );

/**
 * Cancel unpaid orders whose hold ran out more than an hour ago.
 */
function exf_release_lapsed_holds() {
	$ids = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'order_status',
					'value' => 'pending',
				),
				array(
					'key'     => 'hold_expires',
					'value'   => time() - HOUR_IN_SECONDS,
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	foreach ( $ids as $id ) {
		exf_order_set_status( $id, 'cancelled', 'Not paid in time' );
	}
}

/**
 * Remind clients about appointments roughly 24 hours ahead.
 */
function exf_send_booking_reminders() {
	$ids = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'booking_start',
					'value'   => array( time() + 20 * HOUR_IN_SECONDS, time() + 26 * HOUR_IN_SECONDS ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => 'reminder_sent',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	foreach ( $ids as $id ) {
		if ( ! exf_order_is_live( $id ) ) {
			continue;
		}
		$user = get_userdata( (int) get_post_meta( $id, 'client_id', true ) );
		if ( $user ) {
			exf_send_email( 'booking_reminder', $user->user_email, exf_order_email_vars( $id ) );
		}
		update_post_meta( $id, 'reminder_sent', time() );
	}
}

/* -------------------------------------------------------------------------
 * Admin
 * ---------------------------------------------------------------------- */

add_filter(
	'manage_exf_order_posts_columns',
	function () {
		return array(
			'cb'          => '<input type="checkbox" />',
			'title'       => __( 'Order', 'exfiles' ),
			'exf_client'  => __( 'Client', 'exfiles' ),
			'exf_file'    => __( 'Ex File', 'exfiles' ),
			'exf_amount'  => __( 'Amount', 'exfiles' ),
			'exf_status'  => __( 'Status', 'exfiles' ),
			'exf_booking' => __( 'Appointment', 'exfiles' ),
			'date'        => __( 'Date', 'exfiles' ),
		);
	}
);

add_action(
	'manage_exf_order_posts_custom_column',
	function ( $column, $id ) {
		switch ( $column ) {
			case 'exf_client':
				$u = get_userdata( (int) get_post_meta( $id, 'client_id', true ) );
				echo $u ? '<a href="' . esc_url( get_edit_user_link( $u->ID ) ) . '">' . esc_html( $u->display_name ) . '</a>' : '—';
				break;
			case 'exf_file':
				$f = (int) get_post_meta( $id, 'ex_file_id', true );
				echo $f ? '<a href="' . esc_url( get_edit_post_link( $f ) ) . '">' . esc_html( get_the_title( $f ) ) . '</a>' : '—';
				break;
			case 'exf_amount':
				$p = (int) get_post_meta( $id, 'amount_pence', true );
				echo esc_html( $p ? '£' . number_format( $p / 100, 2 ) : __( 'Free', 'exfiles' ) );
				break;
			case 'exf_status':
				echo esc_html( exf_order_status_label( exf_order_status( $id ) ) );
				if ( get_post_meta( $id, 'needs_reschedule', true ) ) {
					echo '<br><strong style="color:#b32d2e">' . esc_html__( 'Needs rescheduling', 'exfiles' ) . '</strong>';
				}
				break;
			case 'exf_booking':
				$s = (int) get_post_meta( $id, 'booking_start', true );
				echo esc_html( $s ? exf_booking_label( $s ) : '—' );
				break;
		}
	},
	10,
	2
);

add_action(
	'add_meta_boxes_exf_order',
	function () {
		remove_meta_box( 'submitdiv', 'exf_order', 'side' );
		remove_meta_box( 'slugdiv', 'exf_order', 'normal' );
		add_meta_box( 'exf_order_box', __( 'Order', 'exfiles' ), 'exf_order_box', 'exf_order', 'normal', 'high' );
	}
);

add_action(
	'admin_head-post.php',
	function () {
		$screen = get_current_screen();
		if ( $screen && 'exf_order' === $screen->post_type ) {
			remove_post_type_support( 'exf_order', 'title' );
		}
	}
);

/**
 * Order details box.
 *
 * @param WP_Post $post Order.
 */
function exf_order_box( $post ) {
	$id      = $post->ID;
	$client  = get_userdata( (int) get_post_meta( $id, 'client_id', true ) );
	$file    = (int) get_post_meta( $id, 'ex_file_id', true );
	$pi      = (string) get_post_meta( $id, 'payment_intent', true );
	$start   = (int) get_post_meta( $id, 'booking_start', true );
	$q       = (int) get_post_meta( $id, 'questionnaire_id', true );
	$log     = array_reverse( array_filter( (array) get_post_meta( $id, 'order_log', true ) ) );
	$pence   = (int) get_post_meta( $id, 'amount_pence', true );
	$cancel  = wp_nonce_url( admin_url( 'admin-post.php?action=exf_order_cancel&order=' . $id ), 'exf_order_cancel_' . $id );
	?>
	<h2 style="padding-left:0"><?php echo esc_html( get_the_title( $post ) ); ?></h2>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Status', 'exfiles' ); ?></th><td><strong><?php echo esc_html( exf_order_status_label( exf_order_status( $id ) ) ); ?></strong>
			<?php if ( get_post_meta( $id, 'needs_reschedule', true ) ) : ?>
				<br><strong style="color:#b32d2e"><?php esc_html_e( 'Paid after the slot was released, and the slot has since been taken. Please contact the client to rebook.', 'exfiles' ); ?></strong>
			<?php endif; ?>
		</td></tr>
		<tr><th><?php esc_html_e( 'Client', 'exfiles' ); ?></th><td><?php echo $client ? '<a href="' . esc_url( get_edit_user_link( $client->ID ) ) . '">' . esc_html( $client->display_name ) . '</a> (' . esc_html( $client->user_email ) . ')' : '—'; ?></td></tr>
		<tr><th><?php esc_html_e( 'Ex File', 'exfiles' ); ?></th><td><?php echo $file ? '<a href="' . esc_url( get_edit_post_link( $file ) ) . '">' . esc_html( get_the_title( $file ) ) . '</a>' : '—'; ?></td></tr>
		<tr><th><?php esc_html_e( 'Service', 'exfiles' ); ?></th><td><?php echo esc_html( (string) get_post_meta( $id, 'service_name', true ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Amount', 'exfiles' ); ?></th><td><?php echo esc_html( $pence ? '£' . number_format( $pence / 100, 2 ) : __( 'Free', 'exfiles' ) ); ?>
			<?php if ( $pi ) : ?>
				&nbsp; <a href="<?php echo esc_url( exf_stripe_dashboard_url( $pi ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View in Stripe (refunds are done there)', 'exfiles' ); ?></a>
			<?php endif; ?>
		</td></tr>
		<?php if ( $start ) : ?>
			<tr><th><?php esc_html_e( 'Appointment', 'exfiles' ); ?></th><td><?php echo esc_html( exf_booking_label( $start, (int) get_post_meta( $id, 'booking_end', true ) ) ); ?>
				<?php if ( exf_order_is_live( $id ) && $start > time() ) : ?>
					&nbsp; <a href="<?php echo esc_url( $cancel ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Cancel this appointment and free the slot? This does not refund the payment or email the client.', 'exfiles' ) ); ?>')" style="color:#b32d2e"><?php esc_html_e( 'Cancel appointment', 'exfiles' ); ?></a>
				<?php endif; ?>
			</td></tr>
		<?php endif; ?>
		<?php if ( $q ) : ?>
			<tr><th><?php esc_html_e( 'Questionnaire', 'exfiles' ); ?></th><td><a href="<?php echo esc_url( get_edit_post_link( $q ) ); ?>"><?php echo esc_html( get_the_title( $q ) ); ?></a></td></tr>
		<?php endif; ?>
	</table>
	<h3><?php esc_html_e( 'History', 'exfiles' ); ?></h3>
	<table class="widefat striped"><tbody>
		<?php foreach ( $log as $row ) : ?>
			<tr><td style="width:160px"><?php echo esc_html( wp_date( 'j M Y H:i', (int) $row['time'] ) ); ?></td><td><?php echo esc_html( $row['msg'] ); ?></td></tr>
		<?php endforeach; ?>
	</tbody></table>
	<?php
}

add_action(
	'admin_post_exf_order_cancel',
	function () {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		check_admin_referer( 'exf_order_cancel_' . $id );
		if ( ! exf_user_can_manage() || 'exf_order' !== get_post_type( $id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		delete_post_meta( $id, 'booking_start' );
		delete_post_meta( $id, 'booking_end' );
		exf_order_log( $id, 'Appointment cancelled by ' . wp_get_current_user()->display_name );
		wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
		exit;
	}
);
