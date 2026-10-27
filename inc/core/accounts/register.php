<?php
/**
 * "Create your Ex File": registration, email verification and resending the link.
 *
 * Since October 2026 this is step 3 of the checkout (see commerce/basket.php): the
 * visitor has already chosen a service. Registration creates the WordPress user and
 * their first Ex File as "awaiting_payment" and runs the check straight away; if it's
 * clear they go on to pay, and the account is only created properly (and the
 * confirmation email sent) once the payment is confirmed. Nothing about the check is
 * shown on the page.
 *
 * Registrations from before the checkout (status "pending_verification", Ex File not
 * checked yet) still work as they did: the check runs when the email link is clicked.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_VERIFY_HOURS = 48;

/**
 * Registration fields: name => [ label, required, step ].
 * Steps: 1 your details, 2 your ex, 3 relationship, 4 account.
 */
function exf_register_fields() {
	return array(
		'title'           => array( __( 'Title', 'exfiles' ), false, 1 ),
		'first_name'      => array( __( 'First name', 'exfiles' ), true, 1 ),
		'last_name'       => array( __( 'Last name', 'exfiles' ), true, 1 ),
		'prev_names'      => array( __( 'Any previous or other names', 'exfiles' ), false, 1 ),
		'dob'             => array( __( 'Date of birth', 'exfiles' ), true, 1 ),
		'email'           => array( __( 'Email address', 'exfiles' ), true, 1 ),
		'phone'           => array( __( 'Phone number', 'exfiles' ), true, 1 ),
		'address_1'       => array( __( 'Address line 1', 'exfiles' ), true, 1 ),
		'address_2'       => array( __( 'Address line 2', 'exfiles' ), false, 1 ),
		'city'            => array( __( 'Town or city', 'exfiles' ), true, 1 ),
		'postcode'        => array( __( 'Postcode', 'exfiles' ), true, 1 ),
		'ex_first_name'   => array( __( 'Their first name', 'exfiles' ), true, 2 ),
		'ex_last_name'    => array( __( 'Their last name', 'exfiles' ), true, 2 ),
		'ex_prev_names'   => array( __( 'Any previous or other names', 'exfiles' ), false, 2 ),
		'ex_dob'          => array( __( 'Their date of birth', 'exfiles' ), false, 2 ),
		'ex_dob_unknown'  => array( __( "I don't know their date of birth", 'exfiles' ), false, 2 ),
		'ex_email'        => array( __( 'Their email address', 'exfiles' ), false, 2 ),
		'ex_phone'        => array( __( 'Their phone number', 'exfiles' ), false, 2 ),
		'ex_postcode'     => array( __( 'Their last known postcode', 'exfiles' ), false, 2 ),
		'relationship'    => array( __( 'Are you married or in a civil partnership with them?', 'exfiles' ), true, 3 ),
		'marriage_date'   => array( __( 'Date of marriage or civil partnership', 'exfiles' ), false, 3 ),
		'separation_date' => array( __( 'Date you separated (if you have)', 'exfiles' ), false, 3 ),
		'password'        => array( __( 'Choose a password', 'exfiles' ), true, 4 ),
		'password2'       => array( __( 'Confirm your password', 'exfiles' ), true, 4 ),
		'terms'           => array( __( 'Terms and privacy', 'exfiles' ), true, 4 ),
		'marketing'       => array( __( 'Marketing', 'exfiles' ), false, 4 ),
		// Only on the "panels" layout (page-register-panels.php).
		'middle_names'    => array( __( 'Middle name(s)', 'exfiles' ), false, 1 ),
		'ex_middle_names' => array( __( 'Their middle name(s)', 'exfiles' ), false, 2 ),
		'ex_address'      => array( __( 'Their address', 'exfiles' ), false, 2 ),
	);
}

/**
 * The registration page templates: the step-by-step form, and the alternative
 * "panels" layout (from the Wix design) for comparison.
 */
