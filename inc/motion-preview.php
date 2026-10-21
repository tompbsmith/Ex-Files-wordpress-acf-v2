<?php
/**
 * Motion preview: on test sites, make one copy of the homepage with every
 * section's "With motion?" switched off, at /homepage-motion-off/, so the two
 * can be compared side by side. It's created once; deleting the page doesn't
 * bring it back. The copy is kept out of search engines.
 *
 * Safe to remove this file (and its require in functions.php) once motion is signed off.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'exf_motion_preview_create', 20 );

/**
 * Create the motion-off copy of the homepage, once, on test sites only.
 */
function exf_motion_preview_create() {
	if ( get_option( 'exf_motion_preview_page' ) || ! function_exists( 'exf_is_test_site' ) || ! exf_is_test_site() ) {
		return;
	}
	$front = ( 'page' === get_option( 'show_on_front' ) ) ? (int) get_option( 'page_on_front' ) : 0;
	if ( ! $front || ! get_post_meta( $front, 'sections', true ) ) {
		return;
	}
	// Claim the job first so two requests at once don't both make a copy.
	if ( ! add_option( 'exf_motion_preview_page', 'pending', '', false ) ) {
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => __( 'Homepage (motion off)', 'exfiles' ),
			'post_name'   => 'homepage-motion-off',
			'post_author' => (int) get_post_field( 'post_author', $front ),
		),
		true
	);
	if ( is_wp_error( $page_id ) ) {
		delete_option( 'exf_motion_preview_page' );
		return;
	}

	// Copy every field, then switch motion off on each section that has the switch.
	$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_page_template' );
	foreach ( get_post_meta( $front ) as $key => $values ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}
		foreach ( $values as $value ) {
			add_post_meta( $page_id, $key, wp_slash( maybe_unserialize( $value ) ) );
		}
	}
	update_post_meta( $page_id, '_wp_page_template', 'page-sections.php' );

	$animated = array( 'hero', 'guide', 'services', 'steps', 'trust', 'contact' );
	foreach ( (array) get_post_meta( $page_id, 'sections', true ) as $i => $layout ) {
		if ( in_array( $layout, $animated, true ) ) {
			update_post_meta( $page_id, 'sections_' . $i . '_motion', '0' );
			update_post_meta( $page_id, '_sections_' . $i . '_motion', 'field_exf_sec_' . $layout . '_motion' );
		}
	}

	update_option( 'exf_motion_preview_page', $page_id, false );
}

// Keep the copy out of search results.
add_filter(
	'wp_robots',
	function ( $robots ) {
		$id = (int) get_option( 'exf_motion_preview_page' );
		if ( $id && is_page( $id ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);
