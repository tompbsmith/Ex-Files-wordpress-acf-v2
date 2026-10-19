<?php
/**
 * Site footer.
 *
 * @package exfiles
 */

$exf_tagline = exf_option( 'footer_tagline', 'Making separation feel less alien' );
$exf_email   = exf_option( 'contact_email', 'info@theexfiles.co.uk' );
$exf_socials = array(
	'instagram' => array( 'ig', 'Instagram', exf_option( 'instagram_url' ) ),
	'linkedin'  => array( 'li', 'LinkedIn', exf_option( 'linkedin_url' ) ),
	'facebook'  => array( 'fb', 'Facebook', exf_option( 'facebook_url' ) ),
);
$exf_socials = array_filter(
	$exf_socials,
	function ( $s ) {
		return ! empty( $s[2] );
	}
);
?>
</main>

<footer class="site-footer">
	<div class="wrap">
		<?php if ( $exf_tagline ) : ?>
			<p class="tagline"><?php echo esc_html( $exf_tagline ); ?></p>
		<?php endif; ?>
		<?php if ( $exf_email ) : ?>
			<p class="tagline"><a href="mailto:<?php echo esc_attr( antispambot( $exf_email ) ); ?>"><?php echo esc_html( antispambot( $exf_email ) ); ?></a></p>
		<?php endif; ?>

		<?php if ( $exf_socials ) : ?>
			<div class="socials">
				<?php foreach ( $exf_socials as $exf_network => $exf_s ) : ?>
					<a class="<?php echo esc_attr( $exf_s[0] ); ?>" href="<?php echo esc_url( $exf_s[2] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $exf_s[1] ); ?>"><?php echo exf_social_icon( $exf_network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( exf_option( 'show_footer_bottom' ) ) : ?>
			<div class="foot-bottom">
				<span><?php echo esc_html( str_replace( '{year}', gmdate( 'Y' ), exf_option( 'copyright', '© {year} The Ex Files.' ) ) ); ?></span>
				<?php $exf_legal = exf_option( 'legal_links', array() ); ?>
				<?php if ( $exf_legal ) : ?>
					<span>
						<?php
						$exf_out = array();
						foreach ( $exf_legal as $exf_row ) {
							if ( ! empty( $exf_row['link']['url'] ) ) {
								$exf_out[] = sprintf( '<a href="%s"%s>%s</a>', esc_url( $exf_row['link']['url'] ), ! empty( $exf_row['link']['target'] ) ? ' target="_blank" rel="noopener"' : '', esc_html( $exf_row['link']['title'] ) );
							}
						}
						echo implode( ' | ', $exf_out ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="foot-logo">
			<?php echo exf_logo_img( 'logo-img', exf_option( 'footer_logo', null ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
