<?php
/**
 * Template Name: My Ex File (account)
 *
 * The client's account page. For now: status, Ex Files, and the reply form when
 * the solicitor asks for more information. Becomes the WooCommerce account area
 * in the portal phase, at the same URL.
 *
 * @package exfiles
 */

$exf_user    = wp_get_current_user();
$exf_uid     = (int) $exf_user->ID;
$exf_status  = exf_user_status( $exf_uid );
$exf_files   = exf_client_ex_files( $exf_uid );
$exf_errors  = $GLOBALS['exf_info_errors'] ?? array();
$exf_replied = isset( $_GET['replied'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exf_is_staff = current_user_can( EXF_CAP );

get_header();
?>
<section class="guide-form-section account-section">
	<div class="wrap guide-form-wrap">
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
			<h1>
				<?php
				/* translators: %s: first name */
				echo esc_html( $exf_user->first_name ? sprintf( __( 'Hello, %s', 'exfiles' ), $exf_user->first_name ) : __( 'Your Ex File', 'exfiles' ) );
				?>
			</h1>
		</header>

		<?php if ( $exf_replied ) : ?>
			<div class="notice-box" role="status"><p><?php esc_html_e( "Thank you. We've received your reply and will be in touch shortly.", 'exfiles' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! exf_is_client( $exf_uid ) ) : ?>
			<div class="guide-card account-card">
				<p>
					<?php
					echo $exf_is_staff
						? esc_html__( "You're logged in as staff, so you don't have an Ex File. Client accounts are managed under Ex Files in the admin.", 'exfiles' )
						: esc_html__( "You don't have an Ex File yet.", 'exfiles' );
					?>
				</p>
				<?php if ( ! $exf_is_staff ) : ?>
					<p style="margin-top:20px"><a class="btn" href="<?php echo esc_url( exf_page_url( 'register' ) ); ?>"><?php esc_html_e( 'Open your Ex File', 'exfiles' ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php else : ?>

			<div class="guide-card account-card">
				<?php if ( EXF_STATUS_ACTIVE === $exf_status ) : ?>
					<h3><?php esc_html_e( 'Your Ex File is open', 'exfiles' ); ?></h3>
					<p><?php esc_html_e( 'You can now choose a service. Online booking and payment are coming soon; in the meantime, please get in touch and we’ll set it up for you.', 'exfiles' ); ?></p>
					<p style="margin-top:20px"><a class="btn" href="<?php echo esc_url( home_url( '/#services' ) ); ?>"><?php esc_html_e( 'Our services', 'exfiles' ); ?></a></p>
				<?php elseif ( EXF_STATUS_REVIEW === $exf_status ) : ?>
					<h3><?php esc_html_e( "We're checking a few details", 'exfiles' ); ?></h3>
					<p><?php esc_html_e( "Before we can act for you, we complete a routine check, which we do by hand for some new clients. This is usually done within one working day, and we'll email you as soon as it's finished.", 'exfiles' ); ?></p>
				<?php endif; ?>
			</div>

			<?php foreach ( $exf_files as $exf_id ) : ?>
				<?php
				$exf_fs = exf_ex_file_status( $exf_id );
				$exf_ex = exf_ex_details( $exf_id );
				?>
				<div class="guide-form account-file">
					<p class="eyebrow"><?php esc_html_e( 'Ex File', 'exfiles' ); ?></p>
					<h2 class="account-file-title"><?php echo esc_html( trim( $exf_ex['first_name'] . ' ' . $exf_ex['last_name'] ) ); ?></h2>
					<p class="account-file-status status-<?php echo esc_attr( $exf_fs ); ?>"><?php echo esc_html( exf_ex_file_client_label( $exf_fs ) ); ?></p>

					<?php if ( 'info_requested' === $exf_fs ) : ?>
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
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<p class="account-links">
				<?php echo esc_html( $exf_user->user_email ); ?>
				&nbsp;·&nbsp; <a href="<?php echo esc_url( wp_lostpassword_url( exf_page_url( 'account' ) ) ); ?>"><?php esc_html_e( 'Change password', 'exfiles' ); ?></a>
				&nbsp;·&nbsp; <a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'exfiles' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
