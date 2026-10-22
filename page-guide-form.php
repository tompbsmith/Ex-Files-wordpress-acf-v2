<?php
/**
 * Template Name: Divorce Guide Form
 *
 * The Free Divorce Guide questionnaire. One question per screen with JavaScript
 * (assets/js/guide.js); without it, every question shows and the server works out
 * which ones apply.
 *
 * @package exfiles
 */

$exf_questions = exf_guide_questions();
$exf_routes    = exf_guide_route_keys();
$exf_after_q3  = $exf_routes ? end( $exf_routes ) : 'q3'; // The eligibility screens follow the last eligibility question.
$exf_heading   = get_field( 'intro_heading' ) ? get_field( 'intro_heading' ) : __( 'Your free divorce guide', 'exfiles' );
$exf_intro     = get_field( 'intro_text' ) ? get_field( 'intro_text' ) : __( "Answer a few anonymous questions and we'll email you a guide written around your answers.", 'exfiles' );
$exf_next      = get_field( 'next_label' ) ? get_field( 'next_label' ) : __( 'Next', 'exfiles' );
$exf_back      = get_field( 'back_label' ) ? get_field( 'back_label' ) : __( 'Back', 'exfiles' );
$exf_submit    = get_field( 'submit_label' ) ? get_field( 'submit_label' ) : __( 'Get my guide', 'exfiles' );

