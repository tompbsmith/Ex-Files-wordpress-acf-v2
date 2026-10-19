<?php
/**
 * Portal: one Ex File. Its status, the reply form when we've asked for more
 * information, its services, questionnaires and documents.
 *
 * @package exfiles
 */

$exf_user   = $args['user'];
$exf_uid    = (int) $exf_user->ID;
$exf_id     = (int) $args['ex_file'];
$exf_fs     = exf_ex_file_status( $exf_id );
$exf_ex     = exf_ex_details( $exf_id );
$exf_errors = $GLOBALS['exf_info_errors'] ?? array();
$exf_orders = array_filter(
	exf_client_orders( $exf_uid, $exf_id ),
	function ( $o ) {
		return in_array( exf_order_status( $o ), array( 'paid', 'confirmed', 'refunded' ), true );
	}
);
$exf_qs     = exf_client_questionnaires( $exf_uid, $exf_id );
$exf_docs   = exf_client_documents( $exf_uid, $exf_id );
?>
<header class="portal-head">
	<p class="eyebrow"><a href="<?php echo esc_url( exf_portal_url( 'ex-files' ) ); ?>"><?php esc_html_e( 'Ex Files', 'exfiles' ); ?></a></p>
	<h1>
		<?php
		/* translators: %s: ex's name */
		echo esc_html( sprintf( __( 'Separation from %s', 'exfiles' ), trim( $exf_ex['first_name'] . ' ' . $exf_ex['last_name'] ) ) );
		?>
	</h1>
	<p class="account-file-status status-<?php echo esc_attr( $exf_fs ); ?>"><?php echo esc_html( exf_ex_file_client_label( $exf_fs ) ); ?></p>
</header>