function exf_register_templates() {
	return array( 'page-register.php', 'page-register-panels.php' );
}

/**
 * Split "address and postcode" typed in one box into the address and a UK postcode.
 *
 * @param string $text Typed address.
 * @return array [ address, postcode ] (postcode '' if none found).
 */
function exf_split_address_postcode( $text ) {
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( preg_match( '/^(.*?)[,\s]*\b([A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2})$/i', $text, $m ) ) {
		$pc = strtoupper( preg_replace( '/\s+/', '', $m[2] ) );
		return array( trim( $m[1], " ,\t" ), substr( $pc, 0, -3 ) . ' ' . substr( $pc, -3 ) );
	}
	return array( trim( $text, " ,\t" ), '' );
}

/**
 * The "panels" layout asks for some things differently: address and postcode in one
 * box, and a blank ex date of birth means "don't know". Map them onto the usual fields.
 *
 * @param array $post Unslashed $_POST.
 * @return array [ $post, errors ]
 */
function exf_register_panels_map( array $post ) {
	$e = array();
	list( $address, $postcode ) = exf_split_address_postcode( $post['address_full'] ?? '' );
	$post['address_1'] = $address;
	$post['postcode']  = $postcode;
	$post['city']      = '';
	if ( '' === trim( (string) ( $post['address_full'] ?? '' ) ) ) {
		$e['address_full'] = __( 'Please enter your address and postcode.', 'exfiles' );
	} elseif ( '' === $postcode ) {
		$e['address_full'] = __( 'Please include your postcode at the end of your address.', 'exfiles' );
	} elseif ( '' === $address ) {
		$e['address_full'] = __( 'Please enter your address as well as your postcode.', 'exfiles' );
	}
	list( $ex_address, $ex_postcode ) = exf_split_address_postcode( $post['ex_address_full'] ?? '' );
	$post['ex_address']  = trim( (string) ( $post['ex_address_full'] ?? '' ) );
	$post['ex_postcode'] = $ex_postcode;
	if ( '' === trim( (string) ( $post['ex_dob'] ?? '' ) ) ) {
		$post['ex_dob_unknown'] = 1;
	}
	unset( $ex_address );
	return array( $post, $e );
}

/**
 * Relationship options.
 */
function exf_relationship_options() {
	return array(
		'married'           => __( "Yes, we're married", 'exfiles' ),
		'civil_partnership' => __( "Yes, we're in a civil partnership", 'exfiles' ),
		'not_married'       => __( "No, we're not married", 'exfiles' ),
	);
}

/**
 * Title options.
 */
function exf_title_options() {
	return array( '', 'Mr', 'Mrs', 'Ms', 'Miss', 'Mx', 'Dr' );
}

// Handle the form, verification links and resend requests on the registration page.
add_action( 'template_redirect', 'exf_register_route', 5 );

/**
 * Route registration-page requests.
 */
function exf_register_route() {
	if ( ! is_page_template( exf_register_templates() ) ) {
		return;
	}
	nocache_headers();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verification links are token-checked.
	if ( isset( $_GET['verify'], $_GET['uid'] ) ) {
		exf_handle_verify( absint( $_GET['uid'] ), sanitize_text_field( wp_unslash( $_GET['verify'] ) ) );
	}
	// phpcs:enable

	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Logged-in clients have nothing to do here.
		if ( is_user_logged_in() && exf_is_client( get_current_user_id() ) && empty( $_GET['result'] ) ) {
			wp_safe_redirect( exf_page_url( 'account' ) );
			exit;
		}
		// The form is the step after choosing a service: without one, choose first.
		if ( ! is_user_logged_in() && empty( $_GET['result'] ) ) {
			$basket = exf_basket_get();
			if ( isset( $_GET['service'] ) && exf_service( absint( $_GET['service'] ) ) ) {
				wp_safe_redirect( add_query_arg( 'service', absint( $_GET['service'] ), exf_page_url( 'buy' ) ) ); // Old links.
				exit;
			}
			if ( ! exf_basket_service( $basket ) ) {
				wp_safe_redirect( exf_page_url( 'choose_service' ) );
				exit;
			}
			if ( exf_basket_user( $basket ) ) {
				// Details already given in this browser: back to the basket to pay.
				wp_safe_redirect( add_query_arg( 'service', (int) $basket['service'], exf_page_url( 'buy' ) ) );
				exit;
			}
		}
		// phpcs:enable
		return;
	}

	if ( isset( $_POST['exf_resend'] ) ) {
		check_admin_referer( 'exf_resend' );
		exf_handle_resend( isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '' );
	}

	if ( isset( $_POST['exf_register'] ) ) {
		check_admin_referer( 'exf_register' );
		$result = exf_handle_register( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field.
		if ( is_array( $result ) ) {
			// Errors: the template shows them with the submitted values.
			$GLOBALS['exf_register_errors'] = $result['errors'];
			$GLOBALS['exf_register_values'] = $result['values'];
		}
	}
}

