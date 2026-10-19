<?php
/**
 * "Open your Ex File": registration, email verification and resending the link.
 *
 * Registration creates the WordPress user and their first Ex File together, as
 * "pending_verification". Nothing is checked until the email link is clicked, so a
 * made-up email address can't be used to probe the conflict check.
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
	);
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
	if ( ! is_page_template( 'page-register.php' ) ) {
		return;
	}
	nocache_headers();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verification links are token-checked.
	if ( isset( $_GET['verify'], $_GET['uid'] ) ) {
		exf_handle_verify( absint( $_GET['uid'] ), sanitize_text_field( wp_unslash( $_GET['verify'] ) ) );
	}
	// phpcs:enable

	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		// Logged-in clients have nothing to do here.
		if ( is_user_logged_in() && exf_is_client( get_current_user_id() ) && empty( $_GET['result'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( exf_page_url( 'account' ) );
			exit;
		}
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
	$v['service'] = sanitize_key( $post['service'] ?? '' );

	$e = array();
	foreach ( exf_register_fields() as $name => $f ) {
		if ( $f[1] && empty( $v[ $name ] ) ) {
			/* translators: %s: field label */
			$e[ $name ] = 'terms' === $name ? __( 'Please accept the terms and privacy notice.', 'exfiles' ) : sprintf( __( 'Please enter %s.', 'exfiles' ), strtolower( $f[0] ) );
		}
	}

	if ( $v['email'] && ! is_email( $v['email'] ) ) {
		$e['email'] = __( 'Please enter a valid email address.', 'exfiles' );
	} elseif ( $v['email'] && email_exists( $v['email'] ) ) {
		$e['email'] = sprintf(
			/* translators: %s: login URL */
			__( 'There is already an account with this email address. <a href="%s">Log in</a> or reset your password.', 'exfiles' ),
			esc_url( wp_login_url( exf_page_url( 'account' ) ) )
		);
	}
	if ( $v['ex_email'] && ! is_email( $v['ex_email'] ) ) {
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

	// 5 registrations per hour per IP.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$limit = 'exf_reg_' . md5( $ip );
	if ( ! $e && (int) get_transient( $limit ) >= 5 ) {
		$e['_form'] = __( 'Too many attempts. Please try again later.', 'exfiles' );
	}

	if ( $e ) {
		unset( $v['password'], $v['password2'] );
		return array(
			'errors' => $e,
			'values' => $v,
		);
	}
	set_transient( $limit, (int) get_transient( $limit ) + 1, HOUR_IN_SECONDS );

	$user_id = exf_create_client( $v );
	if ( is_wp_error( $user_id ) ) {
		return array(
			'errors' => array( '_form' => $user_id->get_error_message() ),
			'values' => array_diff_key( $v, array_flip( array( 'password', 'password2' ) ) ),
		);
	}

	exf_send_verification( $user_id );
	wp_safe_redirect( add_query_arg( 'result', 'check-email', exf_page_url( 'register' ) ) );
	exit;
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
		'exf_status'           => EXF_STATUS_PENDING,
		'exf_title'            => $v['title'],
		'exf_prev_names'       => $v['prev_names'],
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
		'exf_marketing'        => $v['marketing'],
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
	foreach ( array( 'ex_first_name', 'ex_last_name', 'ex_prev_names', 'ex_dob', 'ex_dob_unknown', 'ex_email', 'ex_phone', 'ex_postcode', 'relationship', 'marriage_date', 'separation_date' ) as $k ) {
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
 * @param int $user_id User.
 */
function exf_send_verification( $user_id ) {
	$token = wp_generate_password( 32, false, false );
	update_user_meta( $user_id, 'exf_verify_hash', hash( 'sha256', $token ) );
	update_user_meta( $user_id, 'exf_verify_expires', time() + EXF_VERIFY_HOURS * HOUR_IN_SECONDS );

	$link = add_query_arg(
		array(
			'uid'    => $user_id,
			'verify' => $token,
		),
		exf_page_url( 'register' )
	);
	$user = get_userdata( $user_id );
	return exf_send_email(
		'verify',
		$user->user_email,
		array(
			'first_name' => $user->first_name,
			'link'       => $link,
		)
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
		wp_safe_redirect( wp_login_url( exf_page_url( 'account' ) ) );
		exit;
	}
	if ( ! $hash || ! hash_equals( $hash, hash( 'sha256', $token ) ) || $expires < time() ) {
		wp_safe_redirect( add_query_arg( 'result', 'link-expired', $register ) );
		exit;
	}

	delete_user_meta( $user_id, 'exf_verify_hash' );
	delete_user_meta( $user_id, 'exf_verify_expires' );
	update_user_meta( $user_id, 'exf_email_verified', time() );
	exf_set_user_status( $user_id, EXF_STATUS_REVIEW ); // Placeholder until the check sets it.

	$ex_file = exf_first_ex_file( $user_id );
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
		if ( $user && EXF_STATUS_PENDING === exf_user_status( $user->ID ) ) {
			exf_send_verification( $user->ID );
		}
	}
	wp_safe_redirect( add_query_arg( 'result', 'resent', exf_page_url( 'register' ) ) );
	exit;
}

// Daily: remove registrations that were never verified.
add_action( 'exf_daily_cleanup', 'exf_cleanup_unverified' );

/**
 * Delete unverified clients (and their Ex Files) after the retention period.
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
	foreach ( $users as $uid ) {
		foreach ( exf_client_ex_files( $uid ) as $ex_file ) {
			wp_delete_post( $ex_file, true );
		}
		wp_delete_user( $uid );
	}
	return count( $users );
}
