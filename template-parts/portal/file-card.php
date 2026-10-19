<?php
/**
 * Portal: one Ex File as a card, linking to its page.
 *
 * @package exfiles
 */

$exf_id = (int) $args['ex_file'];
$exf_fs = exf_ex_file_status( $exf_id );
$exf_ex = exf_ex_details( $exf_id );
$exf_n  = count(
	array_filter(
		exf_client_orders( get_current_user_id(), $exf_id ),
		function ( $o ) {
			return in_array( exf_order_status( $o ), array( 'paid', 'confirmed', 'refunded' ), true );
		}
	)
);
?>
<a class="portal-file-card" href="<?php echo esc_url( exf_portal_url( 'ex-files', $exf_id ) ); ?>">
	<span class="eyebrow"><?php esc_html_e( 'Separation from', 'exfiles' ); ?></span>
	<strong><?php echo esc_html( trim( $exf_ex['first_name'] . ' ' . $exf_ex['last_name'] ) ); ?></strong>
	<span class="account-file-status status-<?php echo esc_attr( $exf_fs ); ?>"><?php echo esc_html( exf_ex_file_client_label( $exf_fs ) ); ?></span>
	<span class="portal-file-meta">
		<?php
		/* translators: %d: number of services */
		echo esc_html( $exf_n ? sprintf( _n( '%d service', '%d services', $exf_n, 'exfiles' ), $exf_n ) : __( 'No services yet', 'exfiles' ) );
		?>
		&rarr;
	</span>
</a>
