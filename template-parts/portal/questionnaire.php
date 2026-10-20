<?php
/**
 * Portal: the full service questionnaire (start, steps, review, submitted).
 *
 * @package exfiles
 */

$exf_q      = (int) $args['questionnaire'];
$exf_status = exf_q_status( $exf_q );
$exf_data   = exf_q_data( $exf_q );
$exf_errors = exf_portal_errors( 'questionnaire' );
$exf_secs   = exf_q_sections();
$exf_vis    = exf_q_visible_sections( $exf_data );
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$exf_step   = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
$exf_return = isset( $_GET['return'] ) ? sanitize_key( wp_unslash( $_GET['return'] ) ) : '';
// phpcs:enable
$exf_service = exf_q_service_name( $exf_q );

if ( 'submitted' === $exf_status ) {
	$exf_view = 'submitted';
} elseif ( 'to_do' === $exf_status && ! $exf_data ) {
	$exf_view = 'start';
} elseif ( 'review' === $exf_step ) {
	$exf_view = 'review';
} else {
	$exf_view = 'step';
	if ( ! in_array( $exf_step, $exf_vis, true ) ) {
		// Carry on from the first section that isn't finished.
		$exf_todo = exf_q_validate_all( $exf_data );
		$exf_step = $exf_todo ? array_keys( $exf_todo )[0] : $exf_vis[0];
	}
}
$exf_form_open = function ( $action ) use ( $exf_q ) {
	wp_nonce_field( 'exf_q_' . $exf_q );
	echo '<input type="hidden" name="exf_q" value="' . esc_attr( $action ) . '"><input type="hidden" name="questionnaire" value="' . (int) $exf_q . '">';
};
?>
<header class="portal-head">
	<p class="eyebrow"><?php echo esc_html( $exf_service ? $exf_service : __( 'Questionnaire', 'exfiles' ) ); ?></p>
	<h1><?php esc_html_e( 'Your financial questionnaire', 'exfiles' ); ?></h1>
</header>

