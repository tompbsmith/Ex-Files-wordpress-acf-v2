<?php
/**
 * Section: Heading and list (e.g. "Our Expertise").
 *
 * @package exfiles
 */

$exf_heading = get_sub_field( 'heading' );
$exf_intro   = get_sub_field( 'intro' );
$exf_items   = (array) get_sub_field( 'items' );
$exf_bg      = 'cream' === get_sub_field( 'background' ) ? 'cream' : 'sand';
?>
<section <?php exf_section_attrs( 'expertise expertise--' . $exf_bg ); ?>>
	<div class="wrap expertise-wrap">
		<?php if ( $exf_heading ) : ?>
			<h2><?php echo esc_html( $exf_heading ); ?></h2>
		<?php endif; ?>
		<?php if ( $exf_intro ) : ?>
			<p class="expertise-intro"><?php echo exf_lines( $exf_intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<?php if ( $exf_items ) : ?>
			<dl class="expertise-list">
				<?php foreach ( $exf_items as $exf_item ) : ?>
					<div class="expertise-item">
						<dt><?php echo esc_html( (string) ( $exf_item['title'] ?? '' ) ); ?></dt>
						<dd><?php echo exf_lines( (string) ( $exf_item['text'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	</div>
</section>
