<?php
/**
 * Checkout step 2: the basket, for visitors who aren't logged in. The service, a
 * time if it has an appointment, any add-on and the cancellation consent. Then
 * "Create your Ex File" (or straight to payment if this browser has already done
 * that step). Handling is in inc/core/commerce/basket.php.
 *
 * @package exfiles
 */

// phpcs:disable WordPress.Security.NonceVerification
$exf_service = isset( $_GET['service'] ) ? exf_service( absint( $_GET['service'] ) ) : ( isset( $_POST['service'] ) ? exf_service( absint( $_POST['service'] ) ) : null );
$exf_basket  = exf_basket_get();
$exf_errors  = $GLOBALS['exf_buy_errors'] ?? array();
$exf_same    = $exf_service && $exf_basket && (int) $exf_basket['service'] === (int) $exf_service['id'];
$exf_posted  = isset( $_POST['exf_basket'] );
$exf_slot    = $exf_posted ? absint( $_POST['slot'] ?? 0 ) : ( $exf_same ? (int) $exf_basket['slot'] : 0 );
$exf_addon_v = $exf_posted ? ! empty( $_POST['addon'] ) : ( $exf_same && ! empty( $exf_basket['addon'] ) );
$exf_cons_v  = $exf_posted ? ! empty( $_POST['immediate_consent'] ) : ( $exf_same && ! empty( $exf_basket['consent'] ) );
// phpcs:enable
$exf_resume = exf_basket_user( $exf_basket );

