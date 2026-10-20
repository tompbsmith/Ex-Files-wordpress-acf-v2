<?php
/**
 * Retention: matters (Ex Files) whose last activity is older than the retention
 * period are listed in Ex Files → Retention review for the solicitor to delete.
 * Nothing is deleted automatically: deleting legal records is a decision for her.
 *
 * Deleting a matter removes its documents, questionnaires, schedules and orders,
 * and the client's account if they have no other matters. A minimal entry for the
 * client and their ex is added to the Conflict Register first, so future conflict
 * checks still find them.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Years to keep a matter after its last activity (0 = keep forever).
 */
function exf_retention_years() {
	return max( 0, (int) exf_setting( 'client_retention_years', 6 ) );
}

/**
 * The most recent activity on a matter.
 *
 * @param int $ex_file Ex File.
 * @return int Timestamp.
 */
function exf_matter_last_activity( $ex_file ) {
	$times   = array( (int) get_post_timestamp( $ex_file, 'modified' ) );
	$log     = array_filter( (array) get_post_meta( $ex_file, 'exf_log', true ) );
	$times[] = $log ? (int) end( $log )['time'] : 0;
	$client  = (int) get_post_field( 'post_author', $ex_file );
	foreach ( exf_client_orders( $client, $ex_file ) as $o ) {
		$times[] = (int) get_post_timestamp( $o, 'modified' );
		$times[] = (int) get_post_meta( $o, 'booking_end', true );
	}
	foreach ( exf_client_documents( $client, $ex_file ) as $d ) {
		$times[] = (int) get_post_timestamp( $d );
	}
	foreach ( exf_client_questionnaires( $client, $ex_file ) as $q ) {
		$times[] = (int) get_post_meta( $q, 'q_saved_at', true );
		$times[] = (int) get_post_meta( $q, 'q_submitted_at', true );
	}
	return max( $times );
}

/**
 * Matters past the retention period, oldest first.
 *
 * @return array[] [ id, last ]
 */
function exf_matters_past_retention() {
	$years = exf_retention_years();
	if ( ! $years ) {
		return array();
	}
	$cutoff = strtotime( '-' . $years . ' years' );
	$out    = array();
	$ids    = get_posts(
		array(
			'post_type'      => 'ex_file',
			'post_status'    => 'any',
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', $cutoff ), 'column' => 'post_date_gmt' ) ),
		)
	);
	foreach ( $ids as $id ) {
		$last = exf_matter_last_activity( $id );
		if ( $last < $cutoff ) {
			$out[] = array(
				'id'   => (int) $id,
				'last' => $last,
			);
		}
	}
	usort(
		$out,
		function ( $a, $b ) {
			return $a['last'] <=> $b['last'];
		}
	);
	return $out;
}

/**
 * Add someone to the Conflict Register.
 *
 * @param array  $person first_name, last_name, prev_names, dob, email, phone, postcode.
 * @param string $role   former_client|opposing_party.
 * @param string $source Where it came from.
 */
function exf_conflict_register_add( array $person, $role, $source ) {
	if ( '' === trim( ( $person['first_name'] ?? '' ) . ( $person['last_name'] ?? '' ) ) ) {
		return 0;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'conflict_record',
			'post_status' => 'publish',
			'post_title'  => trim( $person['first_name'] . ' ' . $person['last_name'] ),
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	foreach ( array( 'first_name', 'last_name', 'prev_names', 'email', 'phone', 'postcode' ) as $k ) {
		update_post_meta( $id, $k, (string) ( $person[ $k ] ?? '' ) );
	}
	update_post_meta( $id, 'dob', str_replace( '-', '', exf_norm_dob( $person['dob'] ?? '' ) ) );
	update_post_meta( $id, 'record_role', $role );
	update_post_meta( $id, 'source', $source );
	return (int) $id;
}

/**
 * Permanently delete a matter and everything in it.
 *
 * @param int $ex_file Ex File.
 * @return array Counts.
 */
function exf_delete_matter( $ex_file ) {
	$client_id = (int) get_post_field( 'post_author', $ex_file );
	$title     = get_the_title( $ex_file );
	$source    = sprintf( 'Kept for conflict checks after deleting Ex File %d on %s', $ex_file, wp_date( 'j M Y' ) );
	$counts    = array( 'documents' => 0, 'questionnaires' => 0, 'orders' => 0, 'account' => 0 );

	// Keep enough for future conflict checks.
	exf_conflict_register_add( exf_client_details( $client_id ), 'former_client', $source );
	exf_conflict_register_add( exf_ex_details( $ex_file ), 'opposing_party', $source );

	foreach ( exf_client_documents( $client_id, $ex_file ) as $d ) {
		wp_delete_post( $d, true );
		++$counts['documents'];
	}
	foreach ( exf_client_questionnaires( $client_id, $ex_file ) as $q ) {
		exf_private_delete( (string) get_post_meta( $q, 'q_xlsx', true ) );
		wp_delete_post( $q, true );
		++$counts['questionnaires'];
	}
	foreach ( exf_client_orders( $client_id, $ex_file ) as $o ) {
		wp_delete_post( $o, true );
		++$counts['orders'];
	}
	wp_delete_post( $ex_file, true );

	$user = get_userdata( $client_id );
	if ( $user && ! user_can( $user, EXF_CAP ) && ! user_can( $user, 'edit_posts' ) && ! exf_client_ex_files( $client_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $client_id );
		$counts['account'] = 1;
	}

	exf_audit(
		'matter_deleted',
		sprintf( 'Deleted matter "%s" (%d documents, %d questionnaires, %d orders%s). Client and ex added to the Conflict Register.', $title, $counts['documents'], $counts['questionnaires'], $counts['orders'], $counts['account'] ? ', and the client account' : '' ),
		'ex_file',
		$ex_file
	);
	return $counts;
}

// Delete the client's other private data with their account.
add_action(
	'delete_user',
	function ( $user_id ) {
		$files = exf_client_ex_files( $user_id );
		if ( $files ) {
			// Deleted from the Users screen: keep conflict details first.
			$source = sprintf( 'Kept for conflict checks after deleting a client account on %s', wp_date( 'j M Y' ) );
			exf_conflict_register_add( exf_client_details( $user_id ), 'former_client', $source );
			foreach ( $files as $f ) {
				exf_conflict_register_add( exf_ex_details( $f ), 'opposing_party', $source );
			}
			exf_audit( 'client_deleted', 'Deleted client account ' . get_userdata( $user_id )->display_name . ' and their Ex Files. Added to the Conflict Register.', 'user', $user_id );
		}
		foreach ( array( 'ex_file', 'fs_questionnaire', 'client_document', 'exf_order' ) as $type ) {
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
				wp_delete_post( $id, true );
			}
		}
	}
);

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		$count = (int) get_transient( 'exf_retention_due' );
		$title = __( 'Retention review', 'exfiles' ) . ( $count ? ' <span class="awaiting-mod">' . $count . '</span>' : '' );
		add_submenu_page( 'edit.php?post_type=ex_file', __( 'Retention review', 'exfiles' ), $title, EXF_CAP, 'exf-retention', 'exf_retention_page' );
	},
	31
);