/**
 * Validate and create the account.
 *
 * @param array $post Unslashed $_POST.
 * @return array|void Errors and values, or redirects on success.
 */
function exf_handle_register( array $post ) {
	if ( ! empty( $post['company'] ) ) {
		wp_safe_redirect( add_query_arg( 'result', 'check-email', exf_page_url( 'register' ) ) );
		exit; // Bot.
	}

	$basket = exf_basket_get();
	if ( ! exf_basket_service( $basket ) ) {
		wp_safe_redirect( exf_page_url( 'choose_service' ) );
		exit;
	}

	$panels = isset( $post['layout'] ) && 'panels' === $post['layout'];
	$pre_e  = array();
	if ( $panels ) {
		list( $post, $pre_e ) = exf_register_panels_map( $post );
	}

	$v = array();
	foreach ( exf_register_fields() as $name => $f ) {
		$raw = $post[ $name ] ?? '';
		if ( in_array( $name, array( 'password', 'password2' ), true ) ) {
			$v[ $name ] = (string) $raw;
		} elseif ( in_array( $name, array( 'email', 'ex_email' ), true ) ) {
			$v[ $name ] = sanitize_email( $raw );
		} elseif ( in_array( $name, array( 'ex_dob_unknown', 'terms', 'marketing' ), true ) ) {
			$v[ $name ] = ! empty( $raw ) ? 1 : 0;
		} else {
			$v[ $name ] = sanitize_text_field( $raw );
		}
	}
	$v['service'] = (string) (int) $basket['service'];

	$e = $pre_e;
	foreach ( exf_register_fields() as $name => $f ) {
		if ( $panels && in_array( $name, array( 'address_1', 'city', 'postcode' ), true ) ) {
			continue; // Checked as one "address and postcode" box above.
		}
		if ( $f[1] && empty( $v[ $name ] ) ) {
			if ( 'terms' === $name ) {
				$e[ $name ] = __( 'Please accept the terms and privacy notice.', 'exfiles' );
			} elseif ( 'relationship' === $name ) {
				$e[ $name ] = __( 'Please choose an option.', 'exfiles' );
			} else {
				/* translators: %s: field label */
				$e[ $name ] = sprintf( __( 'Please enter %s.', 'exfiles' ), strtolower( $f[0] ) );
			}
		}
	}

	// sanitize_email() turns an invalid address into '', so check what was typed.
	if ( '' === $v['email'] && '' !== trim( (string) ( $post['email'] ?? '' ) ) ) {
		$v['email']     = sanitize_text_field( (string) $post['email'] );
		$e['email']     = __( 'Please enter a valid email address.', 'exfiles' );
	} elseif ( $v['email'] && ! is_email( $v['email'] ) ) {
		$e['email'] = __( 'Please enter a valid email address.', 'exfiles' );
	} elseif ( $v['email'] && email_exists( $v['email'] ) && ! exf_register_replace_unpaid( (int) email_exists( $v['email'] ) ) ) {
		$e['email'] = sprintf(
			/* translators: %s: login URL */
			__( 'There is already an account with this email address. <a href="%s">Log in</a> or reset your password.', 'exfiles' ),
			esc_url( wp_login_url( exf_page_url( 'account' ) ) )
		);
	}
	// sanitize_email() turns an invalid address into '', so check what was typed.
	if ( ( $v['ex_email'] && ! is_email( $v['ex_email'] ) ) || ( '' === $v['ex_email'] && '' !== trim( (string) ( $post['ex_email'] ?? '' ) ) ) ) {
		$v['ex_email'] = sanitize_text_field( (string) ( $post['ex_email'] ?? '' ) );
		$e['ex_email'] = __( 'Please enter a valid email address, or leave it blank.', 'exfiles' );
	}
	if ( $v['ex_email'] && strtolower( $v['ex_email'] ) === strtolower( $v['email'] ) ) {
		$e['ex_email'] = __( "Their email address can't be the same as yours.", 'exfiles' );
	}
	foreach ( array( 'dob', 'ex_dob', 'marriage_date', 'separation_date' ) as $date ) {
		if ( $v[ $date ] ) {
			$norm = exf_norm_dob( $v[ $date ] );
			if ( ! $norm || $norm > gmdate( 'Y-m-d' ) ) {
				$e[ $date ] = __( 'Please enter a valid date.', 'exfiles' );
			} else {
				$v[ $date ] = str_replace( '-', '', $norm ); // Stored as Ymd, like SCF date fields.
			}
		}
	}
	if ( ! $v['ex_dob'] && ! $v['ex_dob_unknown'] ) {
		$e['ex_dob'] = __( "Please enter their date of birth, or tick that you don't know it.", 'exfiles' );
	}
	if ( $v['relationship'] && ! isset( exf_relationship_options()[ $v['relationship'] ] ) ) {
		$e['relationship'] = __( 'Please choose an option.', 'exfiles' );
	}
	if ( ! in_array( $v['title'], exf_title_options(), true ) ) {
		$v['title'] = '';
	}
	if ( $v['password'] && strlen( $v['password'] ) < 10 ) {
		$e['password'] = __( 'Please use at least 10 characters.', 'exfiles' );
	} elseif ( $v['password'] !== $v['password2'] ) {
		$e['password2'] = __( "The passwords don't match.", 'exfiles' );
	}

	// Sign-ups per hour from one internet connection: enough for an office or a
	// household (or a demo), low enough to stop automated sign-ups.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$limit = 'exf_reg_' . md5( $ip );
	if ( ! $e && (int) get_transient( $limit ) >= (int) apply_filters( 'exf_register_hourly_limit', 20 ) ) {
		$e['_form'] = __( 'Too many attempts. Please try again later.', 'exfiles' );
	}

	if ( $e ) {
		unset( $v['password'], $v['password2'] );
		if ( $panels ) {
			$v['address_full']    = sanitize_text_field( (string) ( $post['address_full'] ?? '' ) );
			$v['ex_address_full'] = sanitize_text_field( (string) ( $post['ex_address_full'] ?? '' ) );
			$v['ex_dob']          = isset( $e['ex_dob'] ) ? $v['ex_dob'] : ( $v['ex_dob_unknown'] ? '' : $v['ex_dob'] );
		}
		return array(
			'errors' => $e,
			'values' => $v,
		);
	}
	set_transient( $limit, (int) get_transient( $limit ) + 1, HOUR_IN_SECONDS );

	// An unpaid sign-up from earlier with this address (exf_register_replace_unpaid()
	// said it can go): start again with the new details.
	$old_user = $v['email'] ? (int) email_exists( $v['email'] ) : 0;
	if ( $old_user && function_exists( 'exf_delete_prospect' ) ) {
		exf_delete_prospect( $old_user, 'started checkout again with the same email address' );
	}

	$user_id = exf_create_client( $v );
	if ( is_wp_error( $user_id ) ) {
		return array(
			'errors' => array( '_form' => $user_id->get_error_message() ),
			'values' => array_diff_key( $v, array_flip( array( 'password', 'password2' ) ) ),
		);
	}

	exf_register_after_create( $user_id, $basket );
}

