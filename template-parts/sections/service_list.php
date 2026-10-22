<?php
/**
 * Section: Services list, numbered, with images and "Show more".
 *
 * @package exfiles
 */

$exf_eyebrow = get_sub_field( 'eyebrow' );
$exf_heading = get_sub_field( 'heading' );
$exf_cards   = (array) get_sub_field( 'cards' );
?>
<section <?php exf_section_attrs( 'service-list' ); ?>>
	<div class="wrap">
		<?php if ( $exf_eyebrow ) : ?>
			<p class="service-list-eyebrow"><?php echo esc_html( $exf_eyebrow ); ?></p>
		<?php endif; ?>
		<?php if ( $exf_heading ) : ?>
			<h1 class="service-list-heading"><?php echo esc_html( $exf_heading ); ?></h1>
		<?php endif; ?>
		<div class="service-list-grid">
			<?php foreach ( $exf_cards as $exf_n => $exf_card ) : ?>
				<?php $exf_tid = wp_unique_id( 'exf-svc-text-' ); ?>
				<article class="service-list-card">
					<?php if ( ! empty( $exf_card['image']['ID'] ) ) : ?>
						<div class="service-list-image">
							<?php echo wp_get_attachment_image( (int) $exf_card['image']['ID'], 'medium_large', false, array( 'alt' => (string) $exf_card['image']['alt'], 'sizes' => '(max-width: 880px) 92vw, 360px' ) ); ?>
						</div>
					<?php endif; ?>
					<p class="service-list-number"><?php echo esc_html( sprintf( '%02d.', $exf_n + 1 ) ); ?></p>
					<h2 class="service-list-title"><?php echo esc_html( (string) ( $exf_card['title'] ?? '' ) ); ?></h2>
					<div class="service-list-text" id="<?php echo esc_attr( $exf_tid ); ?>" data-clamp>
						<p><?php echo exf_lines( (string) ( $exf_card['text'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					</div>
					<button type="button" class="service-list-more" aria-expanded="false" aria-controls="<?php echo esc_attr( $exf_tid ); ?>" data-more="<?php esc_attr_e( 'Show more', 'exfiles' ); ?>" data-less="<?php esc_attr_e( 'Show less', 'exfiles' ); ?>" hidden><?php esc_html_e( 'Show more', 'exfiles' ); ?></button>
					<?php if ( ! empty( $exf_card['link']['url'] ) ) : ?>
						<p class="service-list-link"><?php exf_button( $exf_card['link'], 'btn outline' ); ?></p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
