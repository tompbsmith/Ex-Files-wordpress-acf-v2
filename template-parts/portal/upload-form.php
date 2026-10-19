<?php
/**
 * Portal: send us a document.
 *
 * @package exfiles
 */

$exf_files  = array_values(
	array_filter(
		(array) $args['ex_files'],
		function ( $id ) {
			return in_array( exf_ex_file_status( $id ), array( 'clear', 'review', 'info_requested' ), true );
		}
	)
);
$exf_errors = exf_portal_errors( 'upload' );
if ( ! $exf_files ) {
	return;
}
$exf_open = $exf_errors ? ' open' : '';
?>
<details class="portal-upload"<?php echo esc_attr( $exf_open ); ?>>
	<summary class="btn"><?php esc_html_e( 'Send us a document', 'exfiles' ); ?></summary>
	<form method="post" enctype="multipart/form-data" class="guide-form portal-form">
		<?php exf_portal_form_fields( 'upload' ); ?>
		<input type="hidden" name="back" value="<?php echo esc_attr( $args['back'] ?? 'documents' ); ?>">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) exf_document_max_bytes(); ?>">
		<?php if ( isset( $exf_errors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_errors['_form'] ); ?></p></div>
		<?php endif; ?>

		<?php if ( count( $exf_files ) > 1 ) : ?>
			<?php
			$exf_options = array( '' => __( 'Choose…', 'exfiles' ) );
			foreach ( $exf_files as $exf_id ) {
				$exf_ex                 = exf_ex_details( $exf_id );
				$exf_options[ $exf_id ] = trim( $exf_ex['first_name'] . ' ' . $exf_ex['last_name'] );
			}
			exf_form_field(
				'ex_file',
				__( 'Which separation is this for?', 'exfiles' ),
				array(
					'type'     => 'select',
					'required' => true,
					'options'  => $exf_options,
					'errors'   => $exf_errors,
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'values'   => array( 'ex_file' => isset( $_POST['ex_file'] ) ? absint( $_POST['ex_file'] ) : '' ),
				)
			);
			?>
		<?php else : ?>
			<input type="hidden" name="ex_file" value="<?php echo (int) $exf_files[0]; ?>">
		<?php endif; ?>

		<?php
		exf_form_field(
			'doc_type',
			__( 'What is it?', 'exfiles' ),
			array(
				'type'     => 'select',
				'required' => true,
				'options'  => array( '' => __( 'Choose…', 'exfiles' ) ) + exf_client_upload_types(),
				'errors'   => $exf_errors,
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'values'   => array( 'doc_type' => isset( $_POST['doc_type'] ) ? sanitize_key( wp_unslash( $_POST['doc_type'] ) ) : '' ),
			)
		);
		exf_form_field(
			'doc_file',
			__( 'File', 'exfiles' ),
			array(
				'type'     => 'file',
				'required' => true,
				'errors'   => $exf_errors,
				'note'     => exf_portal_copy( 'upload_help' ),
				'attrs'    => ' accept=".pdf,.jpg,.jpeg,.png,.heic,.doc,.docx,.xls,.xlsx,.csv,.txt"',
			)
		);
		exf_form_field(
			'doc_note',
			__( 'Anything we should know about it?', 'exfiles' ),
			array(
				'type'   => 'textarea',
				'errors' => $exf_errors,
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'values' => array( 'doc_note' => isset( $_POST['doc_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['doc_note'] ) ) : '' ),
			)
		);
		?>
		<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Send document', 'exfiles' ); ?></button></div>
	</form>
</details>