/**
 * Whether an existing account with this email address is an unpaid checkout sign-up
 * that can be replaced (they're starting again, e.g. on another device).
 *
 * @param int $user_id Existing user.
 */
function exf_register_replace_unpaid( $user_id ) {
	if ( ! $user_id || EXF_STATUS_AWAITING !== exf_user_status( $user_id ) || ! function_exists( 'exf_is_deletable_prospect' ) || ! exf_is_deletable_prospect( $user_id ) ) {
		return false;
	}
	// A payment page from the earlier attempt may still be open: close it at Stripe
	// first, so it can't be paid after the account is replaced.
	foreach ( exf_client_orders( $user_id ) as $o ) {
		if ( 'pending' !== exf_order_status( $o ) ) {
			continue;
		}
		$sid = (string) get_post_meta( $o, 'stripe_session', true );
		if ( $sid && get_post_timestamp( $o ) > time() - 2 * HOUR_IN_SECONDS ) {
			$session = exf_stripe_request( 'GET', 'checkout/sessions/' . rawurlencode( $sid ) );
			if ( is_wp_error( $session ) || 'complete' === ( $session['status'] ?? '' ) ) {
				return false; // Paid (or we can't tell): keep it.
			}
			if ( 'open' === ( $session['status'] ?? '' ) ) {
				$expired = exf_stripe_request( 'POST', 'checkout/sessions/' . rawurlencode( $sid ) . '/expire' );
				if ( is_wp_error( $expired ) ) {
					return false;
				}
			}
		}
		exf_order_set_status( $o, 'cancelled', 'Client started checkout again' );
	}
	return true;
}

