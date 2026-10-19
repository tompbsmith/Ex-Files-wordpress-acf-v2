<?php
/**
 * The buy page (/my-ex-file/buy/, template page-buy.php):
 *
 *   ?service=ID                 choose the Ex File (and a slot), then pay
 *   ?new_separation=1           add a new separation (runs the conflict check)
 *   ?order=ID&session_id=…      back from Stripe: confirm the payment
 *   ?order=ID&cancelled=1       back from Stripe without paying: release the slot
 *   ?order=ID&done=1            free booking confirmed
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', 'exf_buy_route', 5 );

/**
 * Route buy-page requests.
 */
function exf_buy_route() {
	if ( ! is_page_template( 'page-buy.php' ) ) {
		return;
	}
	nocache_headers();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$service = isset( $_GET['service'] ) ? absint( $_GET['service'] ) : 0;

	if ( ! is_user_logged_in() ) {
		$target = $service ? add_query_arg( 'service', $service, exf_page_url( 'register' ) ) : wp_login_url( exf_page_url( 'buy' ) );
		wp_safe_redirect( $target );
		exit;
	}
	$user_id = get_current_user_id();

	// Back from Stripe.
	if ( isset( $_GET['order'] ) ) {
		$order = absint( $_GET['order'] );
		if ( 'exf_order' !== get_post_type( $order ) || (int) get_post_meta( $order, 'client_id', true ) !== $user_id ) {
			wp_safe_redirect( exf_page_url( 'account' ) );
			exit;
		}
		if ( isset( $_GET['cancelled'] ) && 'pending' === exf_order_status( $order ) ) {
			exf_order_set_status( $order, 'cancelled', 'Client left the payment page' );
		}
		if ( isset( $_GET['session_id'] ) && ! exf_order_is_live( $order ) ) {
			$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ) );
			if ( $session_id === (string) get_post_meta( $order, 'stripe_session', true ) ) {
				$session = exf_stripe_request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
				if ( ! is_wp_error( $session ) && 'paid' === ( $session['payment_status'] ?? '' ) ) {
					exf_order_mark_paid( $order, $session );
				}
			}
		}
		return; // The template shows the result.
	}
	// phpcs:enable

	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}

	if ( isset( $_POST['exf_new_separation'] ) ) {
		check_admin_referer( 'exf_new_separation' );
		$errors = exf_handle_new_separation( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field.
		$GLOBALS['exf_buy_errors'] = $errors;
		return;
	}

	if ( isset( $_POST['exf_buy'] ) ) {
		check_admin_referer( 'exf_buy' );
		$errors = exf_handle_buy(
			absint( $_POST['service'] ?? 0 ),
			absint( $_POST['ex_file'] ?? 0 ),
			absint( $_POST['slot'] ?? 0 )
		);
		$GLOBALS['exf_buy_errors'] = $errors;
	}
}

/**
 * Why the current client can't buy right now, or '' if they can.
 *
 * @param int $user_id Client.
 */
function exf_buy_blocker( $user_id ) {
	if ( ! exf_is_client( $user_id ) ) {
		return current_user_can( EXF_CAP )
			? __( "You're logged in as staff, so you can't buy services. Log in with a client account to test buying.", 'exfiles' )
			: __( "You'll need an Ex File before you can choose a service.", 'exfiles' );
	}
	$status = exf_user_status( $user_id );
	if ( EXF_STATUS_REVIEW === $status ) {
		return __( "We're still checking a few details on your Ex File. You'll be able to choose a service as soon as that's done, and we'll email you.", 'exfiles' );
	}
	if ( EXF_STATUS_ACTIVE !== $status ) {
		return __( "We're unable to take a booking for you at the moment. Please contact us.", 'exfiles' );
	}
	return '';
}

/**
 * Validate a purchase, create the order and send the client to Stripe (or confirm a free service).
 *
 * @param int $service_id Service page.
 * @param int $ex_file    Ex File.
 * @param int $slot       Appointment start.
 * @return array Errors (redirects on success).
 */
