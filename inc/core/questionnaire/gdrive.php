<?php
/**
 * Google Sheets copies of asset schedules.
 *
 * When connected (Ex Files → Settings → Google Drive), each submitted
 * questionnaire's .xlsx schedule is also uploaded to a folder in the solicitor's
 * Google Drive, converted to a Google Sheet. The site uses the "drive.file"
 * permission, so it can only see the folder and files it creates itself.
 *
 * Setup: a Google Cloud OAuth client (Web application) with the redirect URI shown
 * on the settings tab. Client ID and secret go in the settings or, better, in
 * wp-config.php as EXF_GOOGLE_CLIENT_ID / EXF_GOOGLE_CLIENT_SECRET.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_GDRIVE_OPTION = 'exf_gdrive';
const EXF_GDRIVE_SCOPE  = 'https://www.googleapis.com/auth/drive.file';

/**
 * OAuth client details.
 */
function exf_gdrive_client() {
	return array(
		'id'     => defined( 'EXF_GOOGLE_CLIENT_ID' ) ? EXF_GOOGLE_CLIENT_ID : trim( (string) exf_setting( 'gdrive_client_id', '' ) ),
		'secret' => defined( 'EXF_GOOGLE_CLIENT_SECRET' ) ? EXF_GOOGLE_CLIENT_SECRET : trim( (string) exf_setting( 'gdrive_client_secret', '' ) ),
	);
}

/**
 * Stored connection: refresh_token, access_token, expires, folder_id, email.
 */
function exf_gdrive_state() {
	$s = get_option( EXF_GDRIVE_OPTION );
	return is_array( $s ) ? $s : array();
}

/**
 * Update the stored connection.
 *
 * @param array $changes Keys to set (null deletes).
 */
function exf_gdrive_save( array $changes ) {
	$s = array_filter( array_merge( exf_gdrive_state(), $changes ), fn( $v ) => null !== $v );
	update_option( EXF_GDRIVE_OPTION, $s, false );
}

/**
 * Whether schedules should go to Google Drive.
 */
function exf_gdrive_enabled() {
	return 'on' === exf_setting( 'gdrive_enabled', 'off' ) && ! empty( exf_gdrive_state()['refresh_token'] );
}

/**
 * The OAuth redirect URI to register in Google Cloud.
 */
function exf_gdrive_redirect_uri() {
	return admin_url( 'admin-post.php?action=exf_gdrive_callback' );
}

/**
 * A current access token, refreshing it when needed.
 *
 * @return string|WP_Error
 */
function exf_gdrive_token() {
	$s = exf_gdrive_state();
	if ( empty( $s['refresh_token'] ) ) {
		return new WP_Error( 'exf_gdrive_off', 'Google Drive is not connected.' );
	}
	if ( ! empty( $s['access_token'] ) && (int) ( $s['expires'] ?? 0 ) > time() + 60 ) {
		return $s['access_token'];
	}
	$c = exf_gdrive_client();
	$r = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 20,
			'body'    => array(
				'client_id'     => $c['id'],
				'client_secret' => $c['secret'],
				'refresh_token' => $s['refresh_token'],
				'grant_type'    => 'refresh_token',
			),
		)
	);
	$body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $body['access_token'] ) ) {
		$error = is_wp_error( $r ) ? $r->get_error_message() : ( $body['error_description'] ?? $body['error'] ?? 'unknown error' );
		if ( 'invalid_grant' === ( $body['error'] ?? '' ) ) {
			exf_gdrive_save( array( 'refresh_token' => null, 'access_token' => null, 'problem' => 'Access was revoked or expired. Please connect again.' ) );
		}
		return new WP_Error( 'exf_gdrive_token', 'Google sign-in failed: ' . $error );
	}
	exf_gdrive_save(
		array(
			'access_token' => $body['access_token'],
			'expires'      => time() + (int) ( $body['expires_in'] ?? 3600 ),
		)
	);
	return $body['access_token'];
}

/**
 * Call the Drive API.
 *
 * @param string $method Method.
 * @param string $url    Full URL.
 * @param array  $args   Extra wp_remote_request args.
 * @return array|WP_Error Decoded body.
 */
function exf_gdrive_request( $method, $url, array $args = array() ) {
	$token = exf_gdrive_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$args            += array( 'headers' => array() );
	$args['headers'] += array( 'Authorization' => 'Bearer ' . $token );
	$args['method']   = $method;
	$args['timeout']  = 30;
	$r                = wp_remote_request( $url, $args );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = wp_remote_retrieve_response_code( $r );
	$body = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( $code >= 300 ) {
		return new WP_Error( 'exf_gdrive_http', 'Google Drive error ' . $code . ': ' . ( $body['error']['message'] ?? 'unknown' ) );
	}
	return is_array( $body ) ? $body : array();
}

/**
 * The site's folder in Drive, created on first use.
 *
 * @return string|WP_Error Folder ID.
 */
