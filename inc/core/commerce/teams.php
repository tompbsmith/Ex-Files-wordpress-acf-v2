<?php
/**
 * A Microsoft Teams meeting for each appointment (October 2026).
 *
 * The solicitor keeps her diary in Google Calendar but holds appointments on
 * Teams. Once Microsoft 365 is connected (Ex Files → Settings → Microsoft Teams),
 * every appointment that's paid for or confirmed gets its own Teams meeting, and its
 * link goes in the confirmation email, the calendar invite, the reminder and the
 * client's Appointments page. Moving the appointment moves the meeting; cancelling it
 * deletes the meeting. Nothing is added to her Outlook or Teams calendar: the meeting
 * is "standalone" (Microsoft Graph onlineMeetings), and the calendar invite the site
 * already sends puts it in her Google Calendar.
 *
 * If Teams isn't connected, or Microsoft returns an error, the fixed meeting link in
 * Settings → Bookings is used instead and the problem is shown in the admin.
 *
 * Setup: an app registration in Microsoft Entra (Azure) with the redirect URI shown
 * on the settings tab and the delegated permissions OnlineMeetings.ReadWrite,
 * User.Read and offline_access. Client ID, secret and tenant go in the settings or,
 * better, in wp-config.php as EXF_MS_CLIENT_ID, EXF_MS_CLIENT_SECRET, EXF_MS_TENANT.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_TEAMS_OPTION = 'exf_teams';
const EXF_TEAMS_SCOPE  = 'offline_access User.Read OnlineMeetings.ReadWrite';
const EXF_GRAPH        = 'https://graph.microsoft.com/v1.0';

/* -------------------------------------------------------------------------
 * Connection
 * ---------------------------------------------------------------------- */

/**
 * App registration details.
 */
function exf_teams_client() {
	$tenant = defined( 'EXF_MS_TENANT' ) ? EXF_MS_TENANT : trim( (string) exf_setting( 'teams_tenant', '' ) );
	return array(
		'id'     => defined( 'EXF_MS_CLIENT_ID' ) ? EXF_MS_CLIENT_ID : trim( (string) exf_setting( 'teams_client_id', '' ) ),
		'secret' => defined( 'EXF_MS_CLIENT_SECRET' ) ? EXF_MS_CLIENT_SECRET : trim( (string) exf_setting( 'teams_client_secret', '' ) ),
		// A tenant ID or domain, or "organizations" for any work or school account.
		'tenant' => preg_match( '/^[A-Za-z0-9.\-]+$/', $tenant ) ? $tenant : 'organizations',
	);
}

/**
 * Stored connection: refresh_token, access_token, expires, email, name, problem.
 */
function exf_teams_state() {
	$s = get_option( EXF_TEAMS_OPTION );
	return is_array( $s ) ? $s : array();
}

/**
 * Update the stored connection.
 *
 * @param array $changes Keys to set (null deletes).
 */
function exf_teams_save( array $changes ) {
	$s = array_filter( array_merge( exf_teams_state(), $changes ), fn( $v ) => null !== $v );
	update_option( EXF_TEAMS_OPTION, $s, false );
}

/**
 * Whether appointments get their own Teams meeting.
 */
function exf_teams_connected() {
	return ! empty( exf_teams_state()['refresh_token'] );
}

/**
 * The redirect URI to register in Microsoft Entra.
 */
function exf_teams_redirect_uri() {
	return admin_url( 'admin-post.php?action=exf_teams_callback' );
}

/**
 * Microsoft's sign-in endpoint for this tenant.
 *
 * @param string $what authorize|token.
 */
function exf_teams_endpoint( $what ) {
	return 'https://login.microsoftonline.com/' . rawurlencode( exf_teams_client()['tenant'] ) . '/oauth2/v2.0/' . $what;
}

/**
 * Remember a problem, for the settings tab and an admin notice.
 *
 * @param string $message What went wrong.
 */
