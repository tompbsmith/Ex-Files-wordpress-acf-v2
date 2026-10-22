<?php
/**
 * Client documents: files the firm shares with a client, and files the client sends in.
 *
 * Files are kept in the private uploads folder (never the media library) and are only
 * served through exf_document_download(), to staff or to the client who owns them.
 *
 * Meta on a client_document post:
 *   ex_file_id, client_id, doc_type, doc_note, file, file_name, file_size,
 *   uploaded_by (firm|client), viewed_at (first download by the client), notified_at
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_DOC_MAX_MB      = 15;
const EXF_DOC_HOURLY_MAX  = 20;
const EXF_DOC_PRIVATE_DIR = 'documents';

/**
 * Document types the firm can share.
 */
function exf_document_types() {
	return array(
		'report'        => __( 'Report', 'exfiles' ),
		'letter'        => __( 'Letter', 'exfiles' ),
		'consent_order' => __( 'Consent order', 'exfiles' ),
		'form'          => __( 'Form to complete', 'exfiles' ),
		'questionnaire' => __( 'Questionnaire answers', 'exfiles' ),
		'other'         => __( 'Other', 'exfiles' ),
	);
}

/**
 * Document types a client can upload.
 */
function exf_client_upload_types() {
	return array(
		'bank_statement' => __( 'Bank statement', 'exfiles' ),
		'pension'        => __( 'Pension statement', 'exfiles' ),
		'payslip'        => __( 'Payslip or P60', 'exfiles' ),
		'property'       => __( 'Property or mortgage', 'exfiles' ),
		'agreement'      => __( 'Draft agreement', 'exfiles' ),
		'other'          => __( 'Something else', 'exfiles' ),
	);
}

/**
 * Label for any document type.
 *
 * @param string $type Type key.
 */
function exf_document_type_label( $type ) {
	$all = exf_document_types() + exf_client_upload_types();
	return $all[ $type ] ?? __( 'Document', 'exfiles' );
}

/**
 * File types accepted from clients: extension => mime.
 */
function exf_document_mimes() {
	return array(
		'pdf'      => 'application/pdf',
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'heic'     => 'image/heic',
		'doc'      => 'application/msword',
		'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'      => 'application/vnd.ms-excel',
		'xlsx'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'csv'      => 'text/csv',
		'txt'      => 'text/plain',
	);
}

/**
 * Largest upload allowed, in bytes (our limit, or the server's if lower).
 */
function exf_document_max_bytes() {
	return (int) min( EXF_DOC_MAX_MB * MB_IN_BYTES, wp_max_upload_size() );
}

/**
 * Check and store an uploaded file.
 *
 * @param array $file  One entry from $_FILES.
 * @param bool  $strict Limit to exf_document_mimes() (client uploads).
 * @return array|WP_Error [ file, file_name, file_size ]
 */
function exf_document_store_upload( $file, $strict = true ) {
	if ( empty( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
		return new WP_Error( 'exf_no_file', __( 'Please choose a file.', 'exfiles' ) );
	}
	if ( in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || (int) $file['size'] > exf_document_max_bytes() ) {
		/* translators: %s: size such as 15 MB */
		return new WP_Error( 'exf_too_big', sprintf( __( 'That file is too large. The limit is %s.', 'exfiles' ), size_format( exf_document_max_bytes() ) ) );
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'exf_upload_failed', __( "The upload didn't work. Please try again.", 'exfiles' ) );
	}
	if ( (int) $file['size'] <= 0 ) {
		return new WP_Error( 'exf_empty', __( 'That file is empty.', 'exfiles' ) );
	}

	$name  = sanitize_file_name( wp_basename( (string) $file['name'] ) );
	$mimes = $strict ? exf_document_mimes() : null;
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $name, $mimes );
	if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
		return new WP_Error( 'exf_bad_type', __( "We can't accept that type of file. Please send a PDF, Word or Excel document, or a photo.", 'exfiles' ) );
	}
	if ( ! empty( $check['proper_filename'] ) ) {
		$name = $check['proper_filename'];
	}

	$bytes = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$saved = false === $bytes ? false : exf_private_save( EXF_DOC_PRIVATE_DIR . '/' . gmdate( 'Y/m' ), $name, $bytes );
	if ( ! $saved ) {
		return new WP_Error( 'exf_save_failed', __( "We couldn't save that file. Please try again.", 'exfiles' ) );
	}
	return array(
		'file'      => $saved,
		'file_name' => $name,
		'file_size' => (int) $file['size'],
	);
}

