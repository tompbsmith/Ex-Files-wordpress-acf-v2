<?php
/**
 * Template Name: Service
 *
 * A service page: title, price and Buy/Book button from the Service settings,
 * then any Page Builder sections. Settings and selling are in inc/core/commerce/.
 *
 * @package exfiles
 */

$exf_service = exf_service( get_queried_object_id() );

get_header();
?>
<section class="service-hero">
	<div class="wrap service-hero-grid">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Fixed-fee service', 'exfiles' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<?php if ( $exf_service && $exf_service['intro'] ) : ?>
				<p class="lead"><?php echo exf_lines( $exf_service['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $exf_service ) : ?>
			<div class="guide-card service-buy-card">
				<?php $exf_price = exf_service_price_label( $exf_service ); ?>
				<?php if ( $exf_price ) : ?>
					<p class="service-price"><?php echo esc_html( $exf_price ); ?></p>
					<?php if ( $exf_service['price_note'] ) : ?>
						<p class="field-note"><?php echo esc_html( $exf_service['price_note'] ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
				<ul class="service-facts">
					<?php if ( $exf_service['booking'] ) : ?>
						<li>
							<?php
							/* translators: %d: minutes */
							echo esc_html( sprintf( __( '%d-minute appointment, booked online', 'exfiles' ), $exf_service['minutes'] ) );
							?>
						</li>
					<?php endif; ?>
					<?php if ( $exf_service['turnaround'] ) : ?>
						<li><?php echo esc_html( $exf_service['turnaround'] ); ?></li>
					<?php endif; ?>
					<?php if ( $exf_service['questionnaire'] ) : ?>
						<li><?php esc_html_e( 'Includes our online financial questionnaire', 'exfiles' ); ?></li>
					<?php endif; ?>
				</ul>
				<?php if ( $exf_service['sellable'] ) : ?>
					<a class="btn btn-block" href="<?php echo esc_url( exf_service_buy_url( $exf_service['id'] ) ); ?>">
						<?php echo esc_html( $exf_service['buy_label'] ? $exf_service['buy_label'] : ( $exf_service['booking'] ? __( 'Book now', 'exfiles' ) : __( 'Get started', 'exfiles' ) ) ); ?>
					</a>
					<?php if ( ! is_user_logged_in() ) : ?>
						<p class="field-note" style="margin-top:12px"><?php esc_html_e( "You'll open your Ex File first.", 'exfiles' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Please get in touch to book this service.', 'exfiles' ); ?></p>
					<a class="btn btn-block outline" href="<?php echo esc_url( home_url( '/#contact' ) ); ?>"><?php esc_html_e( 'Contact us', 'exfiles' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
if ( exf_acf_ready() && get_field( 'sections' ) ) {
	exf_render_sections( get_queried_object_id() );
} else {
	while ( have_posts() ) {
		the_post();
		if ( '' !== trim( get_the_content() ) ) {
			echo '<section class="entry-section"><div class="wrap entry"><div class="entry-content">';
			the_content();
			echo '</div></div></section>';
		}
	}
}
get_footer();
