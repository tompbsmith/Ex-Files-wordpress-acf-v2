<?php
/**
 * Section: Profile (photo + about), e.g. "The Brains".
 *
 * @package exfiles
 */

$exf_photo   = get_sub_field( 'photo' );
$exf_eyebrow = get_sub_field( 'eyebrow' );
$exf_name    = get_sub_field( 'name' );
$exf_role    = get_sub_field( 'role' );
$exf_bio     = get_sub_field( 'bio' );
$exf_button  = get_sub_field( 'button' );
?>
<section <?php exf_section_attrs( 'profile' ); ?>>
	<div class="wrap profile-grid<?php echo empty( $exf_photo['ID'] ) ? ' profile-grid--no-photo' : ''; ?>">
		<?php if ( ! empty( $exf_photo['ID'] ) ) : ?>
			<figure class="profile-photo">
				<?php
				echo wp_get_attachment_image(
					(int) $exf_photo['ID'],
					'large',
					false,
					array(
						'alt'     => $exf_photo['alt'] ? $exf_photo['alt'] : (string) $exf_name,
						'loading' => 'eager',
						'sizes'   => '(max-width: 760px) 80vw, 420px',
					)
				);
				?>
			</figure>
		<?php endif; ?>
		<div class="profile-text">
			<?php if ( $exf_eyebrow ) : ?>
				<p class="eyebrow"><?php echo esc_html( $exf_eyebrow ); ?></p>
			<?php endif; ?>
			<?php if ( $exf_name ) : ?>
				<h1 class="profile-name"><?php echo esc_html( $exf_name ); ?></h1>
			<?php endif; ?>
			<?php if ( $exf_role ) : ?>
				<p class="profile-role"><?php echo esc_html( $exf_role ); ?></p>
			<?php endif; ?>
			<?php if ( $exf_bio ) : ?>
				<div class="profile-bio entry-content"><?php echo wp_kses_post( $exf_bio ); ?></div>
			<?php endif; ?>
			<?php exf_button( $exf_button, 'btn' ); ?>
		</div>
	</div>
</section>
