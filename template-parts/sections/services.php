<?php
/**
 * Section: Services (cards).
 *
 * @package exfiles
 */

$exf_heading = get_sub_field( 'heading' );
$exf_intro   = get_sub_field( 'intro' );
$exf_cards   = get_sub_field( 'cards' );
?>
<section <?php exf_section_attrs( 'services' ); ?>>
	<div class="wrap">
		<?php if ( $exf_heading ) : ?>
			<h2><?php echo esc_html( $exf_heading ); ?></h2>
		<?php endif; ?>
		<?php if ( $exf_intro ) : ?>
			<p><?php echo exf_lines( $exf_intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<?php if ( $exf_cards ) : ?>
			<div class="cards">
				<?php foreach ( $exf_cards as $exf_card ) : ?>
					<div class="card">
						<?php if ( ! empty( $exf_card['title'] ) ) : ?>
							<h3><?php echo esc_html( $exf_card['title'] ); ?></h3>
						<?php endif; ?>
						<p><?php echo exf_lines( $exf_card['text'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php exf_button( $exf_card['button'] ?? null ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