function exf_teams_problem( $message ) {
	exf_teams_save(
		array(
			'problem'    => $message,
			'problem_at' => time(),
		)
	);
}

/**
 * A current access token, refreshing it when needed. Microsoft issues a new refresh
 * token each time, so that's saved too.
 *
 * @return string|WP_Error
 */
function exf_teams_token() {
	$s = exf_teams_state();
	if ( empty( $s['refresh_token'] ) ) {
		return new WP_Error( 'exf_teams_off', 'Microsoft Teams is not connected.' );
	}
	if ( ! empty( $s['access_token'] ) && (int) ( $s['expires'] ?? 0 ) > time() + 120 ) {
		return $s['access_token'];
	}
	$c = exf_teams_client();
	$r = wp_remote_post(
		exf_teams_endpoint( 'token' ),
		array(
			'timeout' => 20,
			'body'    => array(
				'client_id'     => $c['id'],
				'client_secret' => $c['secret'],
				'refresh_token' => $s['refresh_token'],
				'grant_type'    => 'refresh_token',
				'scope'         => EXF_TEAMS_SCOPE,
			),
		)
	);
	$body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $body['access_token'] ) ) {
		$error = is_wp_error( $r ) ? $r->get_error_message() : ( $body['error_description'] ?? $body['error'] ?? 'unknown error' );
		if ( in_array( $body['error'] ?? '', array( 'invalid_grant', 'interaction_required' ), true ) ) {
			exf_teams_save(
				array(
					'refresh_token' => null,
					'access_token'  => null,
				)
			);
			exf_teams_problem( __( 'The Microsoft 365 connection has expired or was removed. Please connect again.', 'exfiles' ) );
		}
		return new WP_Error( 'exf_teams_token', 'Microsoft sign-in failed: ' . strtok( (string) $error, "\r\n" ) );
	}
	exf_teams_save(
		array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? $s['refresh_token'],
			'expires'       => time() + (int) ( $body['expires_in'] ?? 3600 ),
		)
	);
	return $body['access_token'];
}

/**
 * Call Microsoft Graph.
 *
 * @param string     $method GET, POST, PATCH or DELETE.
 * @param string     $path   e.g. "/me/onlineMeetings".
 * @param array|null $data   JSON body.
 * @return array|WP_Error Decoded body ([] for no content).
 */
function exf_graph_request( $method, $path, $data = null ) {
	$token = exf_teams_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$args = array(
		'method'  => $method,
		'timeout' => 20,
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/json',
		),
	);
	if ( null !== $data ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $data );
	}
	$r = wp_remote_request( EXF_GRAPH . $path, $args );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$body = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( $code >= 300 ) {
		return new WP_Error( 'exf_graph_http', sprintf( 'Microsoft Teams error %d: %s', $code, $body['error']['message'] ?? 'unknown' ), array( 'status' => $code ) );
	}
	return is_array( $body ) ? $body : array();
}

/* -------------------------------------------------------------------------
 * Meetings
 * ---------------------------------------------------------------------- */

/**
 * ISO 8601 in UTC, as Graph expects.
 *
 * @param int $ts Timestamp.
 */
function exf_graph_time( $ts ) {
	return gmdate( 'Y-m-d\TH:i:s\Z', (int) $ts );
}

/**
 * Make sure an order's appointment has its own Teams meeting at the right time.
 * Creates one if there isn't one yet, or moves it if the time has changed.
 *
 * @param int $order_id Order.
 * @return string The join link, or '' if there isn't one (the fixed link is used).
 */
