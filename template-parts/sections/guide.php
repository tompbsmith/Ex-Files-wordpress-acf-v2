<?php
/**
 * Section: Guide (text + card).
 *
 * @package exfiles
 */

$exf_heading      = get_sub_field( 'heading' );
$exf_text         = get_sub_field( 'text' );
$exf_card_heading = get_sub_field( 'card_heading' );
$exf_card_text    = get_sub_field( 'card_text' );
$exf_card_button  = get_sub_field( 'card_button' );
?>
<section <?php exf_section_attrs( 'guide' ); ?>>
	<div class="wrap guide-grid">
		<div>
			<?php if ( $exf_heading ) : ?>
				<?php $exf_htag = exf_is_first_section() && ! is_front_page() ? 'h1' : 'h2'; ?>
				<<?php echo $exf_htag; // phpcs:ignore ?>><?php echo esc_html( $exf_heading ); ?></<?php echo $exf_htag; // phpcs:ignore ?>>
			<?php endif; ?>
			<?php if ( $exf_text ) : ?>
				<p><?php echo exf_lines( $exf_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $exf_card_heading || $exf_card_text || ! empty( $exf_card_button['url'] ) ) : ?>
			<div class="guide-card">
				<?php if ( $exf_card_heading ) : ?>
					<h3><?php echo esc_html( $exf_card_heading ); ?></h3>
				<?php endif; ?>
				<?php if ( $exf_card_text ) : ?>
					<p><?php echo exf_lines( $exf_card_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
				<?php exf_button( $exf_card_button, 'btn btn-block' ); ?>
			</div>
		<?php endif; ?>
	</div>
</section>
