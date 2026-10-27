<?php
/**
 * Admin: Ex Files list, the Conflict Review queue, and the conflict check panel on
 * each Ex File (side-by-side comparison with Clear / Request info / Block).
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * List columns and filters
 * ---------------------------------------------------------------------- */

add_filter(
	'manage_ex_file_posts_columns',
	function () {
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => __( 'Ex File', 'exfiles' ),
			'exf_client'   => __( 'Client', 'exfiles' ),
			'exf_ex'       => __( 'Ex', 'exfiles' ),
			'exf_conflict' => __( 'Conflict check', 'exfiles' ),
			'date'         => __( 'Opened', 'exfiles' ),
		);
	}
);

add_action(
	'manage_ex_file_posts_custom_column',
	function ( $column, $post_id ) {
		switch ( $column ) {
			case 'exf_client':
				$uid = (int) get_post_field( 'post_author', $post_id );
				$u   = get_userdata( $uid );
				if ( $u ) {
					printf( '<a href="%s">%s</a><br><small>%s · %s</small>', esc_url( get_edit_user_link( $uid ) ), esc_html( $u->display_name ), esc_html( $u->user_email ), esc_html( exf_status_label( exf_user_status( $uid ) ) ) );
				}
				break;
			case 'exf_ex':
				$ex = exf_ex_details( $post_id );
				echo esc_html( trim( $ex['first_name'] . ' ' . $ex['last_name'] ) );
				break;
			case 'exf_conflict':
				$status = exf_ex_file_status( $post_id );
				echo exf_conflict_badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( 'pending' !== $status ) {
					echo ' <small>' . esc_html(
						/* translators: %d: score */
						sprintf( __( 'score %d', 'exfiles' ), (int) get_post_meta( $post_id, 'conflict_score', true ) )
					) . '</small>';
				}
				if ( get_post_meta( $post_id, 'info_provided', true ) && 'review' === $status ) {
					echo '<br><small>' . esc_html__( 'Client has replied', 'exfiles' ) . '</small>';
				}
				break;
		}
	},
	10,
	2
);

/**
 * Coloured status label.
 *
 * @param string $status Ex File status.
 */
function exf_conflict_badge( $status ) {
	$colours = array(
		'pending'        => '#646970',
		'clear'          => '#00703c',
		'review'         => '#b26200',
		'info_requested' => '#b26200',
		'blocked'        => '#b32d2e',
	);
	return sprintf( '<strong style="color:%s">%s</strong>', esc_attr( $colours[ $status ] ?? '#646970' ), esc_html( exf_ex_file_status_label( $status ) ) );
}

