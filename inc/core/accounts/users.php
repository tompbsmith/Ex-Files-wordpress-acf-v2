<?php
/**
 * Client accounts: role, status, and keeping clients out of wp-admin.
 *
 * Clients use the "customer" role, the same one WooCommerce uses, so nothing
 * changes when WooCommerce is added in the next phase. Contact details use
 * WooCommerce's billing_* meta keys so checkout will be pre-filled.
 *
 * Account status (user meta exf_status):
 *   pending_verification  registered, email not confirmed yet (can't log in)
 *   active                conflict check clear
 *   review                possible conflict, waiting for the solicitor (can log in, can't buy)
 *   blocked               conflict found (can't log in)
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_STATUS_PENDING = 'pending_verification';
const EXF_STATUS_ACTIVE  = 'active';
const EXF_STATUS_REVIEW  = 'review';
const EXF_STATUS_BLOCKED = 'blocked';

add_action(
	'init',
	function () {
		// WooCommerce creates this role on install; create it now if it's not there yet.
		if ( ! get_role( 'customer' ) ) {
			add_role( 'customer', __( 'Customer', 'exfiles' ), array( 'read' => true ) );
		}
	},
	5
);

/**
 * Account status of a user.
 *
 * @param int $user_id User ID.
 */
function exf_user_status( $user_id ) {
	return (string) get_user_meta( $user_id, 'exf_status', true );
}

/**
 * Set a user's account status.
 *
 * @param int    $user_id User ID.
 * @param string $status  Status.
 */
function exf_set_user_status( $user_id, $status ) {
	update_user_meta( $user_id, 'exf_status', $status );
}

/**
 * Whether a user is an Ex Files client (has gone through registration).
 *
 * @param int $user_id User ID.
 */
function exf_is_client( $user_id ) {
	return '' !== exf_user_status( $user_id );
}

/**
 * Human label for a status.
 *
 * @param string $status Status.
 */
function exf_status_label( $status ) {
	$labels = array(
		EXF_STATUS_PENDING => __( 'Email not verified', 'exfiles' ),
		EXF_STATUS_ACTIVE  => __( 'Active', 'exfiles' ),
		EXF_STATUS_REVIEW  => __( 'Under review', 'exfiles' ),
		EXF_STATUS_BLOCKED => __( 'Blocked', 'exfiles' ),
	);
	return $labels[ $status ] ?? '—';
}

/**
 * The client's details as one array (used by the conflict check and emails).
 *
 * @param int $user_id User ID.
 */
function exf_client_details( $user_id ) {
	$user = get_userdata( $user_id );
	return array(
		'first_name' => $user ? $user->first_name : '',
		'last_name'  => $user ? $user->last_name : '',
		'prev_names' => (string) get_user_meta( $user_id, 'exf_prev_names', true ),
		'dob'        => (string) get_user_meta( $user_id, 'exf_dob', true ),
		'email'      => $user ? $user->user_email : '',
		'phone'      => (string) get_user_meta( $user_id, 'billing_phone', true ),
		'postcode'   => (string) get_user_meta( $user_id, 'billing_postcode', true ),
	);
}

// Unverified and blocked clients can't log in. Blocked clients get the same generic wording as at registration.
add_filter(
	'wp_authenticate_user',
	function ( $user ) {
		if ( ! $user instanceof WP_User ) {
			return $user;
		}
		$status = exf_user_status( $user->ID );
		if ( EXF_STATUS_PENDING === $status ) {
			return new WP_Error(
				'exf_unverified',
				sprintf(
					/* translators: %s: link to resend the email */
					__( 'Please confirm your email address first, using the link we emailed you. <a href="%s">Send the link again</a>.', 'exfiles' ),
					esc_url( add_query_arg( 'result', 'check-email', exf_page_url( 'register' ) ) )
				)
			);
		}
		if ( EXF_STATUS_BLOCKED === $status ) {
			return new WP_Error( 'exf_blocked', __( "We're unable to open an account for you. We can't give details, as it can be for a number of reasons.", 'exfiles' ) );
		}
		return $user;
	},
	20
);

// After logging in, clients go to their account page, not wp-admin.
add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && exf_is_client( $user->ID ) && ! user_can( $user, EXF_CAP ) ) {
			$wants_admin = ! $requested || false !== strpos( $requested, '/wp-admin' );
			return $wants_admin ? exf_page_url( 'account' ) : $redirect;
		}
		return $redirect;
	},
	20,
	3
);

/**
 * Whether the current user is a client with no back-office access.
 */
function exf_current_user_is_plain_client() {
	return is_user_logged_in() && exf_is_client( get_current_user_id() ) && ! current_user_can( EXF_CAP ) && ! current_user_can( 'edit_posts' );
}

// Keep clients out of wp-admin (admin-ajax and admin-post still work for forms).
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( 'admin-post.php' === $script ) {
			return;
		}
		if ( exf_current_user_is_plain_client() ) {
			wp_safe_redirect( exf_page_url( 'account' ) );
			exit;
		}
	}
);

add_filter(
	'show_admin_bar',
	function ( $show ) {
		return exf_current_user_is_plain_client() ? false : $show;
	}
);

// Branded login screen.
add_action(
	'login_enqueue_scripts',
	function () {
		$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' ) : EXF_URI . '/assets/img/logo.png';
		?>
		<style>
			body.login{background:#f1e9de;font-family:'Jost',sans-serif;}
			#login h1 a{background-image:url(<?php echo esc_url( $logo ); ?>);background-size:contain;width:220px;height:70px;}
			.login form{border:1px solid rgba(49,25,15,.15);box-shadow:0 18px 40px rgba(49,25,15,.08);border-radius:12px;}
			.wp-core-ui .button-primary{background:#5a4e38;border-color:#5a4e38;}
			.wp-core-ui .button-primary:hover{background:#3f331f;border-color:#3f331f;}
			.login #nav a,.login #backtoblog a{color:#31190f;}
		</style>
		<?php
	}
);
add_filter( 'login_headerurl', fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => exf_site_name() );

// Registration link on the login screen points to our registration, not WordPress's.
add_filter(
	'register_url',
	function () {
		return exf_page_url( 'register' );
	}
);

/*
 * WordPress emails the site admin every time anyone resets their password.
 * For clients that's just noise in the firm's inbox, so only keep it for staff.
 */
add_action(
	'after_password_reset',
	function ( $user ) {
		if ( $user instanceof WP_User && ! user_can( $user, EXF_CAP ) ) {
			remove_action( 'after_password_reset', 'wp_password_change_notification' );
		}
	},
	0
);