/**
 * Create a document record.
 *
 * @param int    $ex_file_id Ex File.
 * @param array  $stored     From exf_document_store_upload().
 * @param string $type       Type key.
 * @param string $note       Note.
 * @param string $by         firm|client.
 * @param string $title      Title (defaults to the type and file name).
 * @return int Document ID.
 */
function exf_document_create( $ex_file_id, array $stored, $type, $note, $by, $title = '' ) {
	$client = (int) get_post_field( 'post_author', $ex_file_id );
	$id     = wp_insert_post(
		array(
			'post_type'   => 'client_document',
			'post_status' => 'publish',
			'post_title'  => $title ? $title : exf_document_type_label( $type ) . ' – ' . $stored['file_name'],
			'post_author' => 'client' === $by ? $client : get_current_user_id(),
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		exf_private_delete( $stored['file'] );
		return 0;
	}
	$meta = array(
		'ex_file_id'  => (int) $ex_file_id,
		'client_id'   => $client,
		'doc_type'    => $type,
		'doc_note'    => $note,
		'uploaded_by' => $by,
	) + $stored;
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	return (int) $id;
}

/**
 * Documents for a client, newest first.
 *
 * @param int    $user_id Client.
 * @param int    $ex_file Optional Ex File.
 * @param string $by      Optional: firm|client.
 * @return int[]
 */
function exf_client_documents( $user_id, $ex_file = 0, $by = '' ) {
	$meta = array(
		array(
			'key'   => 'client_id',
			'value' => (int) $user_id,
		),
	);
	if ( $ex_file ) {
		$meta[] = array(
			'key'   => 'ex_file_id',
			'value' => (int) $ex_file,
		);
	}
	if ( $by ) {
		$meta[] = array(
			'key'   => 'uploaded_by',
			'value' => $by,
		);
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'client_document',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		)
	);
}

/**
 * Documents from the firm that the client hasn't opened yet.
 *
 * @param int $user_id Client.
 * @return int[]
 */
function exf_client_new_documents( $user_id ) {
	return array_values(
		array_filter(
			exf_client_documents( $user_id, 0, 'firm' ),
			function ( $id ) {
				return ! get_post_meta( $id, 'viewed_at', true );
			}
		)
	);
}

/**
 * Whether the current user may download a document.
 *
 * @param int $doc_id Document.
 */
function exf_document_can_access( $doc_id ) {
	if ( 'client_document' !== get_post_type( $doc_id ) || ! is_user_logged_in() ) {
		return false;
	}
	if ( exf_user_can_manage() ) {
		return true;
	}
	return 'publish' === get_post_status( $doc_id ) && (int) get_post_meta( $doc_id, 'client_id', true ) === get_current_user_id();
}

/**
 * Download link for a document (works for staff and the owning client).
 *
 * @param int  $doc_id Document.
 * @param bool $inline Open in the browser rather than download.
 */
function exf_document_url( $doc_id, $inline = false ) {
	$args = array(
		'exf_doc'  => (int) $doc_id,
		'_wpnonce' => wp_create_nonce( 'exf_doc_' . (int) $doc_id ),
	);
	if ( $inline ) {
		$args['view'] = 1;
	}
	return add_query_arg( $args, home_url( '/' ) );
}

