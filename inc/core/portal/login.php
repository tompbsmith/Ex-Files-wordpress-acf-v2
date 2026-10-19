<?php
/**
 * Branded log in, forgotten password and reset password, on the "Log in" page
 * (page-login.php). wp-login.php still works for staff, but every link the site
 * generates points here.
 *
 *   /log-in/                               Log in
 *   /log-in/?action=lostpassword           Ask for a reset link
 *   /log-in/?action=rp&key=…&login=…       Choose a new password
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_LOGIN_FAIL_LIMIT = 10;

/**
 * State for the login template: [ view, errors, notice, values ].
 */
function exf_login_state( $set = null ) {
	static $state = array(
		'view'   => 'login',
		'errors' => array(),
		'notice' => '',
		'values' => array(),
	);
	if ( is_array( $set ) ) {
		$state = array_merge( $state, $set );
	}
	return $state;
}

/**
 * Where to go after logging in, kept on this site.
 *
 * @param string $requested Requested URL.
 */
function exf_login_safe_redirect( $requested ) {
	$requested = (string) $requested;
	return $requested ? wp_validate_redirect( $requested, exf_page_url( 'account' ) ) : exf_page_url( 'account' );
}

/**
 * Rate-limit key for the visitor's IP.
 *
 * @param string $what What is being limited.
 */
function exf_login_limit_key( $what ) {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return 'exf_' . $what . '_' . md5( $ip );
}

