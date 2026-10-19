<?php
/**
 * Portal: a list of documents with download links.
 *
 * @package exfiles
 */

$exf_docs      = $args['docs'];
$exf_show_file = ! empty( $args['show_file'] );
?>
<?php if ( ! $exf_docs ) : ?>
	<p class="portal-empty"><?php echo esc_html( $args['empty'] ?? __( 'No documents yet.', 'exfiles' ) ); ?></p>
<?php else : ?>
	<ul class="portal-list portal-docs">
		<?php foreach ( $exf_docs as $exf_d ) : ?>
			<?php
			$exf_from_client = 'client' === get_post_meta( $exf_d, 'uploaded_by', true );
			$exf_is_new      = ! $exf_from_client && ! get_post_meta( $exf_d, 'viewed_at', true );
			$exf_note        = (string) get_post_meta( $exf_d, 'doc_note', true );
			$exf_file        = (int) get_post_meta( $exf_d, 'ex_file_id', true );
			$exf_ex          = $exf_file ? exf_ex_details( $exf_file ) : null;
			?>
			<li>
				<div>
					<strong><?php echo esc_html( $exf_from_client ? (string) get_post_meta( $exf_d, 'file_name', true ) : get_the_title( $exf_d ) ); ?></strong>
					<?php if ( $exf_is_new ) : ?>
						<span class="portal-pill portal-pill--new"><?php esc_html_e( 'New', 'exfiles' ); ?></span>
					<?php endif; ?>
					<span class="portal-list-meta">
						<?php
						$exf_bits = array(
							exf_document_type_label( (string) get_post_meta( $exf_d, 'doc_type', true ) ),
							$exf_from_client ? __( 'Sent by you', 'exfiles' ) : __( 'From The Ex Files', 'exfiles' ),
							get_the_date( 'j M Y', $exf_d ),
						);
						if ( $exf_show_file && $exf_ex ) {
							$exf_bits[] = trim( $exf_ex['first_name'] . ' ' . $exf_ex['last_name'] );
						}
						echo esc_html( implode( ' · ', $exf_bits ) );
						?>
					</span>
					<?php if ( $exf_note ) : ?>
						<span class="portal-doc-note"><?php echo esc_html( $exf_note ); ?></span>
					<?php endif; ?>
				</div>
				<div class="portal-actions">
					<a class="btn small outline" href="<?php echo esc_url( exf_document_url( $exf_d ) ); ?>"><?php esc_html_e( 'Download', 'exfiles' ); ?></a>
					<?php if ( $exf_from_client ) : ?>
						<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this document? We will no longer be able to see it.', 'exfiles' ) ); ?>');">
							<?php exf_portal_form_fields( 'delete_doc' ); ?>
							<input type="hidden" name="doc" value="<?php echo (int) $exf_d; ?>">
							<button type="submit" class="link-button"><?php esc_html_e( 'Remove', 'exfiles' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
