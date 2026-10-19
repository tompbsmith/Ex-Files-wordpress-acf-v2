<?php
/**
 * Section: How it works (numbered steps).
 *
 * @package exfiles
 */

$exf_heading = get_sub_field( 'heading' );
$exf_intro   = get_sub_field( 'intro' );
$exf_steps   = get_sub_field( 'steps' );
?>
<section <?php exf_section_attrs( 'howitworks' ); ?>>
	<div class="wrap">
		<?php if ( $exf_heading ) : ?>
			<h2><?php echo esc_html( $exf_heading ); ?></h2>
		<?php endif; ?>
		<?php if ( $exf_intro ) : ?>
			<p class="sub"><?php echo exf_lines( $exf_intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<?php if ( $exf_steps ) : ?>
			<div class="steps">
				<?php foreach ( array_values( $exf_steps ) as $exf_i => $exf_step ) : ?>
					<div class="step">
						<div class="num"><?php echo esc_html( str_pad( (string) ( $exf_i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></div>
						<div>
							<?php if ( ! empty( $exf_step['title'] ) ) : ?>
								<h3><?php echo esc_html( $exf_step['title'] ); ?></h3>
							<?php endif; ?>
							<?php if ( ! empty( $exf_step['text'] ) ) : ?>
								<p><?php echo exf_lines( $exf_step['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
