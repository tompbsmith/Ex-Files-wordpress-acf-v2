<?php
/**
 * Consultation add-ons booked after a report (e.g. Check Your Deal's virtual
 * consultation).
 *
 * The original order holds the add-on (meta addon_type = consultation, and
 * addon_pence when it was paid for up front). Booking opens when the solicitor shares
 * a document of type "Report" on that Ex File, or when she opens it from the order:
 *
 *   consult_status  ''      not open yet
 *                   open    paid for up front: the client is emailed a link to book
 *                   offer   not paid for: the client can add it (and pay) from their Ex File
 *                   booked  booked (consult_order is the booking)
 *
 * The booking itself is a separate order (meta parent_order), so it appears in the
 * appointment lists, reminders and calendar like any other booking. It's free when
 * the add-on was paid for with the original order.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether an order's service has a consultation add-on.
 *
 * @param int $order_id Order.
 * @return array|null The service.
 */
function exf_consult_service( $order_id ) {
	$service = exf_service( (int) get_post_meta( $order_id, 'service_id', true ) );
	if ( get_post_meta( $order_id, 'parent_order', true ) || ! exf_order_is_live( $order_id ) ) {
		return null;
	}
	if ( 'consultation' === get_post_meta( $order_id, 'addon_type', true ) && $service ) {
		// Paid for: use what was bought, even if the service's add-on has changed since.
		if ( ! $service['addon'] || 'consultation' !== $service['addon']['type'] ) {
			$service['addon'] = array(
				'name'        => (string) get_post_meta( $order_id, 'addon_name', true ),
				'price'       => (int) get_post_meta( $order_id, 'addon_pence', true ) / 100,
				'pence'       => (int) get_post_meta( $order_id, 'addon_pence', true ),
				'type'        => 'consultation',
				'minutes'     => 60,
				'description' => '',
			);
		}
		return $service;
	}
	return ( $service && $service['addon'] && 'consultation' === $service['addon']['type'] ) ? $service : null;
}

/**
 * Whether the consultation was paid for with the original order.
 *
 * @param int $order_id Order.
 */
function exf_consult_prepaid( $order_id ) {
	return 'consultation' === get_post_meta( $order_id, 'addon_type', true ) && (int) get_post_meta( $order_id, 'addon_pence', true ) > 0;
}

/**
 * The live order on an Ex File whose consultation hasn't been opened yet, or 0.
 *
 * @param int $ex_file Ex File.
 */
function exf_document_consult_order( $ex_file ) {
	if ( ! $ex_file ) {
		return 0;
	}
	$client = (int) get_post_field( 'post_author', $ex_file );
	foreach ( exf_client_orders( $client, $ex_file ) as $o ) {
		if ( exf_consult_service( $o ) && '' === (string) get_post_meta( $o, 'consult_status', true ) ) {
			return $o;
		}
	}
	return 0;
}

/**
 * Open consultation booking for an order. Emails the client if they've already paid.
 *
 * @param int    $order_id Order.
 * @param string $why      For the log.
 */
function exf_consult_open( $order_id, $why = '' ) {
	$service = exf_consult_service( $order_id );
	if ( ! $service || in_array( get_post_meta( $order_id, 'consult_status', true ), array( 'open', 'booked' ), true ) ) {
		return false;
	}
	$prepaid = exf_consult_prepaid( $order_id );
	update_post_meta( $order_id, 'consult_status', $prepaid ? 'open' : 'offer' );
	update_post_meta( $order_id, 'consult_opened_at', time() );
	exf_order_log( $order_id, ( $prepaid ? 'Consultation booking opened' : 'Consultation offered to the client' ) . ( $why ? ' (' . $why . ')' : '' ) );

	if ( $prepaid ) {
		$user = get_userdata( (int) get_post_meta( $order_id, 'client_id', true ) );
		if ( $user ) {
			exf_send_email(
				'consultation_open',
				$user->user_email,
				exf_order_email_vars( $order_id ) + array(
					'addon' => lcfirst( (string) get_post_meta( $order_id, 'addon_name', true ) ),
					'link'  => exf_consult_url( $order_id ),
				)
			);
		}
	}
	return true;
}