// Serve document downloads.
add_action(
	'init',
	function () {
		if ( empty( $_GET['exf_doc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id = absint( $_GET['exf_doc'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( exf_portal_url( 'documents' ) ) );
			exit;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'exf_doc_' . $id ) || ! exf_document_can_access( $id ) ) {
			wp_die( esc_html__( "Sorry, you can't open that document.", 'exfiles' ), '', array( 'response' => 403 ) );
		}
		$is_owner = (int) get_post_meta( $id, 'client_id', true ) === get_current_user_id();
		if ( ! $is_owner && function_exists( 'exf_audit' ) ) {
			exf_audit( 'document_download', 'Downloaded ' . get_post_meta( $id, 'file_name', true ) . ' (' . get_the_title( (int) get_post_meta( $id, 'ex_file_id', true ) ) . ')', 'document', $id );
		}
		if ( $is_owner && 'firm' === get_post_meta( $id, 'uploaded_by', true ) && ! get_post_meta( $id, 'viewed_at', true ) ) {
			update_post_meta( $id, 'viewed_at', time() );
			$ex_file = (int) get_post_meta( $id, 'ex_file_id', true );
			if ( $ex_file ) {
				exf_ex_file_log( $ex_file, 'Client opened document: ' . get_the_title( $id ) );
			}
		}
		exf_private_stream( (string) get_post_meta( $id, 'file', true ), (string) get_post_meta( $id, 'file_name', true ), ! empty( $_GET['view'] ) );
	},
	1
);

// Deleting a document record deletes its file.
add_action(
	'before_delete_post',
	function ( $post_id ) {
		if ( 'client_document' === get_post_type( $post_id ) ) {
			exf_private_delete( (string) get_post_meta( $post_id, 'file', true ) );
		}
	}
);

/**
 * Handle a client's upload from the portal.
 *
 * @param int $user_id Client.
 * @return array Errors, or redirects on success.
 */
function exf_handle_client_upload( $user_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by the caller.
	$ex_file = isset( $_POST['ex_file'] ) ? absint( $_POST['ex_file'] ) : 0;
	$type    = isset( $_POST['doc_type'] ) ? sanitize_key( wp_unslash( $_POST['doc_type'] ) ) : '';
	$note    = isset( $_POST['doc_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['doc_note'] ) ) : '';
	// phpcs:enable

	$errors = array();
	if ( ! exf_portal_owns_ex_file( $ex_file ) || ! in_array( exf_ex_file_status( $ex_file ), array( 'clear', 'review', 'info_requested' ), true ) ) {
		$errors['_form'] = __( 'Please choose which Ex File this is for.', 'exfiles' );
	}
	if ( ! isset( exf_client_upload_types()[ $type ] ) ) {
		$errors['doc_type'] = __( 'Please choose what kind of document this is.', 'exfiles' );
	}
	$limit = 'exf_uploads_' . $user_id;
	$count = (int) get_transient( $limit );
	if ( $count >= EXF_DOC_HOURLY_MAX ) {
		$errors['_form'] = __( "You've sent a lot of documents in the last hour. Please wait a little and try again, or email them to us.", 'exfiles' );
	}
	if ( $errors ) {
		return $errors;
	}

	$stored = exf_document_store_upload( $_FILES['doc_file'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked in exf_document_store_upload().
	if ( is_wp_error( $stored ) ) {
		return array( 'doc_file' => $stored->get_error_message() );
	}
	set_transient( $limit, $count + 1, HOUR_IN_SECONDS );

	$doc = exf_document_create( $ex_file, $stored, $type, $note, 'client' );
	if ( ! $doc ) {
		return array( '_form' => __( "We couldn't save that file. Please try again.", 'exfiles' ) );
	}
	exf_ex_file_log( $ex_file, 'Client uploaded: ' . $stored['file_name'] );

	$vars = array_merge(
		exf_ex_file_email_vars( $ex_file ),
		array(
			'document'   => $stored['file_name'],
			'doc_type'   => exf_document_type_label( $type ),
			'note'       => $note ? $note : '—',
			'admin_link' => admin_url( 'post.php?post=' . $doc . '&action=edit' ),
		)
	);
	exf_send_email( 'admin_client_upload', exf_notify_emails(), $vars );

	$back = isset( $_POST['back'] ) && 'ex-file' === $_POST['back'] ? exf_portal_url( 'ex-files', $ex_file ) : exf_portal_url( 'documents' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	wp_safe_redirect( add_query_arg( 'uploaded', 1, $back ) );
	exit;
}

/**
 * Handle a client deleting one of their own uploads.
 *
 * @param int $doc_id Document.
 */
function exf_handle_client_delete_document( $doc_id ) {
	if ( 'client_document' === get_post_type( $doc_id )
		&& (int) get_post_meta( $doc_id, 'client_id', true ) === get_current_user_id()
		&& 'client' === get_post_meta( $doc_id, 'uploaded_by', true ) ) {
		$ex_file = (int) get_post_meta( $doc_id, 'ex_file_id', true );
		$name    = (string) get_post_meta( $doc_id, 'file_name', true );
		wp_delete_post( $doc_id, true );
		if ( $ex_file ) {
			exf_ex_file_log( $ex_file, 'Client deleted their upload: ' . $name );
		}
	}
	wp_safe_redirect( add_query_arg( 'deleted', 1, exf_portal_url( 'documents' ) ) );
	exit;
}

/**
 * Tell a client a document is ready: a link to their portal and, if asked, the file
 * attached (files over 10 MB are never attached; the link still works).
 *
 * @param int  $doc_id Document.
 * @param bool $attach Attach the file to the email.
 */
function exf_document_notify_client( $doc_id, $attach = false ) {
	$ex_file = (int) get_post_meta( $doc_id, 'ex_file_id', true );
	$user    = get_userdata( (int) get_post_meta( $doc_id, 'client_id', true ) );
	if ( ! $user || ! $ex_file ) {
		return false;
	}
	$path = $attach ? exf_private_path( (string) get_post_meta( $doc_id, 'file', true ) ) : '';
	$copy = '';
	if ( $path && is_readable( $path ) && filesize( $path ) <= 10 * MB_IN_BYTES ) {
		// Attach under its original file name.
		$dir = trailingslashit( get_temp_dir() ) . 'exf-' . wp_generate_password( 12, false, false );
		wp_mkdir_p( $dir );
		$copy = $dir . '/' . sanitize_file_name( (string) get_post_meta( $doc_id, 'file_name', true ) ?: 'document' );
		if ( ! copy( $path, $copy ) ) {
			$copy = '';
		}
	}
	$add = function ( $args ) use ( $copy ) {
		$args['attachments'] = array_merge( (array) $args['attachments'], array( $copy ) );
		return $args;
	};
	if ( $copy ) {
		add_filter( 'wp_mail', $add );
	}
	$sent = exf_send_email(
		'document_ready',
		$user->user_email,
		exf_ex_file_email_vars( $ex_file ) + array(
			'document'        => get_the_title( $doc_id ),
			'attachment_note' => $copy
				? __( 'A copy is attached to this email. You can also view it at any time in your Ex File:', 'exfiles' )
				: __( "For your privacy we don't attach documents to emails. Please log in to view it:", 'exfiles' ),
			'link'            => exf_portal_url( 'documents' ),
		)
	);
	if ( $copy ) {
		remove_filter( 'wp_mail', $add );
		wp_delete_file( $copy );
		rmdir( dirname( $copy ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
	if ( $sent ) {
		update_post_meta( $doc_id, 'notified_at', time() );
		exf_ex_file_log( $ex_file, 'Client emailed that a document is ready' . ( $copy ? ' (file attached)' : '' ) . ': ' . get_the_title( $doc_id ) );
	}
	return $sent;
}

/* -------------------------------------------------------------------------
 * Admin: upload a document for a client
 * ---------------------------------------------------------------------- */

// File uploads need a multipart form.
add_action(
	'post_edit_form_tag',
	function ( $post ) {
		if ( $post && 'client_document' === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}
);

add_action(
	'add_meta_boxes_client_document',
	function () {
		add_meta_box( 'exf_document', __( 'Document', 'exfiles' ), 'exf_document_box', 'client_document', 'normal', 'high' );
	}
);

/**
 * Document details box.
 *
 * @param WP_Post $post Document.
 */
function exf_document_box( $post ) {
	$id      = $post->ID;
	$ex_file = (int) get_post_meta( $id, 'ex_file_id', true );
	if ( ! $ex_file && isset( $_GET['ex_file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ex_file = absint( $_GET['ex_file'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$by    = (string) get_post_meta( $id, 'uploaded_by', true );
	$file  = (string) get_post_meta( $id, 'file', true );
	$type  = (string) get_post_meta( $id, 'doc_type', true );
	$files = get_posts(
		array(
			'post_type'      => 'ex_file',
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$errors = get_transient( 'exf_doc_err_' . get_current_user_id() );
	if ( $errors ) {
		delete_transient( 'exf_doc_err_' . get_current_user_id() );
		echo '<div class="notice notice-error inline"><p>' . esc_html( $errors ) . '</p></div>';
	}
	wp_nonce_field( 'exf_document_save', 'exf_document_nonce' );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="exf_doc_ex_file"><?php esc_html_e( 'Ex File', 'exfiles' ); ?></label></th>
			<td>
				<?php if ( 'client' === $by ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $ex_file ) ); ?>"><?php echo esc_html( get_the_title( $ex_file ) ); ?></a>
					<input type="hidden" name="exf_doc_ex_file" value="<?php echo (int) $ex_file; ?>">
				<?php else : ?>
					<select name="exf_doc_ex_file" id="exf_doc_ex_file" required>
						<option value=""><?php esc_html_e( '— Choose —', 'exfiles' ); ?></option>
						<?php foreach ( $files as $f ) : ?>
							<option value="<?php echo (int) $f->ID; ?>" <?php selected( $ex_file, $f->ID ); ?>><?php echo esc_html( $f->post_title . ' (' . exf_ex_file_status_label( exf_ex_file_status( $f->ID ) ) . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'From', 'exfiles' ); ?></th>
			<td><?php echo 'client' === $by ? esc_html__( 'Uploaded by the client', 'exfiles' ) : esc_html__( 'Shared by The Ex Files', 'exfiles' ); ?></td>
		</tr>
		<tr>
			<th><label for="exf_doc_type"><?php esc_html_e( 'Type', 'exfiles' ); ?></label></th>
			<td>
				<?php if ( 'client' === $by ) : ?>
					<?php echo esc_html( exf_document_type_label( $type ) ); ?>
				<?php else : ?>
					<select name="exf_doc_type" id="exf_doc_type">
						<?php foreach ( exf_document_types() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="exf_doc_note"><?php echo 'client' === $by ? esc_html__( "Client's note", 'exfiles' ) : esc_html__( 'Note for the client', 'exfiles' ); ?></label></th>
			<td>
				<?php if ( 'client' === $by ) : ?>
					<?php echo nl2br( esc_html( (string) get_post_meta( $id, 'doc_note', true ) ) ); ?>
				<?php else : ?>
					<textarea name="exf_doc_note" id="exf_doc_note" rows="3" class="large-text"><?php echo esc_textarea( (string) get_post_meta( $id, 'doc_note', true ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown next to the document in the client’s portal. Optional.', 'exfiles' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'File', 'exfiles' ); ?></th>
			<td>
				<?php if ( $file ) : ?>
					<p><a href="<?php echo esc_url( exf_document_url( $id ) ); ?>"><?php echo esc_html( (string) get_post_meta( $id, 'file_name', true ) ); ?></a>
					(<?php echo esc_html( size_format( (int) get_post_meta( $id, 'file_size', true ) ) ); ?>)</p>
				<?php endif; ?>
				<?php if ( 'client' !== $by ) : ?>
					<input type="file" name="exf_doc_file" id="exf_doc_file">
					<p class="description"><?php echo $file ? esc_html__( 'Choose a file only to replace the current one.', 'exfiles' ) : esc_html__( 'Stored privately: only you and this client can open it.', 'exfiles' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( 'client' !== $by ) : ?>
			<tr>
				<th><?php esc_html_e( 'Client', 'exfiles' ); ?></th>
				<td>
					<?php
					$viewed   = (int) get_post_meta( $id, 'viewed_at', true );
					$notified = (int) get_post_meta( $id, 'notified_at', true );
					if ( $notified ) {
						/* translators: %s: date */
						echo '<p>' . esc_html( sprintf( __( 'Emailed %s', 'exfiles' ), wp_date( 'j M Y H:i', $notified ) ) ) . '</p>';
					}
					if ( $viewed ) {
						/* translators: %s: date */
						echo '<p>' . esc_html( sprintf( __( 'Opened by the client %s', 'exfiles' ), wp_date( 'j M Y H:i', $viewed ) ) ) . '</p>';
					} elseif ( $file ) {
						echo '<p>' . esc_html__( 'Not opened yet', 'exfiles' ) . '</p>';
					}
					?>
					<label><input type="checkbox" name="exf_doc_notify" value="1" <?php checked( ! $notified ); ?>> <?php esc_html_e( 'Email the client that it’s ready, with a link to their Ex File', 'exfiles' ); ?></label><br>
					<label style="margin-left:24px"><input type="checkbox" name="exf_doc_attach" value="1" <?php checked( ! $notified ); ?>> <?php esc_html_e( 'Attach the file to that email (files up to 10 MB)', 'exfiles' ); ?></label>
					<?php if ( exf_document_consult_order( $ex_file ) ) : ?>
						<p class="description"><?php esc_html_e( 'This client has paid for a consultation add-on. Sharing a document of type “Report” opens their consultation booking and emails them a link to book.', 'exfiles' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		<?php endif; ?>
	</table>
	<?php
}

add_action(
	'save_post_client_document',
	function ( $post_id, $post ) {
		if ( ! isset( $_POST['exf_document_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['exf_document_nonce'] ) ), 'exf_document_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! exf_user_can_manage() ) {
			return;
		}
		$by      = (string) get_post_meta( $post_id, 'uploaded_by', true );
		$ex_file = isset( $_POST['exf_doc_ex_file'] ) ? absint( $_POST['exf_doc_ex_file'] ) : 0;
		if ( 'client' === $by ) {
			return; // Client uploads are read-only here.
		}
		$err = function ( $msg ) {
			set_transient( 'exf_doc_err_' . get_current_user_id(), $msg, MINUTE_IN_SECONDS );
		};
		if ( 'ex_file' !== get_post_type( $ex_file ) ) {
			$err( __( 'Please choose the Ex File this document belongs to.', 'exfiles' ) );
			return;
		}

		update_post_meta( $post_id, 'ex_file_id', $ex_file );
		update_post_meta( $post_id, 'client_id', (int) get_post_field( 'post_author', $ex_file ) );
		update_post_meta( $post_id, 'uploaded_by', 'firm' );
		$type = isset( $_POST['exf_doc_type'] ) ? sanitize_key( wp_unslash( $_POST['exf_doc_type'] ) ) : 'other';
		update_post_meta( $post_id, 'doc_type', isset( exf_document_types()[ $type ] ) ? $type : 'other' );
		update_post_meta( $post_id, 'doc_note', isset( $_POST['exf_doc_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['exf_doc_note'] ) ) : '' );

		if ( ! empty( $_FILES['exf_doc_file']['name'] ) ) {
			$stored = exf_document_store_upload( $_FILES['exf_doc_file'], false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked in exf_document_store_upload().
			if ( is_wp_error( $stored ) ) {
				$err( $stored->get_error_message() );
			} else {
				exf_private_delete( (string) get_post_meta( $post_id, 'file', true ) );
				foreach ( $stored as $k => $v ) {
					update_post_meta( $post_id, $k, $v );
				}
				delete_post_meta( $post_id, 'viewed_at' );
				exf_ex_file_log( $ex_file, 'Document shared with client: ' . $post->post_title );
			}
		} elseif ( ! get_post_meta( $post_id, 'file', true ) ) {
			$err( __( 'Please choose a file to upload.', 'exfiles' ) );
		}

		if ( 'publish' === $post->post_status && ! empty( $_POST['exf_doc_notify'] ) && get_post_meta( $post_id, 'file', true ) ) {
			exf_document_notify_client( $post_id, ! empty( $_POST['exf_doc_attach'] ) );
		}
		// A report opens booking for a consultation add-on on this Ex File.
		if ( 'publish' === $post->post_status && 'report' === get_post_meta( $post_id, 'doc_type', true ) && get_post_meta( $post_id, 'file', true ) ) {
			$order = exf_document_consult_order( $ex_file );
			if ( $order ) {
				exf_consult_open( $order, 'Report shared: ' . $post->post_title );
			}
		}
	},
	10,
	2
);

// A document without a title takes the file name.
add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( 'client_document' === $data['post_type'] && '' === trim( (string) $data['post_title'] ) && ! empty( $_FILES['exf_doc_file']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['post_title'] = sanitize_text_field( wp_unslash( $_FILES['exf_doc_file']['name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		return $data;
	},
	10,
	2
);

add_filter(
	'manage_client_document_posts_columns',
	function () {
		return array(
			'cb'         => '<input type="checkbox" />',
			'title'      => __( 'Document', 'exfiles' ),
			'exf_file'   => __( 'Ex File', 'exfiles' ),
			'exf_from'   => __( 'From', 'exfiles' ),
			'exf_type'   => __( 'Type', 'exfiles' ),
			'exf_opened' => __( 'Client opened', 'exfiles' ),
			'date'       => __( 'Date', 'exfiles' ),
		);
	}
);

add_action(
	'manage_client_document_posts_custom_column',
	function ( $column, $id ) {
		switch ( $column ) {
			case 'exf_file':
				$f = (int) get_post_meta( $id, 'ex_file_id', true );
				echo $f ? '<a href="' . esc_url( get_edit_post_link( $f ) ) . '">' . esc_html( get_the_title( $f ) ) . '</a>' : '—';
				break;
			case 'exf_from':
				echo 'client' === get_post_meta( $id, 'uploaded_by', true ) ? '<strong>' . esc_html__( 'Client', 'exfiles' ) . '</strong>' : esc_html__( 'You', 'exfiles' );
				break;
			case 'exf_type':
				echo esc_html( exf_document_type_label( (string) get_post_meta( $id, 'doc_type', true ) ) );
				break;
			case 'exf_opened':
				if ( 'client' === get_post_meta( $id, 'uploaded_by', true ) ) {
					echo '—';
				} else {
					$v = (int) get_post_meta( $id, 'viewed_at', true );
					echo esc_html( $v ? wp_date( 'j M Y', $v ) : __( 'Not yet', 'exfiles' ) );
				}
				break;
		}
	},
	10,
	2
);

// Filter the list by Ex File (used by the links on the Ex File screen).
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'client_document' !== $query->get( 'post_type' ) || empty( $_GET['ex_file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$query->set( 'meta_key', 'ex_file_id' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query->set( 'meta_value', absint( $_GET['ex_file'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.Security.NonceVerification.Recommended
	}
);
