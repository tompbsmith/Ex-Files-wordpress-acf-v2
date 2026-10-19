<?php
/**
 * Account details in the portal: contact details, email address and password.
 *
 * Name and date of birth can't be changed here: they're part of the conflict check.
 * A new email address only takes effect once the client clicks a link sent to it.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_EMAIL_CHANGE_HOURS = 24;

/**
 * Save phone, address and marketing choice.
 *
 * @param int   $user_id Client.
 * @param array $post    Posted data (unslashed).
 * @return array Errors, or redirects on success.
 */
function exf_handle_details_update( $user_id, array $post ) {
	$phone    = sanitize_text_field( $post['phone'] ?? '' );
	$postcode = strtoupper( sanitize_text_field( $post['postcode'] ?? '' ) );
	$errors   = array();

	if ( '' === $phone ) {
		$errors['phone'] = __( 'Please enter a phone number.', 'exfiles' );
	} elseif ( strlen( exf_norm_phone( $phone ) ) < 10 ) {
		$errors['phone'] = __( 'Please check your phone number.', 'exfiles' );
	}
	if ( '' === trim( (string) ( $post['address_1'] ?? '' ) ) ) {
		$errors['address_1'] = __( 'Please enter your address.', 'exfiles' );
	}
	if ( '' === trim( (string) ( $post['city'] ?? '' ) ) ) {
		$errors['city'] = __( 'Please enter your town or city.', 'exfiles' );
	}
	if ( '' === $postcode ) {
		$errors['postcode'] = __( 'Please enter your postcode.', 'exfiles' );
	} elseif ( ! preg_match( '/^[A-Z]{1,2}[0-9][0-9A-Z]?\s*[0-9][A-Z]{2}$/', $postcode ) ) {
		$errors['postcode'] = __( 'Please check your postcode.', 'exfiles' );
	}
	if ( $errors ) {
		return $errors;
	}

	$fields = array(
		'billing_phone'     => $phone,
		'billing_address_1' => sanitize_text_field( $post['address_1'] ?? '' ),
		'billing_address_2' => sanitize_text_field( $post['address_2'] ?? '' ),
		'billing_city'      => sanitize_text_field( $post['city'] ?? '' ),
		'billing_postcode'  => $postcode,
		'exf_marketing'     => empty( $post['marketing'] ) ? 0 : 1,
	);
	$changed = array();
	foreach ( $fields as $k => $v ) {
		if ( (string) get_user_meta( $user_id, $k, true ) !== (string) $v ) {
			$changed[] = $k;
			update_user_meta( $user_id, $k, $v );
		}
	}
	if ( in_array( 'exf_marketing', $changed, true ) ) {
		update_user_meta( $user_id, 'exf_marketing_changed', time() );
	}
	if ( $changed ) {
		$first = exf_first_ex_file( $user_id );
		if ( $first ) {
			exf_ex_file_log( $first, 'Client updated their details: ' . implode( ', ', str_replace( 'billing_', '', $changed ) ) );
		}
	}
	wp_safe_redirect( add_query_arg( 'saved', 'details', exf_portal_url( 'account-details' ) ) );
	exit;
}

/**
 * Start an email change: send a confirmation link to the new address.
 *
 * @param int   $user_id Client.
 * @param array $post    Posted data (unslashed).
 * @return array Errors, or redirects on success.
 */
function exf_handle_email_change( $user_id, array $post ) {
	$user  = get_userdata( $user_id );
	$email = sanitize_email( $post['new_email'] ?? '' );
	$pass  = (string) ( $post['current_password_email'] ?? '' );

	if ( ! is_email( $email ) ) {
		return array( 'new_email' => __( 'Please enter a valid email address.', 'exfiles' ) );
	}
	if ( strtolower( $email ) === strtolower( $user->user_email ) ) {
		return array( 'new_email' => __( "That's already your email address.", 'exfiles' ) );
	}
	if ( ! wp_check_password( $pass, $user->user_pass, $user_id ) ) {
		return array( 'current_password_email' => __( 'That password isn’t right.', 'exfiles' ) );
	}
	$limit = 'exf_email_change_' . $user_id;
	if ( (int) get_transient( $limit ) >= 5 ) {
		return array( 'new_email' => __( 'Please wait a little before trying again.', 'exfiles' ) );
	}
	set_transient( $limit, (int) get_transient( $limit ) + 1, HOUR_IN_SECONDS );

	// Don't reveal whether the address belongs to someone else: just never send the link.
	if ( ! email_exists( $email ) ) {
		$token = wp_generate_password( 32, false, false );
		update_user_meta(
			$user_id,
			'exf_new_email',
			array(
				'email'   => $email,
				'hash'    => hash( 'sha256', $token ),
				'expires' => time() + EXF_EMAIL_CHANGE_HOURS * HOUR_IN_SECONDS,
			)
		);
		exf_send_email(
			'email_change',
			$email,
			array(
				'first_name' => $user->first_name,
				'new_email'  => $email,
				'link'       => add_query_arg(
					array(
						'confirm_email' => $token,
						'uid'           => $user_id,
					),
					exf_portal_url( 'account-details' )
				),
			)
		);
	}
	wp_safe_redirect( add_query_arg( 'saved', 'email-sent', exf_portal_url( 'account-details' ) ) );
	exit;
}

