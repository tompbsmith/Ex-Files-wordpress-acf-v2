<?php
/**
 * Section: Hero with background video.
 *
 * @package exfiles
 */

$exf_url = function ( $file ) {
	return ( is_array( $file ) && ! empty( $file['url'] ) ) ? $file['url'] : '';
};

$exf_video_desktop  = $exf_url( get_sub_field( 'video_desktop' ) );
$exf_video_mobile   = $exf_url( get_sub_field( 'video_mobile' ) );
$exf_poster_desktop = $exf_url( get_sub_field( 'poster_desktop' ) );
$exf_poster_mobile  = $exf_url( get_sub_field( 'poster_mobile' ) );
$exf_poster_any     = $exf_poster_desktop ? $exf_poster_desktop : $exf_poster_mobile;

$exf_eyebrow   = get_sub_field( 'eyebrow' );
$exf_heading   = get_sub_field( 'heading' );
$exf_questions = get_sub_field( 'questions' );
$exf_buttons   = get_sub_field( 'buttons' );
?>
<section <?php exf_section_attrs( 'hero' ); ?>>
	<div class="hero-media" aria-hidden="true" data-hero-media
		data-video-desktop="<?php echo esc_url( $exf_video_desktop ); ?>"
		data-video-mobile="<?php echo esc_url( $exf_video_mobile ); ?>"
		data-poster-desktop="<?php echo esc_url( $exf_poster_desktop ); ?>"
		data-poster-mobile="<?php echo esc_url( $exf_poster_mobile ); ?>">
		<?php if ( $exf_video_desktop || $exf_video_mobile ) : ?>
			<video autoplay muted loop playsinline></video>
		<?php endif; ?>
		<?php if ( $exf_poster_any ) : ?>
			<img src="<?php echo esc_url( $exf_poster_any ); ?>" alt="" class="poster-fallback">
		<?php endif; ?>
	</div>
	<div class="hero-overlay" aria-hidden="true"></div>
	<?php if ( $exf_video_desktop || $exf_video_mobile ) : ?>
		<button type="button" class="hero-pause" aria-pressed="false" hidden data-pause="<?php esc_attr_e( 'Pause video', 'exfiles' ); ?>" data-play="<?php esc_attr_e( 'Play video', 'exfiles' ); ?>"><span class="ico" aria-hidden="true"></span><span class="label"><?php esc_html_e( 'Pause video', 'exfiles' ); ?></span></button>
	<?php endif; ?>
	<div class="wrap">
		<?php if ( $exf_eyebrow ) : ?>
			<p class="eyebrow"><?php echo esc_html( $exf_eyebrow ); ?></p>
		<?php endif; ?>
		<?php if ( $exf_heading ) : ?>
			<h1><?php echo exf_lines( $exf_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
		<?php endif; ?>
		<?php if ( $exf_questions ) : ?>
			<div class="hero-questions">
				<?php foreach ( $exf_questions as $exf_q ) : ?>
					<?php if ( ! empty( $exf_q['text'] ) ) : ?>
						<p><?php echo esc_html( $exf_q['text'] ); ?></p>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $exf_buttons ) : ?>
			<div class="hero-cta">
				<?php foreach ( $exf_buttons as $exf_b ) : ?>
					<?php exf_button( $exf_b['link'] ?? null, 'outline' === ( $exf_b['style'] ?? '' ) ? 'btn outline' : 'btn' ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
