<?php
/**
 * Portal: payments. Stripe emails the receipt for each payment.
 *
 * @package exfiles
 */

$exf_uid    = (int) $args['user']->ID;
$exf_orders = exf_client_payments( $exf_uid );
?>
<header class="portal-head">
	<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
	<h1><?php esc_html_e( 'Payments', 'exfiles' ); ?></h1>
	<p class="lead"><?php esc_html_e( 'Everything you have paid for. Stripe, our payment provider, emails you a receipt for each payment.', 'exfiles' ); ?></p>
</header>

<?php if ( ! $exf_orders ) : ?>
	<p class="portal-empty"><?php esc_html_e( 'No payments yet.', 'exfiles' ); ?></p>
<?php else : ?>
	<div class="portal-table-wrap">
		<table class="portal-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Date', 'exfiles' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Service', 'exfiles' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reference', 'exfiles' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Amount', 'exfiles' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'exfiles' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $exf_orders as $exf_o ) : ?>
					<?php
					$exf_paid  = (int) get_post_meta( $exf_o, 'paid_at', true );
					$exf_pence = (int) get_post_meta( $exf_o, 'amount_pence', true );
					$exf_st    = exf_order_status( $exf_o );
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Date', 'exfiles' ); ?>"><?php echo esc_html( wp_date( 'j M Y', $exf_paid ? $exf_paid : get_post_timestamp( $exf_o ) ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Service', 'exfiles' ); ?>"><?php echo esc_html( (string) get_post_meta( $exf_o, 'service_name', true ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Reference', 'exfiles' ); ?>"><?php echo esc_html( exf_order_reference( $exf_o ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Amount', 'exfiles' ); ?>" class="num"><?php echo esc_html( $exf_pence ? '£' . number_format( $exf_pence / 100, 2 ) : __( 'Free', 'exfiles' ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'exfiles' ); ?>"><?php echo esc_html( 'refunded' === $exf_st ? __( 'Refunded', 'exfiles' ) : ( $exf_pence ? __( 'Paid', 'exfiles' ) : __( 'Booked', 'exfiles' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="field-note"><?php esc_html_e( 'Need a copy of a receipt? Please get in touch and quote the reference.', 'exfiles' ); ?></p>
<?php endif; ?>
