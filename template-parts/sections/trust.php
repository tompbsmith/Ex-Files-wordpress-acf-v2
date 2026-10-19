<?php
/**
 * Section: Trust (icon columns).
 *
 * @package exfiles
 */

$exf_heading = get_sub_field( 'heading' );
$exf_items   = get_sub_field( 'items' );
?>
<section <?php exf_section_attrs( 'trust' ); ?>>
	<div class="wrap trust-grid">
		<h2><?php echo exf_lines( $exf_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<?php if ( $exf_items ) : ?>
			<div class="trust-items">
				<?php foreach ( $exf_items as $exf_item ) : ?>
					<div class="trust-item">
						<?php
						$exf_icon_img = $exf_item['icon_image'] ?? null;
						$exf_icon     = $exf_item['icon'] ?? 'none';
						if ( ! empty( $exf_icon_img['ID'] ) ) :
							?>
							<div class="trust-icon"><?php echo wp_get_attachment_image( $exf_icon_img['ID'], 'thumbnail', false, array( 'alt' => '' ) ); ?></div>
						<?php elseif ( 'none' !== $exf_icon && exf_icon( $exf_icon ) ) : ?>
							<div class="trust-icon"><?php echo exf_icon( $exf_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $exf_item['title'] ) ) : ?>
							<h3><?php echo esc_html( $exf_item['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( ! empty( $exf_item['text'] ) ) : ?>
							<p><?php echo exf_lines( $exf_item['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
