<?php
/**
 * Checkout, last step, for a new client who isn't logged in yet: back from Stripe
 * (or a free booking). On success their account now exists and they've been sent
 * the "confirm your email" link. See inc/core/commerce/basket.php.
 *
 * @package exfiles
 */

$exf_order  = (int) ( $GLOBALS['exf_guest_order'] ?? 0 );
$exf_status = exf_order_status( $exf_order );
$exf_start  = (int) get_post_meta( $exf_order, 'booking_start', true );
$exf_osvc   = exf_service( (int) get_post_meta( $exf_order, 'service_id', true ) );
$exf_client = get_userdata( (int) get_post_meta( $exf_order, 'client_id', true ) );
?>
<section class="guide-form-section buy-section">
	<div class="wrap guide-form-wrap">
		<header class="guide-form-head">
			<p class="eyebrow"><?php echo esc_html( (string) get_post_meta( $exf_order, 'service_name', true ) ); ?></p>
			<?php if ( exf_order_is_live( $exf_order ) ) : ?>
				<h1><?php esc_html_e( 'Thank you. Now check your email', 'exfiles' ); ?></h1>
				<p class="lead">
					<?php
					echo esc_html(
						$exf_client
							/* translators: %s: email address */
							? sprintf( __( "We've sent a link to %s. Click it to confirm your email address and open your Ex File, where you'll find your booking and anything we need from you next.", 'exfiles' ), $exf_client->user_email )
							: __( "We've emailed you a link. Click it to confirm your email address and open your Ex File.", 'exfiles' )
					);
					?>
				</p>
			<?php elseif ( 'cancelled' === $exf_status ) : ?>
				<h1><?php esc_html_e( "You haven't been charged", 'exfiles' ); ?></h1>
				<p class="lead"><?php esc_html_e( "The payment was cancelled, so nothing has been booked. We've kept your details, so you can go straight back to payment.", 'exfiles' ); ?></p>
			<?php else : ?>
				<h1><?php esc_html_e( "We're confirming your payment", 'exfiles' ); ?></h1>
				<p class="lead"><?php esc_html_e( "This usually takes a few seconds. We'll email you as soon as it's confirmed, with a link to open your Ex File, so you can leave this page.", 'exfiles' ); ?></p>
			<?php endif; ?>
		</header>
		<div class="guide-card account-card">
			<?php
			// Everything in the order, so a client who didn't pay sees what they chose.
			$exf_intro = $exf_osvc ? trim( (string) preg_split( '/\R\s*\R/', (string) $exf_osvc['intro'] )[0] ) : '';
			$exf_base  = (int) get_post_meta( $exf_order, 'base_pence', true );
			$exf_addp  = (int) get_post_meta( $exf_order, 'addon_pence', true );
			?>
			<p><strong><?php echo esc_html( (string) get_post_meta( $exf_order, 'service_name', true ) ); ?></strong><?php echo $exf_base ? ' — ' . esc_html( exf_money( $exf_base ) ) : ''; ?></p>
			<?php if ( $exf_intro ) : ?>
				<p class="field-note"><?php echo esc_html( $exf_intro ); ?></p>
			<?php endif; ?>
			<?php if ( $exf_start ) : ?>
				<p><strong><?php esc_html_e( 'Appointment:', 'exfiles' ); ?></strong> <?php echo esc_html( exf_booking_label( $exf_start, (int) get_post_meta( $exf_order, 'booking_end', true ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( get_post_meta( $exf_order, 'addon_name', true ) ) : ?>
				<p><strong><?php esc_html_e( 'Add-on:', 'exfiles' ); ?></strong> <?php echo esc_html( (string) get_post_meta( $exf_order, 'addon_name', true ) ); ?><?php echo $exf_addp ? ' — ' . esc_html( exf_money( $exf_addp ) ) : ''; ?></p>
			<?php endif; ?>
			<?php if ( exf_order_is_live( $exf_order ) ) : ?>
				<p><?php esc_html_e( "We've also emailed you your order confirmation, and Stripe will send a receipt.", 'exfiles' ); ?></p>
				<?php if ( $exf_osvc && $exf_osvc['questionnaire'] ) : ?>
					<p style="margin-top:14px"><?php esc_html_e( "Once you're in, please complete your financial questionnaire. Your answers are saved as you go, so you can do it in more than one sitting.", 'exfiles' ); ?></p>
					<?php if ( function_exists( 'exf_order_deadline_note' ) && exf_order_deadline_note( $exf_order ) ) : ?>
						<p class="field-note"><?php echo esc_html( exf_order_deadline_note( $exf_order ) ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
				<p class="field-note" style="margin-top:14px"><?php esc_html_e( "Can't see the email? It may take a few minutes to arrive, so check your junk folder.", 'exfiles' ); ?></p>
			<?php elseif ( 'cancelled' === $exf_status && $exf_osvc ) : ?>
				<p style="margin-top:22px"><a class="btn" href="<?php echo esc_url( add_query_arg( 'service', (int) $exf_osvc['id'], exf_page_url( 'buy' ) ) ); ?>"><?php esc_html_e( 'Try again', 'exfiles' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
</section>