<?php if ( 'submitted' === $exf_view ) : ?>

	<?php if ( isset( $_GET['submitted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice-box" role="status"><p><?php echo esc_html( exf_portal_copy( 'q_submitted' ) ); ?></p></div>
	<?php endif; ?>
	<p class="lead">
		<?php
		$exf_at = (int) get_post_meta( $exf_q, 'q_submitted_at', true );
		/* translators: %s: date */
		echo esc_html( sprintf( __( 'You submitted this on %s. If you need to change anything, please contact us and we can reopen it for you.', 'exfiles' ), wp_date( 'j F Y', $exf_at ) ) );
		?>
	</p>
	<?php $exf_pdf = (int) get_post_meta( $exf_q, 'q_pdf_doc', true ); ?>
	<?php if ( $exf_pdf && 'client_document' === get_post_type( $exf_pdf ) ) : ?>
		<p><a class="btn small outline" href="<?php echo esc_url( exf_document_url( $exf_pdf ) ); ?>"><?php esc_html_e( 'Download a copy (PDF)', 'exfiles' ); ?></a></p>
	<?php endif; ?>
	<div class="q-review"><?php echo exf_q_summary_html( $exf_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

<?php elseif ( 'start' === $exf_view ) : ?>

	<div class="guide-card account-card q-start">
		<p><?php echo exf_lines( exf_portal_copy( 'q_intro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<h3 style="margin-top:22px"><?php esc_html_e( 'It covers', 'exfiles' ); ?></h3>
		<ol class="q-start-list">
			<?php foreach ( $exf_secs as $exf_k => $exf_s ) : ?>
				<?php if ( 'agreements' === $exf_k ) { continue; } ?>
				<li><?php echo esc_html( $exf_s['title'] ); ?></li>
			<?php endforeach; ?>
		</ol>
		<p class="field-note"><?php esc_html_e( "Sections that don't apply to you are skipped. Your answers are saved each time you press Next, so you can stop and come back.", 'exfiles' ); ?></p>
		<form method="post" class="q-start-form">
			<?php $exf_form_open( 'start' ); ?>
			<?php if ( exf_q_previous( $exf_q ) ) : ?>
				<p><?php esc_html_e( "You've completed this questionnaire before for this separation. You can start from your last answers and update them.", 'exfiles' ); ?></p>
				<div class="guide-nav">
					<button type="submit" name="copy" value="1" class="btn"><?php esc_html_e( 'Start from my last answers', 'exfiles' ); ?></button>
					<button type="submit" name="copy" value="0" class="btn outline"><?php esc_html_e( 'Start afresh', 'exfiles' ); ?></button>
				</div>
			<?php else : ?>
				<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Start', 'exfiles' ); ?></button></div>
			<?php endif; ?>
		</form>
	</div>

<?php else : ?>

	<?php
	$exf_all_errors = exf_q_validate_all( $exf_data );
	$exf_pos        = 'review' === $exf_view ? count( $exf_vis ) : array_search( $exf_step, $exf_vis, true );
	?>
	<nav class="q-progress" aria-label="<?php esc_attr_e( 'Questionnaire sections', 'exfiles' ); ?>">
		<ol>
			<?php foreach ( $exf_vis as $exf_i => $exf_k ) : ?>
				<?php
				$exf_done = isset( $exf_data[ $exf_k ] ) && ! isset( $exf_all_errors[ $exf_k ] );
				$exf_cls  = $exf_k === $exf_step && 'step' === $exf_view ? 'current' : ( $exf_done ? 'done' : '' );
				?>
				<li class="<?php echo esc_attr( $exf_cls ); ?>"><a href="<?php echo esc_url( exf_q_url( $exf_q, $exf_k ) ); ?>"<?php echo 'current' === $exf_cls ? ' aria-current="step"' : ''; ?>><span><?php echo (int) ( $exf_i + 1 ); ?></span> <?php echo esc_html( $exf_secs[ $exf_k ]['title'] ); ?></a></li>
			<?php endforeach; ?>
			<li class="<?php echo 'review' === $exf_view ? 'current' : ''; ?>"><a href="<?php echo esc_url( exf_q_url( $exf_q, 'review' ) ); ?>"><span>✓</span> <?php esc_html_e( 'Review and submit', 'exfiles' ); ?></a></li>
		</ol>
	</nav>

	<?php if ( 'review' === $exf_view ) : ?>

		<h2 class="portal-h2"><?php esc_html_e( 'Check your answers', 'exfiles' ); ?></h2>
		<p><?php echo exf_lines( exf_portal_copy( 'q_review' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php if ( isset( $exf_errors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_errors['_form'] ); ?></p></div>
		<?php endif; ?>
		<div class="q-review">
			<?php
			echo exf_q_summary_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$exf_data,
				function ( $k ) use ( $exf_q ) {
					return add_query_arg( 'return', 'review', exf_q_url( $exf_q, $k ) );
				},
				$exf_all_errors
			);
			?>
		</div>
		<form method="post" class="guide-form portal-form q-submit">
			<?php $exf_form_open( 'submit' ); ?>
			<label class="gq-option gq-option--check<?php echo isset( $exf_errors['declaration'] ) ? ' has-error' : ''; ?>"><input type="checkbox" name="declaration" value="1"><span><?php echo esc_html( exf_q_declaration_text() ); ?></span></label>
			<?php if ( isset( $exf_errors['declaration'] ) ) : ?>
				<p class="field-error"><?php echo esc_html( $exf_errors['declaration'] ); ?></p>
			<?php endif; ?>
			<div class="guide-nav">
				<a class="btn outline" href="<?php echo esc_url( exf_q_url( $exf_q, end( $exf_vis ) ) ); ?>"><?php esc_html_e( 'Back', 'exfiles' ); ?></a>
				<button type="submit" class="btn"<?php echo $exf_all_errors ? ' disabled aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Submit my answers', 'exfiles' ); ?></button>
			</div>
			<?php if ( $exf_all_errors ) : ?>
				<p class="field-note"><?php esc_html_e( 'Please finish the sections marked above before submitting.', 'exfiles' ); ?></p>
			<?php endif; ?>
		</form>

	<?php else : ?>

		<?php $exf_sec = $exf_secs[ $exf_step ]; ?>
		<form method="post" class="guide-form portal-form q-form" data-questionnaire novalidate>
			<?php $exf_form_open( 'step' ); ?>
			<input type="hidden" name="section" value="<?php echo esc_attr( $exf_step ); ?>">
			<?php if ( 'review' === $exf_return ) : ?>
				<input type="hidden" name="return" value="review">
			<?php endif; ?>
			<p class="eyebrow">
				<?php
				/* translators: 1: step number, 2: number of steps */
				echo esc_html( sprintf( __( 'Section %1$d of %2$d', 'exfiles' ), $exf_pos + 1, count( $exf_vis ) ) );
				?>
			</p>
			<h2 class="q-title"><?php echo esc_html( $exf_sec['title'] ); ?></h2>
			<?php if ( ! empty( $exf_sec['intro'] ) ) : ?>
				<p class="q-intro"><?php echo esc_html( $exf_sec['intro'] ); ?></p>
			<?php endif; ?>
			<?php if ( $exf_errors ) : ?>
				<div class="notice-box notice-box--error" role="alert"><p><?php esc_html_e( "Your answers are saved, but some need attention before you move on. They're marked below.", 'exfiles' ); ?></p></div>
			<?php endif; ?>
			<?php exf_q_render_section( $exf_step, $exf_data, $exf_errors ); ?>
			<div class="guide-nav q-nav">
				<?php // Next comes first in the markup so pressing Enter means Next. ?>
				<button type="submit" name="go" value="next" class="btn q-next"><?php echo 'review' === $exf_return ? esc_html__( 'Save and return to review', 'exfiles' ) : esc_html__( 'Next', 'exfiles' ); ?></button>
				<?php if ( $exf_pos > 0 ) : ?>
					<button type="submit" name="go" value="back" class="btn outline q-back"><?php esc_html_e( 'Back', 'exfiles' ); ?></button>
				<?php endif; ?>
				<button type="submit" name="go" value="save" class="link-button q-save"><?php esc_html_e( 'Save and finish later', 'exfiles' ); ?></button>
			</div>
		</form>

	<?php endif; ?>
<?php endif; ?>
