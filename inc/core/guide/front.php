<?php
/**
 * Front-end assets for the guide pages.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_page_template( 'page-guide-form.php' ) ) {
			return;
		}
		$js = EXF_DIR . '/assets/js/guide.js';
		wp_enqueue_script(
			'exf-guide',
			EXF_URI . '/assets/js/guide.js',
			array(),
			file_exists( $js ) ? (string) filemtime( $js ) : EXF_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'exf-guide',
			'exfGuide',
			array(
				'chooseAnswer'  => __( 'Please choose an answer.', 'exfiles' ),
				'badEmail'      => __( 'Please enter a valid email address so we can send you the guide.', 'exfiles' ),
				'sending'       => __( 'Creating your guide…', 'exfiles' ),
				'progressLabel' => __( 'Question', 'exfiles' ),
				'routes'        => exf_guide_route_keys(),
			)
		);
	}
);

// The "guide sent" page carries a personal download link: keep it out of search engines.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page_template( 'page-guide-sent.php' ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);
