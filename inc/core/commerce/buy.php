<?php
/**
 * The buy page (/my-ex-file/buy/, template page-buy.php). For visitors who aren't
 * logged in it's the basket of the checkout flow instead (see basket.php).
 *
 *   ?service=ID                 choose the Ex File (and a slot), then pay
 *   ?new_separation=1           add a new separation (runs the conflict check)
 *   ?consult=ORDER              book a consultation add-on (see consult.php)
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

	// New clients: the basket, then "Create your Ex File", then payment (basket.php).
	if ( ! is_user_logged_in() ) {
		exf_basket_route( $service );
		return;
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

	if ( isset( $_POST['exf_consult'] ) ) {
		check_admin_referer( 'exf_consult' );
		$GLOBALS['exf_buy_errors'] = exf_handle_consult( absint( $_POST['consult'] ?? 0 ), absint( $_POST['slot'] ?? 0 ) );
		return;
	}

	if ( isset( $_POST['exf_buy'] ) ) {
		check_admin_referer( 'exf_buy' );
		$errors = exf_handle_buy(
			absint( $_POST['service'] ?? 0 ),
			absint( $_POST['ex_file'] ?? 0 ),
			absint( $_POST['slot'] ?? 0 ),
			array(
				'addon'   => ! empty( $_POST['addon'] ),
				'consent' => ! empty( $_POST['immediate_consent'] ),
			)
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
		return __( "We're still checking a few details on your Ex File. You'll be able to choose a service as soon as that's done (within one working day), and we'll email you.", 'exfiles' );
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
 * @param int   $slot       Appointment start.
 * @param array $opts       addon (bool), consent (bool: asked for work to start straight away).
 * @return array Errors (redirects on success).
 */
function exf_handle_buy( $service_id, $ex_file, $slot, array $opts = array() ) {
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
	if ( $service['immediate'] && empty( $opts['consent'] ) ) {
		$errors['immediate_consent'] = __( 'Please tick to confirm, so we can start work straight away.', 'exfiles' );
	}
	if ( ! $service['free'] && ! exf_stripe_ready() ) {
		$errors['_form'] = __( "Online payment isn't set up yet. Please contact us to book.", 'exfiles' );
	}
	if ( $errors ) {
		return $errors;
	}

	// Check again before the first purchase on an Ex File: someone named in it may
	// have become a client since it was checked at sign-up.
	$recheck = exf_conflict_recheck_before_purchase( $ex_file );
	if ( $recheck ) {
		return array( '_form' => $recheck );
	}

	$result = exf_purchase_start( $user_id, $ex_file, $service, $slot, $opts );
	if ( isset( $result['errors'] ) ) {
		return $result['errors'];
	}
	delete_user_meta( $user_id, 'exf_saved_basket' );
	exf_purchase_redirect( $result );
}

/**
 * The basket a client chose at checkout before being held for review, if it's for
 * this service: [ slot, addon ]. The slot is 0 if it has gone meanwhile.
 *
 * @param int   $user_id Client.
 * @param array $service From exf_service().
 * @return array|null
 */
function exf_saved_basket_for( $user_id, array $service ) {
	$saved = get_user_meta( $user_id, 'exf_saved_basket', true );
	if ( ! is_array( $saved ) || (int) ( $saved['service'] ?? 0 ) !== (int) $service['id'] ) {
		return null;
	}
	$slot = (int) ( $saved['slot'] ?? 0 );
	return array(
		'slot'      => $service['booking'] && $slot && exf_slot_is_free( $slot, $service['minutes'] ) ? $slot : 0,
		'slot_gone' => $service['booking'] && $slot && ! exf_slot_is_free( $slot, $service['minutes'] ),
		'addon'     => ! empty( $saved['addon'] ),
	);
}

/**
 * Hold the slot, create the order and start the payment (or confirm a free service).
 * Shared by logged-in clients (exf_handle_buy) and new clients at checkout (basket.php).
 * The caller has already checked the client, the Ex File and the options.
 *
 * @param int   $user_id Client.
 * @param int   $ex_file Ex File.
 * @param array $service From exf_service().
 * @param int   $slot    Appointment start, or 0.
 * @param array $opts    addon (bool), consent (bool).
 * @return array { order, url, free } on success, or { errors } (field => message).
 */
function exf_purchase_start( $user_id, $ex_file, array $service, $slot, array $opts = array() ) {
	// Lock, re-check the slot, and create the order so the slot is held while they pay.
	if ( $service['booking'] && ! exf_lock( 'booking', 20 ) ) {
		return array( 'errors' => array( '_form' => __( 'Sorry, the booking system is busy. Please try again.', 'exfiles' ) ) );
	}
	if ( $service['booking'] && ! exf_slot_is_free( $slot, $service['minutes'] ) ) {
		exf_unlock( 'booking' );
		return array( 'errors' => array( 'slot' => __( 'Sorry, that time has just been taken. Please choose another.', 'exfiles' ) ) );
	}
	$order = exf_order_create(
		$user_id,
		$ex_file,
		$service,
		$service['booking'] ? $slot : 0,
		array(
			'addon'   => $service['addon'] && ! empty( $opts['addon'] ),
			'consent' => $service['immediate'] && ! empty( $opts['consent'] ),
		)
	);
	if ( $service['booking'] ) {
		exf_unlock( 'booking' );
	}
	if ( ! $order ) {
		return array( 'errors' => array( '_form' => __( 'Sorry, something went wrong. Please try again.', 'exfiles' ) ) );
	}

	if ( $service['free'] ) {
		exf_order_confirm_free( $order );
		return array(
			'order' => $order,
			'free'  => true,
			'url'   => add_query_arg(
				array(
					'order' => $order,
					'done'  => 1,
				),
				exf_page_url( 'buy' )
			),
		);
	}

	$session = exf_stripe_create_session( $order );
	if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
		exf_order_set_status( $order, 'cancelled', 'Could not start payment: ' . ( is_wp_error( $session ) ? $session->get_error_message() : 'no URL' ) );
		return array( 'errors' => array( '_form' => __( "Sorry, we couldn't start the payment. Please try again, or contact us if it keeps happening.", 'exfiles' ) ) );
	}
	update_post_meta( $order, 'stripe_session', (string) $session['id'] );
	exf_order_log( $order, 'Sent to Stripe to pay' );

	return array(
		'order' => $order,
		'free'  => false,
		'url'   => (string) $session['url'],
	);
}

/**
 * Send the visitor on after exf_purchase_start(): to Stripe, or to the confirmation.
 *
 * @param array $result From exf_purchase_start().
 */
function exf_purchase_redirect( array $result ) {
	if ( ! empty( $result['free'] ) ) {
		wp_safe_redirect( $result['url'] );
	} else {
		wp_redirect( $result['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Stripe's hosted checkout.
	}
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
	if ( ( $v['ex_email'] && ! is_email( $v['ex_email'] ) ) || ( '' === $v['ex_email'] && '' !== trim( (string) ( $post['ex_email'] ?? '' ) ) ) ) {
		$v['ex_email'] = sanitize_text_field( (string) ( $post['ex_email'] ?? '' ) );
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