<?php if ( isset( $_GET['replied'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice-box" role="status"><p><?php esc_html_e( "Thank you. We've received your reply and will be in touch shortly.", 'exfiles' ); ?></p></div>
<?php endif; ?>
<?php if ( isset( $_GET['uploaded'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice-box" role="status"><p><?php esc_html_e( 'Thank you. Your document has been sent to us.', 'exfiles' ); ?></p></div>
<?php endif; ?>

<?php if ( 'info_requested' === $exf_fs ) : ?>
	<div class="guide-form account-file">
		<h2 class="portal-h2" style="margin-top:0"><?php esc_html_e( 'We need a little more information', 'exfiles' ); ?></h2>
		<div class="reg-why">
			<p><strong><?php esc_html_e( 'Our question:', 'exfiles' ); ?></strong></p>
			<p><?php echo nl2br( esc_html( (string) get_post_meta( $exf_id, 'info_question', true ) ) ); ?></p>
		</div>
		<?php if ( isset( $exf_errors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_errors['_form'] ); ?></p></div>
		<?php endif; ?>
		<form method="post" class="info-reply-form">
			<?php wp_nonce_field( 'exf_info_reply' ); ?>
			<input type="hidden" name="exf_info_reply" value="1">
			<input type="hidden" name="ex_file" value="<?php echo (int) $exf_id; ?>">
			<?php
			$exf_a = array( 'errors' => $exf_errors );
			exf_form_field( 'info_reply', __( 'Your answer', 'exfiles' ), $exf_a + array( 'type' => 'textarea', 'required' => true ) );
			?>
			<p class="field-note" style="margin-bottom:14px"><?php esc_html_e( 'If you can add any of these details about your ex, it will help:', 'exfiles' ); ?></p>
			<div class="field-row">
				<?php
				exf_form_field( 'ex_dob', __( 'Their date of birth', 'exfiles' ), $exf_a + array( 'type' => 'date', 'values' => array( 'ex_dob' => get_post_meta( $exf_id, 'ex_dob', true ) ) ) );
				exf_form_field( 'ex_postcode', __( 'Their last known postcode', 'exfiles' ), $exf_a + array( 'values' => array( 'ex_postcode' => get_post_meta( $exf_id, 'ex_postcode', true ) ) ) );
				?>
			</div>
			<div class="field-row">
				<?php
				exf_form_field( 'ex_email', __( 'Their email address', 'exfiles' ), $exf_a + array( 'type' => 'email', 'values' => array( 'ex_email' => get_post_meta( $exf_id, 'ex_email', true ) ) ) );
				exf_form_field( 'ex_phone', __( 'Their phone number', 'exfiles' ), $exf_a + array( 'type' => 'tel', 'values' => array( 'ex_phone' => get_post_meta( $exf_id, 'ex_phone', true ) ) ) );
				?>
			</div>
			<?php exf_form_field( 'ex_prev_names', __( 'Any previous or other names they use', 'exfiles' ), $exf_a + array( 'values' => array( 'ex_prev_names' => get_post_meta( $exf_id, 'ex_prev_names', true ) ) ) ); ?>
			<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Send my reply', 'exfiles' ); ?></button></div>
		</form>
	</div>
<?php elseif ( 'review' === $exf_fs || 'pending' === $exf_fs ) : ?>
	<div class="guide-card account-card">
		<p><?php echo exf_lines( exf_portal_copy( 'review_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	</div>
<?php elseif ( 'blocked' === $exf_fs ) : ?>
	<div class="guide-card account-card">
		<p><?php esc_html_e( "We're unable to act for you on this matter. Please contact us if you have any questions.", 'exfiles' ); ?></p>
	</div>
<?php endif; ?>

<?php if ( 'blocked' !== $exf_fs ) : ?>

	<h2 class="portal-h2"><?php esc_html_e( 'Services', 'exfiles' ); ?></h2>
	<?php if ( $exf_orders ) : ?>
		<ul class="portal-list">
			<?php foreach ( $exf_orders as $exf_o ) : ?>
				<?php $exf_os = (int) get_post_meta( $exf_o, 'booking_start', true ); ?>
				<li>
					<div>
						<strong><?php echo esc_html( (string) get_post_meta( $exf_o, 'service_name', true ) ); ?></strong>
						<?php if ( $exf_os ) : ?>
							<span class="portal-list-meta"><?php echo esc_html( exf_booking_label( $exf_os, (int) get_post_meta( $exf_o, 'booking_end', true ) ) ); ?><?php echo $exf_os < time() ? ' ' . esc_html__( '(past)', 'exfiles' ) : ''; ?></span>
						<?php endif; ?>
					</div>
					<span class="portal-pill"><?php echo esc_html( exf_order_status_label( exf_order_status( $exf_o ) ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="portal-empty"><?php esc_html_e( "You haven't chosen a service for this Ex File yet.", 'exfiles' ); ?></p>
	<?php endif; ?>
	<?php if ( 'clear' === $exf_fs && EXF_STATUS_ACTIVE === exf_user_status( $exf_uid ) ) : ?>
		<p><a class="btn outline" href="<?php echo esc_url( add_query_arg( 'ex_file', $exf_id, exf_page_url( 'buy' ) ) ); ?>"><?php esc_html_e( 'Choose a service for this Ex File', 'exfiles' ); ?></a></p>
	<?php endif; ?>

	<?php if ( $exf_qs ) : ?>
		<h2 class="portal-h2"><?php esc_html_e( 'Questionnaires', 'exfiles' ); ?></h2>
		<ul class="portal-list">
			<?php foreach ( $exf_qs as $exf_q ) : ?>
				<?php
				$exf_qstatus = (string) get_post_meta( $exf_q, 'q_status', true );
				$exf_qurl    = (string) apply_filters( 'exf_questionnaire_url', '', $exf_q );
				?>
				<li>
					<div>
						<strong><?php esc_html_e( 'Financial questionnaire', 'exfiles' ); ?></strong>
						<?php if ( ! $exf_qurl && 'submitted' !== $exf_qstatus ) : ?>
							<span class="portal-list-meta"><?php esc_html_e( "Ready soon. We'll email you when you can fill it in.", 'exfiles' ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $exf_qurl && 'submitted' !== $exf_qstatus ) : ?>
						<a class="btn small" href="<?php echo esc_url( $exf_qurl ); ?>"><?php echo 'in_progress' === $exf_qstatus ? esc_html__( 'Continue', 'exfiles' ) : esc_html__( 'Start', 'exfiles' ); ?></a>
					<?php else : ?>
						<span class="portal-pill"><?php echo esc_html( exf_questionnaire_status_label( $exf_qstatus ) ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h2 class="portal-h2"><?php esc_html_e( 'Documents', 'exfiles' ); ?></h2>
	<?php get_template_part( 'template-parts/portal/document-list', null, array( 'docs' => $exf_docs, 'show_file' => false ) ); ?>
	<?php
	get_template_part(
		'template-parts/portal/upload-form',
		null,
		array(
			'ex_files' => array( $exf_id ),
			'back'     => 'ex-file',
		)
	);
	?>
<?php endif; ?>
