<?php
/**
 * Section: Testimonials.
 *
 * @package exfiles
 */

$exf_heading = get_sub_field( 'heading' );
$exf_items   = array_filter(
	(array) get_sub_field( 'items' ),
	function ( $i ) {
		return '' !== trim( (string) ( $i['quote'] ?? '' ) );
	}
);
?>
<section <?php exf_section_attrs( 'testimonials' ); ?>>
	<div class="wrap">
		<?php if ( $exf_heading ) : ?>
			<h2><?php echo esc_html( $exf_heading ); ?></h2>
		<?php endif; ?>
		<div class="testimonial-grid">
			<?php foreach ( $exf_items as $exf_item ) : ?>
				<figure class="testimonial">
					<blockquote><p>&ldquo;<?php echo esc_html( trim( (string) $exf_item['quote'], " \t\n\r\"“”" ) ); ?>&rdquo;</p></blockquote>
					<?php if ( ! empty( $exf_item['name'] ) ) : ?>
						<figcaption><?php echo esc_html( $exf_item['name'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
