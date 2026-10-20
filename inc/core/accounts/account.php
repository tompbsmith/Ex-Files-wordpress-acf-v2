<?php
/**
 * The client's "My Ex File" page (template page-account.php).
 *
 * For now this shows account status, Ex Files, and the reply form when the
 * solicitor has asked for more information. In the portal phase it becomes the
 * WooCommerce account area, keeping the same URL.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	function () {
		if ( ! is_page_template( 'page-account.php' ) ) {
			return;
		}
		nocache_headers();

		if ( ! is_user_logged_in() ) {
			$here = isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ? esc_url_raw( set_url_scheme( 'http://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : get_permalink(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_safe_redirect( wp_login_url( $here ) );
			exit;
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['exf_info_reply'], $_POST['ex_file'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			check_admin_referer( 'exf_info_reply' );
			$errors = exf_handle_info_reply( absint( $_POST['ex_file'] ), wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field.
			if ( $errors ) {
				$GLOBALS['exf_info_errors'] = $errors;
			}
		}
	},
	5
);

/**
 * Client-facing label for an Ex File's status. Never says "conflict".
 *
 * @param string $status Ex File status.
 */
function exf_ex_file_client_label( $status ) {
	$labels = array(
		'pending'        => __( 'Waiting for you to confirm your email', 'exfiles' ),
		'clear'          => __( 'Open', 'exfiles' ),
		'review'         => __( "We're checking a few details", 'exfiles' ),
		'info_requested' => __( 'We need more information from you', 'exfiles' ),
		'blocked'        => __( "We're unable to take on this matter", 'exfiles' ),
	);
	return $labels[ $status ] ?? '';
}

/**
 * Save the client's reply to a request for more information, and re-run the check.
 *
 * The Ex File goes back to the solicitor's review queue (or is blocked if the
 * new details now show a clear conflict). It's never cleared automatically after
 * the solicitor has asked a question.
 *
 * @param int   $ex_file_id Ex File.
 * @param array $post       Unslashed $_POST.
 * @return array Errors (empty on success, which redirects).
 */
function exf_handle_info_reply( $ex_file_id, array $post ) {
	$user_id = get_current_user_id();
	if ( 'ex_file' !== get_post_type( $ex_file_id ) || (int) get_post_field( 'post_author', $ex_file_id ) !== $user_id || 'info_requested' !== exf_ex_file_status( $ex_file_id ) ) {
		return array( '_form' => __( 'Sorry, that request has already been dealt with.', 'exfiles' ) );
	}

	$reply = sanitize_textarea_field( $post['info_reply'] ?? '' );
	$e     = array();
	if ( '' === trim( $reply ) ) {
		$e['info_reply'] = __( 'Please answer the question above.', 'exfiles' );
	}
	$updates = array();
	foreach ( array( 'ex_prev_names', 'ex_phone', 'ex_postcode' ) as $k ) {
		$val = sanitize_text_field( $post[ $k ] ?? '' );
		if ( '' !== $val ) {
			$updates[ $k ] = 'ex_postcode' === $k ? strtoupper( $val ) : $val;
		}
	}
	$ex_email = sanitize_email( $post['ex_email'] ?? '' );
	if ( '' !== ( $post['ex_email'] ?? '' ) && ! is_email( $ex_email ) ) {
		$e['ex_email'] = __( 'Please enter a valid email address, or leave it blank.', 'exfiles' );
	} elseif ( $ex_email ) {
		$updates['ex_email'] = $ex_email;
	}
	if ( ! empty( $post['ex_dob'] ) ) {
		$dob = exf_norm_dob( sanitize_text_field( $post['ex_dob'] ) );
		if ( ! $dob ) {
			$e['ex_dob'] = __( 'Please enter a valid date.', 'exfiles' );
		} else {
			$updates['ex_dob']         = str_replace( '-', '', $dob );
			$updates['ex_dob_unknown'] = 0;
		}
	}
	if ( $e ) {
		return $e;
	}

	foreach ( $updates as $k => $val ) {
		update_post_meta( $ex_file_id, $k, $val );
	}
	$replies   = (array) get_post_meta( $ex_file_id, 'info_replies', true );
	$replies[] = array(
		'time'     => time(),
		'question' => (string) get_post_meta( $ex_file_id, 'info_question', true ),
		'reply'    => $reply,
		'updated'  => array_keys( $updates ),
	);
	update_post_meta( $ex_file_id, 'info_replies', array_filter( $replies ) );
	exf_ex_file_log( $ex_file_id, 'Client replied to the request for more information' . ( $updates ? ' and updated: ' . implode( ', ', array_keys( $updates ) ) : '' ) );

	$result = exf_conflict_evaluate( $ex_file_id );
	update_post_meta( $ex_file_id, 'conflict_score', $result['score'] );
	update_post_meta( $ex_file_id, 'conflict_matches', $result['matches'] );
	update_post_meta( $ex_file_id, 'conflict_checked_at', time() );

	if ( 'blocked' === $result['outcome'] ) {
		exf_ex_file_set_status( $ex_file_id, 'blocked' );
		exf_ex_file_log( $ex_file_id, sprintf( 'Conflict check after reply: blocked (score %d)', $result['score'] ) );
		exf_sync_user_status( $user_id );
		exf_conflict_notify( $ex_file_id, 'blocked' );
		if ( EXF_STATUS_BLOCKED === exf_user_status( $user_id ) ) {
			wp_logout();
			wp_safe_redirect( add_query_arg( 'result', 'unable', exf_page_url( 'register' ) ) );
			exit;
		}
	} else {
		exf_ex_file_set_status( $ex_file_id, 'review' );
		update_post_meta( $ex_file_id, 'info_provided', 1 );
		exf_ex_file_log( $ex_file_id, sprintf( 'Conflict check after reply: score %d, back to review', $result['score'] ) );
		exf_sync_user_status( $user_id );
		exf_send_email( 'admin_info', exf_notify_emails(), exf_ex_file_email_vars( $ex_file_id ) );
	}

	wp_safe_redirect( add_query_arg( 'replied', 1, exf_portal_url( 'ex-files', $ex_file_id ) ) );
	exit;
}
