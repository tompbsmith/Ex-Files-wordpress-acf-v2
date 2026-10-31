<?php
/**
 * Admin colour scheme: wp-admin in the website's colours (dark brown menu, cream background, brown buttons).
 * WordPress's fonts and layout are unchanged. The scheme CSS is compiled from WordPress's own
 * colour-scheme Sass; the settings are in assets/css/admin/colors.scss.txt.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_init',
	function () {
		wp_admin_css_color(
			'exfiles',
			__( 'The Ex Files', 'exfiles' ),
			EXF_URI . '/assets/css/admin/colors.css?ver=' . EXF_VERSION,
			array( '#5a4e38', '#3f331f', '#b19a7a', '#f1e9de' ),
			array(
				'base'    => '#d9cbb6',
				'focus'   => '#f1e9de',
				'current' => '#f1e9de',
			)
		);
	}
);

// Everyone uses The Ex Files scheme; the per-user picker is removed from profiles.
add_filter( 'get_user_option_admin_color', fn() => 'exfiles' );
remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

add_action(
	'admin_enqueue_scripts',
	function () {
		$css = EXF_DIR . '/assets/css/admin/admin-theme.css';
		wp_enqueue_style( 'exf-admin-theme', EXF_URI . '/assets/css/admin/admin-theme.css', array( 'colors' ), file_exists( $css ) ? (string) filemtime( $css ) : EXF_VERSION );
	}
);

// The admin bar on the website itself.
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin_bar_showing() ) {
			$css = EXF_DIR . '/assets/css/admin/admin-bar-front.css';
			wp_enqueue_style( 'exf-admin-bar', EXF_URI . '/assets/css/admin/admin-bar-front.css', array( 'admin-bar' ), file_exists( $css ) ? (string) filemtime( $css ) : EXF_VERSION );
		}
	}
);