function exf_teams_sync_meeting( $order_id ) {
	$start = (int) get_post_meta( $order_id, 'booking_start', true );
	$end   = (int) get_post_meta( $order_id, 'booking_end', true );
	if ( ! $start || ! exf_teams_connected() ) {
		return (string) get_post_meta( $order_id, 'teams_join_url', true );
	}
	$id    = (string) get_post_meta( $order_id, 'teams_meeting_id', true );
	$times = array(
		'startDateTime' => exf_graph_time( $start ),
		'endDateTime'   => exf_graph_time( $end > $start ? $end : $start + HOUR_IN_SECONDS ),
	);

	if ( $id ) {
		if ( (int) get_post_meta( $order_id, 'teams_start', true ) === $start && (int) get_post_meta( $order_id, 'teams_end', true ) === $end ) {
			return (string) get_post_meta( $order_id, 'teams_join_url', true );
		}
		$r = exf_graph_request( 'PATCH', '/me/onlineMeetings/' . rawurlencode( $id ), $times );
		if ( ! is_wp_error( $r ) ) {
			update_post_meta( $order_id, 'teams_start', $start );
			update_post_meta( $order_id, 'teams_end', $end );
			exf_order_log( $order_id, 'Teams meeting moved to ' . exf_booking_label( $start ) );
			return (string) get_post_meta( $order_id, 'teams_join_url', true );
		}
		if ( 404 !== (int) ( $r->get_error_data()['status'] ?? 0 ) ) {
			exf_order_log( $order_id, 'Could not move the Teams meeting: ' . $r->get_error_message() );
			exf_teams_problem( $r->get_error_message() );
			return (string) get_post_meta( $order_id, 'teams_join_url', true );
		}
		// Deleted in Teams meanwhile: make a new one below.
	}

	$client  = get_userdata( (int) get_post_meta( $order_id, 'client_id', true ) );
	$subject = (string) get_post_meta( $order_id, 'service_name', true ) . ( $client ? ' – ' . $client->display_name : '' ) . ' (' . exf_site_name() . ')';
	$r       = exf_graph_request( 'POST', '/me/onlineMeetings', $times + array( 'subject' => $subject ) );
	$link    = is_wp_error( $r ) ? '' : (string) ( $r['joinWebUrl'] ?? $r['joinUrl'] ?? '' );
	if ( is_wp_error( $r ) || ! $link || empty( $r['id'] ) ) {
		$why = is_wp_error( $r ) ? $r->get_error_message() : 'no link returned';
		exf_order_log( $order_id, 'Could not create a Teams meeting (the fixed meeting link was used instead): ' . $why );
		exf_teams_problem( $why );
		return '';
	}
	update_post_meta( $order_id, 'teams_meeting_id', (string) $r['id'] );
	update_post_meta( $order_id, 'teams_join_url', esc_url_raw( $link ) );
	update_post_meta( $order_id, 'teams_start', $start );
	update_post_meta( $order_id, 'teams_end', $end );
	exf_order_log( $order_id, 'Teams meeting created' );
	exf_teams_save(
		array(
			'problem'    => null,
			'problem_at' => null,
		)
	);
	return esc_url_raw( $link );
}

/**
 * Delete an order's Teams meeting (when the appointment is cancelled).
 *
 * @param int $order_id Order.
 */
function exf_teams_delete_meeting( $order_id ) {
	$id = (string) get_post_meta( $order_id, 'teams_meeting_id', true );
	if ( ! $id ) {
		return;
	}
	if ( exf_teams_connected() ) {
		$r = exf_graph_request( 'DELETE', '/me/onlineMeetings/' . rawurlencode( $id ) );
		if ( is_wp_error( $r ) && 404 !== (int) ( $r->get_error_data()['status'] ?? 0 ) ) {
			exf_order_log( $order_id, 'Could not delete the Teams meeting (please delete it in Teams): ' . $r->get_error_message() );
			return;
		}
		exf_order_log( $order_id, 'Teams meeting deleted' );
	}
	foreach ( array( 'teams_meeting_id', 'teams_join_url', 'teams_start', 'teams_end' ) as $k ) {
		delete_post_meta( $order_id, $k );
	}
}

/**
 * The Teams link for an order's appointment, if it has its own.
 *
 * @param int $order_id Order.
 */
function exf_teams_link( $order_id ) {
	$link = $order_id ? (string) get_post_meta( $order_id, 'teams_join_url', true ) : '';
	return preg_match( '#^https://#i', $link ) ? $link : '';
}