function exf_gdrive_folder() {
	$id = exf_gdrive_state()['folder_id'] ?? '';
	if ( $id ) {
		return $id;
	}
	$r = exf_gdrive_request(
		'POST',
		'https://www.googleapis.com/drive/v3/files?fields=id',
		array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'name'     => exf_site_name() . ' – Asset schedules',
					'mimeType' => 'application/vnd.google-apps.folder',
				)
			),
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	exf_gdrive_save( array( 'folder_id' => $r['id'] ) );
	return $r['id'];
}

/**
 * Upload an .xlsx and convert it to a Google Sheet.
 *
 * @param string $name  File name.
 * @param string $bytes File contents.
 * @return array|WP_Error [ id, webViewLink ]
 */
function exf_gdrive_upload_sheet( $name, $bytes ) {
	$folder = exf_gdrive_folder();
	if ( is_wp_error( $folder ) ) {
		return $folder;
	}
	$boundary = 'exf' . wp_generate_password( 16, false, false );
	$meta     = wp_json_encode(
		array(
			'name'     => preg_replace( '/\.xlsx$/', '', $name ),
			'mimeType' => 'application/vnd.google-apps.spreadsheet',
			'parents'  => array( $folder ),
		)
	);
	$body     = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n"
		. "--$boundary\r\nContent-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\r\n\r\n$bytes\r\n--$boundary--";
	$r        = exf_gdrive_request(
		'POST',
		'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink',
		array(
			'headers' => array( 'Content-Type' => 'multipart/related; boundary=' . $boundary ),
			'body'    => $body,
		)
	);
	if ( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), '404' ) ) {
		exf_gdrive_save( array( 'folder_id' => null ) ); // Folder deleted in Drive: make a new one next time.
	}
	return $r;
}

/**
 * Delete a file from Drive (ignores files already gone).
 *
 * @param string $file_id File.
 */
function exf_gdrive_delete( $file_id ) {
	if ( ! $file_id || empty( exf_gdrive_state()['refresh_token'] ) ) {
		return;
	}
	exf_gdrive_request( 'DELETE', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id ) );
}

/**
 * Send a questionnaire's schedule to Google Sheets, replacing any earlier copy.
 *
 * @param int $q Questionnaire.
 * @return bool
 */