// A message carried over from the next step, e.g. the time was taken meanwhile. Shown once.
if ( $exf_basket && ! empty( $exf_basket['error'] ) && ! $exf_errors ) {
	$exf_errors = array( '_form' => $exf_basket['error'] );
	$exf_basket['error'] = '';
	set_transient( 'exf_basket_' . exf_basket_token(), $exf_basket, EXF_BASKET_HOURS * HOUR_IN_SECONDS );
}
?>
<section class="guide-form-section buy-section">
	<div class="wrap guide-form-wrap">
	<?php if ( ! $exf_service ) : ?>
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Create your Ex File', 'exfiles' ); ?></p>
			<h1><?php esc_html_e( 'Please choose a service', 'exfiles' ); ?></h1>
			<p style="margin-top:24px"><a class="btn" href="<?php echo esc_url( exf_page_url( 'choose_service' ) ); ?>"><?php esc_html_e( 'See our services', 'exfiles' ); ?></a></p>
		</header>
	<?php else : ?>
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Your basket', 'exfiles' ); ?></p>
			<h1><?php echo esc_html( $exf_service['name'] ); ?></h1>
			<p class="lead">
				<?php echo esc_html( exf_service_price_label( $exf_service ) ); ?>
				<?php if ( $exf_service['price_note'] ) : ?>
					<span class="field-note"><?php echo esc_html( $exf_service['price_note'] ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( $exf_service['intro'] ) : ?>
				<p class="field-note" style="margin-top:10px"><?php echo exf_lines( $exf_service['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
			<p class="field-note" style="margin-top:14px">
				<?php
				printf(
					/* translators: %s: login URL */
					wp_kses( __( 'Already have an Ex File? <a href="%s">Log in</a> to book.', 'exfiles' ), array( 'a' => array( 'href' => array() ) ) ),
					esc_url( wp_login_url( add_query_arg( 'service', (int) $exf_service['id'], exf_page_url( 'buy' ) ) ) )
				);
				?>
			</p>
		</header>

		<?php if ( $exf_resume ) : ?>
			<div class="notice-box" role="status"><p><?php esc_html_e( "We've saved the details you gave us, so you can go straight to payment.", 'exfiles' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $exf_errors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_errors['_form'] ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! $exf_service['sellable'] ) : ?>
			<div class="guide-card account-card">
				<p><?php esc_html_e( "This service can't be booked online yet. Please contact us.", 'exfiles' ); ?></p>
				<p style="margin-top:22px"><a class="btn outline" href="<?php echo esc_url( home_url( '/#contact' ) ); ?>"><?php esc_html_e( 'Contact us', 'exfiles' ); ?></a></p>
			</div>
		<?php else : ?>
			<?php $exf_slots = $exf_service['booking'] ? exf_available_slots( $exf_service['minutes'] ) : array(); ?>
			<form class="guide-form buy-form" method="post" action="<?php echo esc_url( add_query_arg( 'service', (int) $exf_service['id'], exf_page_url( 'buy' ) ) ); ?>" novalidate>
				<?php wp_nonce_field( 'exf_basket' ); ?>
				<input type="hidden" name="exf_basket" value="1">
				<input type="hidden" name="service" value="<?php echo (int) $exf_service['id']; ?>">

				<?php
				if ( $exf_service['booking'] ) {
					exf_render_slot_picker( $exf_slots, $exf_service['minutes'], $exf_slot, $exf_errors['slot'] ?? '' );
				}
				?>

				<?php $exf_addon = $exf_service['addon']; ?>
				<?php if ( $exf_addon ) : ?>
					<fieldset class="gq-services buy-step">
						<legend class="gq-label"><?php esc_html_e( 'Optional add-on', 'exfiles' ); ?></legend>
						<label class="gq-option gq-option--check">
							<input type="checkbox" name="addon" value="1" <?php checked( $exf_addon_v ); ?>>
							<span>
								<?php
								/* translators: 1: add-on, 2: price */
								echo esc_html( sprintf( __( 'Add a %1$s (+%2$s)', 'exfiles' ), lcfirst( $exf_addon['name'] ), exf_money( $exf_addon['pence'] ) ) );
								?>
								<?php if ( $exf_addon['description'] ) : ?>
									<br><small><?php echo esc_html( $exf_addon['description'] ); ?></small>
								<?php elseif ( 'consultation' === $exf_addon['type'] ) : ?>
									<br><small><?php esc_html_e( "Once your report is ready, we'll email you a link to book a time.", 'exfiles' ); ?></small>
								<?php else : ?>
									<br><small><?php esc_html_e( "We'll email it to you and add it to your Ex File.", 'exfiles' ); ?></small>
								<?php endif; ?>
							</span>
						</label>
					</fieldset>
				<?php endif; ?>

				<?php if ( ! $exf_service['free'] ) : ?>
					<fieldset class="gq-services buy-step buy-terms">
						<legend class="gq-label"><?php esc_html_e( 'Cancellations', 'exfiles' ); ?></legend>
						<p class="field-note"><?php echo esc_html( exf_cancellation_text( $exf_service ) ); ?></p>
						<?php if ( $exf_service['immediate'] ) : ?>
							<label class="gq-option gq-option--check gq-consent"><input type="checkbox" name="immediate_consent" value="1" required <?php checked( $exf_cons_v ); ?>><span><?php echo esc_html( exf_portal_copy( 'immediate_consent' ) ); ?></span></label>
							<?php if ( isset( $exf_errors['immediate_consent'] ) ) : ?>
								<p class="field-error"><?php echo esc_html( $exf_errors['immediate_consent'] ); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					</fieldset>
				<?php endif; ?>

				<?php if ( function_exists( 'exf_service_deadline_note' ) && exf_service_deadline_note( $exf_service ) ) : ?>
					<p class="field-note"><?php echo esc_html( exf_service_deadline_note( $exf_service ) ); ?></p>
				<?php endif; ?>

				<div class="buy-summary" data-buy-summary>
					<p><strong><?php echo esc_html( $exf_service['name'] ); ?></strong> — <?php echo esc_html( exf_service_price_label( $exf_service ) ); ?></p>
					<?php if ( $exf_addon ) : ?>
						<p data-addon-line hidden><?php echo esc_html( $exf_addon['name'] ); ?> — <?php echo esc_html( exf_money( $exf_addon['pence'] ) ); ?></p>
						<p data-total-line hidden><strong><?php esc_html_e( 'Total', 'exfiles' ); ?></strong> — <?php echo esc_html( exf_money( $exf_service['pence'] + $exf_addon['pence'] ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! $exf_service['free'] ) : ?>
						<p class="field-note"><?php echo esc_html( exf_vat_note() ); ?></p>
					<?php endif; ?>
					<p class="field-note">
						<?php
						if ( $exf_resume ) {
							echo esc_html( $exf_service['booking'] ? __( "You'll pay securely with Stripe. Your appointment is held for 30 minutes while you pay.", 'exfiles' ) : __( "You'll pay securely with Stripe.", 'exfiles' ) );
						} else {
							echo esc_html( $exf_service['free'] ? __( "Next, you'll create your Ex File: your private account with us.", 'exfiles' ) : __( "Next, you'll create your Ex File (your private account with us), then pay securely with Stripe.", 'exfiles' ) );
						}
						?>
					</p>
				</div>
				<?php if ( $exf_addon ) : ?>
					<script>
					( function () {
						var box = document.querySelector( '[name="addon"]' );
						var sum = document.querySelector( '[data-buy-summary]' );
						if ( ! box || ! sum ) { return; }
						function update() {
							sum.querySelector( '[data-addon-line]' ).hidden = ! box.checked;
							sum.querySelector( '[data-total-line]' ).hidden = ! box.checked;
						}
						box.addEventListener( 'change', update );
						update();
					}() );
					</script>
				<?php endif; ?>
				<div class="guide-nav">
					<a class="btn outline" href="<?php echo esc_url( exf_page_url( 'choose_service' ) ); ?>"><?php esc_html_e( 'Back', 'exfiles' ); ?></a>
					<button type="submit" class="btn" <?php disabled( $exf_service['booking'] && ! $exf_slots ); ?>>
						<?php echo esc_html( $exf_resume ? ( $exf_service['free'] ? __( 'Confirm booking', 'exfiles' ) : __( 'Continue to payment', 'exfiles' ) ) : __( 'Continue', 'exfiles' ) ); ?>
					</button>
				</div>
			</form>
		<?php endif; ?>
	<?php endif; ?>
	</div>
</section>
