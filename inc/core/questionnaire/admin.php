<?php
/**
 * Questionnaires in the admin: list columns, a read-only view of the answers,
 * downloads (PDF and asset schedule) and "Reopen for the client".
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'manage_fs_questionnaire_posts_columns',
	function () {
		return array(
			'cb'            => '<input type="checkbox" />',
			'title'         => __( 'Questionnaire', 'exfiles' ),
			'exf_client'    => __( 'Client', 'exfiles' ),
			'exf_status'    => __( 'Status', 'exfiles' ),
			'exf_submitted' => __( 'Submitted', 'exfiles' ),
			'exf_files'     => __( 'Downloads', 'exfiles' ),
		);
	}
);

add_action(
	'manage_fs_questionnaire_posts_custom_column',
	function ( $column, $q ) {
		switch ( $column ) {
			case 'exf_client':
				$u = get_userdata( (int) get_post_field( 'post_author', $q ) );
				echo $u ? esc_html( $u->display_name ) : '—';
				break;
			case 'exf_status':
				echo esc_html( exf_questionnaire_status_label( exf_q_status( $q ) ) );
				break;
			case 'exf_submitted':
				$at = (int) get_post_meta( $q, 'q_submitted_at', true );
				echo esc_html( $at && 'submitted' === exf_q_status( $q ) ? wp_date( 'j M Y', $at ) : '—' );
				break;
			case 'exf_files':
				exf_q_download_links( $q );
				break;
		}
	},
	10,
	2
);

// Filter: ?q_status=submitted.
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() && $query->is_main_query() && 'fs_questionnaire' === $query->get( 'post_type' ) && ! empty( $_GET['q_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query->set( 'meta_key', 'q_status' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'meta_value', sanitize_key( wp_unslash( $_GET['q_status'] ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.Security.NonceVerification.Recommended
		}
	}
);

add_filter(
	'views_edit-fs_questionnaire',
	function ( $views ) {
		$current = isset( $_GET['q_status'] ) ? sanitize_key( wp_unslash( $_GET['q_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'to_do', 'in_progress', 'submitted' ) as $s ) {
			$views[ 'q_' . $s ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( admin_url( 'edit.php?post_type=fs_questionnaire&q_status=' . $s ) ),
				$current === $s ? ' class="current"' : '',
				esc_html( exf_questionnaire_status_label( $s ) )
			);
		}
		return $views;
	}
);

/**
 * Download links for a questionnaire.
 *
 * @param int $q Questionnaire.
 */