add_action(
	'template_redirect',
	function () {
		if ( ! is_page_template( 'page-login.php' ) ) {
			return;
		}
		nocache_headers();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action   = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		// phpcs:enable
		$is_post  = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );

		if ( 'login' === $action && is_user_logged_in() && ! $is_post ) {
			wp_safe_redirect( exf_login_safe_redirect( $redirect ) );
			exit;
		}

		if ( 'lostpassword' === $action ) {
			exf_login_state( array( 'view' => 'lostpassword' ) );
			if ( $is_post ) {
				check_admin_referer( 'exf_lostpassword' );
				exf_handle_lost_password( sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) ) );
			}
			return;
		}

		if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
			// phpcs:disable WordPress.Security.NonceVerification
			$key   = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : '';
			$login = isset( $_REQUEST['login'] ) ? sanitize_user( wp_unslash( $_REQUEST['login'] ) ) : '';
			// phpcs:enable
			$user = check_password_reset_key( $key, $login );
			if ( is_wp_error( $user ) ) {
				exf_login_state(
					array(
						'view'   => 'lostpassword',
						'errors' => array( '_form' => __( 'That link has expired or has already been used. Please ask for a new one.', 'exfiles' ) ),
					)
				);
				return;
			}
			exf_login_state(
				array(
					'view'   => 'resetpass',
					'values' => array(
						'key'   => $key,
						'login' => $login,
					),
				)
			);
			if ( $is_post ) {
				check_admin_referer( 'exf_resetpass' );
				$pass    = (string) wp_unslash( $_POST['new_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords aren't sanitised.
				$confirm = (string) wp_unslash( $_POST['new_password_confirm'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$problem = exf_password_problem( $pass );
				if ( ! $problem && $pass !== $confirm ) {
					$problem = __( "The passwords don't match.", 'exfiles' );
				}
				if ( $problem ) {
					exf_login_state( array( 'errors' => array( 'new_password' => $problem ) ) );
					return;
				}
				reset_password( $user, $pass );
				wp_safe_redirect( add_query_arg( 'done', 'reset', exf_page_url( 'login' ) ) );
				exit;
			}
			return;
		}

		// Log in.
		if ( $is_post ) {
			check_admin_referer( 'exf_login' );
			$limit = exf_login_limit_key( 'loginfail' );
			$fails = (int) get_transient( $limit );
			$email = sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) );
			exf_login_state( array( 'values' => array( 'log' => $email ) ) );
			if ( $fails >= EXF_LOGIN_FAIL_LIMIT ) {
				exf_login_state( array( 'errors' => array( '_form' => __( 'Too many attempts. Please wait 15 minutes, or reset your password.', 'exfiles' ) ) ) );
				return;
			}
			$user = wp_signon(
				array(
					'user_login'    => $email,
					'user_password' => (string) wp_unslash( $_POST['pwd'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'remember'      => ! empty( $_POST['rememberme'] ),
				),
				is_ssl()
			);
			if ( is_wp_error( $user ) ) {
				set_transient( $limit, $fails + 1, 15 * MINUTE_IN_SECONDS );
				$own = array( 'exf_unverified', 'exf_blocked' );
				$msg = in_array( $user->get_error_code(), $own, true )
					? $user->get_error_message()
					: __( "That email address and password don't match. Please try again, or reset your password.", 'exfiles' );
				exf_login_state( array( 'errors' => array( '_form' => $msg ) ) );
				return;
			}
			delete_transient( $limit );
			$to = apply_filters( 'login_redirect', exf_login_safe_redirect( $redirect ), $redirect, $user );
			wp_safe_redirect( $to ? $to : exf_page_url( 'account' ) );
			exit;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$done    = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		$notices = array(
			'reset'    => __( 'Your password has been changed. You can now log in.', 'exfiles' ),
			'loggedout' => __( "You've logged out.", 'exfiles' ),
		);
		// phpcs:enable
		if ( isset( $notices[ $done ] ) ) {
			exf_login_state( array( 'notice' => $notices[ $done ] ) );
		}
	},
	5
);

/**
 * Send a reset link. The reply is the same whether or not the account exists.
 *
 * @param string $login Email or username.
 */
function exf_handle_lost_password( $login ) {
	$limit = exf_login_limit_key( 'lostpw' );
	$count = (int) get_transient( $limit );
	if ( $count < 5 && '' !== $login ) {
		set_transient( $limit, $count + 1, HOUR_IN_SECONDS );
		$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
		if ( $user && EXF_STATUS_BLOCKED !== exf_user_status( $user->ID ) ) {
			retrieve_password( $user->user_login );
		}
	}
	exf_login_state(
		array(
			'view'   => 'lostpassword',
			'notice' => __( "If there's an account for that address, we've emailed a link to reset your password. It may take a few minutes to arrive, so check your junk folder too.", 'exfiles' ),
		)
	);
}

/**
 * Link to choose a new password on our page.
 *
 * @param string $key   Reset key.
 * @param string $login User login.
 */
function exf_reset_password_url( $key, $login ) {
	return add_query_arg(
		array(
			'action' => 'rp',
			'key'    => $key,
			'login'  => rawurlencode( $login ),
		),
		exf_page_url( 'login' )
	);
}

// The reset email: our wording, our link.
add_filter(
	'retrieve_password_notification_email',
	function ( $email, $key, $login, $user ) {
		if ( ! exf_page_id( 'login' ) ) {
			return $email;
		}
		$rendered = exf_email_render(
			'password_reset',
			array(
				'first_name' => $user->first_name ? $user->first_name : $user->display_name,
				'link'       => exf_reset_password_url( $key, $login ),
			)
		);
		if ( $rendered ) {
			$email['subject'] = $rendered[0];
			$email['message'] = $rendered[1];
		}
		return $email;
	},
	10,
	4
);

// Every login link goes to our page.
add_filter(
	'login_url',
	function ( $url, $redirect ) {
		if ( is_admin() && ! wp_doing_ajax() && isset( $_GET['interim-login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $url;
		}
		$page = exf_page_id( 'login' ) ? exf_page_url( 'login' ) : '';
		if ( ! $page ) {
			return $url;
		}
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $page ) : $page;
	},
	10,
	2
);

add_filter(
	'lostpassword_url',
	function ( $url ) {
		return exf_page_id( 'login' ) ? add_query_arg( 'action', 'lostpassword', exf_page_url( 'login' ) ) : $url;
	}
);

// After logging out from the site, land on the login page with a message.
add_filter(
	'logout_redirect',
	function ( $redirect, $requested ) {
		if ( ! $requested && exf_page_id( 'login' ) ) {
			return add_query_arg( 'done', 'loggedout', exf_page_url( 'login' ) );
		}
		return $redirect;
	},
	10,
	2
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page_template( 'page-login.php' ) || is_page_template( 'page-account.php' ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);
