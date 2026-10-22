<?php
/**
 * Template Name: Buy a Service
 *
 * Choose the Ex File (and an appointment time), then pay through Stripe. Also the
 * "new separation" form, and the confirmation after paying. Handling is in
 * inc/core/commerce/buy.php.
 *
 * @package exfiles
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$exf_uid      = get_current_user_id();
$exf_errors   = $GLOBALS['exf_buy_errors'] ?? array();
$exf_values   = $exf_errors['values'] ?? array();
$exf_ferrors  = $exf_errors['errors'] ?? $exf_errors;
$exf_service  = isset( $_GET['service'] ) ? exf_service( absint( $_GET['service'] ) ) : null;
$exf_order    = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
$exf_new      = isset( $_GET['new_separation'] ) || isset( $exf_errors['values'] );
$exf_chosen   = isset( $_POST['ex_file'] ) ? absint( $_POST['ex_file'] ) : ( isset( $_GET['ex_file'] ) ? absint( $_GET['ex_file'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
$exf_slot     = isset( $_POST['slot'] ) ? absint( $_POST['slot'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
$exf_blocker  = exf_buy_blocker( $exf_uid );
$exf_consult  = isset( $_GET['consult'] ) ? absint( $_GET['consult'] ) : ( isset( $_POST['consult'] ) ? absint( $_POST['consult'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
// phpcs:enable

/**
 * The appointment time picker.
 *
 * @param array $slots   From exf_available_slots().
 * @param int   $minutes Length.
 */
$exf_slot_picker = function ( $slots, $minutes ) use ( $exf_slot, $exf_ferrors ) {
	?>
				<fieldset class="buy-step slot-picker" data-slot-picker>
					<legend class="gq-label">
						<?php
						/* translators: %d: minutes */
						echo esc_html( sprintf( __( 'Choose a time (%d minutes)', 'exfiles' ), $minutes ) );
						?>
					</legend>
					<?php if ( ! $slots ) : ?>
						<p><?php esc_html_e( 'Sorry, there are no times available online at the moment. Please contact us and we’ll find a time.', 'exfiles' ); ?></p>
					<?php else : ?>
						<div class="slot-days">
							<?php $exf_n = 0; ?>
							<?php foreach ( $slots as $exf_day => $exf_times ) : ?>
								<details class="slot-day" <?php echo ( 0 === $exf_n || in_array( $exf_slot, $exf_times, true ) ) ? 'open' : ''; ?>>
									<summary><?php echo esc_html( wp_date( 'l j F', $exf_times[0] ) ); ?> <span class="slot-count">
										<?php
										/* translators: %d: number of times */
										echo esc_html( sprintf( _n( '%d time', '%d times', count( $exf_times ), 'exfiles' ), count( $exf_times ) ) );
										?>
									</span></summary>
									<div class="slot-times">
										<?php foreach ( $exf_times as $exf_t ) : ?>
											<label class="slot"><input type="radio" name="slot" value="<?php echo (int) $exf_t; ?>" <?php checked( $exf_slot, $exf_t ); ?>><span><?php echo esc_html( wp_date( 'g:ia', $exf_t ) ); ?></span></label>
										<?php endforeach; ?>
									</div>
								</details>
								<?php ++$exf_n; ?>
							<?php endforeach; ?>
						</div>
						<p class="field-note">
							<?php
							/* translators: %d: hours */
							echo esc_html( sprintf( __( 'Times are UK time. Appointments need at least %d hours’ notice.', 'exfiles' ), exf_booking_settings()['notice_hours'] ) );
							?>
						</p>
					<?php endif; ?>
					<?php if ( isset( $exf_ferrors['slot'] ) ) : ?>
						<p class="field-error"><?php echo esc_html( $exf_ferrors['slot'] ); ?></p>
					<?php endif; ?>
				</fieldset>
	<?php
};