function exf_q_send_to_gsheets( $q ) {
	$path = exf_private_path( (string) get_post_meta( $q, 'q_xlsx', true ) );
	if ( ! $path ) {
		return false;
	}
	$r = exf_gdrive_upload_sheet( (string) get_post_meta( $q, 'q_xlsx_name', true ), (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( is_wp_error( $r ) ) {
		exf_q_log( $q, 'Google Sheets copy failed: ' . $r->get_error_message() );
		update_post_meta( $q, 'q_gsheet_error', $r->get_error_message() );
		return false;
	}
	exf_gdrive_delete( (string) get_post_meta( $q, 'q_gsheet_id', true ) );
	update_post_meta( $q, 'q_gsheet_id', $r['id'] );
	update_post_meta( $q, 'q_gsheet_url', $r['webViewLink'] ?? 'https://docs.google.com/spreadsheets/d/' . $r['id'] );
	delete_post_meta( $q, 'q_gsheet_error' );
	exf_q_log( $q, 'Asset schedule copied to Google Sheets' );
	return true;
}

// Deleting a questionnaire (or its matter) deletes its Sheet too.
add_action(
	'before_delete_post',
	function ( $post_id ) {
		if ( 'fs_questionnaire' === get_post_type( $post_id ) ) {
			exf_gdrive_delete( (string) get_post_meta( $post_id, 'q_gsheet_id', true ) );
		}
	}
);

/* -------------------------------------------------------------------------
 * Connecting
 * ---------------------------------------------------------------------- */

add_action(
	'admin_post_exf_gdrive_connect',
	function () {
		check_admin_referer( 'exf_gdrive_connect' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$c = exf_gdrive_client();
		if ( ! $c['id'] || ! $c['secret'] ) {
			wp_die( esc_html__( 'Enter the Google client ID and secret first, and save the settings.', 'exfiles' ) );
		}
		$state = wp_generate_password( 24, false, false );
		set_transient( 'exf_gdrive_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );
		wp_redirect( // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google's sign-in page.
			add_query_arg(
				array(
					'client_id'     => rawurlencode( $c['id'] ),
					'redirect_uri'  => rawurlencode( exf_gdrive_redirect_uri() ),
					'response_type' => 'code',
					'scope'         => rawurlencode( EXF_GDRIVE_SCOPE . ' email' ),
					'access_type'   => 'offline',
					'prompt'        => 'consent',
					'state'         => $state,
				),
				'https://accounts.google.com/o/oauth2/v2/auth'
			)
		);
		exit;
	}
);

add_action(
	'admin_post_exf_gdrive_callback',
	function () {
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$back  = admin_url( 'edit.php?post_type=ex_file&page=exf-settings' );
		$state = (string) get_transient( 'exf_gdrive_state_' . get_current_user_id() );
		delete_transient( 'exf_gdrive_state_' . get_current_user_id() );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the OAuth state is the check.
		if ( ! $state || ! hash_equals( $state, sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ) ) || empty( $_GET['code'] ) ) {
			set_transient( 'exf_gdrive_notice_' . get_current_user_id(), 'fail:' . sanitize_text_field( wp_unslash( $_GET['error'] ?? 'The connection was cancelled or timed out.' ) ), MINUTE_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}
		$c = exf_gdrive_client();
		$r = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => sanitize_text_field( wp_unslash( $_GET['code'] ) ),
					'client_id'     => $c['id'],
					'client_secret' => $c['secret'],
					'redirect_uri'  => exf_gdrive_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		// phpcs:enable
		$body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $body['refresh_token'] ) ) {
			set_transient( 'exf_gdrive_notice_' . get_current_user_id(), 'fail:' . ( is_wp_error( $r ) ? $r->get_error_message() : ( $body['error_description'] ?? 'Google did not return a connection.' ) ), MINUTE_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}
		$email = '';
		if ( ! empty( $body['id_token'] ) ) {
			$parts   = explode( '.', $body['id_token'] );
			$payload = json_decode( base64_decode( strtr( $parts[1] ?? '', '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$email   = sanitize_email( $payload['email'] ?? '' );
		}
		exf_gdrive_save(
			array(
				'refresh_token' => $body['refresh_token'],
				'access_token'  => $body['access_token'] ?? null,
				'expires'       => time() + (int) ( $body['expires_in'] ?? 3600 ),
				'email'         => $email,
				'problem'       => null,
			)
		);
		exf_audit( 'gdrive_connect', 'Connected Google Drive' . ( $email ? ' (' . $email . ')' : '' ) );
		set_transient( 'exf_gdrive_notice_' . get_current_user_id(), 'ok:Google Drive connected.', MINUTE_IN_SECONDS );
		wp_safe_redirect( $back );
		exit;
	}
);

add_action(
	'admin_post_exf_gdrive_disconnect',
	function () {
		check_admin_referer( 'exf_gdrive_disconnect' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$s = exf_gdrive_state();
		if ( ! empty( $s['refresh_token'] ) ) {
			wp_remote_post( 'https://oauth2.googleapis.com/revoke', array( 'body' => array( 'token' => $s['refresh_token'] ), 'timeout' => 10 ) );
		}
		delete_option( EXF_GDRIVE_OPTION );
		exf_audit( 'gdrive_disconnect', 'Disconnected Google Drive' );
		wp_safe_redirect( admin_url( 'edit.php?post_type=ex_file&page=exf-settings' ) );
		exit;
	}
);

add_action(
	'admin_post_exf_q_gsheet',
	function () {
		$q = isset( $_GET['q'] ) ? absint( $_GET['q'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'exf_q_gsheet_' . $q );
		if ( ! exf_user_can_manage() || 'fs_questionnaire' !== get_post_type( $q ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		exf_q_send_to_gsheets( $q );
		wp_safe_redirect( get_edit_post_link( $q, 'url' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		$n = get_transient( 'exf_gdrive_notice_' . get_current_user_id() );
		if ( ! $n ) {
			return;
		}
		delete_transient( 'exf_gdrive_notice_' . get_current_user_id() );
		list( $state, $msg ) = explode( ':', $n, 2 );
		printf( '<div class="notice notice-%s"><p>%s</p></div>', 'ok' === $state ? 'success' : 'error', esc_html( 'ok' === $state ? $msg : 'Google Drive: ' . $msg ) );
	}
);

// The settings tab's explanation, status and buttons.
add_filter(
	'acf/load_field/key=field_exf_set_gdrive_help',
	function ( $field ) {
		$s    = exf_gdrive_state();
		$html = '<p>' . esc_html__( 'Optional: also save each asset schedule as a Google Sheet in a folder in your Google Drive. The site can only see the folder and files it creates.', 'exfiles' ) . '</p>';
		$html .= '<p>' . esc_html__( 'One-off setup in Google Cloud: create an OAuth client (type "Web application"), add this redirect URI, then paste the client ID and secret below and save:', 'exfiles' ) . '<br><code>' . esc_html( exf_gdrive_redirect_uri() ) . '</code></p>';
		if ( ! empty( $s['refresh_token'] ) ) {
			/* translators: %s: account */
			$html .= '<p><strong>' . esc_html( sprintf( __( 'Connected%s.', 'exfiles' ), ! empty( $s['email'] ) ? ' as ' . $s['email'] : '' ) ) . '</strong> ';
			$html .= '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_gdrive_disconnect' ), 'exf_gdrive_disconnect' ) ) . '">' . esc_html__( 'Disconnect', 'exfiles' ) . '</a></p>';
			if ( ! empty( $s['folder_id'] ) ) {
				$html .= '<p><a href="' . esc_url( 'https://drive.google.com/drive/folders/' . $s['folder_id'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the schedules folder in Google Drive', 'exfiles' ) . '</a></p>';
			}
		} else {
			if ( ! empty( $s['problem'] ) ) {
				$html .= '<p style="color:#b32d2e">' . esc_html( $s['problem'] ) . '</p>';
			}
			$html .= '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_gdrive_connect' ), 'exf_gdrive_connect' ) ) . '">' . esc_html__( 'Connect Google Drive', 'exfiles' ) . '</a></p>';
		}
		$field['message'] = $html;
		return $field;
	}
);