function exf_handle_buy( $service_id, $ex_file, $slot ) {
	$user_id = get_current_user_id();
	$service = exf_service( $service_id );
	$errors  = array();

	if ( $blocker = exf_buy_blocker( $user_id ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
		return array( '_form' => $blocker );
	}
	if ( ! $service || ! $service['sellable'] ) {
		return array( '_form' => __( "Sorry, that service isn't available to book online yet.", 'exfiles' ) );
	}
	if ( ! $ex_file || 'ex_file' !== get_post_type( $ex_file ) || (int) get_post_field( 'post_author', $ex_file ) !== $user_id ) {
		$errors['ex_file'] = __( 'Please choose which Ex File this is for.', 'exfiles' );
	} elseif ( 'clear' !== exf_ex_file_status( $ex_file ) ) {
		$errors['ex_file'] = __( "We're still checking that Ex File, so it can't be used yet.", 'exfiles' );
	}
	if ( $service['booking'] && ! $slot ) {
		$errors['slot'] = __( 'Please choose a time for your appointment.', 'exfiles' );
	}
	if ( ! $service['free'] && ! exf_stripe_ready() ) {
		$errors['_form'] = __( "Online payment isn't set up yet. Please contact us to book.", 'exfiles' );
	}
	if ( $errors ) {
		return $errors;
	}

	// Lock, re-check the slot, and create the order so the slot is held while they pay.
	if ( $service['booking'] && ! exf_lock( 'booking', 20 ) ) {
		return array( '_form' => __( 'Sorry, the booking system is busy. Please try again.', 'exfiles' ) );
	}
	if ( $service['booking'] && ! exf_slot_is_free( $slot, $service['minutes'] ) ) {
		exf_unlock( 'booking' );
		return array( 'slot' => __( 'Sorry, that time has just been taken. Please choose another.', 'exfiles' ) );
	}
	$order = exf_order_create( $user_id, $ex_file, $service, $service['booking'] ? $slot : 0 );
	if ( $service['booking'] ) {
		exf_unlock( 'booking' );
	}
	if ( ! $order ) {
		return array( '_form' => __( 'Sorry, something went wrong. Please try again.', 'exfiles' ) );
	}

	if ( $service['free'] ) {
		exf_order_confirm_free( $order );
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

	wp_redirect( $session['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Stripe's hosted checkout.
	exit;
}

/**
 * Add a new separation (a second Ex File) and run the conflict check on it.
 *
 * The client's account isn't affected: a review or block applies only to the new Ex File.
 *
 * @param array $post Unslashed $_POST.
 * @return array Errors and submitted values (redirects on success).
 */
function exf_handle_new_separation( array $post ) {
	$user_id = get_current_user_id();
	if ( ! exf_is_client( $user_id ) || EXF_STATUS_BLOCKED === exf_user_status( $user_id ) ) {
		return array( '_form' => __( "Sorry, you can't add a separation to this account.", 'exfiles' ) );
	}

	$fields = array( 'ex_first_name', 'ex_last_name', 'ex_prev_names', 'ex_dob', 'ex_email', 'ex_phone', 'ex_postcode', 'relationship', 'marriage_date', 'separation_date' );
	$v      = array();
	foreach ( $fields as $f ) {
		$v[ $f ] = 'ex_email' === $f ? sanitize_email( $post[ $f ] ?? '' ) : sanitize_text_field( $post[ $f ] ?? '' );
	}
	$v['ex_dob_unknown'] = empty( $post['ex_dob_unknown'] ) ? 0 : 1;
	$service             = absint( $post['service'] ?? 0 );

	$e = array();
	foreach ( array( 'ex_first_name', 'ex_last_name', 'relationship' ) as $req ) {
		if ( '' === $v[ $req ] ) {
			$e[ $req ] = __( 'Please fill this in.', 'exfiles' );
		}
	}
	if ( ! isset( exf_relationship_options()[ $v['relationship'] ] ) && '' !== $v['relationship'] ) {
		$e['relationship'] = __( 'Please choose an option.', 'exfiles' );
	}
	foreach ( array( 'ex_dob', 'marriage_date', 'separation_date' ) as $date ) {
		if ( $v[ $date ] ) {
			$norm = exf_norm_dob( $v[ $date ] );
			if ( ! $norm || $norm > gmdate( 'Y-m-d' ) ) {
				$e[ $date ] = __( 'Please enter a valid date.', 'exfiles' );
			} else {
				$v[ $date ] = str_replace( '-', '', $norm );
			}
		}
	}
	if ( ! $v['ex_dob'] && ! $v['ex_dob_unknown'] ) {
		$e['ex_dob'] = __( "Please enter their date of birth, or tick that you don't know it.", 'exfiles' );
	}
	if ( $v['ex_email'] && ! is_email( $v['ex_email'] ) ) {
		$e['ex_email'] = __( 'Please enter a valid email address, or leave it blank.', 'exfiles' );
	}
	if ( $e ) {
		return array(
			'errors' => $e,
			'values' => $v,
		);
	}

	$ex_file = exf_create_ex_file( $user_id, $v );
	if ( ! $ex_file ) {
		return array( '_form' => __( 'Sorry, something went wrong. Please try again.', 'exfiles' ) );
	}
	exf_ex_file_log( $ex_file, 'New separation added by the client' );

	// Check it, without the "account" emails meant for new registrations.
	$outcome = exf_conflict_run( $ex_file, false );
	$vars    = exf_ex_file_email_vars( $ex_file );
	if ( 'review' === $outcome ) {
		exf_send_email( 'admin_review', exf_notify_emails(), $vars );
	} elseif ( 'blocked' === $outcome ) {
		exf_send_email( 'admin_blocked', exf_notify_emails(), $vars );
	}

	$args = array( 'separation' => $outcome );
	if ( $service ) {
		$args['service'] = $service;
		if ( 'clear' === $outcome ) {
			$args['ex_file'] = $ex_file;
		}
	}
	wp_safe_redirect( add_query_arg( $args, 'clear' === $outcome && $service ? exf_page_url( 'buy' ) : exf_page_url( 'account' ) ) );
	exit;
}