/**
 * After the details are saved: run the check, then on to payment (clear), "we're
 * checking a few details" (review) or "unable to help" (blocked).
 *
 * @param int   $user_id New client (awaiting_payment).
 * @param array $basket  Basket.
 */
function exf_register_after_create( $user_id, array $basket ) {
	$register = exf_page_url( 'register' );
	$ex_file  = exf_first_ex_file( $user_id );
	// Not exf_conflict_notify(): the account emails are for after payment.
	$outcome = $ex_file ? exf_conflict_run( $ex_file, false ) : 'review';
	$vars    = $ex_file ? exf_ex_file_email_vars( $ex_file ) : array();

	if ( 'clear' === $outcome ) {
		$basket['user_id'] = $user_id;
		$basket['ex_file'] = $ex_file;
		$basket            = exf_basket_save( $basket );
		$errors            = exf_basket_checkout( $basket ); // Redirects to Stripe.
		exf_basket_back_with_errors( $basket, $errors );
	}

	// Keep what they chose, so the link they're sent when cleared reopens their basket.
	update_user_meta(
		$user_id,
		'exf_saved_basket',
		array(
			'service' => (int) $basket['service'],
			'slot'    => (int) $basket['slot'],
			'addon'   => ! empty( $basket['addon'] ),
		)
	);
	exf_basket_clear();
	if ( 'blocked' === $outcome ) {
		exf_set_user_status( $user_id, EXF_STATUS_BLOCKED );
		$user = get_userdata( $user_id );
		exf_send_email( 'unable', $user->user_email, $vars );
		exf_send_email( 'admin_blocked', exf_notify_emails(), $vars );
		wp_safe_redirect( add_query_arg( 'result', 'unable', $register ) );
		exit;
	}

	// Review: nothing is charged, and they can't log in yet. No confirmation link is
	// sent until the solicitor decides: clearing it emails them a link that confirms
	// their email and takes them to pay; asking for more information emails a link
	// that confirms it and opens the reply form; blocking sends the usual
	// "unable to help" email.
	exf_set_user_status( $user_id, EXF_STATUS_PENDING );
	if ( $vars ) {
		exf_send_email( 'admin_review', exf_notify_emails(), $vars );
		exf_send_email( 'review_checkout', get_userdata( $user_id )->user_email, $vars );
	}
	wp_safe_redirect( add_query_arg( 'result', 'checking', $register ) );
	exit;
}