/**
 * Confirm an email change from the link.
 *
 * @param int    $user_id Logged-in client.
 * @param string $token   Token.
 */
function exf_handle_email_confirm( $user_id, $token ) {
	$pending = get_user_meta( $user_id, 'exf_new_email', true );
	$url     = exf_portal_url( 'account-details' );
	if ( ! is_array( $pending ) || empty( $pending['hash'] ) || ! hash_equals( $pending['hash'], hash( 'sha256', $token ) ) || (int) $pending['expires'] < time() ) {
		wp_safe_redirect( add_query_arg( 'saved', 'email-expired', $url ) );
		exit;
	}
	delete_user_meta( $user_id, 'exf_new_email' );
	if ( email_exists( $pending['email'] ) ) {
		wp_safe_redirect( add_query_arg( 'saved', 'email-expired', $url ) );
		exit;
	}
	$user = get_userdata( $user_id );
	$old  = $user->user_email;
	$res  = wp_update_user(
		array(
			'ID'         => $user_id,
			'user_email' => $pending['email'],
		)
	);
	if ( is_wp_error( $res ) ) {
		wp_safe_redirect( add_query_arg( 'saved', 'email-expired', $url ) );
		exit;
	}
	update_user_meta( $user_id, 'billing_email', $pending['email'] );

	exf_send_email(
		'email_changed',
		$old,
		array(
			'first_name' => $user->first_name,
			'new_email'  => $pending['email'],
		)
	);
	$first = exf_first_ex_file( $user_id );
	if ( $first ) {
		exf_ex_file_log( $first, 'Client changed their email address from ' . $old . ' to ' . $pending['email'] );
	}
	wp_safe_redirect( add_query_arg( 'saved', 'email', $url ) );
	exit;
}

// WordPress's own "email changed" notice would duplicate ours.
add_filter(
	'send_email_change_email',
	function ( $send, $user ) {
		return exf_is_client( (int) ( $user['ID'] ?? 0 ) ) ? false : $send;
	},
	10,
	2
);

/**
 * Change password.
 *
 * @param int   $user_id Client.
 * @param array $post    Posted data (unslashed).
 * @return array Errors, or redirects on success.
 */
function exf_handle_password_change( $user_id, array $post ) {
	$user    = get_userdata( $user_id );
	$current = (string) ( $post['current_password'] ?? '' );
	$new     = (string) ( $post['new_password'] ?? '' );
	$confirm = (string) ( $post['new_password_confirm'] ?? '' );

	if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
		return array( 'current_password' => __( 'That password isn’t right.', 'exfiles' ) );
	}
	$problem = exf_password_problem( $new );
	if ( $problem ) {
		return array( 'new_password' => $problem );
	}
	if ( $new !== $confirm ) {
		return array( 'new_password_confirm' => __( "The passwords don't match.", 'exfiles' ) );
	}
	wp_set_password( $new, $user_id );
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, false );
	wp_safe_redirect( add_query_arg( 'saved', 'password', exf_portal_url( 'account-details' ) ) );
	exit;
}

/**
 * What's wrong with a new password, or '' if it's fine. Same rule as registration.
 *
 * @param string $password Password.
 */
function exf_password_problem( $password ) {
	if ( strlen( $password ) < 10 ) {
		return __( 'Please use at least 10 characters.', 'exfiles' );
	}
	return '';
}
