<?php
/**
 * Fallback template for posts, pages and archives.
 *
 * @package exfiles
 */

get_header();
?>
<section class="entry-section">
	<div class="wrap entry">
		<?php if ( have_posts() ) : ?>
			<?php if ( ! is_singular() ) : ?>
				<h1><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ? get_the_archive_title() : get_bloginfo( 'name' ) ) ); ?></h1>
			<?php endif; ?>
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class(); ?>>
					<?php if ( is_singular() ) : ?>
						<h1><?php the_title(); ?></h1>
						<div class="entry-content"><?php the_content(); ?></div>
					<?php else : ?>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<div class="entry-content"><?php the_excerpt(); ?></div>
					<?php endif; ?>
				</article>
			<?php endwhile; ?>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<h1><?php esc_html_e( 'Page not found', 'exfiles' ); ?></h1>
			<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'exfiles' ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
