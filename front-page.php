<?php
/**
 * Homepage: rendered from the "Sections" flexible content field.
 *
 * @package exfiles
 */

if ( ! is_page() ) {
	// "Your latest posts" is set as the homepage.
	require get_template_directory() . '/index.php';
	return;
}

get_header();
exf_render_sections( get_queried_object_id() );
get_footer();