get_header();
?>
<section class="guide-form-section buy-section">
	<div class="wrap guide-form-wrap">

	<?php if ( $exf_order ) : ?>
		<?php
		$exf_status = exf_order_status( $exf_order );
		$exf_start  = (int) get_post_meta( $exf_order, 'booking_start', true );
		$exf_osvc   = exf_service( (int) get_post_meta( $exf_order, 'service_id', true ) );
		?>
		<header class="guide-form-head">
			<p class="eyebrow"><?php echo esc_html( (string) get_post_meta( $exf_order, 'service_name', true ) ); ?></p>
			<?php if ( exf_order_is_live( $exf_order ) ) : ?>
				<h1><?php echo esc_html( $exf_start ? __( "You're booked in", 'exfiles' ) : __( 'Thank you for your order', 'exfiles' ) ); ?></h1>
				<p class="lead"><?php echo esc_html( $exf_start ? __( "We've emailed you a confirmation with a calendar invite.", 'exfiles' ) : __( "We've emailed you a confirmation.", 'exfiles' ) ); ?></p>
			<?php elseif ( 'cancelled' === $exf_status ) : ?>
				<h1><?php esc_html_e( "You haven't been charged", 'exfiles' ); ?></h1>
				<p class="lead"><?php esc_html_e( 'The payment was cancelled, so nothing has been booked.', 'exfiles' ); ?></p>
			<?php else : ?>
				<h1><?php esc_html_e( "We're confirming your payment", 'exfiles' ); ?></h1>
				<p class="lead"><?php esc_html_e( "This usually takes a few seconds. We'll email you as soon as it's confirmed, so you can leave this page.", 'exfiles' ); ?></p>
			<?php endif; ?>
		</header>
		<div class="guide-card account-card">
			<?php if ( $exf_start ) : ?>
				<p><strong><?php esc_html_e( 'Appointment:', 'exfiles' ); ?></strong> <?php echo esc_html( exf_booking_label( $exf_start, (int) get_post_meta( $exf_order, 'booking_end', true ) ) ); ?></p>
			<?php endif; ?>
			<p><strong><?php esc_html_e( 'Ex File:', 'exfiles' ); ?></strong> <?php echo esc_html( trim( implode( ' ', array_slice( exf_ex_details( (int) get_post_meta( $exf_order, 'ex_file_id', true ) ), 0, 2 ) ) ) ); ?></p>
			<?php if ( get_post_meta( $exf_order, 'addon_name', true ) ) : ?>
				<p><strong><?php esc_html_e( 'Add-on:', 'exfiles' ); ?></strong> <?php echo esc_html( (string) get_post_meta( $exf_order, 'addon_name', true ) ); ?></p>
			<?php endif; ?>
			<?php if ( $exf_start && exf_order_is_live( $exf_order ) && exf_meeting_link() ) : ?>
				<p><strong><?php esc_html_e( 'Meeting link:', 'exfiles' ); ?></strong> <?php esc_html_e( "it's in your confirmation email and calendar invite.", 'exfiles' ); ?></p>
			<?php endif; ?>
			<?php if ( exf_order_is_live( $exf_order ) && $exf_osvc && $exf_osvc['questionnaire'] && ! get_post_meta( $exf_order, 'parent_order', true ) ) : ?>
				<?php
				$exf_oq   = (int) get_post_meta( $exf_order, 'questionnaire_id', true );
				$exf_qsub = $exf_oq && 'submitted' === get_post_meta( $exf_oq, 'q_status', true );
				?>
				<?php if ( $exf_qsub ) : ?>
					<?php $exf_oq = 0; // Nothing to fill in: no questionnaire button. ?>
					<p style="margin-top:14px"><?php esc_html_e( "We'll use the financial questionnaire you've already completed, so there's nothing more to fill in. If anything has changed since, please let us know and we'll reopen it for you.", 'exfiles' ); ?></p>
				<?php elseif ( $exf_oq && function_exists( 'exf_q_url' ) ) : ?>
					<p style="margin-top:14px"><?php esc_html_e( 'Next, please complete your financial questionnaire. Your answers are saved as you go, so you can do it in more than one sitting.', 'exfiles' ); ?></p>
				<?php else : ?>
					<p style="margin-top:14px"><?php esc_html_e( "Next, you'll complete a questionnaire about your finances. We'll email you when it's ready.", 'exfiles' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
			<p style="margin-top:22px">
				<?php if ( ! empty( $exf_oq ) && function_exists( 'exf_q_url' ) ) : ?>
					<a class="btn" href="<?php echo esc_url( exf_q_url( $exf_oq ) ); ?>"><?php esc_html_e( 'Start your questionnaire', 'exfiles' ); ?></a>
				<?php endif; ?>
				<?php if ( 'cancelled' === $exf_status && $exf_osvc ) : ?>
					<a class="btn" href="<?php echo esc_url( add_query_arg( 'service', $exf_osvc['id'], exf_page_url( 'buy' ) ) ); ?>"><?php esc_html_e( 'Try again', 'exfiles' ); ?></a>
				<?php endif; ?>
				<a class="btn outline" href="<?php echo esc_url( exf_page_url( 'account' ) ); ?>"><?php esc_html_e( 'Go to My Ex File', 'exfiles' ); ?></a>
			</p>
		</div>

	<?php elseif ( $exf_blocker ) : ?>
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Choose your service', 'exfiles' ); ?></p>
			<h1><?php esc_html_e( 'Not just yet', 'exfiles' ); ?></h1>
			<p class="lead"><?php echo esc_html( $exf_blocker ); ?></p>
			<p style="margin-top:24px"><a class="btn outline" href="<?php echo esc_url( exf_page_url( 'account' ) ); ?>"><?php esc_html_e( 'Go to My Ex File', 'exfiles' ); ?></a></p>
		</header>

	<?php elseif ( $exf_consult ) : ?>
		<?php
		$exf_offers  = exf_consult_offers( $exf_uid );
		$exf_csvc    = $exf_offers[ $exf_consult ] ?? null;
		$exf_prepaid = $exf_csvc && exf_consult_prepaid( $exf_consult );
		?>
		<header class="guide-form-head">
			<p class="eyebrow"><?php echo esc_html( $exf_csvc ? $exf_csvc['name'] : __( 'Consultation', 'exfiles' ) ); ?></p>
			<h1><?php esc_html_e( 'Book your consultation', 'exfiles' ); ?></h1>
			<?php if ( $exf_csvc ) : ?>
				<p class="lead">
					<?php
					echo esc_html(
						$exf_prepaid
							/* translators: %s: add-on */
							? sprintf( __( 'You paid for your %s with your order, so there’s nothing more to pay. Choose a time below.', 'exfiles' ), lcfirst( $exf_csvc['addon']['name'] ) )
							/* translators: 1: add-on, 2: price */
							: sprintf( __( 'Talk your report through with us: a %1$s costs %2$s.', 'exfiles' ), lcfirst( $exf_csvc['addon']['name'] ), exf_money( $exf_csvc['addon']['pence'] ) )
					);
					?>
				</p>
			<?php endif; ?>
		</header>
		<?php if ( isset( $exf_ferrors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_ferrors['_form'] ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! $exf_csvc ) : ?>
			<div class="guide-card account-card">
				<p><?php esc_html_e( "There's no consultation to book here. If you think there should be, please contact us.", 'exfiles' ); ?></p>
				<p style="margin-top:22px"><a class="btn outline" href="<?php echo esc_url( exf_page_url( 'account' ) ); ?>"><?php esc_html_e( 'Go to My Ex File', 'exfiles' ); ?></a></p>
			</div>
		<?php else : ?>
			<?php $exf_cslots = exf_available_slots( $exf_csvc['addon']['minutes'] ); ?>
			<form class="guide-form buy-form" method="post" novalidate>
				<?php wp_nonce_field( 'exf_consult' ); ?>
				<input type="hidden" name="exf_consult" value="1">
				<input type="hidden" name="consult" value="<?php echo (int) $exf_consult; ?>">
				<?php $exf_slot_picker( $exf_cslots, $exf_csvc['addon']['minutes'] ); ?>
				<div class="buy-summary">
					<?php if ( ! $exf_prepaid ) : ?>
						<p><strong><?php echo esc_html( $exf_csvc['addon']['name'] ); ?></strong> — <?php echo esc_html( exf_money( $exf_csvc['addon']['pence'] ) ); ?></p>
						<p class="field-note"><?php echo esc_html( exf_vat_note() ); ?></p>
						<p class="field-note"><?php echo esc_html( exf_cancellation_text() ); ?></p>
						<p class="field-note"><?php esc_html_e( "You'll pay securely with Stripe. Your appointment is held for 30 minutes while you pay.", 'exfiles' ); ?></p>
					<?php else : ?>
						<p class="field-note"><?php echo esc_html( exf_cancellation_text() ); ?></p>
					<?php endif; ?>
				</div>
				<div class="guide-nav">
					<button type="submit" class="btn" <?php disabled( ! $exf_cslots ); ?>><?php echo esc_html( $exf_prepaid ? __( 'Confirm booking', 'exfiles' ) : __( 'Continue to payment', 'exfiles' ) ); ?></button>
				</div>
			</form>
		<?php endif; ?>

	<?php elseif ( $exf_new ) : ?>
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'A new separation', 'exfiles' ); ?></p>
			<h1><?php esc_html_e( 'Tell us about your ex', 'exfiles' ); ?></h1>
			<p class="lead"><?php esc_html_e( "We'll open a new Ex File for this separation. As before, we'll run a quick check to make sure we haven't already been instructed by them.", 'exfiles' ); ?></p>
		</header>
		<?php if ( isset( $exf_ferrors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_ferrors['_form'] ); ?></p></div>
		<?php endif; ?>
		<form class="guide-form" method="post" novalidate>
			<?php wp_nonce_field( 'exf_new_separation' ); ?>
			<input type="hidden" name="exf_new_separation" value="1">
			<input type="hidden" name="service" value="<?php echo (int) ( $exf_service['id'] ?? 0 ); ?>">
			<?php $exf_a = array( 'values' => $exf_values, 'errors' => $exf_ferrors ); ?>
			<div class="field-row">
				<?php
				exf_form_field( 'ex_first_name', __( 'Their first name', 'exfiles' ), $exf_a + array( 'required' => true, 'autocomplete' => 'off' ) );
				exf_form_field( 'ex_last_name', __( 'Their last name', 'exfiles' ), $exf_a + array( 'required' => true, 'autocomplete' => 'off' ) );
				?>
			</div>
			<?php
			exf_form_field( 'ex_prev_names', __( 'Any previous or other names they use', 'exfiles' ), $exf_a + array( 'autocomplete' => 'off' ) );
			exf_form_field( 'ex_dob', __( 'Their date of birth', 'exfiles' ), $exf_a + array( 'type' => 'date', 'autocomplete' => 'off' ) );
			?>
			<label class="gq-option gq-option--check reg-unknown"><input type="checkbox" name="ex_dob_unknown" value="1" <?php checked( ! empty( $exf_values['ex_dob_unknown'] ) ); ?>><span><?php esc_html_e( "I don't know their date of birth", 'exfiles' ); ?></span></label>
			<div class="field-row">
				<?php
				exf_form_field( 'ex_email', __( 'Their email address', 'exfiles' ), $exf_a + array( 'type' => 'email', 'autocomplete' => 'off' ) );
				exf_form_field( 'ex_phone', __( 'Their phone number', 'exfiles' ), $exf_a + array( 'type' => 'tel', 'autocomplete' => 'off' ) );
				?>
			</div>
			<?php exf_form_field( 'ex_postcode', __( 'Their last known postcode', 'exfiles' ), $exf_a + array( 'autocomplete' => 'off', 'class' => 'field--half' ) ); ?>
			<fieldset class="gq-services">
				<legend><?php esc_html_e( 'Are you married or in a civil partnership with them?', 'exfiles' ); ?></legend>
				<?php foreach ( exf_relationship_options() as $exf_k => $exf_l ) : ?>
					<label class="gq-option"><input type="radio" name="relationship" value="<?php echo esc_attr( $exf_k ); ?>" <?php checked( $exf_values['relationship'] ?? '', $exf_k ); ?>><span><?php echo esc_html( $exf_l ); ?></span></label>
				<?php endforeach; ?>
				<?php if ( isset( $exf_ferrors['relationship'] ) ) : ?>
					<p class="field-error"><?php echo esc_html( $exf_ferrors['relationship'] ); ?></p>
				<?php endif; ?>
			</fieldset>
			<div class="field-row" style="margin-top:22px">
				<?php
				exf_form_field( 'marriage_date', __( 'Date of marriage or civil partnership', 'exfiles' ), $exf_a + array( 'type' => 'date' ) );
				exf_form_field( 'separation_date', __( 'Date you separated', 'exfiles' ), $exf_a + array( 'type' => 'date' ) );
				?>
			</div>
			<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Open a new Ex File', 'exfiles' ); ?></button></div>
		</form>

	<?php elseif ( ! $exf_service ) : ?>
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
			<h1><?php esc_html_e( 'Choose your service', 'exfiles' ); ?></h1>
		</header>
		<div class="cards buy-cards">
			<?php foreach ( exf_services() as $exf_s ) : ?>
				<div class="card">
					<h3><?php echo esc_html( $exf_s['name'] ); ?></h3>
					<p><?php echo esc_html( $exf_s['intro'] ); ?></p>
					<p class="buy-price"><?php echo esc_html( exf_service_price_label( $exf_s ) ); ?></p>
					<?php if ( $exf_s['sellable'] ) : ?>
						<a class="btn" href="<?php echo esc_url( add_query_arg( array_filter( array( 'service' => $exf_s['id'], 'ex_file' => $exf_chosen ) ), exf_page_url( 'buy' ) ) ); ?>"><?php echo esc_html( $exf_s['buy_label'] ? $exf_s['buy_label'] : ( $exf_s['booking'] ? __( 'Book', 'exfiles' ) : __( 'Buy', 'exfiles' ) ) ); ?></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="field-note" style="margin-top:18px"><?php echo esc_html( exf_vat_note() ); ?></p>

	<?php else : ?>
		<?php
		$exf_files = array_filter(
			exf_client_ex_files( $exf_uid ),
			function ( $id ) {
				return 'blocked' !== exf_ex_file_status( $id );
			}
		);
		$exf_clear = array_values(
			array_filter(
				$exf_files,
				function ( $id ) {
					return 'clear' === exf_ex_file_status( $id );
				}
			)
		);
		if ( ! $exf_chosen && 1 === count( $exf_clear ) ) {
			$exf_chosen = $exf_clear[0];
		}
		$exf_slots = $exf_service['booking'] ? exf_available_slots( $exf_service['minutes'] ) : array();
		?>
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Choose your service', 'exfiles' ); ?></p>
			<h1><?php echo esc_html( $exf_service['name'] ); ?></h1>
			<p class="lead">
				<?php echo esc_html( exf_service_price_label( $exf_service ) ); ?>
				<?php if ( $exf_service['price_note'] ) : ?>
					<span class="field-note"><?php echo esc_html( $exf_service['price_note'] ); ?></span>
				<?php endif; ?>
			</p>
		</header>

		<?php if ( isset( $_GET['separation'] ) && 'clear' === $_GET['separation'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice-box" role="status"><p><?php esc_html_e( 'Your new Ex File is open.', 'exfiles' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $exf_ferrors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo esc_html( $exf_ferrors['_form'] ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! $exf_service['sellable'] ) : ?>
			<div class="guide-card account-card"><p><?php esc_html_e( "This service can't be booked online yet. Please contact us.", 'exfiles' ); ?></p></div>
		<?php else : ?>
		<form class="guide-form buy-form" method="post" novalidate>
			<?php wp_nonce_field( 'exf_buy' ); ?>
			<input type="hidden" name="exf_buy" value="1">
			<input type="hidden" name="service" value="<?php echo (int) $exf_service['id']; ?>">

			<fieldset class="gq-services buy-step">
				<legend class="gq-label"><?php esc_html_e( 'Which Ex File is this for?', 'exfiles' ); ?></legend>
				<?php foreach ( $exf_files as $exf_id ) : ?>
					<?php
					$exf_ex    = exf_ex_details( $exf_id );
					$exf_ready = 'clear' === exf_ex_file_status( $exf_id );
					?>
					<label class="gq-option<?php echo $exf_ready ? '' : ' is-disabled'; ?>">
						<input type="radio" name="ex_file" value="<?php echo (int) $exf_id; ?>" <?php checked( $exf_chosen, $exf_id ); ?> <?php disabled( ! $exf_ready ); ?>>
						<span>
							<?php
							/* translators: %s: ex's name */
							echo esc_html( sprintf( __( 'My separation from %s', 'exfiles' ), trim( $exf_ex['first_name'] . ' ' . $exf_ex['last_name'] ) ) );
							?>
							<?php if ( ! $exf_ready ) : ?>
								<br><small><?php echo esc_html( exf_ex_file_client_label( exf_ex_file_status( $exf_id ) ) ); ?></small>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
				<a class="gq-option gq-option--link" href="<?php echo esc_url( add_query_arg( array( 'service' => $exf_service['id'], 'new_separation' => 1 ), exf_page_url( 'buy' ) ) ); ?>"><span><?php esc_html_e( '+ A new separation (someone not listed here)', 'exfiles' ); ?></span></a>
				<?php if ( isset( $exf_ferrors['ex_file'] ) ) : ?>
					<p class="field-error"><?php echo esc_html( $exf_ferrors['ex_file'] ); ?></p>
				<?php endif; ?>
			</fieldset>

			<?php
			if ( $exf_service['booking'] ) {
				$exf_slot_picker( $exf_slots, $exf_service['minutes'] );
			}
			?>

			<?php $exf_addon = $exf_service['addon']; ?>
			<?php if ( $exf_addon ) : ?>
				<fieldset class="gq-services buy-step">
					<legend class="gq-label"><?php esc_html_e( 'Optional add-on', 'exfiles' ); ?></legend>
					<label class="gq-option gq-option--check">
						<input type="checkbox" name="addon" value="1" data-addon-pence="<?php echo (int) $exf_addon['pence']; ?>" <?php checked( ! empty( $_POST['addon'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>>
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
						<label class="gq-option gq-option--check gq-consent"><input type="checkbox" name="immediate_consent" value="1" required <?php checked( ! empty( $_POST['immediate_consent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>><span><?php echo esc_html( exf_portal_copy( 'immediate_consent' ) ); ?></span></label>
						<?php if ( isset( $exf_ferrors['immediate_consent'] ) ) : ?>
							<p class="field-error"><?php echo esc_html( $exf_ferrors['immediate_consent'] ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</fieldset>
			<?php endif; ?>

			<div class="buy-summary" data-buy-summary data-base-pence="<?php echo (int) $exf_service['pence']; ?>">
				<p><strong><?php echo esc_html( $exf_service['name'] ); ?></strong> — <?php echo esc_html( exf_service_price_label( $exf_service ) ); ?></p>
				<?php if ( $exf_addon ) : ?>
					<p data-addon-line hidden><?php echo esc_html( $exf_addon['name'] ); ?> — <?php echo esc_html( exf_money( $exf_addon['pence'] ) ); ?></p>
					<p data-total-line hidden><strong><?php esc_html_e( 'Total', 'exfiles' ); ?></strong> — <span data-total><?php echo esc_html( exf_money( $exf_service['pence'] + $exf_addon['pence'] ) ); ?></span></p>
				<?php endif; ?>
				<?php if ( ! $exf_service['free'] ) : ?>
					<p class="field-note"><?php echo esc_html( exf_vat_note() ); ?></p>
					<p class="field-note"><?php echo esc_html( $exf_service['booking'] ? __( "You'll pay securely with Stripe. Your appointment is held for 30 minutes while you pay.", 'exfiles' ) : __( "You'll pay securely with Stripe.", 'exfiles' ) ); ?></p>
				<?php endif; ?>
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
				<button type="submit" class="btn" <?php disabled( ! $exf_clear || ( $exf_service['booking'] && ! $exf_slots ) ); ?>>
					<?php echo esc_html( $exf_service['free'] ? __( 'Confirm booking', 'exfiles' ) : __( 'Continue to payment', 'exfiles' ) ); ?>
				</button>
			</div>
		</form>
		<?php endif; ?>

	<?php endif; ?>
	</div>
</section>
<?php
get_footer();
