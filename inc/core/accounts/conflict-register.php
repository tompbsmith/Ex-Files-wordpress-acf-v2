<?php
/**
 * Conflict Register: people from outside the website the conflict check must know
 * about (existing and past clients, and the people they were against), with a CSV
 * import so the solicitor's existing records can be loaded before launch.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register roles.
 */
function exf_conflict_record_roles() {
	return array(
		'former_client'  => __( 'Client (current or former)', 'exfiles' ),
		'opposing_party' => __( 'Opposing party (a client’s ex)', 'exfiles' ),
		'other'          => __( 'Other (always review)', 'exfiles' ),
	);
}

// Title = the person's name, so the list is readable.
add_action(
	'acf/save_post',
	function ( $post_id ) {
		if ( ! is_numeric( $post_id ) || 'conflict_record' !== get_post_type( $post_id ) ) {
			return;
		}
		$title = trim( get_post_meta( $post_id, 'first_name', true ) . ' ' . get_post_meta( $post_id, 'last_name', true ) );
		if ( $title && get_the_title( $post_id ) !== $title ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $title,
				)
			);
		}
	},
	20
);

add_filter(
	'manage_conflict_record_posts_columns',
	function () {
		return array(
			'cb'         => '<input type="checkbox" />',
			'title'      => __( 'Name', 'exfiles' ),
			'exf_role'   => __( 'Role', 'exfiles' ),
			'exf_detail' => __( 'Details', 'exfiles' ),
			'exf_source' => __( 'Source', 'exfiles' ),
		);
	}
);

add_action(
	'manage_conflict_record_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'exf_role' === $column ) {
			$roles = exf_conflict_record_roles();
			$role  = (string) get_post_meta( $post_id, 'record_role', true );
			echo esc_html( $roles[ $role ] ?? $roles['former_client'] );
		} elseif ( 'exf_detail' === $column ) {
			$dob   = exf_norm_dob( get_post_meta( $post_id, 'dob', true ) );
			$parts = array_filter(
				array(
					$dob ? wp_date( 'j M Y', strtotime( $dob . ' 12:00' ) ) : '',
					get_post_meta( $post_id, 'email', true ),
					get_post_meta( $post_id, 'phone', true ),
					get_post_meta( $post_id, 'postcode', true ),
				)
			);
			echo esc_html( implode( ' · ', $parts ) );
		} elseif ( 'exf_source' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, 'source', true ) );
		}
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * CSV import
 * ---------------------------------------------------------------------- */

/**
 * CSV columns, in order.
 */
function exf_conflict_csv_columns() {
	return array( 'first_name', 'last_name', 'previous_names', 'date_of_birth', 'email', 'phone', 'postcode', 'role', 'source' );
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=ex_file',
			__( 'Import Conflict Register', 'exfiles' ),
			__( 'Import Register', 'exfiles' ),
			EXF_CAP,
			'exf-conflict-import',
			'exf_conflict_import_page'
		);
	},
	20
);

/**
 * Import page.
 */