// Filter by conflict status: ?conflict=review shows review + info requested.
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'ex_file' !== $q->get( 'post_type' ) || empty( $_GET['conflict'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$want   = sanitize_key( $_GET['conflict'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$values = 'review' === $want ? array( 'review', 'info_requested' ) : array( $want );
		$q->set(
			'meta_query',
			array(
				array(
					'key'     => 'conflict_status',
					'value'   => $values,
					'compare' => 'IN',
				),
			)
		);
		$q->set( 'orderby', 'date' );
		$q->set( 'order', 'review' === $want ? 'ASC' : 'DESC' ); // Oldest first in the queue.
	}
);

/**
 * Number of Ex Files waiting for the solicitor's decision (not those waiting on the client).
 */
function exf_review_count() {
	$q = new WP_Query(
		array(
			'post_type'      => 'ex_file',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'conflict_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'review', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return (int) $q->found_posts;
}

add_filter(
	'views_edit-ex_file',
	function ( $views ) {
		$base    = admin_url( 'edit.php?post_type=ex_file' );
		$current = isset( $_GET['conflict'] ) ? sanitize_key( $_GET['conflict'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( array(
			'review'  => __( 'Needs review', 'exfiles' ),
			'clear'   => __( 'Clear', 'exfiles' ),
			'blocked' => __( 'Blocked', 'exfiles' ),
			'pending' => __( 'Email not verified', 'exfiles' ),
		) as $key => $label ) {
			$views[ 'exf_' . $key ] = sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( 'conflict', $key, $base ) ), $current === $key ? ' class="current" aria-current="page"' : '', esc_html( $label ) );
		}
		return $views;
	}
);

// "Conflict Review" menu item with a count bubble.
add_action(
	'admin_menu',
	function () {
		$count = exf_review_count();
		$title = __( 'Conflict Review', 'exfiles' ) . ( $count ? ' <span class="awaiting-mod">' . (int) $count . '</span>' : '' );
		add_submenu_page( 'edit.php?post_type=ex_file', __( 'Conflict Review', 'exfiles' ), $title, EXF_CAP, 'edit.php?post_type=ex_file&conflict=review', '', 1 );
	}
);

/* -------------------------------------------------------------------------
 * The conflict panel on an Ex File
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_ex_file',
	function ( $post ) {
		add_meta_box( 'exf_conflict', __( 'Conflict check', 'exfiles' ), 'exf_conflict_box', 'ex_file', 'normal', 'high' );
		add_meta_box( 'exf_client', __( 'Client', 'exfiles' ), 'exf_client_box', 'ex_file', 'side', 'high' );
		add_meta_box( 'exf_log', __( 'Activity', 'exfiles' ), 'exf_log_box', 'ex_file', 'normal', 'low' );
	}
);

/**
 * Conflict panel.
 *
 * @param WP_Post $post Ex File.
 */
function exf_conflict_box( $post ) {
	$status    = exf_ex_file_status( $post->ID );
	$matches   = (array) get_post_meta( $post->ID, 'conflict_matches', true );
	$checked   = (int) get_post_meta( $post->ID, 'conflict_checked_at', true );
	$client_id = (int) $post->post_author;
	$client    = exf_client_details( $client_id );
	$ex        = exf_ex_details( $post->ID );
	$signals   = exf_conflict_signals();
	$replies   = (array) get_post_meta( $post->ID, 'info_replies', true );
	$question  = (string) get_post_meta( $post->ID, 'info_question', true );
	$reviewed  = (int) get_post_meta( $post->ID, 'conflict_reviewed_at', true );
	?>
	<p style="font-size:14px">
		<?php echo exf_conflict_badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $checked ) : ?>
			&nbsp;·&nbsp;
			<?php
			/* translators: 1: score, 2: date */
			echo esc_html( sprintf( __( 'Score %1$d, checked %2$s', 'exfiles' ), (int) get_post_meta( $post->ID, 'conflict_score', true ), wp_date( 'j M Y H:i', $checked ) ) );
			?>
		<?php endif; ?>
		<?php if ( $reviewed ) : ?>
			&nbsp;·&nbsp;
			<?php
			/* translators: 1: name, 2: date */
			echo esc_html( sprintf( __( 'Reviewed by %1$s on %2$s', 'exfiles' ), (string) get_post_meta( $post->ID, 'conflict_reviewed_by', true ), wp_date( 'j M Y H:i', $reviewed ) ) );
			?>
		<?php endif; ?>
	</p>

	<?php if ( 'pending' === $status ) : ?>
		<p><?php esc_html_e( "The client hasn't confirmed their email yet, so the check hasn't run.", 'exfiles' ); ?></p>
	<?php elseif ( ! $matches ) : ?>
		<p><?php esc_html_e( 'No possible matches were found.', 'exfiles' ); ?></p>
	<?php else : ?>
		<table class="widefat striped exf-matches">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Who matched', 'exfiles' ); ?></th>
					<th><?php esc_html_e( 'Compared with', 'exfiles' ); ?></th>
					<th><?php esc_html_e( 'Matching record', 'exfiles' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'Score', 'exfiles' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $matches as $m ) : ?>
				<?php
				$mine = 'ex' === $m['direction'] ? $ex : $client;
				$who  = 'ex' === $m['direction'] ? __( "This client's ex", 'exfiles' ) : __( 'This client', 'exfiles' );
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $who ); ?></strong><br>
						<?php exf_person_summary( $mine, $m['signals'] ); ?>
					</td>
					<td>
						<?php echo esc_html( exf_match_kind( $m ) ); ?><br>
						<small><?php echo esc_html( implode( '; ', array_map( fn( $s ) => $signals[ $s ][1] ?? $s, (array) $m['signals'] ) ) ); ?></small>
					</td>
					<td>
						<strong><?php echo esc_html( $m['label'] ); ?></strong> <?php echo exf_match_link( $m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
						<?php exf_person_summary( $m['raw'] ?? array(), $m['signals'] ); ?>
					</td>
					<td><strong><?php echo (int) $m['score']; ?></strong></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Matching details are in bold. 100 or more blocks automatically; 30–99 needs review.', 'exfiles' ); ?></p>
	<?php endif; ?>

	<?php if ( $question ) : ?>
		<h4><?php esc_html_e( 'Information requested', 'exfiles' ); ?></h4>
		<p><em><?php echo esc_html( $question ); ?></em></p>
	<?php endif; ?>
	<?php foreach ( array_filter( $replies ) as $r ) : ?>
		<div style="background:#f6f7f7;padding:8px 12px;margin:6px 0;border-left:3px solid #2271b1">
			<small><?php echo esc_html( wp_date( 'j M Y H:i', (int) $r['time'] ) ); ?> — <?php esc_html_e( 'client replied', 'exfiles' ); ?><?php echo $r['updated'] ? esc_html( ' (' . __( 'updated', 'exfiles' ) . ': ' . implode( ', ', $r['updated'] ) . ')' ) : ''; ?></small>
			<p style="margin:4px 0 0"><?php echo nl2br( esc_html( $r['reply'] ) ); ?></p>
		</div>
	<?php endforeach; ?>

	<?php if ( 'pending' !== $status ) : ?>
		<hr>
		<h4 style="margin-bottom:6px"><?php esc_html_e( 'Decision', 'exfiles' ); ?></h4>
		<?php // Buttons submit to admin-post.php through the form attribute; they're outside the post form's fields. ?>
		<p>
			<label for="exf-review-note"><?php esc_html_e( 'Note for the file (not sent to the client)', 'exfiles' ); ?></label><br>
			<textarea id="exf-review-note" name="exf_note" form="exf-conflict-form" rows="2" style="width:100%"></textarea>
		</p>
		<p>
			<label for="exf-question"><?php esc_html_e( 'Question for the client (only used with “Request more information”)', 'exfiles' ); ?></label><br>
			<textarea id="exf-question" name="exf_question" form="exf-conflict-form" rows="3" style="width:100%" placeholder="<?php esc_attr_e( "For example: Please give us your ex's date of birth and their address while you lived together.", 'exfiles' ); ?>"></textarea>
		</p>
		<p>
			<button class="button button-primary" name="do" value="clear" form="exf-conflict-form" onclick="return confirm('<?php echo esc_js( __( 'Clear this Ex File? The client will be told they can go ahead.', 'exfiles' ) ); ?>')"><?php esc_html_e( 'Clear', 'exfiles' ); ?></button>
			<button class="button" name="do" value="request" form="exf-conflict-form"><?php esc_html_e( 'Request more information', 'exfiles' ); ?></button>
			<button class="button" name="do" value="block" form="exf-conflict-form" style="color:#b32d2e;border-color:#b32d2e" onclick="return confirm('<?php echo esc_js( __( 'Block this Ex File? The client will be told only that you are unable to act.', 'exfiles' ) ); ?>')"><?php esc_html_e( 'Block', 'exfiles' ); ?></button>
			<button class="button-link" name="do" value="rerun" form="exf-conflict-form" style="margin-left:10px"><?php esc_html_e( 'Run the check again', 'exfiles' ); ?></button>
		</p>
	<?php endif; ?>
	<?php
}

// The decision form lives outside the post edit form (forms can't be nested), linked by the form="" attribute.
add_action(
	'admin_footer-post.php',
	function () {
		global $post;
		if ( ! $post || 'ex_file' !== $post->post_type ) {
			return;
		}
		?>
		<form id="exf-conflict-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="exf_conflict_action">
			<input type="hidden" name="ex_file" value="<?php echo (int) $post->ID; ?>">
			<?php wp_nonce_field( 'exf_conflict_action_' . $post->ID ); ?>
		</form>
		<?php
	}
);

/**
 * Print a person's details, bolding the ones that matched.
 *
 * @param array    $p       Raw person.
 * @param string[] $signals Matching signals.
 */
function exf_person_summary( array $p, array $signals ) {
	$bold = array(
		'name'     => (bool) array_intersect( $signals, array( 'name', 'name_dob', 'name_postcode', 'surname_dob' ) ),
		'dob'      => (bool) array_intersect( $signals, array( 'name_dob', 'surname_dob' ) ),
		'email'    => in_array( 'email', $signals, true ),
		'phone'    => in_array( 'phone', $signals, true ),
		'postcode' => in_array( 'name_postcode', $signals, true ),
	);
	$dob   = exf_norm_dob( $p['dob'] ?? '' );
	$lines = array(
		'name'     => trim( ( $p['first_name'] ?? '' ) . ' ' . ( $p['last_name'] ?? '' ) ) . ( ! empty( $p['prev_names'] ) ? ' (' . $p['prev_names'] . ')' : '' ),
		'dob'      => $dob ? wp_date( 'j M Y', strtotime( $dob . ' 12:00' ) ) : '',
		'email'    => $p['email'] ?? '',
		'phone'    => $p['phone'] ?? '',
		'postcode' => $p['postcode'] ?? '',
	);
	$out   = array();
	foreach ( $lines as $k => $v ) {
		if ( '' !== $v ) {
			$out[] = $bold[ $k ] ? '<strong>' . esc_html( $v ) . '</strong>' : esc_html( $v );
		}
	}
	echo implode( '<br>', $out ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
}

/**
 * What kind of record a match is.
 *
 * @param array $m Match.
 */
function exf_match_kind( array $m ) {
	if ( 'client' === $m['type'] ) {
		return 'prospect' === ( $m['role'] ?? '' )
			? __( "Someone who has signed up but hasn't bought a service", 'exfiles' )
			: __( 'An existing client', 'exfiles' );
	}
	if ( 'ex_file' === $m['type'] ) {
		return 'prospect' === ( $m['role'] ?? '' )
			? __( "Named as the ex by someone who hasn't bought a service", 'exfiles' )
			: __( "Named as the ex in another client's Ex File", 'exfiles' );
	}
	$roles = exf_conflict_record_roles();
	/* translators: %s: role */
	return sprintf( __( 'Conflict register: %s', 'exfiles' ), $roles[ $m['role'] ] ?? $m['role'] );
}

/**
 * Admin link to a matched record.
 *
 * @param array $m Match.
 */
function exf_match_link( array $m ) {
	if ( 'client' === $m['type'] ) {
		$url = get_edit_user_link( $m['id'] );
	} else {
		$url = get_edit_post_link( $m['id'] );
	}
	return $url ? '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'exfiles' ) . '</a>' : '';
}

/**
 * Side box: the client.
 *
 * @param WP_Post $post Ex File.
 */
function exf_client_box( $post ) {
	$uid = (int) $post->post_author;
	$u   = get_userdata( $uid );
	if ( ! $u ) {
		esc_html_e( 'No client.', 'exfiles' );
		return;
	}
	$d = exf_client_details( $uid );
	?>
	<p><strong><a href="<?php echo esc_url( get_edit_user_link( $uid ) ); ?>"><?php echo esc_html( $u->display_name ); ?></a></strong><br>
	<?php echo esc_html( exf_status_label( exf_user_status( $uid ) ) ); ?></p>
	<p><?php exf_person_summary( $d, array() ); ?></p>
	<?php
	$others = array_diff( exf_client_ex_files( $uid ), array( $post->ID ) );
	if ( $others ) {
		echo '<p><strong>' . esc_html__( 'Other Ex Files', 'exfiles' ) . '</strong><br>';
		foreach ( $others as $oid ) {
			printf( '<a href="%s">%s</a> — %s<br>', esc_url( get_edit_post_link( $oid ) ), esc_html( get_the_title( $oid ) ), esc_html( exf_ex_file_status_label( exf_ex_file_status( $oid ) ) ) );
		}
		echo '</p>';
	}
}

/**
 * Activity log box.
 *
 * @param WP_Post $post Ex File.
 */
function exf_log_box( $post ) {
	$log = array_reverse( array_filter( (array) get_post_meta( $post->ID, 'exf_log', true ) ) );
	if ( ! $log ) {
		esc_html_e( 'Nothing yet.', 'exfiles' );
		return;
	}
	echo '<table class="widefat striped"><tbody>';
	foreach ( $log as $row ) {
		printf( '<tr><td style="width:150px">%s</td><td style="width:140px">%s</td><td>%s</td></tr>', esc_html( wp_date( 'j M Y H:i', (int) $row['time'] ) ), esc_html( $row['user'] ), esc_html( $row['msg'] ) );
	}
	echo '</tbody></table>';
}

/* -------------------------------------------------------------------------
 * Decisions
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_exf_conflict_action', 'exf_handle_conflict_action' );

/**
 * Clear / request info / block / re-run.
 */
function exf_handle_conflict_action() {
	$id = isset( $_POST['ex_file'] ) ? absint( $_POST['ex_file'] ) : 0;
	check_admin_referer( 'exf_conflict_action_' . $id );
	if ( ! exf_user_can_manage() || 'ex_file' !== get_post_type( $id ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}

	$do       = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
	$note     = isset( $_POST['exf_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['exf_note'] ) ) : '';
	$question = isset( $_POST['exf_question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['exf_question'] ) ) : '';
	$client   = (int) get_post_field( 'post_author', $id );
	$user     = get_userdata( $client );
	$me       = wp_get_current_user()->display_name;
	$msg      = '';

	if ( $note ) {
		exf_ex_file_log( $id, 'Note: ' . $note );
	}

	switch ( $do ) {
		case 'clear':
			exf_ex_file_set_status( $id, 'clear' );
			exf_ex_file_log( $id, 'Cleared by ' . $me );
			exf_sync_user_status( $client );
			exf_conflict_notify( $id, 'clear' );
			$msg = 'cleared';
			break;

		case 'request':
			if ( '' === trim( $question ) ) {
				wp_safe_redirect( add_query_arg( 'exf_msg', 'need_question', get_edit_post_link( $id, 'url' ) ) );
				exit;
			}
			exf_ex_file_set_status( $id, 'info_requested' );
			update_post_meta( $id, 'info_question', $question );
			delete_post_meta( $id, 'info_provided' );
			exf_ex_file_log( $id, 'More information requested by ' . $me . ': ' . $question );
			exf_sync_user_status( $client );
			if ( $user ) {
				exf_send_email( 'info_requested', $user->user_email, exf_ex_file_email_vars( $id ) + array( 'question' => $question, 'link' => exf_client_link( $client, exf_portal_url( 'ex-files', $id ) ) ) );
			}
			$msg = 'requested';
			break;

		case 'block':
			exf_ex_file_set_status( $id, 'blocked' );
			exf_ex_file_log( $id, 'Blocked by ' . $me );
			exf_sync_user_status( $client );
			if ( $user ) {
				exf_send_email( 'unable', $user->user_email, exf_ex_file_email_vars( $id ) );
			}
			$msg = 'blocked';
			break;

		case 'rerun':
			$outcome = exf_conflict_run( $id, false );
			$msg     = 'rerun_' . $outcome;
			break;
	}

	if ( in_array( $do, array( 'clear', 'request', 'block' ), true ) ) {
		update_post_meta( $id, 'conflict_reviewed_at', time() );
		update_post_meta( $id, 'conflict_reviewed_by', $me );
	}

	wp_safe_redirect( add_query_arg( 'exf_msg', $msg, get_edit_post_link( $id, 'url' ) ) );
	exit;
}

add_action(
	'admin_notices',
	function () {
		if ( empty( $_GET['exf_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$messages = array(
			'cleared'        => array( 'success', __( 'Cleared. The client has been emailed.', 'exfiles' ) ),
			'requested'      => array( 'success', __( 'Request sent. The client has been emailed and can reply from their account.', 'exfiles' ) ),
			'blocked'        => array( 'success', __( 'Blocked. The client has been told you are unable to act.', 'exfiles' ) ),
			'need_question'  => array( 'error', __( 'Please write the question for the client.', 'exfiles' ) ),
			'rerun_clear'    => array( 'info', __( 'Check run again: no conflict found. No email was sent.', 'exfiles' ) ),
			'rerun_review'   => array( 'info', __( 'Check run again: needs review. No email was sent.', 'exfiles' ) ),
			'rerun_blocked'  => array( 'warning', __( 'Check run again: blocked. No email was sent.', 'exfiles' ) ),
		);
		$key      = sanitize_key( $_GET['exf_msg'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $messages[ $key ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $messages[ $key ][0] ), esc_html( $messages[ $key ][1] ) );
		}
	}
);
