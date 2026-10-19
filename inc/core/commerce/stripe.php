<?php
/**
 * Stripe: Checkout Sessions and the webhook.
 *
 * Keys come from wp-config.php constants when defined (recommended on the live
 * site), otherwise from Ex Files → Settings → Payments:
 *
 *   define( 'EXF_STRIPE_SECRET_KEY', 'sk_live_…' );
 *   define( 'EXF_STRIPE_WEBHOOK_SECRET', 'whsec_…' );
 *
 * Webhook URL: https://<site>/wp-json/exfiles/v1/stripe-webhook
 * Events: checkout.session.completed, checkout.session.async_payment_succeeded,
 *         checkout.session.expired, charge.refunded
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether payments are in Stripe's test mode.
 */
function exf_stripe_test_mode() {
	if ( defined( 'EXF_STRIPE_SECRET_KEY' ) ) {
		return 0 === strpos( EXF_STRIPE_SECRET_KEY, 'sk_test_' );
	}
	return 'live' !== exf_setting( 'stripe_mode', 'test' );
}

/**
 * Secret key for the current mode.
 */
function exf_stripe_secret_key() {
	if ( defined( 'EXF_STRIPE_SECRET_KEY' ) ) {
		return (string) EXF_STRIPE_SECRET_KEY;
	}
	return trim( (string) exf_setting( exf_stripe_test_mode() ? 'stripe_test_secret_key' : 'stripe_live_secret_key', '' ) );
}

/**
 * Webhook signing secret for the current mode.
 */
function exf_stripe_webhook_secret() {
	if ( defined( 'EXF_STRIPE_WEBHOOK_SECRET' ) ) {
		return (string) EXF_STRIPE_WEBHOOK_SECRET;
	}
	return trim( (string) exf_setting( exf_stripe_test_mode() ? 'stripe_test_webhook_secret' : 'stripe_live_webhook_secret', '' ) );
}

/**
 * Whether Stripe is set up enough to take payments.
 */
function exf_stripe_ready() {
	return '' !== exf_stripe_secret_key();
}

/**
 * Call the Stripe API.
 *
 * @param string $method GET or POST.
 * @param string $path   e.g. "checkout/sessions".
 * @param array  $params Parameters (nested arrays become Stripe's bracket syntax).
 * @return array|WP_Error Decoded response.
 */
function exf_stripe_request( $method, $path, array $params = array() ) {
	$key = exf_stripe_secret_key();
	if ( ! $key ) {
		return new WP_Error( 'exf_stripe_key', __( 'Stripe is not set up.', 'exfiles' ) );
	}
	$url  = 'https://api.stripe.com/v1/' . ltrim( $path, '/' );
	$args = array(
		'method'  => $method,
		'timeout' => 20,
		'headers' => array(
			'Authorization'  => 'Bearer ' . $key,
			'Stripe-Version' => '2024-06-20',
		),
	);
	if ( 'GET' === $method ) {
		$url = $params ? $url . '?' . http_build_query( $params ) : $url;
	} else {
		$idempotency = (string) ( $params['_idempotency'] ?? wp_generate_uuid4() );
		unset( $params['_idempotency'] );
		$args['body']                       = http_build_query( $params );
		$args['headers']['Content-Type']    = 'application/x-www-form-urlencoded';
		$args['headers']['Idempotency-Key'] = $idempotency;
	}

	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	$code = wp_remote_retrieve_response_code( $res );
	if ( $code >= 400 || ! is_array( $body ) ) {
		$msg = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
		return new WP_Error( 'exf_stripe_api', $msg, array( 'status' => $code ) );
	}
	return $body;
}

/**
 * Create a Checkout Session for an order.
 *
 * @param int $order_id Order.
 * @return array|WP_Error Session (with "url").
 */
function exf_stripe_create_session( $order_id ) {
	$client = get_userdata( (int) get_post_meta( $order_id, 'client_id', true ) );
	$name   = (string) get_post_meta( $order_id, 'service_name', true );
	$start  = (int) get_post_meta( $order_id, 'booking_start', true );
	$return = exf_page_url( 'buy' );

	$params = array(
		'mode'                 => 'payment',
		'client_reference_id'  => (string) $order_id,
		'customer_email'       => $client ? $client->user_email : '',
		'line_items'           => array(
			array(
				'quantity'   => 1,
				'price_data' => array(
					'currency'     => 'gbp',
					'unit_amount'  => (int) get_post_meta( $order_id, 'amount_pence', true ),
					'product_data' => array(
						'name'        => $name,
						'description' => $start ? exf_booking_label( $start ) : get_the_title( (int) get_post_meta( $order_id, 'ex_file_id', true ) ),
					),
				),
			),
		),
		'metadata'             => array( 'order_id' => (string) $order_id ),
		'payment_intent_data'  => array(
			'metadata'      => array( 'order_id' => (string) $order_id ),
			'description'   => $name . ' (order ' . $order_id . ')',
			'receipt_email' => $client ? $client->user_email : '',
		),
		'success_url'          => add_query_arg( array( 'order' => $order_id ), $return ) . '&session_id={CHECKOUT_SESSION_ID}',
		'cancel_url'           => add_query_arg(
			array(
				'order'     => $order_id,
				'cancelled' => 1,
			),
			$return
		),
		'expires_at'           => time() + EXF_HOLD_MINUTES * MINUTE_IN_SECONDS + 60,
		'_idempotency'         => 'exf-order-' . $order_id . '-' . get_post_meta( $order_id, 'attempt', true ),
	);
	if ( ! $params['customer_email'] ) {
		unset( $params['customer_email'], $params['payment_intent_data']['receipt_email'] );
	}
	return exf_stripe_request( 'POST', 'checkout/sessions', $params );
}

