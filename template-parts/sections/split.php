<?php
/**
 * Section: Image and text (e.g. The Brand, The Brains).
 *
 * @package exfiles
 */

$exf_image   = get_sub_field( 'image' );
$exf_side    = 'right' === get_sub_field( 'image_side' ) ? 'right' : 'left';
$exf_bg      = 'sand' === get_sub_field( 'background' ) ? 'sand' : 'cream';
$exf_tag     = 'h1' === get_sub_field( 'heading_tag' ) ? 'h1' : 'h2';
$exf_heading = get_sub_field( 'heading' );
$exf_sub     = get_sub_field( 'subheading' );
$exf_text    = get_sub_field( 'text' );
$exf_button  = get_sub_field( 'button' );
$exf_has_img = ! empty( $exf_image['ID'] );
?>
<section <?php exf_section_attrs( 'split split--' . $exf_bg . ' split--image-' . $exf_side . ( $exf_has_img ? '' : ' split--no-image' ) ); ?>>
	<div class="wrap split-grid">
		<?php if ( $exf_has_img ) : ?>
			<figure class="split-image">
				<?php
				echo wp_get_attachment_image(
					(int) $exf_image['ID'],
					'large',
					false,
					array(
						'alt'   => (string) $exf_image['alt'],
						'sizes' => '(max-width: 880px) 92vw, 560px',
					)
				);
				?>
			</figure>
		<?php endif; ?>
		<div class="split-text">
			<?php if ( $exf_heading ) : ?>
				<<?php echo esc_html( $exf_tag ); ?> class="split-heading"><?php echo exf_lines( $exf_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $exf_tag ); ?>>
			<?php endif; ?>
			<?php if ( $exf_sub ) : ?>
				<p class="split-subheading"><?php echo esc_html( $exf_sub ); ?></p>
			<?php endif; ?>
			<?php if ( $exf_text ) : ?>
				<div class="split-body"><?php echo wp_kses_post( $exf_text ); ?></div>
			<?php endif; ?>
			<?php exf_button( $exf_button, 'btn' ); ?>
		</div>
	</div>
</section>
