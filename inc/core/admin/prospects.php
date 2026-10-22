<?php
/**
 * Sign-ups who don't go ahead with a service.
 *
 * At the client's request (October 2026), someone who opens an Ex File but never
 * buys a service isn't treated as a client: until they do, she holds nothing that
 * would create a conflict of interest. So:
 *
 *  - in the conflict check they can only send a later sign-up for review, never
 *    turn it away automatically (see exf_conflict_evaluate());
 *  - their account, Ex Files and their ex's details are deleted automatically after
 *    a period with no activity (Settings → Data retention, 30 days by default),
 *    without adding anyone to the Conflict Register;
 *  - the same happens straight away if they ask for their data to be deleted.
 *
 * Sign-ups still being checked (review, more information requested) or turned away
 * are left for the solicitor to decide.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Days of inactivity after which a sign-up with no service is deleted (0 = never).
 */
function exf_prospect_retention_days() {
	return max( 0, (int) exf_setting( 'prospect_retention_days', 30 ) );
}

// Remember when clients last logged in, so an active sign-up isn't deleted.
add_action(
	'wp_login',
	function ( $login, $user ) {
		if ( $user instanceof WP_User && ! user_can( $user, EXF_CAP ) ) {
			update_user_meta( $user->ID, 'exf_last_login', time() );
		}
	},
	10,
	2
);

/**
 * The most recent sign of activity on a sign-up: registering, logging in, starting
 * an order or saving a questionnaire.
 *
 * @param int $user_id User.
 */
function exf_prospect_last_activity( $user_id ) {
	$user  = get_userdata( $user_id );
	$times = array(
		$user ? (int) strtotime( $user->user_registered . ' UTC' ) : 0,
		(int) get_user_meta( $user_id, 'exf_last_login', true ),
	);
	foreach ( exf_client_orders( $user_id ) as $o ) {
		$times[] = (int) get_post_timestamp( $o, 'modified' );
	}
	foreach ( exf_client_questionnaires( $user_id ) as $q ) {
		$times[] = (int) get_post_meta( $q, 'q_saved_at', true );
	}
	return max( $times );
}

/**
 * Whether a user is a sign-up who hasn't gone ahead, and can be deleted outright.
 *
 * @param int $user_id User.
 */
function exf_is_deletable_prospect( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || user_can( $user, EXF_CAP ) || user_can( $user, 'edit_posts' ) || ! exf_is_client( $user_id ) ) {
		return false;
	}
	if ( exf_client_has_service( $user_id ) ) {
		return false;
	}
	foreach ( exf_client_ex_files( $user_id ) as $f ) {
		if ( in_array( exf_ex_file_status( $f ), array( 'review', 'info_requested', 'blocked' ), true ) ) {
			return false; // Waiting for, or decided by, the solicitor.
		}
	}
	return true;
}

/**
 * Delete a sign-up who never bought a service: account, Ex Files (so their ex's
 * details), unpaid orders, questionnaires and uploads. Nothing goes on the
 * Conflict Register.
 *
 * @param int    $user_id User.
 * @param string $why     For the audit log.
 * @return bool
 */
function exf_delete_prospect( $user_id, $why ) {
	if ( ! exf_is_deletable_prospect( $user_id ) ) {
		return false;
	}
	foreach ( array( 'client_document', 'fs_questionnaire', 'exf_order', 'ex_file' ) as $type ) {
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'author'         => (int) $user_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			if ( 'client_document' === $type ) {
				exf_private_delete( (string) get_post_meta( $id, 'file', true ) );
			}
			wp_delete_post( $id, true );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user_id ); // No Ex Files left, so nothing is added to the Conflict Register.
	// No names in the log: the point is that nothing about them is kept.
	exf_audit( 'prospect_deleted', sprintf( 'Deleted a sign-up that never bought a service (user %d): %s. Their ex was not added to the Conflict Register.', $user_id, $why ) );
	return true;
}

add_action( 'exf_daily_cleanup', 'exf_cleanup_prospects' );

/**
 * Delete sign-ups with no service and no activity for the retention period.
 *
 * @return int Number deleted.
 */
function exf_cleanup_prospects() {
	$days = exf_prospect_retention_days();
	if ( ! $days ) {
		return 0;
	}
	$cutoff = time() - $days * DAY_IN_SECONDS;
	$users  = get_users(
		array(
			'meta_key'     => 'exf_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => EXF_STATUS_PENDING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare' => '!=',
			'date_query'   => array( array( 'before' => gmdate( 'Y-m-d H:i:s', $cutoff ) ) ),
			'fields'       => 'ID',
			'number'       => 200,
			'role__not_in' => array( 'administrator', 'ex_files_manager' ),
		)
	);
	$n = 0;
	foreach ( $users as $uid ) {
		if ( exf_is_deletable_prospect( $uid ) && exf_prospect_last_activity( $uid ) < $cutoff ) {
			/* translators: %d: days */
			$n += exf_delete_prospect( $uid, sprintf( 'no activity for %d days', $days ) ) ? 1 : 0;
		}
	}
	return $n;
}
