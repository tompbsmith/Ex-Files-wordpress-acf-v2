<?php
/**
 * Template Name: Divorce Guide Sent
 *
 * Confirmation after the guide form, with a 48-hour download link (?guide=<token>).
 *
 * @package exfiles
 */

nocache_headers(); // The page shows a personal download link.

$exf_token    = isset( $_GET['guide'] ) ? sanitize_text_field( wp_unslash( $_GET['guide'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exf_request  = $exf_token ? exf_guide_request_by_token( $exf_token ) : 0;
$exf_heading  = get_field( 'sent_heading' ) ? get_field( 'sent_heading' ) : __( 'Your guide is on its way', 'exfiles' );
$exf_text     = get_field( 'sent_text' ) ? get_field( 'sent_text' ) : __( "We've emailed your personalised guide to you. It should arrive in the next few minutes, so check your junk folder if you can't see it.", 'exfiles' );
$exf_download = get_field( 'download_label' ) ? get_field( 'download_label' ) : __( 'Download your guide', 'exfiles' );
$exf_expired  = get_field( 'expired_text' ) ? get_field( 'expired_text' ) : __( 'The download link has expired, but your guide was emailed to you.', 'exfiles' );

get_header();
?>
<section class="guide-form-section">
	<div class="wrap guide-form-wrap">
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'Free Divorce Guide', 'exfiles' ); ?></p>
			<h1><?php echo esc_html( $exf_heading ); ?></h1>
			<p class="lead"><?php echo exf_lines( $exf_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		</header>

		<div class="guide-card guide-sent-card">
			<?php if ( $exf_request ) : ?>
				<p><a class="btn" href="<?php echo esc_url( add_query_arg( 'exf_guide_download', rawurlencode( $exf_token ), home_url( '/' ) ) ); ?>"><?php echo esc_html( $exf_download ); ?></a></p>
				<?php if ( ! get_post_meta( $exf_request, '_exf_email_sent', true ) ) : ?>
					<p class="field-note"><?php esc_html_e( "We couldn't email your guide just now, so please download it here. This link works for 48 hours.", 'exfiles' ); ?></p>
				<?php else : ?>
					<p class="field-note"><?php esc_html_e( 'This download link works for 48 hours.', 'exfiles' ); ?></p>
				<?php endif; ?>
			<?php elseif ( $exf_token ) : ?>
				<p><?php echo esc_html( $exf_expired ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
get_footer();