function exf_conflict_import_page() {
	$done = get_transient( 'exf_import_result_' . get_current_user_id() );
	if ( $done ) {
		delete_transient( 'exf_import_result_' . get_current_user_id() );
	}
	$template = wp_nonce_url( admin_url( 'admin-post.php?action=exf_conflict_template' ), 'exf_conflict_template' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Import the Conflict Register', 'exfiles' ); ?></h1>
		<?php if ( $done ) : ?>
			<div class="notice notice-success"><p>
				<?php
				/* translators: 1: added, 2: skipped */
				echo esc_html( sprintf( __( 'Imported %1$d people. Skipped %2$d rows (missing a name, or already in the register).', 'exfiles' ), $done['added'], $done['skipped'] ) );
				?>
			</p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'The conflict check compares every new client and their ex with existing clients. People you acted for (or against) before the website, or outside it, need to be in the register so the check knows about them.', 'exfiles' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( $template ); ?>"><?php esc_html_e( 'Download the CSV template', 'exfiles' ); ?></a></p>
		<p><?php esc_html_e( 'Columns:', 'exfiles' ); ?> <code><?php echo esc_html( implode( ', ', exf_conflict_csv_columns() ) ); ?></code></p>
		<ul style="list-style:disc;padding-left:20px">
			<li><?php esc_html_e( 'First and last name are required. Everything else is optional, but the more you include, the fewer manual reviews you will get.', 'exfiles' ); ?></li>
			<li><?php esc_html_e( 'Dates as DD/MM/YYYY or YYYY-MM-DD. Separate several previous names with semicolons.', 'exfiles' ); ?></li>
			<li><?php esc_html_e( 'Role: client, opposing, or other. Blank means client. Add each client’s ex as a separate row with role “opposing”.', 'exfiles' ); ?></li>
			<li><?php esc_html_e( 'Rows matching someone already in the register (same name and date of birth, or same email) are skipped, so the same file can be imported twice safely.', 'exfiles' ); ?></li>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="exf_conflict_import">
			<?php wp_nonce_field( 'exf_conflict_import' ); ?>
			<p><input type="file" name="csv" accept=".csv,text/csv" required></p>
			<p><button class="button button-primary"><?php esc_html_e( 'Import', 'exfiles' ); ?></button></p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_exf_conflict_template',
	function () {
		check_admin_referer( 'exf_conflict_template' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="conflict-register-template.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, exf_conflict_csv_columns() );
		fputcsv( $out, array( 'Jane', 'Smith', 'Jane Brown', '01/02/1985', 'jane@example.com', '07700 900123', 'SW1A 1AA', 'client', 'Case 2023-014' ) );
		fputcsv( $out, array( 'John', 'Smith', '', '', '', '', 'SW1A 1AA', 'opposing', 'Case 2023-014' ) );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
);

add_action(
	'admin_post_exf_conflict_import',
	function () {
		check_admin_referer( 'exf_conflict_import' );
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$file = $_FILES['csv']['tmp_name'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $file || ! is_uploaded_file( $file ) ) {
			wp_die( esc_html__( 'Please choose a CSV file.', 'exfiles' ) );
		}
		$result = exf_conflict_import_csv( $file );
		set_transient( 'exf_import_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit.php?post_type=ex_file&page=exf-conflict-import' ) );
		exit;
	}
);

/**
 * Import a CSV file into the register.
 *
 * @param string $path File path.
 * @return array { added, skipped }
 */
function exf_conflict_import_csv( $path ) {
	$added   = 0;
	$skipped = 0;
	$fh      = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $fh ) {
		return compact( 'added', 'skipped' );
	}

	// Existing records, to skip duplicates.
	$seen = array();
	foreach ( exf_conflict_pool_register() as $row ) {
		$seen[] = exf_conflict_dupe_keys( $row['raw'] );
	}
	$seen = array_flip( array_merge( array(), ...$seen ) );

	$header = null;
	$roles  = array(
		''         => 'former_client',
		'client'   => 'former_client',
		'former'   => 'former_client',
		'opposing' => 'opposing_party',
		'ex'       => 'opposing_party',
		'other'    => 'other',
	);

	while ( ( $cells = fgetcsv( $fh ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		$cells = array_map( fn( $c ) => trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $c ) ), $cells );
		if ( null === $header ) {
			$lower = array_map( 'strtolower', $cells );
			if ( in_array( 'first_name', $lower, true ) ) {
				$header = $lower;
				continue;
			}
			$header = exf_conflict_csv_columns(); // No header row: assume the template order.
		}
		$row = array();
		foreach ( $header as $i => $col ) {
			$row[ $col ] = $cells[ $i ] ?? '';
		}

		$d = array(
			'first_name' => sanitize_text_field( $row['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $row['last_name'] ?? '' ),
			'prev_names' => sanitize_text_field( str_replace( ';', ',', $row['previous_names'] ?? '' ) ),
			'dob'        => str_replace( '-', '', exf_norm_dob( $row['date_of_birth'] ?? '' ) ),
			'email'      => sanitize_email( $row['email'] ?? '' ),
			'phone'      => sanitize_text_field( $row['phone'] ?? '' ),
			'postcode'   => strtoupper( sanitize_text_field( $row['postcode'] ?? '' ) ),
		);
		if ( '' === $d['first_name'] || '' === $d['last_name'] ) {
			++$skipped;
			continue;
		}
		$keys = exf_conflict_dupe_keys( $d );
		if ( array_intersect_key( $seen, array_flip( $keys ) ) ) {
			++$skipped;
			continue;
		}

		$id = wp_insert_post(
			array(
				'post_type'   => 'conflict_record',
				'post_status' => 'publish',
				'post_title'  => $d['first_name'] . ' ' . $d['last_name'],
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			++$skipped;
			continue;
		}
		foreach ( $d as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		update_post_meta( $id, 'record_role', $roles[ strtolower( $row['role'] ?? '' ) ] ?? 'former_client' );
		update_post_meta( $id, 'source', sanitize_text_field( $row['source'] ?? '' ) );
		$seen += array_flip( $keys );
		++$added;
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	return compact( 'added', 'skipped' );
}

/**
 * Keys that identify a duplicate register entry.
 *
 * @param array $d Person.
 * @return string[]
 */
function exf_conflict_dupe_keys( array $d ) {
	$keys = array();
	$name = exf_norm_name( ( $d['first_name'] ?? '' ) . ' ' . ( $d['last_name'] ?? '' ) );
	$dob  = exf_norm_dob( $d['dob'] ?? '' );
	if ( $name ) {
		$keys[] = 'n:' . $name . '|' . $dob;
	}
	if ( ! empty( $d['email'] ) ) {
		$keys[] = 'e:' . strtolower( $d['email'] );
	}
	return $keys;
}