/**
 * Check a webhook signature (Stripe-Signature header: t=…,v1=…).
 *
 * @param string $payload   Raw body.
 * @param string $header    Header value.
 * @param string $secret    Signing secret.
 * @param int    $tolerance Seconds.
 */
function exf_stripe_verify_signature( $payload, $header, $secret, $tolerance = 300 ) {
	if ( ! $secret || ! $header ) {
		return false;
	}
	$t  = 0;
	$v1 = array();
	foreach ( explode( ',', $header ) as $part ) {
		$kv = explode( '=', trim( $part ), 2 );
		if ( 2 !== count( $kv ) ) {
			continue;
		}
		if ( 't' === $kv[0] ) {
			$t = (int) $kv[1];
		} elseif ( 'v1' === $kv[0] ) {
			$v1[] = $kv[1];
		}
	}
	if ( ! $t || ! $v1 || abs( time() - $t ) > $tolerance ) {
		return false;
	}
	$expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
	foreach ( $v1 as $sig ) {
		if ( hash_equals( $expected, $sig ) ) {
			return true;
		}
	}
	return false;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'exfiles/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => 'exf_stripe_webhook',
				'permission_callback' => '__return_true', // Authenticated by the Stripe signature.
			)
		);
	}
);

/**
 * Handle a Stripe webhook.
 *
 * @param WP_REST_Request $request Request.
 */
function exf_stripe_webhook( WP_REST_Request $request ) {
	$payload = $request->get_body();
	if ( ! exf_stripe_verify_signature( $payload, (string) $request->get_header( 'stripe_signature' ), exf_stripe_webhook_secret() ) ) {
		return new WP_REST_Response( array( 'error' => 'bad signature' ), 400 );
	}
	$event = json_decode( $payload, true );
	$type  = $event['type'] ?? '';
	$obj   = $event['data']['object'] ?? array();

	switch ( $type ) {
		case 'checkout.session.completed':
		case 'checkout.session.async_payment_succeeded':
			$order_id = (int) ( $obj['metadata']['order_id'] ?? $obj['client_reference_id'] ?? 0 );
			if ( $order_id && 'paid' === ( $obj['payment_status'] ?? '' ) ) {
				exf_order_mark_paid( $order_id, $obj );
			}
			break;

		case 'checkout.session.expired':
			$order_id = (int) ( $obj['metadata']['order_id'] ?? 0 );
			if ( $order_id && 'pending' === exf_order_status( $order_id ) ) {
				exf_order_set_status( $order_id, 'cancelled', 'Payment page expired' );
			}
			break;

		case 'charge.refunded':
			$order_id = (int) ( $obj['metadata']['order_id'] ?? 0 );
			if ( ! $order_id && ! empty( $obj['payment_intent'] ) ) {
				$order_id = exf_order_by_payment_intent( (string) $obj['payment_intent'] );
			}
			if ( $order_id ) {
				$full = (int) ( $obj['amount_refunded'] ?? 0 ) >= (int) ( $obj['amount'] ?? 1 );
				exf_order_set_status( $order_id, $full ? 'refunded' : exf_order_status( $order_id ), sprintf( 'Refund of £%s recorded by Stripe', number_format( ( (int) ( $obj['amount_refunded'] ?? 0 ) ) / 100, 2 ) ) );
			}
			break;
	}
	return new WP_REST_Response( array( 'received' => true ), 200 );
}

/**
 * Link to a payment in the Stripe dashboard.
 *
 * @param string $payment_intent Payment intent ID.
 */
function exf_stripe_dashboard_url( $payment_intent ) {
	return 'https://dashboard.stripe.com/' . ( exf_stripe_test_mode() ? 'test/' : '' ) . 'payments/' . rawurlencode( $payment_intent );
}

// Show the webhook URL in the Payments settings.
add_filter(
	'acf/load_field/key=field_exf_set_stripe_help',
	function ( $field ) {
		$field['message'] = str_replace( '{webhook_url}', '<code>' . esc_html( rest_url( 'exfiles/v1/stripe-webhook' ) ) . '</code>', $field['message'] );
		return $field;
	}
);