/**
 * Where the client books a consultation for an order.
 *
 * @param int $order_id Original order.
 */
function exf_consult_url( $order_id ) {
	return add_query_arg( 'consult', (int) $order_id, exf_page_url( 'buy' ) );
}

/**
 * Consultations a client can book now: [ order_id => service ].
 *
 * @param int $user_id Client.
 * @return array<int,array>
 */
function exf_consult_offers( $user_id ) {
	$out = array();
	foreach ( exf_client_orders( $user_id ) as $o ) {
		if ( in_array( get_post_meta( $o, 'consult_status', true ), array( 'open', 'offer' ), true ) ) {
			$service = exf_consult_service( $o );
			if ( $service ) {
				$out[ $o ] = $service;
			}
		}
	}
	return $out;
}

/**
 * Validate a consultation booking and confirm it (or send the client to pay).
 *
 * @param int $order_id Original order.
 * @param int $slot     Start time.
 * @return array Errors (redirects on success).
 */
function exf_handle_consult( $order_id, $slot ) {
	$user_id = get_current_user_id();
	$offers  = exf_consult_offers( $user_id );
	if ( ! isset( $offers[ $order_id ] ) ) {
		return array( '_form' => __( "Sorry, that consultation can't be booked online. Please contact us.", 'exfiles' ) );
	}
	$service = $offers[ $order_id ];
	$prepaid = exf_consult_prepaid( $order_id );
	if ( ! $slot ) {
		return array( 'slot' => __( 'Please choose a time for your consultation.', 'exfiles' ) );
	}
	if ( ! $prepaid && ! exf_stripe_ready() ) {
		return array( '_form' => __( "Online payment isn't set up yet. Please contact us to book.", 'exfiles' ) );
	}
	if ( ! exf_lock( 'booking', 20 ) ) {
		return array( '_form' => __( 'Sorry, the booking system is busy. Please try again.', 'exfiles' ) );
	}
	if ( ! exf_slot_is_free( $slot, $service['addon']['minutes'] ) ) {
		exf_unlock( 'booking' );
		return array( 'slot' => __( 'Sorry, that time has just been taken. Please choose another.', 'exfiles' ) );
	}
	$order = exf_order_create( $user_id, (int) get_post_meta( $order_id, 'ex_file_id', true ), $service, $slot, array( 'consult' => $order_id ) );
	exf_unlock( 'booking' );
	if ( ! $order ) {
		return array( '_form' => __( 'Sorry, something went wrong. Please try again.', 'exfiles' ) );
	}

	if ( ! (int) get_post_meta( $order, 'amount_pence', true ) ) {
		delete_post_meta( $order, 'hold_expires' );
		exf_order_set_status( $order, 'confirmed', 'Consultation booked (paid with order ' . $order_id . ')' );
		exf_order_fulfil( $order );
		wp_safe_redirect(
			add_query_arg(
				array(
					'order' => $order,
					'done'  => 1,
				),
				exf_page_url( 'buy' )
			)
		);
		exit;
	}

	$session = exf_stripe_create_session( $order );
	if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
		exf_order_set_status( $order, 'cancelled', 'Could not start payment: ' . ( is_wp_error( $session ) ? $session->get_error_message() : 'no URL' ) );
		return array( '_form' => __( "Sorry, we couldn't start the payment. Please try again, or contact us if it keeps happening.", 'exfiles' ) );
	}
	update_post_meta( $order, 'stripe_session', (string) $session['id'] );
	exf_order_log( $order, 'Sent to Stripe to pay' );
	wp_redirect( $session['url'] ); // phpcs:ignore WordPress.WP.SafeRedirect.wp_redirect_wp_redirect -- Stripe's hosted checkout.
	exit;
}

// The solicitor can open booking from the order, e.g. if the report was sent another way.
add_action(
	'admin_post_exf_consult_open',
	function () {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		check_admin_referer( 'exf_consult_open_' . $id );
		if ( ! exf_user_can_manage() || 'exf_order' !== get_post_type( $id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		exf_consult_open( $id, 'opened by ' . wp_get_current_user()->display_name );
		wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
		exit;
	}
);
