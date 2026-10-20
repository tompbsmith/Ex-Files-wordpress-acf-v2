<?php
/**
 * Activity log: one place to see who did what across the site (Ex Files → Activity log).
 *
 * Everything written to an Ex File's own activity is copied here, plus staff
 * logins, staff downloads of client files, deletions and settings-level actions.
 * Entries are kept for the client retention period.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_AUDIT_DB_VERSION = 1;

/**
 * Table name.
 */
function exf_audit_table() {
	global $wpdb;
	return $wpdb->prefix . 'exf_audit';
}

add_action(
	'init',
	function () {
		if ( (int) get_option( 'exf_audit_db' ) >= EXF_AUDIT_DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = exf_audit_table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_name varchar(190) NOT NULL DEFAULT '',
				action varchar(60) NOT NULL DEFAULT '',
				object_type varchar(40) NOT NULL DEFAULT '',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				summary text NOT NULL,
				ip varchar(45) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY object (object_type,object_id)
			) {$charset};"
		);
		update_option( 'exf_audit_db', EXF_AUDIT_DB_VERSION, false );
	},
	1
);

/**
 * Record an action.
 *
 * @param string $action      Short code, e.g. conflict_clear, document_download.
 * @param string $summary     What happened, in words.
 * @param string $object_type ex_file, order, questionnaire, document, user, settings.
 * @param int    $object_id   ID.
 */
function exf_audit( $action, $summary, $object_type = '', $object_id = 0 ) {
	global $wpdb;
	$user = wp_get_current_user();
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		exf_audit_table(),
		array(
			'created_at'  => current_time( 'mysql', true ),
			'user_id'     => $user ? (int) $user->ID : 0,
			'user_name'   => $user && $user->exists() ? $user->display_name : ( wp_doing_cron() ? 'Scheduled task' : 'System' ),
			'action'      => substr( sanitize_key( $action ), 0, 60 ),
			'object_type' => substr( sanitize_key( $object_type ), 0, 40 ),
			'object_id'   => (int) $object_id,
			'summary'     => wp_strip_all_tags( (string) $summary ),
			'ip'          => substr( $ip, 0, 45 ),
		)
	);
}

// Everything logged on an Ex File goes into the site-wide log too.
add_action(
	'exf_ex_file_logged',
	function ( $ex_file_id, $message ) {
		exf_audit( 'ex_file', get_the_title( $ex_file_id ) . ': ' . $message, 'ex_file', $ex_file_id );
	},
	10,
	2
);

// Staff logins (clients' logins aren't recorded, to keep this readable).
add_action(
	'wp_login',
	function ( $login, $user ) {
		if ( user_can( $user, EXF_CAP ) ) {
			wp_set_current_user( $user->ID );
			exf_audit( 'staff_login', 'Logged in', 'user', $user->ID );
		}
	},
	10,
	2
);

/**
 * Old entries are removed with the client retention period.
 */
function exf_audit_cleanup() {
	global $wpdb;
	$years = exf_retention_years();
	if ( $years < 1 ) {
		return;
	}
	$table = exf_audit_table();
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', strtotime( '-' . $years . ' years' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
}
add_action( 'exf_daily_cleanup', 'exf_audit_cleanup' );

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=ex_file', __( 'Activity log', 'exfiles' ), __( 'Activity log', 'exfiles' ), EXF_CAP, 'exf-activity', 'exf_audit_page' );
	},
	30
);

/**
 * Query the log.
 *
 * @param string $search Text search.
 * @param int    $page   Page (1-based).
 * @param int    $per    Per page (0 = all).
 * @return array [ rows, total ]
 */
function exf_audit_query( $search, $page = 1, $per = 50 ) {
	global $wpdb;
	$table = exf_audit_table();
	$where = '1=1';
	$args  = array();
	if ( '' !== $search ) {
		$like  = '%' . $wpdb->esc_like( $search ) . '%';
		$where = '(summary LIKE %s OR user_name LIKE %s OR action LIKE %s)';
		$args  = array( $like, $like, $like );
	}
	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
	$total     = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB
	$sql       = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC";
	if ( $per ) {
		$sql .= ' LIMIT ' . (int) $per . ' OFFSET ' . (int) ( ( max( 1, $page ) - 1 ) * $per );
	}
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
	return array( (array) $rows, $total );
}

/**
 * Link to the thing an entry is about.
 *
 * @param object $row Entry.
 */
function exf_audit_object_link( $row ) {
	$id = (int) $row->object_id;
	if ( ! $id ) {
		return '';
	}
	if ( 'user' === $row->object_type ) {
		$u = get_userdata( $id );
		return $u ? '<a href="' . esc_url( get_edit_user_link( $id ) ) . '">' . esc_html( $u->display_name ) . '</a>' : '';
	}
	return get_post( $id ) ? '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html__( 'Open', 'exfiles' ) . '</a>' : esc_html__( '(deleted)', 'exfiles' );
}

/**
 * The Activity log page.
 */
function exf_audit_page() {
	if ( ! exf_user_can_manage() ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable
	list( $rows, $total ) = exf_audit_query( $search, $page );
	$pages                = (int) ceil( $total / 50 );
	$base                 = admin_url( 'edit.php?post_type=ex_file&page=exf-activity' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Activity log', 'exfiles' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'exf_audit_csv', 's' => $search ), admin_url( 'admin-post.php' ) ), 'exf_audit_csv' ) ); ?>"><?php esc_html_e( 'Download CSV', 'exfiles' ); ?></a>
		<p><?php esc_html_e( 'Conflict decisions, documents, questionnaires, appointments, staff logins and deletions, newest first. Entries are kept for the client retention period.', 'exfiles' ); ?></p>
		<form method="get">
			<input type="hidden" name="post_type" value="ex_file"><input type="hidden" name="page" value="exf-activity">
			<p class="search-box"><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search names or actions', 'exfiles' ); ?>"> <button class="button"><?php esc_html_e( 'Search', 'exfiles' ); ?></button></p>
		</form>
		<table class="widefat striped">
			<thead><tr><th style="width:150px"><?php esc_html_e( 'When', 'exfiles' ); ?></th><th style="width:150px"><?php esc_html_e( 'Who', 'exfiles' ); ?></th><th><?php esc_html_e( 'What', 'exfiles' ); ?></th><th style="width:90px"></th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Nothing recorded yet.', 'exfiles' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'j M Y H:i', strtotime( $row->created_at . ' UTC' ) ) ); ?></td>
						<td><?php echo esc_html( $row->user_name ); ?></td>
						<td><?php echo esc_html( $row->summary ); ?></td>
						<td><?php echo exf_audit_object_link( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $pages > 1 ) : ?>
			<p class="tablenav-pages" style="margin-top:12px">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( array( 's' => $search, 'paged' => '%#%' ), $base ),
							'format'  => '',
							'current' => $page,
							'total'   => $pages,
						)
					)
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'admin_post_exf_audit_csv',
	function () {
		check_admin_referer( 'exf_audit_csv' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		list( $rows ) = exf_audit_query( $search, 1, 0 );
		exf_audit( 'audit_export', 'Downloaded the activity log' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="activity-log-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'When (UTC)', 'Who', 'Action', 'What', 'Object type', 'Object ID', 'IP' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array( $r->created_at, $r->user_name, $r->action, exf_csv_safe( $r->summary ), $r->object_type, $r->object_id, $r->ip ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
);

/**
 * Stop a CSV cell being read as a formula by Excel.
 *
 * @param string $value Value.
 */
function exf_csv_safe( $value ) {
	$value = (string) $value;
	return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
}