add_action(
	'exf_daily_cleanup',
	function () {
		set_transient( 'exf_retention_due', count( exf_matters_past_retention() ), 2 * DAY_IN_SECONDS );
	}
);

/**
 * The Retention review page.
 */
function exf_retention_page() {
	if ( ! exf_user_can_manage() ) {
		return;
	}
	$years   = exf_retention_years();
	$matters = exf_matters_past_retention();
	set_transient( 'exf_retention_due', count( $matters ), 2 * DAY_IN_SECONDS );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$done = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : -1;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Retention review', 'exfiles' ); ?></h1>
		<?php if ( $done >= 0 ) : ?>
			<div class="notice notice-success"><p>
				<?php
				/* translators: %d: number */
				echo esc_html( sprintf( _n( '%d matter deleted.', '%d matters deleted.', $done, 'exfiles' ), $done ) );
				?>
			</p></div>
		<?php endif; ?>
		<p>
			<?php
			echo $years
				/* translators: %d: years */
				? esc_html( sprintf( __( 'Matters with no activity for %d years or more. Check each one before deleting: deletion is permanent and removes its documents, questionnaires, schedules and orders, and the client’s account if they have no other matters. The client and their ex are added to the Conflict Register first, so future conflict checks still find them.', 'exfiles' ), $years ) )
				: esc_html__( 'The retention period is set to 0 (keep forever), so nothing is listed.', 'exfiles' );
			?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ex_file&page=exf-settings' ) ); ?>"><?php esc_html_e( 'Change the period in Settings → Data retention.', 'exfiles' ); ?></a>
		</p>
		<?php if ( $matters ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete the selected matters? This cannot be undone.', 'exfiles' ) ); ?>')">
				<?php wp_nonce_field( 'exf_retention_delete' ); ?>
				<input type="hidden" name="action" value="exf_retention_delete">
				<table class="widefat striped">
					<thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.exf-ret').forEach(c=>c.checked=this.checked)"></td><th><?php esc_html_e( 'Ex File', 'exfiles' ); ?></th><th><?php esc_html_e( 'Client', 'exfiles' ); ?></th><th><?php esc_html_e( 'Status', 'exfiles' ); ?></th><th><?php esc_html_e( 'Last activity', 'exfiles' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $matters as $m ) : ?>
							<?php $u = get_userdata( (int) get_post_field( 'post_author', $m['id'] ) ); ?>
							<tr>
								<th class="check-column"><input type="checkbox" class="exf-ret" name="ex_files[]" value="<?php echo (int) $m['id']; ?>"></th>
								<td><a href="<?php echo esc_url( get_edit_post_link( $m['id'] ) ); ?>"><?php echo esc_html( get_the_title( $m['id'] ) ); ?></a></td>
								<td><?php echo esc_html( $u ? $u->display_name . ' (' . $u->user_email . ')' : '—' ); ?></td>
								<td><?php echo esc_html( exf_ex_file_status_label( exf_ex_file_status( $m['id'] ) ) ); ?></td>
								<td><?php echo esc_html( wp_date( 'j M Y', $m['last'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Delete selected permanently', 'exfiles' ); ?></button></p>
			</form>
		<?php elseif ( $years ) : ?>
			<p><strong><?php esc_html_e( 'Nothing is past the retention period.', 'exfiles' ); ?></strong></p>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'admin_post_exf_retention_delete',
	function () {
		check_admin_referer( 'exf_retention_delete' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$due   = wp_list_pluck( exf_matters_past_retention(), 'id' );
		$asked = array_map( 'absint', (array) ( $_POST['ex_files'] ?? array() ) );
		$n     = 0;
		foreach ( array_intersect( $asked, $due ) as $id ) {
			exf_delete_matter( $id );
			++$n;
		}
		delete_transient( 'exf_retention_due' );
		wp_safe_redirect( admin_url( 'edit.php?post_type=ex_file&page=exf-retention&deleted=' . $n ) );
		exit;
	}
);

// A questionnaire's asset schedule goes with it.
add_action(
	'before_delete_post',
	function ( $post_id ) {
		if ( 'fs_questionnaire' === get_post_type( $post_id ) ) {
			exf_private_delete( (string) get_post_meta( $post_id, 'q_xlsx', true ) );
		}
	}
);