$exf_errors = array(
	'exit'       => exf_guide_copy( 'exit_message' ),
	'incomplete' => __( 'Please answer each question that applies to you.', 'exfiles' ),
	'email'      => __( 'Please enter a valid email address so we can send you the guide.', 'exfiles' ),
	'rate'       => __( "You've requested several guides in a short time. Please try again in a few minutes.", 'exfiles' ),
	'failed'     => __( 'Sorry, something went wrong creating your guide. Please try again, or contact us if it keeps happening.', 'exfiles' ),
);
$exf_error  = isset( $_GET['guide_error'] ) ? sanitize_key( wp_unslash( $_GET['guide_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

/**
 * Print one question's radio options.
 *
 * @param string $key Question key.
 * @param array  $q   Question.
 */
$exf_options = function ( $key, $q ) {
	foreach ( $q['options'] as $value => $label ) {
		$id = 'exf-' . $key . '-' . $value;
		printf(
			'<label class="gq-option" for="%1$s"><input type="radio" id="%1$s" name="answers[%2$s]" value="%3$s"><span>%4$s</span></label>',
			esc_attr( $id ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_html( $label )
		);
	}
};

get_header();
?>
<section class="guide-form-section">
	<div class="wrap guide-form-wrap">
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Free Divorce Guide', 'exfiles' ); ?></p>
			<h1><?php echo esc_html( $exf_heading ); ?></h1>
			<p class="lead"><?php echo exf_lines( $exf_intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		</header>

		<?php if ( $exf_error && isset( $exf_errors[ $exf_error ] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_errors[ $exf_error ] ); ?></p></div>
		<?php endif; ?>

		<form class="guide-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-guide-form novalidate>
			<input type="hidden" name="action" value="exf_guide_submit">

			<div class="guide-progress" aria-hidden="true"><span data-guide-progress></span></div>
			<p class="screen-reader-text" aria-live="polite" data-guide-status></p>

			<?php foreach ( $exf_questions as $exf_key => $exf_q ) : ?>
				<fieldset class="gq" data-step="<?php echo esc_attr( $exf_key ); ?>">
					<legend class="gq-label" tabindex="-1"><?php echo esc_html( $exf_q['label'] ); ?></legend>

					<?php if ( ! empty( $exf_q['help'] ) ) : ?>
						<details class="gq-help">
							<summary><?php esc_html_e( 'How do I know if I’m eligible?', 'exfiles' ); ?></summary>
							<div class="gq-help-body">
								<p><?php esc_html_e( 'You can usually apply in England & Wales if at least one of these applies:', 'exfiles' ); ?></p>
								<ul>
									<?php foreach ( exf_guide_routes() as $exf_route ) : ?>
										<li><strong><?php echo esc_html( $exf_route['question'] ); ?></strong><?php if ( $exf_route['explanation'] ) : ?><br><?php echo esc_html( $exf_route['explanation'] ); ?><?php endif; ?></li>
									<?php endforeach; ?>
								</ul>
								<?php foreach ( exf_guide_tooltips() as $exf_tip ) : ?>
									<p><strong><?php echo esc_html( $exf_tip['heading'] ); ?>.</strong> <?php echo esc_html( $exf_tip['text'] ); ?></p>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endif; ?>

					<?php if ( ! empty( $exf_q['route'] ) ) : ?>
						<?php if ( 'r1' === $exf_key ) : ?>
							<p class="gq-intro"><?php esc_html_e( 'A few questions to help work out whether you can apply for a divorce in England & Wales.', 'exfiles' ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $exf_q['note'] ) : ?>
							<p class="field-note"><?php echo esc_html( $exf_q['note'] ); ?></p>
						<?php endif; ?>
						<?php
						foreach ( exf_guide_tooltips() as $exf_tip ) :
							$exf_word = strtolower( strtok( $exf_tip['heading'], ' ' ) );
							if ( '' === $exf_word || false === strpos( strtolower( $exf_q['label'] ), $exf_word ) ) {
								continue;
							}
							?>
							<details class="gq-help">
								<summary>
									<?php
									/* translators: %s: term, e.g. "Habitual residence" */
									echo esc_html( sprintf( __( 'What does “%s” mean?', 'exfiles' ), strtolower( $exf_tip['heading'] ) ) );
									?>
								</summary>
								<div class="gq-help-body"><p><?php echo esc_html( $exf_tip['text'] ); ?></p></div>
							</details>
						<?php endforeach; ?>
					<?php endif; ?>

					<div class="gq-options"><?php $exf_options( $exf_key, $exf_q ); ?></div>
				</fieldset>

				<?php if ( $exf_key === $exf_after_q3 ) : ?>
					<div class="gq gq-info" data-step="q3a">
						<div class="gq-label" tabindex="-1"><?php esc_html_e( 'Eligibility', 'exfiles' ); ?></div>
						<div class="gq-text"><?php echo wp_kses_post( exf_guide_copy( 'q3a_text' ) ); ?></div>
					</div>
					<div class="gq gq-info" data-step="q3n">
						<div class="gq-label" tabindex="-1"><?php esc_html_e( 'Eligibility', 'exfiles' ); ?></div>
						<div class="gq-text"><?php echo wp_kses_post( exf_guide_copy( 'q3n_text' ) ); ?></div>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>

			<div class="gq gq-final" data-step="final">
				<div class="gq-label" tabindex="-1"><?php esc_html_e( 'Where should we send your guide?', 'exfiles' ); ?></div>

				<div class="field">
					<label for="exf-guide-email"><?php esc_html_e( 'Email address', 'exfiles' ); ?></label>
					<input type="email" id="exf-guide-email" name="email" autocomplete="email" required>
					<p class="field-note"><?php echo esc_html( exf_guide_copy( 'privacy_note' ) ); ?></p>
				</div>

				<fieldset class="gq-services">
					<legend><?php esc_html_e( 'Would you like information about any of our fixed-fee services? (optional)', 'exfiles' ); ?></legend>
					<?php foreach ( exf_guide_services() as $exf_value => $exf_label ) : ?>
						<label class="gq-option gq-option--check"><input type="checkbox" name="services[]" value="<?php echo esc_attr( $exf_value ); ?>"><span><?php echo esc_html( $exf_label ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>

				<?php if ( exf_marketing_optin() ) : ?>
					<label class="gq-option gq-option--check gq-consent"><input type="checkbox" name="marketing" value="1"><span><?php echo esc_html( exf_guide_copy( 'marketing_label' ) ); ?></span></label>
				<?php endif; ?>

				<div class="hp-field" aria-hidden="true">
					<label for="exf-guide-company">Company</label>
					<input type="text" id="exf-guide-company" name="company" tabindex="-1" autocomplete="off">
				</div>
			</div>

			<div class="gq gq-exit" data-step="exit" hidden>
				<div class="gq-label" tabindex="-1"><?php esc_html_e( 'This guide may not be right for you', 'exfiles' ); ?></div>
				<p><?php echo esc_html( exf_guide_copy( 'exit_message' ) ); ?></p>
				<p><a class="btn outline" href="<?php echo esc_url( home_url( '/#contact' ) ); ?>"><?php esc_html_e( 'Get in touch', 'exfiles' ); ?></a></p>
			</div>

			<p class="form-status err" role="alert" data-guide-error></p>

			<div class="guide-nav">
				<button type="button" class="btn outline" data-guide-back hidden><?php echo esc_html( $exf_back ); ?></button>
				<button type="button" class="btn" data-guide-next hidden><?php echo esc_html( $exf_next ); ?></button>
				<button type="submit" class="btn" data-guide-submit><?php echo esc_html( $exf_submit ); ?></button>
			</div>
		</form>
	</div>
</section>
<?php
get_footer();
