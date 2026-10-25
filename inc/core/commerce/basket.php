<?php
/**
 * Checkout for new clients (October 2026). People choose a service before they
 * create an Ex File, so nobody gives their ex's details without going ahead:
 *
 *   1. Choose a service      the "Create your Ex File" page (/create-your-ex-file/),
 *                            a copy of Our Services, or a service page's button
 *   2. Basket                /my-ex-file/buy/?service=ID while logged out: the
 *                            service, a time if it has an appointment, any add-on,
 *                            and the cancellation consent
 *   3. Create your Ex File   the registration form (page-register.php). The details
 *                            are saved with the account status "awaiting_payment" and
 *                            checked straight away, without telling the visitor
 *                            anything about the check
 *   4. Payment               Stripe Checkout, if the check is clear
 *   5. Account               once Stripe confirms the payment the account is
 *                            created properly (status pending_verification) and the
 *                            "confirm your email" email is sent; clicking it logs
 *                            them in to My Ex File
 *
 * If the check needs a manual review, they're told only that we're checking a few
 * details and will email them (nothing is charged); once cleared they log in and
 * pay as any client does. Blocked: the usual "unable to help" wording.
 *
 * Details of someone who never pays aren't used in anyone's conflict check
 * (exf_user_counts_in_conflict_check()), and are deleted after
 * Settings → Data retention → "Delete unconfirmed sign-ups after" (7 days by
 * default). Until then the visitor can come back to the basket and pay without
 * filling the form in again (on the same browser), or start again with the same
 * email address.
 *
 * The basket lives in a transient keyed by a random token in the exf_basket cookie.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_BASKET_COOKIE = 'exf_basket';
const EXF_BASKET_HOURS  = 48;

/* -------------------------------------------------------------------------
 * Storage
 * ---------------------------------------------------------------------- */

/**
 * The basket token from the cookie, or ''.
 */
