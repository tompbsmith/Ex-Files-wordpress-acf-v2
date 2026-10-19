<?php
/**
 * Portal: overview. What to do next, and each Ex File at a glance.
 *
 * @package exfiles
 */

$exf_user   = $args['user'];
$exf_uid    = (int) $exf_user->ID;
$exf_status = exf_user_status( $exf_uid );
$exf_files  = exf_client_ex_files( $exf_uid );
$exf_steps  = exf_portal_next_steps( $exf_uid );

$exf_sep          = isset( $_GET['separation'] ) ? sanitize_key( wp_unslash( $_GET['separation'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exf_sep_messages = array(
	'clear'   => __( 'Your new Ex File is open.', 'exfiles' ),
	'review'  => __( "Thanks. We're running a routine check on your new Ex File and will email you as soon as it's done, usually within one working day.", 'exfiles' ),
	'blocked' => __( "We're unable to act for you on this new matter. Your other Ex Files aren't affected. Please contact us if you have any questions.", 'exfiles' ),
);
?>
<header class="portal-head">
	<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
	<h1>
		<?php
		/* translators: %s: first name */
		echo esc_html( $exf_user->first_name ? sprintf( __( 'Hello, %s', 'exfiles' ), $exf_user->first_name ) : __( 'Your Ex File', 'exfiles' ) );
		?>
	</h1>
</header>

<?php if ( isset( $exf_sep_messages[ $exf_sep ] ) ) : ?>
	<div class="notice-box" role="status"><p><?php echo esc_html( $exf_sep_messages[ $exf_sep ] ); ?></p></div>
<?php endif; ?>

<?php if ( EXF_STATUS_REVIEW === $exf_status ) : ?>
	<div class="guide-card account-card">
		<h3><?php esc_html_e( "We're checking a few details", 'exfiles' ); ?></h3>
		<p><?php echo exf_lines( exf_portal_copy( 'review_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	</div>
<?php endif; ?>

<?php if ( $exf_steps ) : ?>
	<h2 class="portal-h2"><?php esc_html_e( 'Next steps', 'exfiles' ); ?></h2>
	<ul class="portal-steps">
		<?php foreach ( $exf_steps as $exf_step ) : ?>
			<li>
				<span><?php echo esc_html( $exf_step['text'] ); ?></span>
				<?php if ( $exf_step['url'] ) : ?>
					<a class="btn small" href="<?php echo esc_url( $exf_step['url'] ); ?>"><?php echo esc_html( $exf_step['label'] ); ?></a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php elseif ( EXF_STATUS_ACTIVE === $exf_status ) : ?>
	<div class="guide-card account-card">
		<h3><?php esc_html_e( 'Your Ex File is open', 'exfiles' ); ?></h3>
		<p><?php echo exf_lines( exf_portal_copy( 'active_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<p style="margin-top:20px"><a class="btn" href="<?php echo esc_url( exf_page_url( 'buy' ) ); ?>"><?php esc_html_e( 'Choose a service', 'exfiles' ); ?></a></p>
	</div>
<?php endif; ?>

<h2 class="portal-h2"><?php echo esc_html( _n( 'Your Ex File', 'Your Ex Files', count( $exf_files ), 'exfiles' ) ); ?></h2>
<div class="portal-files">
	<?php foreach ( $exf_files as $exf_id ) : ?>
		<?php get_template_part( 'template-parts/portal/file-card', null, array( 'ex_file' => $exf_id ) ); ?>
	<?php endforeach; ?>
</div>

<?php if ( EXF_STATUS_ACTIVE === $exf_status ) : ?>
	<p class="account-new-sep"><a href="<?php echo esc_url( add_query_arg( 'new_separation', 1, exf_page_url( 'buy' ) ) ); ?>"><?php esc_html_e( '+ Open an Ex File for a different separation', 'exfiles' ); ?></a></p>
<?php endif; ?>