/**
 * Whether a sign-up who hasn't confirmed their email is waiting for the solicitor
 * (held for review at checkout, see exf_register_after_create()).
 *
 * @param int $user_id User.
 * @return string Ex File status (review|info_requested|blocked), or '' if not waiting.
 */
function exf_unconfirmed_awaiting_decision( $user_id ) {
	if ( EXF_STATUS_PENDING !== exf_user_status( $user_id ) || get_user_meta( $user_id, 'exf_email_verified', true ) ) {
		return '';
	}
	$first  = exf_first_ex_file( $user_id );
	$status = $first ? exf_ex_file_status( $first ) : '';
	return in_array( $status, array( 'review', 'info_requested', 'blocked' ), true ) ? $status : '';
}

/**
 * Create the user and their first Ex File.
 *
 * @param array $v Validated values.
 * @return int|WP_Error User ID.
 */
function exf_create_client( array $v ) {
	$login = sanitize_user( current( explode( '@', $v['email'] ) ), true );
	$login = $login ? $login : 'client';
	$base  = $login;
	for ( $i = 2; username_exists( $login ); $i++ ) {
		$login = $base . $i;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $v['email'],
			'user_pass'    => $v['password'],
			'first_name'   => $v['first_name'],
			'last_name'    => $v['last_name'],
			'display_name' => trim( $v['first_name'] . ' ' . $v['last_name'] ),
			'role'         => 'customer',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	$meta = array(
		'exf_status'           => EXF_STATUS_AWAITING,
		'exf_title'            => $v['title'],
		'exf_prev_names'       => $v['prev_names'],
		'exf_middle_names'     => $v['middle_names'] ?? '',
		'exf_dob'              => $v['dob'],
		'billing_first_name'   => $v['first_name'],
		'billing_last_name'    => $v['last_name'],
		'billing_email'        => $v['email'],
		'billing_phone'        => $v['phone'],
		'billing_address_1'    => $v['address_1'],
		'billing_address_2'    => $v['address_2'],
		'billing_city'         => $v['city'],
		'billing_postcode'     => strtoupper( $v['postcode'] ),
		'billing_country'      => 'GB',
		'exf_terms_at'         => time(),
		'exf_marketing'        => exf_marketing_optin() ? $v['marketing'] : 0,
		'exf_intended_service' => $v['service'],
	);
	foreach ( $meta as $k => $value ) {
		update_user_meta( $user_id, $k, $value );
	}

	exf_create_ex_file( $user_id, $v );
	return $user_id;
}

/**
 * Create an Ex File for a client.
 *
 * @param int   $user_id Client.
 * @param array $v       ex_* fields, relationship, marriage_date, separation_date.
 * @return int Ex File ID.
 */
function exf_create_ex_file( $user_id, array $v ) {
	$client = exf_client_details( $user_id );
	$id     = wp_insert_post(
		array(
			'post_type'   => 'ex_file',
			'post_status' => 'publish',
			'post_author' => $user_id,
			'post_title'  => trim( $client['first_name'] . ' ' . $client['last_name'] ) . ' / ' . trim( $v['ex_first_name'] . ' ' . $v['ex_last_name'] ),
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	foreach ( array( 'ex_first_name', 'ex_middle_names', 'ex_last_name', 'ex_prev_names', 'ex_address', 'ex_dob', 'ex_dob_unknown', 'ex_email', 'ex_phone', 'ex_postcode', 'relationship', 'marriage_date', 'separation_date' ) as $k ) {
		$value = $v[ $k ] ?? '';
		if ( 'ex_postcode' === $k ) {
			$value = strtoupper( $value );
		}
		update_post_meta( $id, $k, $value );
	}
	exf_ex_file_set_status( $id, 'pending' );
	exf_ex_file_log( $id, 'Ex File created' );
	return (int) $id;
}

/**
 * Email a fresh verification link.
 *
 * @param int    $user_id User.
 * @param string $key     Email: verify, or verify_paid after paying at checkout.
 */
function exf_send_verification( $user_id, $key = '' ) {
	if ( '' === $key ) {
		$key = get_user_meta( $user_id, 'exf_paid_signup', true ) ? 'verify_paid' : 'verify';
	}
	$user = get_userdata( $user_id );
	return exf_send_email(
		$key,
		$user->user_email,
		array(
			'first_name' => $user->first_name,
			'link'       => exf_new_verification_link( $user_id ),
		)
	);
}

/**
 * A fresh confirmation link (any earlier one stops working).
 *
 * @param int $user_id User.
 * @return string
 */
function exf_new_verification_link( $user_id ) {
	$token = wp_generate_password( 32, false, false );
	update_user_meta( $user_id, 'exf_verify_hash', hash( 'sha256', $token ) );
	update_user_meta( $user_id, 'exf_verify_expires', time() + EXF_VERIFY_HOURS * HOUR_IN_SECONDS );
	return add_query_arg(
		array(
			'uid'    => $user_id,
			'verify' => $token,
		),
		exf_page_url( 'register' )
	);
}

/**
 * Handle a verification link, run the conflict check and send the client on.
 *
 * @param int    $user_id User.
 * @param string $token   Token.
 */
function exf_handle_verify( $user_id, $token ) {
	$register = exf_page_url( 'register' );
	$hash     = (string) get_user_meta( $user_id, 'exf_verify_hash', true );
	$expires  = (int) get_user_meta( $user_id, 'exf_verify_expires', true );

	if ( get_user_meta( $user_id, 'exf_email_verified', true ) ) {
		// The link has been used already (e.g. they've opened the email again): log in,
		// then go where the link was for, such as the basket they're coming back to.
		wp_safe_redirect( wp_login_url( exf_verify_used_destination( $user_id ) ) );
		exit;
	}
	if ( ! $hash || ! hash_equals( $hash, hash( 'sha256', $token ) ) || $expires < time() ) {
		wp_safe_redirect( add_query_arg( 'result', 'link-expired', $register ) );
		exit;
	}

	delete_user_meta( $user_id, 'exf_verify_hash' );
	delete_user_meta( $user_id, 'exf_verify_expires' );
	update_user_meta( $user_id, 'exf_email_verified', time() );

	$ex_file = exf_first_ex_file( $user_id );
	if ( $ex_file && 'pending' !== exf_ex_file_status( $ex_file ) ) {
		// Checked at checkout: the account follows that result.
		exf_sync_user_status( $user_id );
		$status = exf_user_status( $user_id );
		if ( EXF_STATUS_BLOCKED === $status ) {
			wp_safe_redirect( add_query_arg( 'result', 'unable', $register ) );
			exit;
		}
		// A link from a "more information" or "all set" email goes where that email said.
		$then = (string) get_user_meta( $user_id, 'exf_verify_then', true );
		delete_user_meta( $user_id, 'exf_verify_then' );
		if ( ! $then && EXF_STATUS_REVIEW === $status && ! exf_client_has_service( $user_id ) ) {
			exf_send_email( 'review', get_userdata( $user_id )->user_email, exf_ex_file_email_vars( $ex_file ) + array( 'link' => exf_page_url( 'account' ) ) );
		}
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );
		if ( ! $then ) {
			$then = EXF_STATUS_ACTIVE === $status && ! exf_client_has_service( $user_id ) ? exf_after_clear_url( $user_id ) : exf_page_url( 'account' );
		}
		wp_safe_redirect( wp_validate_redirect( $then, exf_page_url( 'account' ) ) );
		exit;
	}

	// Registered before the checkout flow: check now, as before.
	exf_set_user_status( $user_id, EXF_STATUS_REVIEW ); // Placeholder until the check sets it.
	$outcome = $ex_file ? exf_conflict_run( $ex_file ) : 'review';

	if ( 'blocked' === $outcome ) {
		wp_logout();
		wp_safe_redirect( add_query_arg( 'result', 'unable', $register ) );
		exit;
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, false );
	wp_safe_redirect( 'clear' === $outcome ? exf_after_clear_url( $user_id ) : exf_page_url( 'account' ) );
	exit;
}

/**
 * Where a confirmation link that has already been used should take the client after
 * logging in: their saved basket if they were held for review at checkout and
 * haven't ordered yet, the reply form if the link was for "more information", or
 * My Ex File.
 *
 * @param int $user_id Client.
 */
function exf_verify_used_destination( $user_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks a page to show after logging in.
	$go    = isset( $_GET['go'] ) ? sanitize_key( wp_unslash( $_GET['go'] ) ) : '';
	$saved = get_user_meta( $user_id, 'exf_saved_basket', true );
	if ( is_array( $saved ) && ! empty( $saved['service'] ) && exf_service( (int) $saved['service'] ) ) {
		return add_query_arg( 'service', (int) $saved['service'], exf_page_url( 'buy' ) );
	}
	if ( 'reply' === $go && exf_first_ex_file( $user_id ) ) {
		return exf_portal_url( 'ex-files', exf_first_ex_file( $user_id ) );
	}
	return exf_page_url( 'account' );
}

/**
 * Resend a verification link. The response is the same whether or not the address
 * has an account, so the form can't be used to find out who's registered.
 *
 * @param string $email Email.
 */
function exf_handle_resend( $email ) {
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$limit = 'exf_resend_' . md5( $ip );
	$count = (int) get_transient( $limit );

	if ( $count < 5 && is_email( $email ) ) {
		set_transient( $limit, $count + 1, HOUR_IN_SECONDS );
		$user = get_user_by( 'email', $email );
		if ( $user && EXF_STATUS_PENDING === exf_user_status( $user->ID ) && ! exf_unconfirmed_awaiting_decision( $user->ID ) ) {
			exf_send_verification( $user->ID );
		}
	}
	wp_safe_redirect( add_query_arg( 'result', 'resent', exf_page_url( 'register' ) ) );
	exit;
}

// Daily: remove registrations that were never verified.
add_action( 'exf_daily_cleanup', 'exf_cleanup_unverified' );

/**
 * Delete unverified clients (and their Ex Files) after the retention period. Clients
 * who paid at checkout but haven't confirmed their email are kept.
 *
 * @return int Number deleted.
 */
function exf_cleanup_unverified() {
	$days = (int) exf_setting( 'unverified_retention_days', 7 );
	if ( $days < 1 ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';

	$users = get_users(
		array(
			'meta_key'       => 'exf_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => EXF_STATUS_PENDING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ),
			'fields'         => 'ID',
			'number'         => 200,
			'role__not_in'   => array( 'administrator', 'ex_files_manager' ),
		)
	);
	$n = 0;
	foreach ( $users as $uid ) {
		if ( exf_client_has_service( $uid ) ) {
			continue; // Paid at checkout but hasn't clicked the link yet: keep.
		}
		if ( in_array( exf_unconfirmed_awaiting_decision( $uid ), array( 'review', 'info_requested' ), true ) ) {
			continue; // Waiting for the solicitor: left for her to decide.
		}
		$decided = (int) get_post_meta( exf_first_ex_file( $uid ), 'conflict_reviewed_at', true );
		if ( $decided > time() - $days * DAY_IN_SECONDS ) {
			continue; // Cleared recently: give them the full period to use the link.
		}
		foreach ( exf_client_ex_files( $uid ) as $ex_file ) {
			wp_delete_post( $ex_file, true );
		}
		wp_delete_user( $uid );
		++$n;
	}
	return $n;
}