// Paid or confirmed: create the meeting before the confirmation emails go (orders.php).
add_action( 'exf_before_order_emails', 'exf_teams_sync_meeting' );

// Moved in the admin (appointments-admin.php): move the meeting before the emails go.
add_action( 'exf_before_appointment_moved_emails', 'exf_teams_sync_meeting' );

// Cancelled in the admin: delete the meeting.
add_action( 'exf_appointment_cancelled', 'exf_teams_delete_meeting' );

// A refund in Stripe that cancels the order: delete the meeting too.
add_action(
	'exf_order_status_changed',
	function ( $order_id, $status ) {
		if ( in_array( $status, array( 'refunded', 'cancelled' ), true ) ) {
			exf_teams_delete_meeting( $order_id );
		}
	},
	10,
	2
);

// From the order screen: try again for an appointment that fell back to the fixed link.
add_action(
	'admin_post_exf_teams_retry',
	function () {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		check_admin_referer( 'exf_teams_retry_' . $id );
		if ( ! exf_user_can_manage() || 'exf_order' !== get_post_type( $id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$link = exf_teams_sync_meeting( $id );
		set_transient( 'exf_teams_notice_' . get_current_user_id(), $link ? 'ok:' . __( 'Teams meeting created for this appointment.', 'exfiles' ) : 'fail:' . ( exf_teams_state()['problem'] ?? 'unknown error' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Connecting
 * ---------------------------------------------------------------------- */

add_action(
	'admin_post_exf_teams_connect',
	function () {
		check_admin_referer( 'exf_teams_connect' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$c = exf_teams_client();
		if ( ! $c['id'] || ! $c['secret'] ) {
			wp_die( esc_html__( 'Enter the Microsoft application (client) ID and client secret first, and save the settings.', 'exfiles' ) );
		}
		$state = wp_generate_password( 24, false, false );
		set_transient( 'exf_teams_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );
		wp_redirect( // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Microsoft's sign-in page.
			add_query_arg(
				array(
					'client_id'     => rawurlencode( $c['id'] ),
					'redirect_uri'  => rawurlencode( exf_teams_redirect_uri() ),
					'response_type' => 'code',
					'response_mode' => 'query',
					'scope'         => rawurlencode( EXF_TEAMS_SCOPE ),
					'prompt'        => 'select_account',
					'state'         => $state,
				),
				exf_teams_endpoint( 'authorize' )
			)
		);
		exit;
	}
);

add_action(
	'admin_post_exf_teams_callback',
	function () {
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$back  = admin_url( 'edit.php?post_type=ex_file&page=exf-settings' );
		$key   = 'exf_teams_state_' . get_current_user_id();
		$state = (string) get_transient( $key );
		delete_transient( $key );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the OAuth state is the check.
		if ( ! $state || ! hash_equals( $state, sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ) ) || empty( $_GET['code'] ) ) {
			$why = sanitize_text_field( wp_unslash( $_GET['error_description'] ?? $_GET['error'] ?? 'The connection was cancelled or timed out.' ) );
			set_transient( 'exf_teams_notice_' . get_current_user_id(), 'fail:' . strtok( $why, "\r\n" ), MINUTE_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}
		$c = exf_teams_client();
		$r = wp_remote_post(
			exf_teams_endpoint( 'token' ),
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => sanitize_text_field( wp_unslash( $_GET['code'] ) ),
					'client_id'     => $c['id'],
					'client_secret' => $c['secret'],
					'redirect_uri'  => exf_teams_redirect_uri(),
					'grant_type'    => 'authorization_code',
					'scope'         => EXF_TEAMS_SCOPE,
				),
			)
		);
		// phpcs:enable
		$body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $body['refresh_token'] ) || empty( $body['access_token'] ) ) {
			$why = is_wp_error( $r ) ? $r->get_error_message() : ( $body['error_description'] ?? 'Microsoft did not return a connection.' );
			set_transient( 'exf_teams_notice_' . get_current_user_id(), 'fail:' . strtok( (string) $why, "\r\n" ), MINUTE_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}
		exf_teams_save(
			array(
				'refresh_token' => $body['refresh_token'],
				'access_token'  => $body['access_token'],
				'expires'       => time() + (int) ( $body['expires_in'] ?? 3600 ),
				'problem'       => null,
				'problem_at'    => null,
			)
		);
		$me = exf_graph_request( 'GET', '/me?$select=displayName,mail,userPrincipalName' );
		if ( ! is_wp_error( $me ) ) {
			exf_teams_save(
				array(
					'email' => sanitize_email( $me['mail'] ?? $me['userPrincipalName'] ?? '' ),
					'name'  => sanitize_text_field( $me['displayName'] ?? '' ),
				)
			);
		}
		$email = exf_teams_state()['email'] ?? '';
		exf_audit( 'teams_connect', 'Connected Microsoft Teams' . ( $email ? ' (' . $email . ')' : '' ) );
		set_transient( 'exf_teams_notice_' . get_current_user_id(), 'ok:' . __( 'Microsoft Teams connected. New appointments will each get their own Teams meeting.', 'exfiles' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( $back );
		exit;
	}
);

add_action(
	'admin_post_exf_teams_disconnect',
	function () {
		check_admin_referer( 'exf_teams_disconnect' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		delete_option( EXF_TEAMS_OPTION );
		exf_audit( 'teams_disconnect', 'Disconnected Microsoft Teams' );
		set_transient( 'exf_teams_notice_' . get_current_user_id(), 'ok:' . __( 'Microsoft Teams disconnected. New appointments will use the fixed meeting link. Meetings already created still work.', 'exfiles' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit.php?post_type=ex_file&page=exf-settings' ) );
		exit;
	}
);

// Send a test: create a meeting and delete it straight away.
add_action(
	'admin_post_exf_teams_test',
	function () {
		check_admin_referer( 'exf_teams_test' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$start = time() + DAY_IN_SECONDS;
		$r     = exf_graph_request(
			'POST',
			'/me/onlineMeetings',
			array(
				'subject'       => exf_site_name() . ' – connection test',
				'startDateTime' => exf_graph_time( $start ),
				'endDateTime'   => exf_graph_time( $start + HOUR_IN_SECONDS ),
			)
		);
		if ( is_wp_error( $r ) || empty( $r['id'] ) ) {
			$why = is_wp_error( $r ) ? $r->get_error_message() : 'no meeting returned';
			exf_teams_problem( $why );
			set_transient( 'exf_teams_notice_' . get_current_user_id(), 'fail:' . $why, MINUTE_IN_SECONDS );
		} else {
			exf_graph_request( 'DELETE', '/me/onlineMeetings/' . rawurlencode( $r['id'] ) );
			exf_teams_save(
				array(
					'problem'    => null,
					'problem_at' => null,
				)
			);
			set_transient( 'exf_teams_notice_' . get_current_user_id(), 'ok:' . __( 'Test worked: a Teams meeting was created and then deleted.', 'exfiles' ), MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=ex_file&page=exf-settings' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! exf_user_can_manage() ) {
			return;
		}
		$n = get_transient( 'exf_teams_notice_' . get_current_user_id() );
		if ( $n ) {
			delete_transient( 'exf_teams_notice_' . get_current_user_id() );
			list( $state, $msg ) = explode( ':', $n, 2 );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 'ok' === $state ? 'success' : 'error', esc_html( 'ok' === $state ? $msg : __( 'Microsoft Teams: ', 'exfiles' ) . $msg ) );
			return;
		}
		// A problem in the last week, on the dashboard and Ex Files screens.
		$s      = exf_teams_state();
		$screen = get_current_screen();
		if ( empty( $s['problem'] ) || (int) ( $s['problem_at'] ?? 0 ) < time() - WEEK_IN_SECONDS || ! $screen || ( 'dashboard' !== $screen->id && false === strpos( (string) $screen->id, 'ex_file' ) && false === strpos( (string) $screen->id, 'exf' ) ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			/* translators: %s: the problem */
			esc_html( sprintf( __( 'Microsoft Teams: a meeting couldn’t be created or changed, so the fixed meeting link was used instead (%s).', 'exfiles' ), $s['problem'] ) ),
			esc_url( admin_url( 'edit.php?post_type=ex_file&page=exf-settings' ) ),
			esc_html__( 'Check the Teams settings', 'exfiles' )
		);
	}
);

// The settings tab's explanation, status and buttons.
add_filter(
	'acf/load_field/key=field_exf_set_teams_help',
	function ( $field ) {
		$s    = exf_teams_state();
		$html = '<p>' . esc_html__( 'Give every appointment its own Microsoft Teams meeting. When a booking is paid for (or confirmed), the site creates a Teams meeting for that time and sends its link in the confirmation email, the calendar invite and the reminder, and shows it on the client’s Appointments page. Moving an appointment moves the meeting; cancelling it deletes the meeting. Your diary stays in Google Calendar: nothing is added to Outlook.', 'exfiles' ) . '</p>';
		$html .= '<p>' . esc_html__( 'Until this is connected, or if Microsoft is unavailable, the fixed meeting link in the Bookings tab is used.', 'exfiles' ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'One-off setup', 'exfiles' ) . '</strong> ' . esc_html__( 'In the Microsoft Entra admin centre (entra.microsoft.com) → App registrations → New registration: any name; supported accounts “Accounts in this organizational directory only”; redirect URI type “Web”:', 'exfiles' ) . '<br><code>' . esc_html( exf_teams_redirect_uri() ) . '</code><br>';
		$html .= esc_html__( 'Then API permissions → Add → Microsoft Graph → Delegated: OnlineMeetings.ReadWrite, User.Read and offline_access (and Grant admin consent if your organisation requires it). Certificates & secrets → New client secret. Paste the Application (client) ID, Directory (tenant) ID and the secret’s Value below, save, then Connect.', 'exfiles' ) . '</p>';
		if ( ! empty( $s['refresh_token'] ) ) {
			$who   = trim( ( $s['name'] ?? '' ) . ( ! empty( $s['email'] ) ? ' (' . $s['email'] . ')' : '' ) );
			$html .= '<p><strong>' . esc_html( $who ? sprintf( /* translators: %s: account */ __( 'Connected as %s.', 'exfiles' ), $who ) : __( 'Connected.', 'exfiles' ) ) . '</strong> ';
			$html .= '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_teams_test' ), 'exf_teams_test' ) ) . '">' . esc_html__( 'Test the connection', 'exfiles' ) . '</a> ';
			$html .= '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_teams_disconnect' ), 'exf_teams_disconnect' ) ) . '">' . esc_html__( 'Disconnect', 'exfiles' ) . '</a></p>';
			$html .= '<p class="description">' . esc_html__( 'Meetings are created in this account, so it’s the organiser: sign in with the account you’ll hold the appointments from. Clients join from the link without a Microsoft account; depending on your Teams settings they may wait in the lobby until you let them in.', 'exfiles' ) . '</p>';
		} else {
			$html .= '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_teams_connect' ), 'exf_teams_connect' ) ) . '">' . esc_html__( 'Connect Microsoft 365', 'exfiles' ) . '</a></p>';
		}
		if ( ! empty( $s['problem'] ) ) {
			/* translators: 1: date, 2: problem */
			$html .= '<p style="color:#b32d2e">' . esc_html( sprintf( __( 'Last problem (%1$s): %2$s', 'exfiles' ), wp_date( 'j M Y H:i', (int) ( $s['problem_at'] ?? time() ) ), $s['problem'] ) ) . '</p>';
		}
		$field['message'] = $html;
		return $field;
	}
);