function exf_basket_token() {
	$token = isset( $_COOKIE[ EXF_BASKET_COOKIE ] ) ? (string) wp_unslash( $_COOKIE[ EXF_BASKET_COOKIE ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked below.
	return preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ? $token : '';
}

/**
 * The visitor's basket, or null.
 *
 * @return array|null { service, slot, addon, consent, user_id, ex_file, order, error }
 */
function exf_basket_get() {
	$token = exf_basket_token();
	if ( ! $token ) {
		return null;
	}
	$cache = $GLOBALS['exf_basket_cache'] ?? null;
	if ( ! is_array( $cache ) || $cache[0] !== $token ) {
		$b     = get_transient( 'exf_basket_' . $token );
		$cache = array( $token, is_array( $b ) ? $b : null );
		$GLOBALS['exf_basket_cache'] = $cache;
	}
	return $cache[1];
}

/**
 * Save the basket (and set the cookie if it's new).
 *
 * @param array $basket Basket.
 */
function exf_basket_save( array $basket ) {
	$token = exf_basket_token();
	if ( ! $token ) {
		$token = wp_generate_password( 32, false, false );
		$_COOKIE[ EXF_BASKET_COOKIE ] = $token; // So the rest of this request sees it.
	}
	$basket += array(
		'service' => 0,
		'slot'    => 0,
		'addon'   => false,
		'consent' => false,
		'user_id' => 0,
		'ex_file' => 0,
		'order'   => 0,
		'error'   => '',
	);
	set_transient( 'exf_basket_' . $token, $basket, EXF_BASKET_HOURS * HOUR_IN_SECONDS );
	$GLOBALS['exf_basket_cache'] = array( $token, $basket );
	if ( ! headers_sent() ) {
		setcookie(
			EXF_BASKET_COOKIE,
			$token,
			array(
				'expires'  => time() + EXF_BASKET_HOURS * HOUR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax', // Sent when Stripe sends the visitor back.
			)
		);
	}
	return $basket;
}

/**
 * Empty the basket.
 */
function exf_basket_clear() {
	$token = exf_basket_token();
	if ( $token ) {
		delete_transient( 'exf_basket_' . $token );
	}
	if ( ! headers_sent() ) {
		setcookie( EXF_BASKET_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}
	unset( $_COOKIE[ EXF_BASKET_COOKIE ], $GLOBALS['exf_basket_cache'] );
}

/**
 * The service in the basket, or null.
 *
 * @param array|null $basket Basket.
 */
function exf_basket_service( $basket ) {
	$service = $basket && ! empty( $basket['service'] ) ? exf_service( (int) $basket['service'] ) : null;
	return $service && $service['sellable'] ? $service : null;
}

/**
 * The sign-up already saved in this basket, if they can go straight to payment:
 * details given, check clear, not paid yet. 0 otherwise.
 *
 * @param array|null $basket Basket.
 */
function exf_basket_user( $basket ) {
	$uid = $basket ? (int) ( $basket['user_id'] ?? 0 ) : 0;
	$ex  = $basket ? (int) ( $basket['ex_file'] ?? 0 ) : 0;
	if ( ! $uid || ! $ex || ! get_userdata( $uid ) || EXF_STATUS_AWAITING !== exf_user_status( $uid ) ) {
		return 0;
	}
	if ( 'ex_file' !== get_post_type( $ex ) || (int) get_post_field( 'post_author', $ex ) !== $uid || 'clear' !== exf_ex_file_status( $ex ) ) {
		return 0;
	}
	return $uid;
}

/* -------------------------------------------------------------------------
 * The buy page for visitors who aren't logged in
 * ---------------------------------------------------------------------- */

/**
 * Route the buy page for a visitor who isn't logged in.
 *
 * @param int $service Service from ?service=.
 */
function exf_basket_route( $service ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['order'] ) ) {
		exf_basket_route_order( absint( $_GET['order'] ) );
		return;
	}
	// phpcs:enable

	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['exf_basket'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		check_admin_referer( 'exf_basket' );
		$GLOBALS['exf_buy_errors'] = exf_handle_basket( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field.
		return;
	}

	if ( ! $service || ! exf_service( $service ) ) {
		$basket = exf_basket_get();
		if ( exf_basket_service( $basket ) ) {
			wp_safe_redirect( add_query_arg( 'service', (int) $basket['service'], exf_page_url( 'buy' ) ) );
		} else {
			wp_safe_redirect( exf_page_url( 'choose_service' ) );
		}
		exit;
	}
}

/**
 * Back from Stripe (or a free booking) without being logged in. Only the browser
 * that started the checkout, or Stripe's own return link, can see the order.
 *
 * @param int $order Order.
 */
function exf_basket_route_order( $order ) {
	$basket     = exf_basket_get();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
	$by_session = '' !== $session_id && 'exf_order' === get_post_type( $order ) && hash_equals( (string) get_post_meta( $order, 'stripe_session', true ), $session_id );
	$by_basket  = $basket && (int) $basket['order'] === $order && 'exf_order' === get_post_type( $order );
	if ( ! $by_session && ! $by_basket ) {
		wp_safe_redirect( exf_page_url( 'choose_service' ) );
		exit;
	}

	if ( isset( $_GET['cancelled'] ) && 'pending' === exf_order_status( $order ) ) {
		exf_order_set_status( $order, 'cancelled', 'Client left the payment page' );
	}
	if ( $by_session && ! exf_order_is_live( $order ) ) {
		$session = exf_stripe_request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
		if ( ! is_wp_error( $session ) && 'paid' === ( $session['payment_status'] ?? '' ) ) {
			exf_order_mark_paid( $order, $session );
		}
	}
	// phpcs:enable
	if ( exf_order_is_live( $order ) && $basket ) {
		exf_basket_clear(); // Done: the account is created and the email sent.
	}
	$GLOBALS['exf_guest_order'] = $order;
}

/**
 * Save the basket choices, then go to "Create your Ex File" (or straight to
 * payment if this browser has already done that step).
 *
 * @param array $post Unslashed $_POST.
 * @return array Errors (redirects on success).
 */
function exf_handle_basket( array $post ) {
	$service = exf_service( absint( $post['service'] ?? 0 ) );
	if ( ! $service || ! $service['sellable'] ) {
		return array( '_form' => __( "Sorry, that service isn't available to book online yet.", 'exfiles' ) );
	}
	$slot    = absint( $post['slot'] ?? 0 );
	$addon   = $service['addon'] && ! empty( $post['addon'] );
	$consent = $service['immediate'] && ! empty( $post['immediate_consent'] );
	$errors  = array();

	if ( $service['booking'] && ! $slot ) {
		$errors['slot'] = __( 'Please choose a time for your appointment.', 'exfiles' );
	} elseif ( $service['booking'] && ! exf_slot_is_free( $slot, $service['minutes'] ) ) {
		$errors['slot'] = __( 'Sorry, that time has just been taken. Please choose another.', 'exfiles' );
	}
	if ( $service['immediate'] && ! $consent ) {
		$errors['immediate_consent'] = __( 'Please tick to confirm, so we can start work straight away.', 'exfiles' );
	}
	if ( ! $service['free'] && ! exf_stripe_ready() ) {
		$errors['_form'] = __( "Online payment isn't set up yet. Please contact us to book.", 'exfiles' );
	}
	if ( $errors ) {
		return $errors;
	}

	$basket = (array) exf_basket_get();
	$same   = (int) ( $basket['service'] ?? 0 ) === (int) $service['id'];
	$basket = exf_basket_save(
		array(
			'service' => (int) $service['id'],
			'slot'    => $service['booking'] ? $slot : 0,
			'addon'   => $addon,
			'consent' => $consent,
			// Details already given in this browser carry over, whatever the service.
			'user_id' => (int) ( $basket['user_id'] ?? 0 ),
			'ex_file' => (int) ( $basket['ex_file'] ?? 0 ),
			'order'   => $same ? (int) ( $basket['order'] ?? 0 ) : 0,
			'error'   => '',
		)
	);

	if ( exf_basket_user( $basket ) ) {
		$errors = exf_basket_checkout( $basket );
		return $errors; // Only returns on an error.
	}
	wp_safe_redirect( exf_page_url( 'register' ) );
	exit;
}

/**
 * Create the order for a sign-up whose check is clear, and send them to Stripe.
 *
 * @param array $basket Basket with user_id and ex_file.
 * @return array Errors (redirects on success).
 */
function exf_basket_checkout( array $basket ) {
	$service = exf_basket_service( $basket );
	$user_id = exf_basket_user( $basket );
	if ( ! $service || ! $user_id ) {
		return array( '_form' => __( 'Sorry, something went wrong. Please choose your service again.', 'exfiles' ) );
	}
	update_user_meta( $user_id, 'exf_intended_service', (string) $service['id'] );
	$result = exf_purchase_start(
		$user_id,
		(int) $basket['ex_file'],
		$service,
		(int) $basket['slot'],
		array(
			'addon'   => ! empty( $basket['addon'] ),
			'consent' => ! empty( $basket['consent'] ),
		)
	);
	if ( isset( $result['errors'] ) ) {
		return $result['errors'];
	}
	$basket['order'] = (int) $result['order'];
	$basket['error'] = '';
	exf_basket_save( $basket );
	exf_purchase_redirect( $result );
}

/**
 * Send the visitor back to the basket with a message (e.g. their time was taken
 * while they filled in the form).
 *
 * @param array $basket  Basket.
 * @param array $errors  Errors from exf_basket_checkout().
 */
function exf_basket_back_with_errors( array $basket, array $errors ) {
	$basket['error'] = (string) ( $errors['slot'] ?? $errors['_form'] ?? reset( $errors ) );
	if ( isset( $errors['slot'] ) ) {
		$basket['slot'] = 0;
	}
	exf_basket_save( $basket );
	wp_safe_redirect( add_query_arg( 'service', (int) $basket['service'], exf_page_url( 'buy' ) ) );
	exit;
}

/* -------------------------------------------------------------------------
 * After payment
 * ---------------------------------------------------------------------- */

/**
 * The client has paid (or a free service is confirmed): if their account was
 * waiting for payment, it now exists properly and they're asked to confirm their
 * email address. Called before the order confirmation emails go out.
 *
 * @param int $order_id Order.
 */
function exf_basket_activate_client( $order_id ) {
	$user_id = (int) get_post_meta( $order_id, 'client_id', true );
	if ( ! $user_id || EXF_STATUS_AWAITING !== exf_user_status( $user_id ) ) {
		return;
	}
	exf_set_user_status( $user_id, EXF_STATUS_PENDING );
	update_user_meta( $user_id, 'exf_paid_signup', time() );
	$ex_file = (int) get_post_meta( $order_id, 'ex_file_id', true );
	if ( $ex_file ) {
		exf_ex_file_log( $ex_file, 'Paid at checkout: account created, confirmation email sent' );
	}
	exf_send_verification( $user_id, 'verify_paid' );
}

/* -------------------------------------------------------------------------
 * Tidying up
 * ---------------------------------------------------------------------- */

add_action( 'exf_daily_cleanup', 'exf_cleanup_unpaid_signups' );

/**
 * Delete sign-ups who gave their details at checkout but never paid. Nothing is
 * added to the Conflict Register (see prospects.php).
 *
 * @return int Number deleted.
 */
function exf_cleanup_unpaid_signups() {
	$days = (int) exf_setting( 'unverified_retention_days', 7 );
	if ( $days < 1 || ! function_exists( 'exf_delete_prospect' ) ) {
		return 0;
	}
	$users = get_users(
		array(
			'meta_key'     => 'exf_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => EXF_STATUS_AWAITING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'date_query'   => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ),
			'fields'       => 'ID',
			'number'       => 200,
			'role__not_in' => array( 'administrator', 'ex_files_manager' ),
		)
	);
	$n = 0;
	foreach ( $users as $uid ) {
		/* translators: %d: days */
		$n += exf_delete_prospect( (int) $uid, sprintf( 'gave their details at checkout but did not pay within %d days', $days ) ) ? 1 : 0;
	}
	return $n;
}

/* -------------------------------------------------------------------------
 * Display
 * ---------------------------------------------------------------------- */

/**
 * Total for a basket, in pence.
 *
 * @param array $basket  Basket.
 * @param array $service Service.
 */
function exf_basket_total( array $basket, array $service ) {
	return (int) $service['pence'] + ( ! empty( $basket['addon'] ) && $service['addon'] ? (int) $service['addon']['pence'] : 0 );
}

/**
 * "Your basket" box, shown above the Create your Ex File form.
 *
 * @param array|null $basket Basket.
 */
function exf_basket_summary( $basket ) {
	$service = exf_basket_service( $basket );
	if ( ! $service ) {
		return;
	}
	$start = (int) $basket['slot'];
	?>
	<aside class="guide-card account-card basket-summary" aria-label="<?php esc_attr_e( 'Your basket', 'exfiles' ); ?>">
		<p class="eyebrow"><?php esc_html_e( 'Your basket', 'exfiles' ); ?></p>
		<p><strong><?php echo esc_html( $service['name'] ); ?></strong> — <?php echo esc_html( exf_service_price_label( $service ) ); ?></p>
		<?php if ( $start ) : ?>
			<p><?php echo esc_html( exf_booking_label( $start, $start + $service['minutes'] * MINUTE_IN_SECONDS ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $basket['addon'] ) && $service['addon'] ) : ?>
			<p><?php echo esc_html( $service['addon']['name'] ); ?> — <?php echo esc_html( exf_money( $service['addon']['pence'] ) ); ?></p>
			<p><strong><?php esc_html_e( 'Total', 'exfiles' ); ?></strong> — <?php echo esc_html( exf_money( exf_basket_total( $basket, $service ) ) ); ?></p>
		<?php endif; ?>
		<p class="field-note"><a href="<?php echo esc_url( add_query_arg( 'service', (int) $service['id'], exf_page_url( 'buy' ) ) ); ?>"><?php esc_html_e( 'Change', 'exfiles' ); ?></a>
			<?php if ( ! $service['free'] ) : ?>
				&nbsp;·&nbsp; <?php esc_html_e( "You'll pay securely with Stripe after this step.", 'exfiles' ); ?>
			<?php endif; ?>
		</p>
	</aside>
	<?php
}