function exf_q_download_links( $q ) {
	$links = array();
	$pdf   = (int) get_post_meta( $q, 'q_pdf_doc', true );
	if ( $pdf && 'client_document' === get_post_type( $pdf ) ) {
		$links[] = '<a href="' . esc_url( exf_document_url( $pdf ) ) . '">' . esc_html__( 'PDF', 'exfiles' ) . '</a>';
	}
	if ( get_post_meta( $q, 'q_xlsx', true ) ) {
		$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_q_xlsx&q=' . $q ), 'exf_q_xlsx_' . $q ) ) . '">' . esc_html__( 'Asset schedule (Excel)', 'exfiles' ) . '</a>';
	}
	echo $links ? implode( ' · ', $links ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

add_action(
	'admin_post_exf_q_xlsx',
	function () {
		$q = isset( $_GET['q'] ) ? absint( $_GET['q'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'exf_q_xlsx_' . $q );
		if ( ! exf_user_can_manage() || 'fs_questionnaire' !== get_post_type( $q ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		exf_private_stream( (string) get_post_meta( $q, 'q_xlsx', true ), (string) get_post_meta( $q, 'q_xlsx_name', true ) );
	}
);

add_action(
	'admin_post_exf_q_reopen',
	function () {
		$q = isset( $_GET['q'] ) ? absint( $_GET['q'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'exf_q_reopen_' . $q );
		if ( ! exf_user_can_manage() || 'fs_questionnaire' !== get_post_type( $q ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		if ( 'submitted' === exf_q_status( $q ) ) {
			exf_q_reopen( $q );
		}
		wp_safe_redirect( get_edit_post_link( $q, 'url' ) );
		exit;
	}
);

add_action(
	'admin_post_exf_q_regenerate',
	function () {
		$q = isset( $_GET['q'] ) ? absint( $_GET['q'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'exf_q_regenerate_' . $q );
		if ( ! exf_user_can_manage() || 'fs_questionnaire' !== get_post_type( $q ) || 'submitted' !== exf_q_status( $q ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		exf_q_generate_pdf( $q );
		exf_q_generate_xlsx( $q );
		exf_q_log( $q, 'PDF and asset schedule recreated' );
		wp_safe_redirect( get_edit_post_link( $q, 'url' ) );
		exit;
	}
);

add_action(
	'add_meta_boxes_fs_questionnaire',
	function () {
		remove_meta_box( 'submitdiv', 'fs_questionnaire', 'side' );
		remove_meta_box( 'authordiv', 'fs_questionnaire', 'normal' );
		remove_meta_box( 'slugdiv', 'fs_questionnaire', 'normal' );
		add_meta_box( 'exf_q_status', __( 'Questionnaire', 'exfiles' ), 'exf_q_status_box', 'fs_questionnaire', 'side', 'high' );
		add_meta_box( 'exf_q_answers', __( 'Answers', 'exfiles' ), 'exf_q_answers_box', 'fs_questionnaire', 'normal', 'high' );
	}
);

add_action(
	'admin_head-post.php',
	function () {
		$screen = get_current_screen();
		if ( $screen && 'fs_questionnaire' === $screen->post_type ) {
			remove_post_type_support( 'fs_questionnaire', 'title' );
			echo '<style>
				.q-sum{margin:0 0 22px}.q-sum-head{display:flex;justify-content:space-between;align-items:baseline;border-bottom:1px solid #c3c4c7;margin-bottom:6px}
				.q-sum-head h3{margin:0 0 6px;font-size:15px}.q-sum-table{width:100%;border-collapse:collapse}
				.q-sum-table th,.q-sum-table td{text-align:left;vertical-align:top;padding:6px 10px;border-bottom:1px solid #f0f0f1}
				.q-sum-table th{width:45%;font-weight:400;color:#50575e}.q-sum-sub th{font-weight:600;color:#1d2327;background:#f6f7f7}
				.q-sum-total th,.q-sum-total td{font-weight:600}.q-flag--estimate{background:#fcf0c3;padding:1px 4px}
				.q-flag--dont_know{background:#fbe3e0;padding:1px 4px}.q-out-table{width:100%;border-collapse:collapse}
				.q-out-table th,.q-out-table td{padding:4px 6px;border-bottom:1px solid #f0f0f1;text-align:left}
				.q-sum-empty{color:#646970}
			</style>';
		}
	}
);

/**
 * Status box.
 *
 * @param WP_Post $post Questionnaire.
 */
function exf_q_status_box( $post ) {
	$q       = $post->ID;
	$status  = exf_q_status( $q );
	$client  = get_userdata( (int) $post->post_author );
	$ex_file = (int) get_post_meta( $q, 'ex_file_id', true );
	$order   = (int) get_post_meta( $q, 'order_id', true );
	$log     = array_reverse( array_filter( (array) get_post_meta( $q, 'q_log', true ) ) );
	?>
	<p><strong><?php echo esc_html( exf_questionnaire_status_label( $status ) ); ?></strong>
		<?php
		$at = (int) get_post_meta( $q, 'q_submitted_at', true );
		if ( 'submitted' === $status && $at ) {
			echo '<br>' . esc_html( wp_date( 'j M Y, g:ia', $at ) );
		}
		?>
	</p>
	<p>
		<?php esc_html_e( 'Client:', 'exfiles' ); ?> <?php echo $client ? '<a href="' . esc_url( get_edit_user_link( $client->ID ) ) . '">' . esc_html( $client->display_name ) . '</a>' : '—'; ?><br>
		<?php esc_html_e( 'Ex File:', 'exfiles' ); ?> <?php echo $ex_file ? '<a href="' . esc_url( get_edit_post_link( $ex_file ) ) . '">' . esc_html( get_the_title( $ex_file ) ) . '</a>' : '—'; ?><br>
		<?php esc_html_e( 'Order:', 'exfiles' ); ?> <?php echo $order ? '<a href="' . esc_url( get_edit_post_link( $order ) ) . '">' . esc_html( exf_q_service_name( $q ) ) . '</a>' : '—'; ?>
	</p>
	<?php if ( $ex_file && get_post_meta( $ex_file, 'flag_unmarried', true ) ) : ?>
		<p style="color:#b32d2e"><strong><?php esc_html_e( 'The client says they are not married to their ex. Please check the matter is suitable.', 'exfiles' ); ?></strong></p>
	<?php endif; ?>
	<?php if ( 'submitted' === $status ) : ?>
		<p><?php exf_q_download_links( $q ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_q_reopen&q=' . $q ), 'exf_q_reopen_' . $q ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Reopen this questionnaire so the client can change their answers? They will be emailed.', 'exfiles' ) ); ?>')"><?php esc_html_e( 'Reopen for the client', 'exfiles' ); ?></a>
		</p>
		<p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_q_regenerate&q=' . $q ), 'exf_q_regenerate_' . $q ) ); ?>"><?php esc_html_e( 'Recreate PDF and schedule', 'exfiles' ); ?></a></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'The client fills this in from their Ex File. Answers saved so far are shown here.', 'exfiles' ); ?></p>
	<?php endif; ?>
	<?php if ( $log ) : ?>
		<h4 style="margin-bottom:4px"><?php esc_html_e( 'History', 'exfiles' ); ?></h4>
		<ul style="margin-top:0">
			<?php foreach ( array_slice( $log, 0, 12 ) as $row ) : ?>
				<li><?php echo esc_html( wp_date( 'j M H:i', (int) $row['time'] ) . ' – ' . $row['msg'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php
}

/**
 * Answers box (read-only).
 *
 * @param WP_Post $post Questionnaire.
 */
function exf_q_answers_box( $post ) {
	$data = exf_q_data( $post->ID );
	if ( ! $data ) {
		echo '<p>' . esc_html__( 'No answers yet.', 'exfiles' ) . '</p>';
		return;
	}
	$counts = exf_q_flag_counts( $data );
	echo '<p>';
	/* translators: 1: count, 2: count */
	echo esc_html( sprintf( __( '%1$d answers marked "Don\'t know" (red) and %2$d estimates (yellow).', 'exfiles' ), $counts['dont_know'], $counts['estimate'] ) );
	echo '</p>';
	echo exf_q_summary_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in parts.
}

// Flag on the Ex File list and screen when the client isn't married.
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'ex_file' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id && get_post_meta( $id, 'flag_unmarried', true ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'In their questionnaire, the client says they are not married to (or in a civil partnership with) their ex. Please check the matter is suitable.', 'exfiles' ) . '</p></div>';
		}
	}
);
